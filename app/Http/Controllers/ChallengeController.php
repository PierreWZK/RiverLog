<?php

namespace App\Http\Controllers;

use App\Challenges\DailyQuiz;
use App\Challenges\DisciplineChallenge;
use App\Challenges\EquityQuiz;
use App\Challenges\MathQuiz;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Défis quotidiens : deux quiz ouverts à tous (une tentative par jour) et le défi discipline.
 *
 * Avec un compte, les résultats sont enregistrés (série de jours, meilleur score) ;
 * sans compte, ils sont gardés dans la session du navigateur, le temps de la journée.
 */
class ChallengeController extends Controller
{
    public function __construct(private DisciplineChallenge $discipline) {}

    public function index(Request $request): View
    {
        $today = self::today();
        $user = $request->user();

        return view('challenges.index', [
            'today' => $today,
            'quizzes' => collect($this->quizzes())->map(fn (DailyQuiz $quiz) => [
                'quiz' => $quiz,
                'result' => $this->resultFor($request, $quiz, $today),
                'streak' => $user ? $this->streak($user, $quiz->key(), $today) : null,
            ]),
            'discipline' => $user ? $this->discipline->summary($user, $today) : null,
            'objective' => $this->discipline->objectiveFor($today),
        ]);
    }

    public function show(Request $request, string $challenge): View
    {
        $quiz = $this->quiz($challenge);
        $today = self::today();
        $result = $this->resultFor($request, $quiz, $today);

        return view('challenges.quiz', [
            'quiz' => $quiz,
            'today' => $today,
            'result' => $result,
            // Tant que le défi n'est pas joué, la page ne contient que les questions, sans les réponses
            'questions' => $result ? null : $this->withoutAnswers($quiz->puzzle($today)),
            'streak' => $request->user() ? $this->streak($request->user(), $quiz->key(), $today) : null,
        ]);
    }

    public function submit(Request $request, string $challenge): RedirectResponse
    {
        $quiz = $this->quiz($challenge);
        $today = self::today();

        $data = $request->validate([
            'day' => ['required', 'date_format:Y-m-d'],
            'answers' => ['required', 'array', 'size:'.DailyQuiz::QUESTIONS],
            'answers.*' => $quiz->answerRule(),
        ], [
            'answers.required' => 'Répondez aux 5 questions.',
            'answers.size' => 'Répondez aux 5 questions.',
            'answers.*.required' => 'Répondez aux 5 questions.',
        ]);

        // Le défi change à minuit : une réponse au défi d'hier n'est pas corrigée avec celui d'aujourd'hui
        if ($data['day'] !== $today->toDateString()) {
            return to_route('challenges.show', $challenge)->with('status', 'Le défi a changé à minuit : voici celui du jour.');
        }

        if ($this->resultFor($request, $quiz, $today)) {
            return to_route('challenges.show', $challenge)->with('status', 'Vous avez déjà relevé ce défi aujourd\'hui. Rendez-vous demain !');
        }

        $graded = $quiz->grade($today, array_values($data['answers']));

        if ($user = $request->user()) {
            $user->challengeResults()->create([
                'challenge' => $quiz->key(),
                'day' => $today->toDateString(),
                'score' => $graded['score'],
                'max_score' => $graded['max_score'],
                'details' => $graded['questions'],
            ]);
        } else {
            $request->session()->put($this->sessionKey($quiz, $today), $graded);
        }

        return to_route('challenges.show', $challenge);
    }

    public function discipline(Request $request): View
    {
        return view('challenges.discipline', [
            'summary' => $this->discipline->summary($request->user(), self::today()),
            'objectives' => DisciplineChallenge::OBJECTIVES,
        ]);
    }

    // ---------- Outils ----------

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /** @return array<string, DailyQuiz> */
    private function quizzes(): array
    {
        return [
            'qui-est-devant' => app(EquityQuiz::class),
            'calcul' => app(MathQuiz::class),
        ];
    }

    private function quiz(string $key): DailyQuiz
    {
        return $this->quizzes()[$key] ?? abort(404);
    }

    /**
     * Résultat du jour : en base pour un compte, dans la session pour un visiteur.
     *
     * @return array{score: int, max_score: int, questions: list<array>}|null
     */
    private function resultFor(Request $request, DailyQuiz $quiz, CarbonImmutable $day): ?array
    {
        if ($user = $request->user()) {
            $result = $user->challengeResults()
                ->where('challenge', $quiz->key())
                ->whereDate('day', $day->toDateString())
                ->first();

            return $result ? ['score' => $result->score, 'max_score' => $result->max_score, 'questions' => $result->details] : null;
        }

        return $request->session()->get($this->sessionKey($quiz, $day));
    }

    /**
     * Jours consécutifs joués (jusqu'à aujourd'hui, ou hier si le défi du jour n'est pas encore fait)
     * et meilleur score.
     *
     * @return array{days: int, best: ?int, played: int}
     */
    private function streak(User $user, string $challenge, CarbonImmutable $today): array
    {
        $results = $user->challengeResults()->where('challenge', $challenge)->orderByDesc('day')->get(['day', 'score']);
        $days = $results->map(fn ($r) => $r->day->toDateString())->flip();

        $streak = 0;
        $day = $days->has($today->toDateString()) ? $today : $today->subDay();
        while ($days->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        return ['days' => $streak, 'best' => $results->max('score'), 'played' => $results->count()];
    }

    /**
     * Retire tout ce qui révélerait la réponse avant que le défi soit joué.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    private function withoutAnswers(array $questions): array
    {
        $hidden = ['equity_a', 'tie', 'exact', 'made_a', 'made_b', 'correct', 'explanation'];

        return array_map(fn (array $q) => array_diff_key($q, array_flip($hidden)), $questions);
    }

    private function sessionKey(DailyQuiz $quiz, CarbonImmutable $day): string
    {
        return "defis.{$quiz->key()}.{$day->toDateString()}";
    }
}
