<?php

namespace App\HandHistory;

use App\HandHistory\Data\ParsedHand;
use App\Models\HandImport;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
     * @throws ImportException si le fichier entier est inutilisable
     */
    public function importUpload(User $user, UploadedFile $file): HandImport
    {
        $sha = hash_file('sha256', $file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());

        $texts = $extension === 'zip'
            ? $this->unzip($file->getRealPath())
            : [$file->getClientOriginalName() => (string) file_get_contents($file->getRealPath())];

        // Stockage privé (storage/app/private), jamais servi directement
        $storedPath = $file->storeAs("imports/{$user->id}", "{$sha}.{$extension}", 'local');

        return $this->importTexts($user, $file->getClientOriginalName(), $sha, $texts, $storedPath ?: null);
    }

    /**
     * @param  array<string, string>  $texts  nom du fichier => contenu
     */
    public function importTexts(User $user, string $filename, string $sha, array $texts, ?string $storedPath = null): HandImport
    {
        $hands = [];
        $errors = [];
        $site = null;

        foreach ($texts as $name => $content) {
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

        return DB::transaction(function () use ($user, $filename, $sha, $storedPath, $site, $hands, $errors) {
            $import = $user->handImports()->create([
                'filename' => $filename,
                'stored_path' => $storedPath,
                'sha256' => $sha,
                'site' => $site,
                'hands_found' => count($hands),
                'errors' => $errors ?: null,
            ]);

            // Mains déjà connues de ce compte (ou présentes deux fois dans le fichier) : ignorées
            $known = $user->hands()
                ->whereIn('external_id', array_map(fn (ParsedHand $h) => $h->handId, $hands))
                ->pluck('external_id')
                ->flip()
                ->all();

            $tournaments = [];
            $imported = 0;

            foreach ($hands as $hand) {
                if (isset($known[$hand->handId])) {
                    continue;
                }
                $known[$hand->handId] = true;

                $tournament = $tournaments[$hand->gameId] ??= $this->tournamentFor($user, $hand);
                $this->storeHand($user, $import, $tournament, $hand);
                $imported++;
            }

            foreach ($tournaments as $tournament) {
                $this->aggregator->refresh($tournament);
            }
            $this->sessions->rebuild($user);

            $import->update([
                'hands_imported' => $imported,
                'duplicates' => count($hands) - $imported,
                'tournaments_touched' => count($tournaments),
            ]);

            return $import;
        });
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
            \Illuminate\Support\Facades\Storage::disk('local')->delete($import->stored_path);
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
