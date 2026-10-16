@extends('layouts.app')

@section('title', 'Défis du jour')

@php
    $statusLabels = ['success' => 'Réussi', 'failed' => 'Manqué', 'pending' => 'En cours', 'not_played' => 'Pas joué'];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Défis du jour</h1>
            <p class="page-sub">{{ ucfirst($today->locale('fr')->translatedFormat('l j F Y')) }} · les mêmes défis pour tout le monde, renouvelés chaque jour à minuit.</p>
        </div>
    </div>

    <div class="challenge-grid">
        @foreach ($quizzes as $item)
            @php($quiz = $item['quiz'])
            <article class="panel challenge-card">
                <span class="feature-icon {{ $quiz->key() === 'calcul' ? 'chip-gold' : 'chip-red' }}">{{ $quiz->key() === 'calcul' ? '♦' : '♠' }}</span>
                <h2>{{ $quiz->title() }}</h2>
                <p>{{ $quiz->description() }}</p>

                <div class="challenge-meta">
                    @if ($item['result'])
                        <span class="challenge-badge is-done">Fait · {{ $item['result']['score'] }}/{{ $item['result']['max_score'] }}</span>
                    @else
                        <span class="challenge-badge">À relever</span>
                    @endif
                    @if ($item['streak'])
                        <span class="streak" title="Jours consécutifs joués">🔥 {{ $item['streak']['days'] }} jour{{ $item['streak']['days'] > 1 ? 's' : '' }}</span>
                    @endif
                </div>

                <a href="{{ route('challenges.show', $quiz->key()) }}" class="btn {{ $item['result'] ? 'btn-ghost' : 'btn-primary' }}">
                    {{ $item['result'] ? 'Voir la correction' : 'Relever le défi' }} →
                </a>
            </article>
        @endforeach

        <article class="panel challenge-card">
            <span class="feature-icon chip-green">{{ $objective['icon'] }}</span>
            <h2>Défi discipline</h2>
            <p>Un objectif de gestion de bankroll par jour, vérifié sur vos vraies sessions. Aujourd'hui : <strong>{{ $objective['title'] }}</strong>, {{ lcfirst($objective['rule']) }}</p>

            @if ($discipline)
                <div class="challenge-meta">
                    <span class="challenge-badge is-{{ $discipline['status'] }}">{{ $statusLabels[$discipline['status']] }}</span>
                    <span class="streak" title="Jours réussis d'affilée">🔥 {{ $discipline['streak'] }} jour{{ $discipline['streak'] > 1 ? 's' : '' }}</span>
                </div>
                <a href="{{ route('challenges.discipline') }}" class="btn btn-ghost">Suivre mon défi →</a>
            @else
                <div class="challenge-meta">
                    <span class="challenge-badge">Avec un compte</span>
                </div>
                <a href="{{ route('register') }}" class="btn btn-ghost">Créer un compte →</a>
            @endif
        </article>
    </div>

    @guest
        <p class="sample-warning">
            <strong>Sans compte,</strong> vous pouvez jouer aux deux quiz, mais votre série de jours et vos scores ne sont pas gardés.
            <a href="{{ route('register') }}">Créer un compte</a>
        </p>
    @endguest
@endsection
