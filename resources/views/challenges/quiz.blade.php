@extends('layouts.app')

@use('App\Support\Cards')

@section('title', $quiz->title())

@php
    $isEquity = $quiz->key() === 'qui-est-devant';
    $pct = fn (float $v) => str_replace('.', ',', (string) round($v, 1)).' %';
@endphp

@section('content')
    <div class="page-head">
        <div>
            <a href="{{ route('challenges.index') }}" class="back">← Défis du jour</a>
            <h1>{{ $quiz->title() }}</h1>
            <p class="page-sub">{{ $quiz->description() }} · {{ $today->format('d/m/Y') }}</p>
        </div>
    </div>

    @if ($result)
        {{-- ---------- Correction ---------- --}}
        @php
            $marks = collect($result['questions'])->map(fn ($q) => $q['graded']['points'] >= 16 ? '🟩' : ($q['graded']['points'] >= 8 ? '🟨' : '⬛'))->implode('');
            $share = "RiverLog · {$quiz->title()} du {$today->format('d/m')} : {$result['score']}/{$result['max_score']} {$marks}\n".route('challenges.show', $quiz->key());
        @endphp

        <section class="panel score-panel">
            <div>
                <p class="score">{{ $result['score'] }}<small>/{{ $result['max_score'] }}</small></p>
                <p class="score-marks" aria-hidden="true">{{ $marks }}</p>
            </div>
            <div class="score-side">
                @if ($streak)
                    <p class="streak">🔥 {{ $streak['days'] }} jour{{ $streak['days'] > 1 ? 's' : '' }} d'affilée · meilleur score {{ $streak['best'] }}/100</p>
                @else
                    <p class="page-sub"><a href="{{ route('register') }}">Créez un compte</a> pour garder votre série de jours.</p>
                @endif
                <button type="button" class="btn btn-ghost btn-sm" data-share="{{ $share }}">Copier mon résultat</button>
                <p class="page-sub">Nouveau défi demain à minuit.</p>
            </div>
        </section>

        @foreach ($result['questions'] as $i => $q)
            @php($g = $q['graded'])
            <section @class(['panel', 'question', 'is-right' => $g['points'] >= 16, 'is-wrong' => $g['points'] < 8])>
                <p class="question-head">
                    <span>Question {{ $i + 1 }} · {{ $isEquity ? $q['street'] : $q['type'] }}</span>
                    <strong>+{{ $g['points'] }} pts</strong>
                </p>

                @if ($isEquity)
                    @include('challenges.partials.matchup', ['q' => $q])
                    <div class="equity-result">
                        <div class="equity-bar" aria-hidden="true">
                            <span class="equity-a" style="width: {{ $q['equity_a'] }}%"></span>
                            <span class="equity-guess" style="left: {{ $g['answer'] }}%" title="Votre estimation"></span>
                        </div>
                        <p>
                            Main A : <strong>{{ $pct($q['equity_a']) }}</strong>{{ $q['exact'] ? '' : ' (estimation sur 30 000 boards)' }}
                            · votre estimation : <strong>{{ $g['answer'] }} %</strong>
                            · écart {{ str_replace('.', ',', (string) $g['gap']) }} pt{{ $g['gap'] >= 2 ? 's' : '' }}
                            @if ($q['tie'] > 0) · égalité {{ $pct($q['tie']) }} @endif
                        </p>
                        <p class="muted">
                            {{ $g['correct'] ? '✓ Bon favori.' : '✗ Ce n\'était pas le bon favori.' }}
                            @if ($q['made_a']) Actuellement : main A {{ mb_strtolower($q['made_a']) }}, main B {{ mb_strtolower($q['made_b']) }}. @endif
                        </p>
                    </div>
                @else
                    <p class="question-text">{{ $q['text'] }}</p>
                    <ul class="choices is-graded">
                        @foreach ($q['choices'] as $c => $choice)
                            <li @class(['is-correct' => $c === $q['correct'], 'is-chosen' => $c === $g['answer']])>
                                {{ $choice }}
                                @if ($c === $q['correct']) <span>✓ bonne réponse</span>
                                @elseif ($c === $g['answer']) <span>✗ votre réponse</span> @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="explanation">{{ $q['explanation'] }}</p>
                @endif
            </section>
        @endforeach
    @else
        {{-- ---------- Questions ---------- --}}
        <form method="POST" action="{{ route('challenges.submit', $quiz->key()) }}" class="quiz-form" data-quiz>
            @csrf
            <input type="hidden" name="day" value="{{ $today->toDateString() }}">

            @if ($errors->any())
                <p class="form-alert" role="alert">{{ $errors->first() }}</p>
            @endif

            <p class="sample-warning">Une seule tentative par jour : la correction s'affiche quand vous validez les 5 réponses.</p>

            @foreach ($questions as $i => $q)
                <fieldset class="panel question">
                    <legend class="question-head"><span>Question {{ $i + 1 }} · {{ $isEquity ? $q['street'] : $q['type'] }}</span></legend>

                    @if ($isEquity)
                        @include('challenges.partials.matchup', ['q' => $q])
                        <label class="equity-input">
                            <span>Chance de gagner de la main A : <output data-equity-output>50 %</output></span>
                            <input type="range" name="answers[{{ $i }}]" min="0" max="100" step="1"
                                   value="{{ old("answers.$i", 50) }}" data-equity-range>
                            <span class="equity-scale"><span>Main B favorite</span><span>Main A favorite</span></span>
                        </label>
                    @else
                        <p class="question-text">{{ $q['text'] }}</p>
                        <div class="choices">
                            @foreach ($q['choices'] as $c => $choice)
                                <label class="choice">
                                    <input type="radio" name="answers[{{ $i }}]" value="{{ $c }}" required @checked(old("answers.$i") === (string) $c)>
                                    <span>{{ $choice }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>
            @endforeach

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Valider mes réponses</button>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/challenges.js') }}"></script>
@endpush
