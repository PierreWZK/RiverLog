<?php

namespace App\Http\Controllers;

use App\HandHistory\HandImporter;
use App\HandHistory\ImportException;
use App\Models\HandImport;
use App\Models\User;
use App\Services\TournamentStatsService;
use App\Support\TournamentFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page Import : envoi des historiques de mains, parties importées (statistiques, liste)
 * et historique des imports.
 */
class HandImportController extends Controller
{
    public const MAX_FILES = 50;

    public function __construct(
        private HandImporter $importer,
        private TournamentStatsService $statsService,
    ) {}

    public function index(Request $request): View
    {
        // Messages laissés par les envois avec barre de progression (un envoi par fichier)
        if ($messages = $request->session()->pull('import_messages')) {
            session()->now('status', implode(' ', $messages));
        }

        return view('imports.index', [
            'demo' => false,
            'imports' => $request->user()->handImports()->latest('id')->get(),
            ...$this->statsService->overview($request->user(), TournamentFilters::fromRequest($request)),
        ]);
    }

    public function store(Request $request): RedirectResponse|StreamedResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'max:10240', 'extensions:txt,zip'],
        ], [
            'files.required' => 'Choisis au moins un fichier.',
            'files.max' => self::MAX_FILES.' fichiers maximum par envoi.',
            'files.*.file' => 'L\'envoi du fichier a échoué.',
            'files.*.max' => 'Chaque fichier doit faire 10 Mo maximum.',
            'files.*.extensions' => 'Seuls les fichiers .txt et .zip sont acceptés.',
        ]);

        if ($request->hasHeader('X-Import-Progress')) {
            return $this->streamImport($request);
        }

        $messages = array_map(fn (UploadedFile $file) => $this->importFile($request->user(), $file), $request->file('files'));

        return to_route('imports.index')->with('status', implode(' ', $messages));
    }

    /**
     * Envoi depuis la page (import.js) : l'avancement est renvoyé au fil de l'eau, un objet JSON
     * par ligne ({"step":"store","done":120,"total":3278}), puis {"step":"done","message":"…"}.
     */
    private function streamImport(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            @set_time_limit(0);
            // Sans tampon PHP, chaque ligne part aussitôt (en test, la réponse est capturée par un tampon)
            while (ob_get_level() > 0 && ! app()->runningUnitTests()) {
                ob_end_flush();
            }

            $send = function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE)."\n";
                flush();
            };

            $last = null;
            $progress = function (string $step, int $done, int $total) use ($send, &$last): void {
                // Un message par point de pourcentage suffit
                $percent = $total > 0 ? intdiv(100 * $done, $total) : 100;
                if ($last !== [$step, $percent]) {
                    $last = [$step, $percent];
                    $send(['step' => $step, 'done' => $done, 'total' => $total]);
                }
            };

            try {
                foreach ($request->file('files') as $file) {
                    $message = $this->importFile($request->user(), $file, $progress);
                    // La session a déjà été enregistrée avant le flux : on la réenregistre
                    session()->push('import_messages', $message);
                    $request->session()->save();
                    $send(['step' => 'done', 'message' => $message]);
                }
            } catch (\Throwable $e) {
                report($e);
                $send(['step' => 'error', 'message' => 'Erreur inattendue pendant l\'import.']);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // pas de mise en tampon par un proxy nginx
        ]);
    }

    /** Importe un fichier et renvoie le message à afficher. */
    private function importFile(User $user, UploadedFile $file, ?\Closure $progress = null): string
    {
        $name = $file->getClientOriginalName();

        $previous = $this->importer->findPreviousImport($user, $file);

        // Fichier déjà importé sans erreur : rien à faire
        if ($previous && ! $previous->errors) {
            return "« {$name} » a déjà été importé le {$previous->created_at->format('d/m/Y')} : rien n'a changé.";
        }

        try {
            // Fichier déjà importé avec des erreurs : on relance cet import plutôt que de le refuser
            return $previous
                ? $this->retryImport($previous, $file, $progress)
                : "« {$name} » : ".self::summary($this->importer->importUpload($user, $file, $progress));
        } catch (ImportException $e) {
            return "« {$name} » : {$e->getMessage()}";
        }
    }

    /** Relance un import en erreur à partir du fichier conservé. */
    public function retry(HandImport $import): RedirectResponse
    {
        Gate::authorize('retry', $import);

        try {
            $message = $this->retryImport($import);
        } catch (ImportException $e) {
            $message = "« {$import->filename} » : {$e->getMessage()}";
        }

        return to_route('imports.index')->with('status', $message);
    }

    /** « « export.txt » relancé : 157 mains ajoutées, plus aucune erreur. » */
    private function retryImport(HandImport $import, ?UploadedFile $file = null, ?\Closure $progress = null): string
    {
        $before = $import->hands_imported;
        $this->importer->retry($import, $file, $progress);

        $parts = [self::plural($import->hands_imported - $before, 'main ajoutée', 'mains ajoutées')];
        if ($import->tournaments_touched > 0) {
            $parts[] = self::plural($import->tournaments_touched, 'tournoi mis à jour', 'tournois mis à jour');
        }
        $parts[] = $import->errors
            ? self::plural(count($import->errors), 'erreur restante', 'erreurs restantes').' (détail ci-dessous)'
            : 'plus aucune erreur';

        return "« {$import->filename} » relancé : ".implode(', ', $parts).'.';
    }

    public function destroy(HandImport $import): RedirectResponse
    {
        Gate::authorize('delete', $import);

        $this->importer->delete($import);

        return to_route('imports.index')->with('status', "Import « {$import->filename} » supprimé, statistiques recalculées.");
    }

    /** « 41 mains importées, 5 tournois mis à jour, 2 erreurs. » */
    private static function summary(HandImport $import): string
    {
        $parts = [self::plural($import->hands_imported, 'main importée', 'mains importées')];

        if ($import->duplicates > 0) {
            $parts[] = self::plural($import->duplicates, 'doublon ignoré', 'doublons ignorés');
        }
        if ($import->tournaments_touched > 0) {
            $parts[] = self::plural($import->tournaments_touched, 'tournoi mis à jour', 'tournois mis à jour');
        }
        if ($import->errors) {
            $parts[] = self::plural(count($import->errors), 'erreur', 'erreurs').' (détail ci-dessous)';
        }

        return implode(', ', $parts).'.';
    }

    private static function plural(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count > 1 ? $plural : $singular);
    }
}
