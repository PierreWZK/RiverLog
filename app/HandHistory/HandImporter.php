<?php

namespace App\HandHistory;

use App\HandHistory\Data\ParsedHand;
use App\Models\HandImport;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Importe des historiques de mains (cahier des charges, section 8.5).
 *
 * L'import est idempotent : une main déjà connue (même Hand ID) est ignorée,
 * un tournoi déjà connu (même Game ID) est complété puis recalculé.
 */
class HandImporter
{
    private const MAX_ZIP_ENTRIES = 200;

    private const MAX_UNZIPPED_BYTES = 50 * 1024 * 1024;

    public function __construct(
        private ParserRegistry $parsers,
        private TournamentAggregator $aggregator,
        private SessionBuilder $sessions,
    ) {}

    /** Ce fichier a-t-il déjà été importé par ce compte ? */
    public function findPreviousImport(User $user, UploadedFile $file): ?HandImport
    {
        return $user->handImports()->where('sha256', hash_file('sha256', $file->getRealPath()))->first();
    }

    /**
     * Importe un fichier envoyé (.txt ou .zip) et le garde en privé.
     *
     * @param  (\Closure(string $step, int $done, int $total): void)|null  $progress  suivi de l'avancement
     *
     * @throws ImportException si le fichier entier est inutilisable
     */
    public function importUpload(User $user, UploadedFile $file, ?\Closure $progress = null): HandImport
    {
        $sha = hash_file('sha256', $file->getRealPath());
        $texts = $this->readUpload($file);
        $storedPath = $this->storeUpload($user, $file, $sha);

        return $this->importTexts($user, $file->getClientOriginalName(), $sha, $texts, $storedPath, $progress);
    }

    /**
     * Étapes signalées à $progress : « parse » (par fichier), « store » (par main), « refresh » (fin).
     *
     * @param  array<string, string>  $texts  nom du fichier => contenu
     * @param  (\Closure(string $step, int $done, int $total): void)|null  $progress
     */
    public function importTexts(User $user, string $filename, string $sha, array $texts, ?string $storedPath = null, ?\Closure $progress = null): HandImport
    {
        $progress ??= fn () => null;
        [$site, $hands, $errors] = $this->parseTexts($texts, $progress);

        return DB::transaction(function () use ($user, $filename, $sha, $storedPath, $site, $hands, $errors, $progress) {
            $import = $user->handImports()->create([
                'filename' => $filename,
                'stored_path' => $storedPath,
                'sha256' => $sha,
                'site' => $site,
                'hands_found' => count($hands),
                'errors' => $errors ?: null,
            ]);

            [$imported, $tournaments] = $this->storeHands($import, $hands, $progress);

            $import->update([
                'hands_imported' => $imported,
                'duplicates' => count($hands) - $imported,
                'tournaments_touched' => $tournaments,
            ]);

            return $import;
        });
    }

    /**
     * Relance un import (après une correction de la lecture des historiques) : le fichier est relu,
     * les mains qui manquaient sont ajoutées à cet import et ses erreurs remplacées.
     * Sans $file, c'est le fichier conservé à l'import qui est relu.
     *
     * @param  (\Closure(string $step, int $done, int $total): void)|null  $progress
     *
     * @throws ImportException si le fichier est introuvable ou inutilisable
     */
    public function retry(HandImport $import, ?UploadedFile $file = null, ?\Closure $progress = null): HandImport
    {
        $progress ??= fn () => null;
        $disk = Storage::disk('local');
        $stored = $import->stored_path && $disk->exists($import->stored_path);

        if ($file !== null) {
            $texts = $this->readUpload($file);
            if (! $stored) {
                $import->stored_path = $this->storeUpload($import->user, $file, $import->sha256);
            }
        } elseif ($stored) {
            $path = $disk->path($import->stored_path);
            $texts = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip'
                ? $this->unzip($path)
                : [$import->filename => (string) file_get_contents($path)];
        } else {
            throw new ImportException('Le fichier d\'origine n\'est plus disponible : déposez-le à nouveau pour relancer l\'import.');
        }

        [$site, $hands, $errors] = $this->parseTexts($texts, $progress);

        return DB::transaction(function () use ($import, $site, $hands, $errors, $progress) {
            $this->storeHands($import, $hands, $progress);

            $imported = $import->hands()->count();
            $import->update([
                'stored_path' => $import->stored_path,
                'site' => $site ?? $import->site,
                'hands_found' => count($hands),
                'hands_imported' => $imported,
                'duplicates' => max(0, count($hands) - $imported),
                'tournaments_touched' => $import->hands()->distinct()->count('tournament_id'),
                'errors' => $errors ?: null,
            ]);

            return $import;
        });
    }

