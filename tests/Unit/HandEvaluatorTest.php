<?php

namespace Tests\Unit;

use App\Poker\EquityCalculator;
use App\Poker\HandEvaluator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class HandEvaluatorTest extends TestCase
{
    private function score(string $cards): int
    {
        return HandEvaluator::evaluate(explode(' ', $cards));
    }

    private function category(string $cards): string
    {
        return HandEvaluator::categoryName($this->score($cards));
    }

    public function test_les_combinaisons_sont_reconnues(): void
    {
        $this->assertSame('Quinte flush', $this->category('As Ks Qs Js Ts 2d 3c'));
        $this->assertSame('Carré', $this->category('9h 9d 9s 9c 2h'));
        $this->assertSame('Full', $this->category('Kh Kd Ks 2c 2d 2h 7s')); // deux brelans : full
        $this->assertSame('Couleur', $this->category('Ah Kh 2h 7h 9h Qd Qc'));
        $this->assertSame('Quinte', $this->category('Ah 2c 3d 4s 5h Kd Kc')); // la roue
        $this->assertSame('Brelan', $this->category('7h 7d 7s Ac Kd'));
        $this->assertSame('Double paire', $this->category('Ah Ad Kh Kd Qs Qc 2s')); // trois paires
        $this->assertSame('Paire', $this->category('Ah Ad 9s 7c 3h'));
        $this->assertSame('Hauteur', $this->category('Ah Kd 9s 7c 3h'));
    }

    public function test_les_departages(): void
    {
        $this->assertGreaterThan($this->score('Ah Ad Kh Kd 2s'), $this->score('Ah Ad Kh Kd 3s')); // kicker
        $this->assertGreaterThan($this->score('Ah 2c 3d 4s 5h'), $this->score('6h 2c 3d 4s 5h')); // roue < quinte au 6
        $this->assertGreaterThan($this->score('Kh Kd Ks Ac Ad'), $this->score('Ah Ad As 2c 2d')); // full aux As > full aux Rois
        $this->assertSame($this->score('Ah Kd Qs Jc 9h'), $this->score('Ad Kc Qh Js 9c')); // égalité parfaite
    }

    public function test_equite_exacte_au_turn(): void
    {
        // Tirage couleur contre un brelan : 7 vraies cartes sur 44 (deux cœurs donnent un full au brelan)
        $equity = (new EquityCalculator)->equity(['Ah', '5h'], ['Ks', 'Kd'], ['Kh', '9h', '2c', '7s']);

        $this->assertSame(15.9, $equity['a']);
        $this->assertTrue($equity['exact']);
    }

    public function test_equite_preflop_par_echantillon_reproductible(): void
    {
        $random = fn () => new Randomizer(new Mt19937(42));
        $first = (new EquityCalculator)->equity(['Ah', 'As'], ['Kh', 'Kd'], [], $random());
        $second = (new EquityCalculator)->equity(['Ah', 'As'], ['Kh', 'Kd'], [], $random());

        $this->assertSame($first, $second);
        $this->assertEqualsWithDelta(82.0, $first['a'], 1.0); // AA contre KK : environ 82 %
        $this->assertFalse($first['exact']);
    }

    public function test_une_carte_en_double_est_refusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new EquityCalculator)->equity(['Ah', 'As'], ['Ah', 'Kd'], []);
    }
}
