<?php

namespace App\Http\Controllers;

use App\HandHistory\HandImporter;
use App\HandHistory\ImportException;
use App\Models\HandImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Page Import : envoi des historiques de mains et historique des imports.
 */
class HandImportController extends Controller
{
    public function __construct(private HandImporter $importer) {}

    public function index(Request $request): View
    {
        return view('imports.index', [
            'imports' => $request->user()->handImports()->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'extensions:txt,zip'],
        ], [
            'files.required' => 'Choisis au moins un fichier.',
            'files.max' => '10 fichiers maximum par envoi.',
            'files.*.file' => 'L\'envoi du fichier a échoué.',
            'files.*.max' => 'Chaque fichier doit faire 10 Mo maximum.',
            'files.*.extensions' => 'Seuls les fichiers .txt et .zip sont acceptés.',
        ]);

        $messages = [];
        $user = $request->user();

        foreach ($request->file('files') as $file) {
            $name = $file->getClientOriginalName();

            if ($previous = $this->importer->findPreviousImport($user, $file)) {
                $messages[] = "« {$name} » a déjà été importé le {$previous->created_at->format('d/m/Y')} : rien n'a changé.";

                continue;
            }

            try {
                $import = $this->importer->importUpload($user, $file);
            } catch (ImportException $e) {
                $messages[] = "« {$name} » : {$e->getMessage()}";

                continue;
            }

            $messages[] = "« {$name} » : ".self::summary($import);
        }

        return to_route('imports.index')->with('status', implode(' ', $messages));
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
