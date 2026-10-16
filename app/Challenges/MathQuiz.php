<?php

namespace App\Challenges;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Random\Randomizer;

/**
 * « Le calcul du jour » : 5 questions de maths du poker et de gestion de bankroll,
 * à choix multiples, avec l'explication du calcul après la réponse.
 */
class MathQuiz extends DailyQuiz
{
    /** Tirages courants : nom => nombre d'outs. */
    private const DRAWS = [
        'un tirage couleur' => 9,
        'un tirage quinte par les deux bouts' => 8,
        'un tirage quinte ventral' => 4,
        'deux overcards' => 6,
        'un tirage couleur et quinte' => 15,
    ];

    /** Buy-ins courants, du plus petit au plus grand. */
    private const LIMITS = [0.25, 0.5, 1, 2, 5, 10, 20, 50];

    private const TEMPLATES = ['potOdds', 'outsFlop', 'callTurn', 'roi', 'hourlyRate', 'bankroll'];

    public function key(): string
    {
        return 'calcul';
    }

    public function title(): string
    {
        return 'Le calcul du jour';
    }

    public function description(): string
    {
        return 'Cotes du pot, outs, ROI, taux horaire, bankroll : 5 calculs de poker à choix multiples.';
    }

    public function answerRule(): array
    {
        return ['required', 'integer', 'min:0', 'max:3'];
    }

    public function puzzle(CarbonImmutable $day): array
    {
        $random = $this->random($day);
        $templates = array_slice($random->shuffleArray(self::TEMPLATES), 0, self::QUESTIONS);

        return array_map(fn (string $template) => $this->{$template}($random), $templates);
    }

    protected function gradeQuestion(array $question, mixed $answer): array
    {
        $correct = $answer !== null && (int) $answer === $question['correct'];

        return [
            'points' => $correct ? self::POINTS_PER_QUESTION : 0,
            'correct' => $correct,
            'answer' => $answer === null ? null : (int) $answer,
        ];
    }

    // ---------- Modèles de questions ----------

    private function potOdds(Randomizer $r): array
    {
        $pot = $r->getInt(6, 30) * 10;
        $bet = max(10, (int) round($pot * $r->shuffleArray([1 / 3, 1 / 2, 2 / 3, 3 / 4, 1])[0] / 5) * 5);
        $final = $pot + 2 * $bet;
        $needed = (int) round($bet / $final * 100);

        return $this->question(
            $r,
            type: 'Cotes du pot',
            text: "Le pot contient {$pot} jetons et votre adversaire mise {$bet}. Quelle équité minimale vous faut-il pour payer ?",
            correct: $needed,
            candidates: [(int) round($bet / $pot * 100), (int) round($bet / ($pot + $bet) * 100), $needed + 10],
            format: fn (int $v) => "{$v} %",
            explanation: "Vous payez {$bet} pour un pot final de {$pot} + {$bet} + {$bet} = {$final}. "
                ."Équité nécessaire : {$bet} ÷ {$final} ≈ {$needed} %.",
        );
    }

    private function outsFlop(Randomizer $r): array
    {
        [$name, $outs] = $this->draw($r);
        $exact = (1 - (47 - $outs) * (46 - $outs) / (47 * 46)) * 100;
        $rounded = (int) round($exact);

        return $this->question(
            $r,
            type: 'Outs',
            text: "Au flop, vous avez {$name} ({$outs} outs) et vous êtes à tapis. Quelle est votre chance d'améliorer d'ici la river ?",
            correct: $rounded,
            candidates: [$outs * 2, $rounded + 12, (int) round($rounded / 2)],
            format: fn (int $v) => "{$v} %",
            explanation: 'Chance de rater deux fois : '.(47 - $outs).'/47 × '.(46 - $outs)."/46, d'où 1 − ce résultat ≈ {$rounded} %. "
                ."La règle du 4 donne une estimation rapide : {$outs} × 4 = ".($outs * 4).' %.',
        );
    }

    private function callTurn(Randomizer $r): array
    {
        // On évite les cas limites où la réponse se jouerait à un point près
        do {
            [$name, $outs] = $this->draw($r);
            $pot = $r->getInt(6, 30) * 10;
            $bet = max(10, (int) round($pot * $r->getInt(25, 100) / 100 / 5) * 5);
            $equity = $outs / 46 * 100;
            $needed = $bet / ($pot + 2 * $bet) * 100;
        } while (abs($equity - $needed) < 4);

        $profitable = $equity > $needed;
        $choices = ['Oui, payer est rentable', 'Non, mieux vaut se coucher'];

        return [
            'type' => 'Payer ou se coucher',
            'text' => "Au turn, vous avez {$name} ({$outs} outs). Le pot est de {$pot} et votre adversaire mise {$bet}. "
                .'Sans compter les gains futurs, payer est-il rentable ?',
            'choices' => $choices,
            'correct' => $profitable ? 0 : 1,
            'explanation' => sprintf(
                'Votre équité à la river : %d ÷ 46 ≈ %s %%. Il en faut %s %% (%d ÷ %d). %s',
                $outs, number_format($equity, 1, ',', ''), number_format($needed, 1, ',', ''), $bet, $pot + 2 * $bet,
                $profitable ? 'Vous avez assez d\'équité : payer.' : 'Pas assez d\'équité : se coucher.',
            ),
        ];
    }

