@extends('layouts.app')

@section('title', 'Défi discipline')

@php
    $today = $summary['today'];
    $statusLabels = ['success' => 'Réussi', 'failed' => 'Manqué', 'pending' => 'En cours', 'not_played' => 'Pas joué'];
    $statusText = [
        'success' => 'Objectif tenu aujourd\'hui. Bravo !',
        'failed' => 'Objectif manqué aujourd\'hui. Demain est un autre jour.',
        'pending' => 'La journée n\'est pas finie : l\'objectif est validé à minuit s\'il est respecté.',
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <a href="{{ route('challenges.index') }}" class="back">← Défis du jour</a>
            <h1>Défi discipline</h1>
            <p class="page-sub">Un objectif de gestion de bankroll par jour, vérifié sur vos sessions et vos tournois importés.</p>
        </div>
    </div>

    <section class="panel discipline-today is-{{ $summary['status'] }}">
        <span class="discipline-icon" aria-hidden="true">{{ $today['icon'] }}</span>
        <div>
            <p class="question-head"><span>Objectif du jour</span><span class="challenge-badge is-{{ $summary['status'] }}">{{ $statusLabels[$summary['status']] }}</span></p>
            <h2>{{ $today['title'] }}</h2>
            <p class="discipline-rule">{{ $today['rule'] }}</p>
            <p class="discipline-progress">{{ $summary['progress'] }}</p>
            <p class="muted">{{ $statusText[$summary['status']] ?? '' }}</p>
        </div>
    </section>

    <dl class="stats app-stats">
        <div class="stat">
            <dt>Série en cours</dt>
            <dd>🔥 {{ $summary['streak'] }} <small>jour{{ $summary['streak'] > 1 ? 's' : '' }}</small></dd>
        </div>
        <div class="stat">
            <dt>Réussis sur 14 jours</dt>
            <dd>{{ collect($summary['history'])->where('status', 'success')->count() }}</dd>
        </div>
        <div class="stat">
            <dt>Manqués sur 14 jours</dt>
            <dd>{{ collect($summary['history'])->where('status', 'failed')->count() }}</dd>
        </div>
        <div class="stat">
            <dt>Jours sans jeu</dt>
            <dd>{{ collect($summary['history'])->where('status', 'not_played')->count() }}</dd>
        </div>
    </dl>

    <section class="panel">
        <h2 class="panel-title">Les 14 derniers jours</h2>
        <ol class="discipline-history">
            @foreach ($summary['history'] as $day)
                <li class="is-{{ $day['status'] }}" title="{{ $day['objective']['title'] }} · {{ $statusLabels[$day['status']] }} · {{ $day['progress'] }}">
                    <span class="history-day">{{ ucfirst($day['day']->locale('fr')->translatedFormat('D j')) }}</span>
                    <span class="history-icon" aria-hidden="true">{{ $day['objective']['icon'] }}</span>
                    <span class="history-status">{{ $statusLabels[$day['status']] }}</span>
                </li>
            @endforeach
        </ol>
        <p class="panel-note">Un jour sans jeu ne casse pas la série, sauf le jour de repos qui se réussit justement en ne jouant pas.</p>
    </section>

    <section class="panel">
        <h2 class="panel-title">Les objectifs</h2>
        <ul class="objectives">
            @foreach ($objectives as $objective)
                <li>
                    <span class="history-icon" aria-hidden="true">{{ $objective['icon'] }}</span>
                    <div><strong>{{ $objective['title'] }}</strong> · {{ $objective['rule'] }}</div>
                </li>
            @endforeach
        </ul>
        <p class="panel-note">Ils tournent sur une semaine, avec un jour de repos. Les sessions importées comptent, sauf pour le bilan écrit (leurs notes sont automatiques).</p>
    </section>
@endsection