    /** @return array<string, string> nom du fichier => contenu */
    private function readUpload(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'zip'
            ? $this->unzip($file->getRealPath())
            : [$file->getClientOriginalName() => (string) file_get_contents($file->getRealPath())];
    }

    /** Stockage privé (storage/app/private), jamais servi directement. */
    private function storeUpload(User $user, UploadedFile $file, string $sha): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $file->storeAs("imports/{$user->id}", "{$sha}.{$extension}", 'local') ?: null;
    }

    /**
     * @param  array<string, string>  $texts
     * @param  \Closure(string, int, int): void  $progress
     * @return array{0: ?string, 1: list<ParsedHand>, 2: list<array<string, mixed>>}
     */
    private function parseTexts(array $texts, \Closure $progress): array
    {
        $hands = [];
        $errors = [];
        $site = null;
        $parsed = 0;

        foreach ($texts as $name => $content) {
            $progress('parse', $parsed++, count($texts));
            $parser = $this->parsers->for($content);
            if ($parser === null) {
                $errors[] = ['file' => $name, 'block' => 0, 'hand_id' => null, 'message' => 'Format non reconnu : seuls les exports Betclic sont pris en charge pour l\'instant.'];

                continue;
            }

            $site = $parser->site();
            $result = $parser->parse($content);
            array_push($hands, ...$result->hands);
            foreach ($result->errors as $error) {
                $errors[] = ['file' => $name] + $error;
            }
        }

        $progress('parse', count($texts), count($texts));

        return [$site, $hands, $errors];
    }

    /**
     * Enregistre les mains encore inconnues du compte, puis recalcule tournois et sessions.
     *
     * @param  list<ParsedHand>  $hands
     * @param  \Closure(string, int, int): void  $progress
     * @return array{0: int, 1: int} mains ajoutées, tournois touchés
     */
    private function storeHands(HandImport $import, array $hands, \Closure $progress): array
    {
        $user = $import->user;

        // Mains déjà connues de ce compte (ou présentes deux fois dans le fichier) : ignorées
        $known = $user->hands()
            ->whereIn('external_id', array_map(fn (ParsedHand $h) => $h->handId, $hands))
            ->pluck('external_id')
            ->flip()
            ->all();

        $tournaments = [];
        $imported = 0;

        foreach ($hands as $index => $hand) {
            $progress('store', $index, count($hands));
            if (isset($known[$hand->handId])) {
                continue;
            }
            $known[$hand->handId] = true;

            $tournament = $tournaments[$hand->gameId] ??= $this->tournamentFor($user, $hand);
            $this->storeHand($user, $import, $tournament, $hand);
            $imported++;
        }

        $progress('store', count($hands), count($hands));
        $progress('refresh', 0, 1);
        foreach ($tournaments as $tournament) {
            $this->aggregator->refresh($tournament);
        }
        $this->sessions->rebuild($user);

        return [$imported, count($tournaments)];
    }

    /** Supprime un import, ses mains et son fichier, puis recalcule tournois et sessions. */
    public function delete(HandImport $import): void
    {
        DB::transaction(function () use ($import) {
            $tournamentIds = $import->hands()->distinct()->pluck('tournament_id');

            $import->delete(); // les mains, joueurs et actions suivent (clés étrangères en cascade)

            Tournament::whereKey($tournamentIds)->get()->each(fn (Tournament $t) => $this->aggregator->refresh($t));
            $this->sessions->rebuild($import->user);
        });

        if ($import->stored_path) {
            Storage::disk('local')->delete($import->stored_path);
        }
    }

    private function tournamentFor(User $user, ParsedHand $hand): Tournament
    {
        return $user->tournaments()->firstOrCreate(
            ['site' => $hand->site, 'external_id' => $hand->gameId],
            [
                'game_name' => $hand->gameName,
                'game_mode' => $hand->gameMode,
                'buy_in' => $hand->buyIn,
                'prize_pool' => $hand->prizePool,
                'multiplier' => $hand->multiplier,
                'currency' => $hand->currency,
                'started_at' => $hand->playedAt,
                'ended_at' => $hand->endedAt,
            ],
        );
    }

    private function storeHand(User $user, HandImport $import, Tournament $tournament, ParsedHand $parsed): void
    {
        $hero = $parsed->hero();

        $hand = $user->hands()->create([
            'tournament_id' => $tournament->id,
            'hand_import_id' => $import->id,
            'external_id' => $parsed->handId,
            'table_id' => $parsed->tableId,
            'played_at' => $parsed->playedAt,
            'ended_at' => $parsed->endedAt,
            'small_blind' => $parsed->smallBlind,
            'big_blind' => $parsed->bigBlind,
            'players_count' => count($parsed->players),
            'hero_position' => $hero->position,
            'hero_cards' => $hero->cards,
            'hero_stack' => $hero->stack,
            'hero_stack_bb' => round($hero->stack / max(1, $parsed->bigBlind), 2),
            'board' => $parsed->board,
            'total_pot' => $parsed->totalPot,
            'hero_invested' => $hero->invested,
            'hero_won' => $hero->won,
            'hero_net' => $hero->net(),
            'went_to_showdown' => $parsed->wentToShowdown,
            'hero_all_in' => $parsed->heroAllIn(),
            'hero_finish_position' => $hero->finishPosition,
            'hero_prize' => $hero->prize,
            'raw_text' => $parsed->rawText,
            'parser_version' => Parsers\BetclicParser::VERSION,
        ]);

        $hand->players()->createMany(array_map(fn ($p) => [
            'seat' => $p->seat,
            'name' => $p->name,
            'stack' => $p->stack,
            'position' => $p->position,
            'is_hero' => $p->isHero,
            'cards' => $p->cards,
            'shown_rank' => $p->shownRank,
            'invested' => $p->invested,
            'won' => $p->won,
            'finish_position' => $p->finishPosition,
            'prize' => $p->prize,
        ], $parsed->players));

        // Insertion groupée : bien plus rapide qu'un modèle par action
        DB::table('hand_actions')->insert(array_map(fn ($a) => [
            'hand_id' => $hand->id,
            'sequence' => $a->sequence,
            'street' => $a->street,
            'player' => $a->player,
            'type' => $a->type,
            'amount' => $a->amount,
            'added' => $a->added,
            'street_total' => $a->streetTotal,
            'is_all_in' => $a->isAllIn,
            'acted_at' => $a->actedAt->format('Y-m-d H:i:s'),
            'decision_seconds' => $a->decisionSeconds,
        ], $parsed->actions));
    }

    /**
     * Extrait les fichiers .txt d'un ZIP, avec des limites contre les archives piégées.
     *
     * @return array<string, string>
     */
    private function unzip(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ImportException('Archive ZIP illisible.');
        }

        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw new ImportException('L\'archive contient trop de fichiers (200 maximum).');
            }

            $texts = [];
            $total = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];

                // Dossiers, chemins dangereux (« ../ », chemins absolus) et fichiers non texte : ignorés
                if (str_ends_with($name, '/') || str_contains($name, '..') || preg_match('#^([/\\\\]|[A-Za-z]:)#', $name)
                    || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'txt') {
                    continue;
                }

                $total += $stat['size'];
                if ($total > self::MAX_UNZIPPED_BYTES) {
                    throw new ImportException('L\'archive décompressée dépasse 50 Mo.');
                }

                $texts[basename($name)] = (string) $zip->getFromIndex($i);
            }
        } finally {
            $zip->close();
        }

        if ($texts === []) {
            throw new ImportException('L\'archive ne contient aucun fichier .txt.');
        }

        return $texts;
    }
}
