<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Page vitrine de RiverLog.
 *
 * L'aperçu du dashboard utilise des données fictives, mais elles sont calculées
 * avec les mêmes règles que l'outil final (profit net, heures, taux horaire,
 * profit cumulé), pour que la vitrine montre des chiffres cohérents.
 */
class LandingController extends Controller
{
    // 24 sessions fictives : [résultat net en €, durée en minutes]
    private const DEMO_SESSIONS = [
        [45, 90], [-30, 120], [82.5, 60], [-60, 150], [15, 75], [120, 180],
        [-45, 90], [-20, 60], [60, 120], [35, 105], [-80, 135], [95, 90],
        [10, 60], [-15, 45], [70, 150], [-40, 120], [25, 90], [55, 75],
        [-65, 180], [30, 60], [0, 30], [-35, 120], [40, 105], [50, 90],
    ];

    // Dimensions du graphique SVG de l'aperçu
    private const CHART_WIDTH = 600;
    private const CHART_HEIGHT = 200;
    private const CHART_PADDING = 12;

    public function __invoke(): View
    {
        $results = array_column(self::DEMO_SESSIONS, 0);
        $minutes = array_sum(array_column(self::DEMO_SESSIONS, 1));

        $profit = array_sum($results);
        $hours = $minutes / 60;

        return view('landing', [
            'demo' => [
                'profit' => $profit,
                'hourly' => $hours > 0 ? $profit / $hours : null, // jamais de division par zéro
                'sessions' => count($results),
                'hours' => $hours,
            ],
            'chart' => $this->chart($results),
        ]);
    }

    /**
     * Prépare la courbe du profit cumulé (C_k = somme des k premiers résultats),
     * en coordonnées SVG : une ligne, une zone remplie et la ligne du zéro.
     */
    private function chart(array $results): array
    {
        $cumulative = [0];
        foreach ($results as $result) {
            $cumulative[] = end($cumulative) + $result;
        }

        $min = min($cumulative);
        $max = max($cumulative);
        $range = $max - $min ?: 1;
        $innerHeight = self::CHART_HEIGHT - 2 * self::CHART_PADDING;
        $step = self::CHART_WIDTH / (count($cumulative) - 1);

        $y = fn (float $value) => round(self::CHART_PADDING + ($max - $value) / $range * $innerHeight, 1);

        $points = [];
        foreach ($cumulative as $i => $value) {
            $points[] = round($i * $step, 1).','.$y($value);
        }

        $line = 'M'.implode(' L', $points);

        return [
            'width' => self::CHART_WIDTH,
            'height' => self::CHART_HEIGHT,
            'line' => $line,
            'area' => $line.' L'.self::CHART_WIDTH.','.self::CHART_HEIGHT.' L0,'.self::CHART_HEIGHT.' Z',
            'zeroY' => $y(0),
        ];
    }
}
