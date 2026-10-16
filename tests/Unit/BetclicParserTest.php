<?php

namespace Tests\Unit;

use App\HandHistory\Data\ParsedHand;
use App\HandHistory\Parsers\BetclicParser;
use Tests\TestCase;

/**
 * Lecture de l'export Betclic de test (Spin & Rush du 29/01/2026, pseudos adverses anonymisés).
 * Les valeurs attendues sont celles du cahier des charges, section 8.3.
 */
class BetclicParserTest extends TestCase
{
    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/betclic/spin-2026-01-29.txt'));
    }

    /** @return array<string, ParsedHand> */
    private function hands(): array
    {
        $result = (new BetclicParser)->parse($this->fixture());
        $this->assertSame([], $result->errors);

        $hands = [];
        foreach ($result->hands as $hand) {
            $hands[$hand->handId] = $hand;
        }

        return $hands;
    }

    public function test_le_fichier_est_reconnu(): void
    {
        $this->assertTrue((new BetclicParser)->supports($this->fixture()));
        $this->assertFalse((new BetclicParser)->supports("PokerStars Hand #1: Tournament"));
    }

    public function test_41_mains_et_5_tournois_sans_erreur(): void
    {
        $hands = $this->hands();
        $this->assertCount(41, $hands);

        $byGame = [];
        foreach ($hands as $hand) {
            $byGame[$hand->gameId][] = $hand;
        }
        $this->assertSame(
            ['01KG54P5VXCJ6SP5X0CYFA5SX9' => 11, '01KG54KQQF1VX799G35ZKBK684' => 3, '01KG54H5NZK1B93HYP07XYA353' => 4, '01KG4KN3HW485CEJA2ERC80S3Y' => 11, '01KG4KCM24RKDWY2MJ6GRR3X1E' => 12],
            array_map('count', $byGame),
        );
    }

    public function test_en_tete_du_tournoi_et_heure_de_paris(): void
    {
        $hand = $this->hands()['01KG4KD0XJRCSNVT4XDTTP5JB8'];

        $this->assertSame('Spin & Rush 1€', $hand->gameName);
        $this->assertSame('Spin', $hand->gameMode);
        $this->assertSame('1.00', $hand->buyIn);
        $this->assertSame('4.00', $hand->prizePool);
        $this->assertSame(4, $hand->multiplier);
        $this->assertSame([10, 20], [$hand->smallBlind, $hand->bigBlind]);
        // 10:04:53 UTC = 11:04:53 à Paris en hiver
        $this->assertSame('2026-01-29 11:04:53', $hand->playedAt->format('Y-m-d H:i:s'));
        $this->assertSame('Europe/Paris', $hand->playedAt->timezoneName);
    }

    public function test_joueurs_positions_et_cartes(): void
    {
        $hand = $this->hands()['01KG54X2NBEWT0YV6H4EA0B2V7'];
        $hero = $hand->hero();

        $this->assertSame('PierreWZK', $hero->name);
        $this->assertSame('BTN', $hero->position);
        $this->assertSame('8s Ac', $hero->cards);
        $this->assertSame('Two Pair', $hero->shownRank);
        $this->assertSame('Ah 5s 6s 3s 8c', $hand->board);
        $this->assertTrue($hand->wentToShowdown);
        // En heads-up, le bouton est noté BTN (il est aussi petite blinde)
        $this->assertSame('BTN', $this->hands()['01KG54Z6SPDJC3BDGMY49EHZEA']->players[0]->position);
    }

    public function test_pseudo_avec_accent(): void
    {
        $names = array_column($this->hands()['01KG54MCVFWSNA4JFTXXPBCFWJ']->players, 'name');

        $this->assertContains('Néné9', $names);
    }

    public function test_tapis_non_suivi_tout_le_monde_se_couche(): void
    {
        // Tapis de 390 en petite blinde, les deux autres se couchent : pot de 430
        $hero = $this->hands()['01KG54SNM3X4MZBY258T8K3G7F']->hero();

        $this->assertSame([390, 430, 40], [$hero->invested, $hero->won, $hero->net()]);
    }

    public function test_tapis_paye_par_un_joueur_plus_court(): void
    {
        // Tapis de 850 payé par un stack de 650 : 200 sont rendus, pot de 1300
        $hand = $this->hands()['01KG54Z6SPDJC3BDGMY49EHZEA'];
        $hero = $hand->hero();

        $this->assertSame([650, 1300, 650], [$hero->invested, $hero->won, $hero->net()]);
        $this->assertTrue($hand->heroAllIn());
        $this->assertSame(1, $hero->finishPosition);
        $this->assertSame('10.00', $hero->prize);
    }

    public function test_pot_secondaire(): void
    {
        // Le Hero perd le pot principal mais gagne le pot secondaire de 70
        $hero = $this->hands()['01KG54VD9HV07E4P02TEHXB747']->hero();

        $this->assertSame([75, 70, -5], [$hero->invested, $hero->won, $hero->net()]);
    }

    public function test_relance_en_total_de_street(): void
    {
        // Turn : bet 26, raise to 52, raise to 78, call 26 → chacun 78 sur la street
        $hand = $this->hands()['01KG4KP4JZ551HXCPWG564DPS6'];
        $turn = array_values(array_filter($hand->actions, fn ($a) => $a->street === 'turn'));

        $this->assertSame([26, 52, 52, 26], array_column($turn, 'added'));
        $this->assertSame([26, 52, 78, 78], array_column($turn, 'streetTotal'));
        // Hero : 20 (préflop) + 78 (turn) + 402 (tapis river, adversaire couché) = 500 engagés, pot de 598
        $this->assertSame([500, 598, 98], [$hand->hero()->invested, $hand->hero()->won, $hand->hero()->net()]);
    }

    public function test_places_finales(): void
    {
        $hands = $this->hands();

        $this->assertSame(2, $hands['01KG4KV3WHJ1MZPEXJKRZ380Q7']->hero()->finishPosition);
        $this->assertNull($hands['01KG4KV3WHJ1MZPEXJKRZ380Q7']->hero()->prize);
        $this->assertSame(3, $hands['01KG54NDCVR0GD6JRP6N9SVJMA']->hero()->finishPosition);
        $this->assertSame(1, $hands['01KG4KK27VV5VXMNAJH4R6F1VV']->hero()->finishPosition);
        $this->assertSame('4.00', $hands['01KG4KK27VV5VXMNAJH4R6F1VV']->hero()->prize);
    }

    public function test_temps_de_decision(): void
    {
        // Tapis adverse à 15:11:31, le Hero se couche à 15:11:38 : 7 s
        $actions = $this->hands()['01KG54Y8MPPV9DM7ZQDVBC0D29']->actions;
        $fold = end($actions);

        $this->assertSame('fold', $fold->type);
        $this->assertSame(7, $fold->decisionSeconds);
        $this->assertNull($actions[0]->decisionSeconds); // blinde : automatique
    }

    public function test_bom_et_fins_de_ligne_windows(): void
    {
        $content = "\xEF\xBB\xBF".str_replace("\n", "\r\n", $this->fixture());

        $result = (new BetclicParser)->parse($content);

        $this->assertCount(41, $result->hands);
        $this->assertSame([], $result->errors);
    }

    public function test_une_main_incoherente_est_signalee_sans_bloquer_les_autres(): void
    {
        $content = preg_replace('/Total Pot: 1300/', 'Total Pot: 1350', $this->fixture(), 1);

        $result = (new BetclicParser)->parse($content);

        $this->assertCount(40, $result->hands);
        $this->assertCount(1, $result->errors);
        $this->assertSame('01KG54Z6SPDJC3BDGMY49EHZEA', $result->errors[0]['hand_id']);
        $this->assertStringContainsString('différente du pot total', $result->errors[0]['message']);
    }

    public function test_une_mise_superieure_au_tapis_est_refusee(): void
    {
        // Le Hero n'a plus que 650 jetons mais « paie » 850
        $block = explode('------------', $this->fixture())[0];
        $result = (new BetclicParser)->parse(str_replace('PierreWZK (850)', 'PierreWZK (650)', $block));

        $this->assertStringContainsString('mise plus que son tapis', $result->errors[0]['message']);
    }

    public function test_main_sans_hero(): void
    {
        $block = explode('------------', $this->fixture())[0];

        $result = (new BetclicParser)->parse(str_replace(' Hero]', ']', $block));

        $this->assertSame('Joueur « Hero » introuvable dans la main.', $result->errors[0]['message']);
    }

    /**
     * Cas relevés sur un export d'octobre 2026 : tournoi KO avec antes, déconnexions, mises
     * non suivies laissées dans le pot à trois joueurs.
     *
     * @return array<string, ParsedHand>
     */
    private function specialHands(): array
    {
        $result = (new BetclicParser)->parse(file_get_contents(base_path('tests/Fixtures/betclic/cas-particuliers.txt')));
        $this->assertSame([], $result->errors);

        $hands = [];
        foreach ($result->hands as $hand) {
            $hands[$hand->handId] = $hand;
        }

        return $hands;
    }

    public function test_tournoi_ko_avec_antes_et_deconnexion(): void
    {
        $hand = $this->specialHands()['01M45XJWPCGV671A5ZX30XRV3B'];
        $players = array_column($hand->players, null, 'name');

        $this->assertSame('Tournament', $hand->gameMode);
        $this->assertSame([856, 856, 18], [$players['Chasseur']->invested, $players['Requin']->invested, $players['PierreWZK']->invested]);
        $this->assertSame(1730, $players['Requin']->won);
        $this->assertSame(73, $players['Chasseur']->finishPosition);

        // Les antes sont des mises mortes : la relance « to 60 » ne les inclut pas
        $preflop = array_values(array_filter($hand->actions, fn ($a) => $a->street === 'preflop'));
        $this->assertSame(['post_ante', 3, 0], [$preflop[0]->type, $preflop[0]->added, $preflop[0]->streetTotal]);
        $this->assertSame([15, 30, 60], [$preflop[3]->streetTotal, $preflop[4]->streetTotal, $preflop[5]->added]);

        // « Disconnected » / « Reconnected » ne sont pas des actions
        $this->assertCount(4, array_filter($hand->actions, fn ($a) => $a->street === 'flop'));
    }

    public function test_mise_non_suivie_laissee_dans_le_pot_a_trois(): void
    {
        $hands = $this->specialHands();

        // Tapis de 1090 non suivi : Betclic le garde dans le pot (1180) et le rend en « side pot »
        $hero = $hands['01M470GHM3TPWR4T0ZDRE9Z6Q6']->hero();
        $this->assertSame([1090, 1150, 60], [$hero->invested, $hero->won, $hero->net()]);

        // Mise de 120 au turn sans suite, adversaire déjà à tapis : idem
        $hero = $hands['01M46VV8Y2T2FMYZH7DJT7Y21D']->hero();
        $this->assertSame([225, 210, -15], [$hero->invested, $hero->won, $hero->net()]);
    }
}
