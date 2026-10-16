<?php

namespace App\Challenges;

use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Un défi quotidien sous forme de questions : le même pour tout le monde un jour donné.
 *
 * Les questions sont tirées avec un générateur initialisé par la date : rejouer
 * la génération donne toujours le même défi, et la correction se fait côté serveur
 * (les réponses ne sont jamais envoyées dans la page).
 */
abstract class DailyQuiz
{
    public const QUESTIONS = 5;

    public const POINTS_PER_QUESTION = 20;

    /** Identifiant dans l'URL et en base : « qui-est-devant », « calcul ». */
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Les questions du jour, sans la correction pour l'affichage (voir publicQuestions()).
     *
     * @return list<array<string, mixed>>
     */
    abstract public function puzzle(CarbonImmutable $day): array;

    /**
     * Corrige une question : points obtenus et détail affiché après la réponse.
     *
     * @param  array<string, mixed>  $question
     * @return array{points: int, correct: bool, answer: mixed}
     */
    abstract protected function gradeQuestion(array $question, mixed $answer): array;

    /** Règle de validation d'une réponse (avant correction). */
    abstract public function answerRule(): array;

    /**
     * @param  array<int, mixed>  $answers  réponse par question
     * @return array{score: int, max_score: int, questions: list<array<string, mixed>>}
     */
    public function grade(CarbonImmutable $day, array $answers): array
    {
        $questions = [];
        $score = 0;

        foreach ($this->puzzle($day) as $i => $question) {
            $graded = $this->gradeQuestion($question, $answers[$i] ?? null);
            $score += $graded['points'];
            $questions[] = $question + ['graded' => $graded];
        }

        return ['score' => $score, 'max_score' => self::QUESTIONS * self::POINTS_PER_QUESTION, 'questions' => $questions];
    }

    /** Générateur reproductible : même défi, même jour, pour tout le monde. */
    protected function random(CarbonImmutable $day): Randomizer
    {
        return new Randomizer(new Mt19937(crc32("riverlog:{$this->key()}:{$day->toDateString()}")));
    }
}
