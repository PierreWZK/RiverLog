<?php

namespace App\Challenges;

use App\Poker\EquityCalculator;
use App\Poker\HandEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * « Qui est devant ? » : deux mains à tapis, estimer l'équité de la main A.
 *
 * 2 situations préflop, 2 au flop, 1 au turn. Chaque question rapporte jusqu'à
 * 20 points : 1 point de moins par point d'écart avec l'équité réelle.
 */
class EquityQuiz extends DailyQuiz
{
    /** Street de chaque question du jour. */
    private const STREETS = ['Préflop', 'Préflop', 'Flop', 'Flop', 'Turn'];

    /** Cartes déjà sur le board selon la street. */
    private const BOARD_SIZE = ['Préflop' => 0, 'Flop' => 3, 'Turn' => 4];

    public function __construct(private EquityCalculator $calculator) {}

    public function key(): string
    {
        return 'qui-est-devant';
    }

    public function title(): string
    {
        return 'Qui est devant ?';
    }

    public function description(): string
    {
        return 'Deux mains à tapis : estimez la chance de gagner de la main A. 5 situations, du préflop au turn.';
    }

    public function answerRule(): array
    {
        return ['required', 'integer', 'min:0', 'max:100'];
    }

    public function puzzle(CarbonImmutable $day): array
    {
        // Le préflop demande un calcul par échantillon : le défi du jour est mis en cache
        return Cache::rememberForever("defi:{$this->key()}:{$day->toDateString()}", function () use ($day) {
            $random = $this->random($day);
            $questions = [];

            foreach (self::STREETS as $street) {
                $boardSize = self::BOARD_SIZE[$street];
                // On évite les situations trop évidentes (plus de 90 %) ou les pur pile-ou-face
                for ($attempt = 0; $attempt < 60; $attempt++) {
                    $deck = $random->shuffleArray(HandEvaluator::deck());
                    $cards = array_map(HandEvaluator::card(...), array_slice($deck, 0, 4 + $boardSize));
                    $handA = array_slice($cards, 0, 2);
                    $handB = array_slice($cards, 2, 2);
                    $board = array_slice($cards, 4);

                    $equity = $this->calculator->equity($handA, $handB, $board, $random);
                    $favorite = max($equity['a'], $equity['b']);
                    if ($favorite >= 55 && $favorite <= 90) {
                        break;
                    }
                }

                $questions[] = [
                    'street' => $street,
                    'hand_a' => implode(' ', $handA),
                    'hand_b' => implode(' ', $handB),
                    'board' => $board ? implode(' ', $board) : null,
                    'equity_a' => $equity['a'],
                    'tie' => $equity['tie'],
                    'exact' => $equity['exact'],
                    // Combinaison actuelle de chaque main (utile au flop et au turn)
                    'made_a' => $board ? HandEvaluator::categoryName(HandEvaluator::evaluate([...$handA, ...$board])) : null,
                    'made_b' => $board ? HandEvaluator::categoryName(HandEvaluator::evaluate([...$handB, ...$board])) : null,
                ];
            }

            return $questions;
        });
    }

    protected function gradeQuestion(array $question, mixed $answer): array
    {
        $answer = (int) $answer;
        $gap = abs($answer - $question['equity_a']);
        $truthFavorsA = $question['equity_a'] > 50;

        return [
            'points' => (int) max(0, round(self::POINTS_PER_QUESTION - $gap)),
            // « Bon favori » : la réponse désigne la même main que la réalité
            'correct' => $answer !== 50 && ($answer > 50) === $truthFavorsA,
            'answer' => $answer,
            'gap' => round($gap, 1),
        ];
    }
}