    private function roi(Randomizer $r): array
    {
        $buyIn = $r->shuffleArray([1, 2, 5, 10])[0];
        $count = $r->getInt(4, 20) * 10;
        do {
            $roi = $r->getInt(-6, 8) * 5;
        } while ($roi === 0);
        $spent = $buyIn * $count;
        $winnings = $spent * (100 + $roi) / 100;
        $profit = $winnings - $spent;

        return $this->question(
            $r,
            type: 'ROI',
            text: "Vous avez joué {$count} Spins à {$buyIn} € et encaissé ".Money::format($winnings, signed: false).' de gains. Quel est votre ROI ?',
            correct: $roi,
            candidates: [(int) round($profit / $winnings * 100), $roi + 10, -$roi],
            format: fn (int $v) => Money::percent($v, signed: true, decimals: 0),
            explanation: 'Profit : '.Money::format($winnings, signed: false).' − '.Money::format($spent, signed: false).' = '.Money::format($profit).'. '
                .'ROI = profit ÷ buy-ins = '.Money::format($profit).' ÷ '.Money::format($spent, signed: false).' = '.Money::percent($roi, signed: true, decimals: 0).'.',
            minGap: 3,
            min: -100,
            max: 1000,
        );
    }

    private function hourlyRate(Randomizer $r): array
    {
        do {
            $result = $r->getInt(-12, 30) * 5;
        } while ($result === 0);
        $minutes = $r->getInt(6, 20) * 15;
        $hours = $minutes / 60;
        $rate = (int) round($result / $hours);
        $duration = Money::duration($minutes);
        // Erreur classique : lire « 2 h 30 » comme 2,30 h
        $wrongHours = intdiv($minutes, 60) + ($minutes % 60) / 100;

        return $this->question(
            $r,
            type: 'Taux horaire',
            text: "Votre session a duré {$duration} pour un résultat de ".Money::format($result).'. Quel est votre taux horaire (arrondi) ?',
            correct: $rate,
            candidates: [(int) round($result / $wrongHours), (int) round($result / max(1, intdiv($minutes, 60))), $rate * 2],
            format: fn (int $v) => Money::format($v).'/h',
            explanation: "{$duration} = ".str_replace('.', ',', (string) round($hours, 2)).' h. '
                .Money::format($result).' ÷ '.str_replace('.', ',', (string) round($hours, 2))." h ≈ ".Money::format($rate).'/h.',
            minGap: 2,
            min: -1000,
            max: 1000,
        );
    }

    private function bankroll(Randomizer $r): array
    {
        $rule = $r->shuffleArray([50, 100])[0];
        do {
            $bankroll = $r->getInt(4, 60) * 50;
            $max = $bankroll / $rule;
            $allowed = array_values(array_filter(self::LIMITS, fn ($limit) => $limit <= $max));
            // Il faut au moins une limite au-dessus de la bonne réponse pour proposer un piège
        } while ($allowed === [] || count($allowed) === count(self::LIMITS));

        $correct = $allowed[count($allowed) - 1];
        $index = array_search($correct, self::LIMITS, true);
        // 4 limites consécutives autour de la bonne réponse, dont au moins une au-dessus
        $start = max(0, min($index - 2, count(self::LIMITS) - 4));
        $options = array_slice(self::LIMITS, $start, 4);
        $labels = array_map(fn ($limit) => Money::format($limit, signed: false), $options);

        return [
            'type' => 'Gestion de bankroll',
            'text' => 'Votre bankroll est de '.Money::format($bankroll, signed: false)." et vous appliquez la règle des {$rule} buy-ins. "
                .'Quel est le plus gros buy-in que vous pouvez jouer ?',
            'choices' => $labels,
            'correct' => array_search(Money::format($correct, signed: false), $labels, true),
            'explanation' => Money::format($bankroll, signed: false)." ÷ {$rule} = ".Money::format($max, signed: false)
                .' : le plus gros buy-in qui reste en dessous est '.Money::format($correct, signed: false).'.',
        ];
    }

    // ---------- Outils ----------

    /** @return array{0: string, 1: int} */
    private function draw(Randomizer $r): array
    {
        $name = $r->shuffleArray(array_keys(self::DRAWS))[0];

        return [$name, self::DRAWS[$name]];
    }

    /**
     * Question à 4 choix numériques : la bonne réponse et des pièges plausibles,
     * assez éloignés les uns des autres pour ne pas être ambigus.
     *
     * @param  list<int>  $candidates  erreurs classiques, dans l'ordre de préférence
     */
    private function question(
        Randomizer $r,
        string $type,
        string $text,
        int $correct,
        array $candidates,
        \Closure $format,
        string $explanation,
        int $minGap = 3,
        int $min = 1,
        int $max = 99,
    ): array {
        $values = [$correct];
        $fillers = [$correct + 6, $correct - 6, $correct + 12, $correct - 12, $correct + 18, $correct + 24, $correct + 30];

        foreach ([...$candidates, ...$fillers] as $value) {
            $inRange = $value >= $min && $value <= $max && $value !== 0;
            $farEnough = collect($values)->every(fn ($v) => abs($v - $value) >= $minGap);
            if (count($values) < 4 && $inRange && $farEnough) {
                $values[] = $value;
            }
        }

        $values = $r->shuffleArray($values);

        return [
            'type' => $type,
            'text' => $text,
            'choices' => array_map($format, $values),
            'correct' => array_search($correct, $values, true),
            'explanation' => $explanation,
        ];
    }
}
