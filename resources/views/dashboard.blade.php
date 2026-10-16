@extends('layouts.app')

@use('App\Support\Money')

@section('title', $demo ? 'Démo' : 'Dashboard')

@php
    // Les filtres et « Réinitialiser » restent sur la page affichée (démo ou dashboard)
    $baseUrl = $demo ? route('demo') : route('dashboard');
@endphp

@section('content')
    @if ($demo)
        {{-- Exemple public : données fictives, aucune modification possible --}}
        <aside class="demo-banner">
            <div>
                <strong>Exemple de dashboard</strong>
                <span>Données fictives, en lecture seule. Les filtres fonctionnent : essayez-les.</span>
            </div>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">Mon dashboard</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Créer mon compte</a>
            @endauth
        </aside>
    @endif

    <div class="page-head">
        <div>
            <h1>{{ $demo ? 'Bankroll de démonstration' : 'Ma bankroll' }}</h1>
            <p class="page-sub">
                @if ($filters->isActive())
                    Statistiques des sessions filtrées.
                @else
                    Toutes les sessions, de la plus récente à la plus ancienne.
                @endif
            </p>
        </div>
    </div>

    @if (! $hasSessions)
        {{-- Aucune session enregistrée --}}
        <section class="empty">
            <span class="feature-icon chip-green">♥</span>
            <h2>Aucune session pour l'instant</h2>
            <p>Ajoutez votre première session : RiverLog calculera votre profit, votre taux horaire et votre courbe.</p>
            <div class="empty-actions">
                <a href="{{ route('sessions.create') }}" class="btn btn-primary">Ajouter une session</a>
                <a href="{{ route('demo') }}" class="btn btn-ghost">Voir un exemple</a>
            </div>
        </section>
    @else
        {{-- Filtres : simple formulaire GET, l'URL garde l'état (partageable, bouton retour) --}}
        <form method="GET" action="{{ $baseUrl }}" class="filters" aria-label="Filtres">
            <label class="field">
                <span>Format</span>
                <select name="format">
                    <option value="">Tous</option>
                    @foreach ($formats as $value => $label)
                        <option value="{{ $value }}" @selected($filters->format?->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Du</span>
                <input type="date" name="from" value="{{ $filters->from?->toDateString() }}">
            </label>
            <label class="field">
                <span>Au</span>
                <input type="date" name="to" value="{{ $filters->to?->toDateString() }}">
            </label>
            <label class="field">
                <span>Résultat</span>
                <select name="result">
                    <option value="">Tous</option>
                    @foreach ($results as $value => $label)
                        <option value="{{ $value }}" @selected($filters->result === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="filters-actions">
                <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
                @if ($filters->isActive())
                    <a href="{{ $baseUrl }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </div>
        </form>

        {{-- Cartes de statistiques --}}
        <dl class="stats app-stats">
            <div class="stat">
                <dt>Profit net</dt>
                <dd @class(['is-up' => $stats->profit > 0, 'is-down' => $stats->profit < 0])>{{ Money::format($stats->profit) }}</dd>
            </div>
            <div class="stat">
                <dt>Taux horaire</dt>
                <dd @class(['is-up' => $stats->hourlyRate() > 0, 'is-down' => $stats->hourlyRate() < 0])>{{ Money::hourly($stats->hourlyRate()) }}</dd>
            </div>
            <div class="stat">
                <dt>Sessions</dt>
                <dd>{{ $stats->count }}</dd>
            </div>
            <div class="stat">
                <dt>Temps joué</dt>
                <dd>{{ Money::duration($stats->minutes) }}</dd>
            </div>
        </dl>

        @if ($stats->count === 0)
            {{-- Des sessions existent, mais aucune ne correspond aux filtres --}}
            <section class="empty">
                <h2>Aucune session ne correspond à ces filtres</h2>
                <p>Élargissez la période ou changez de format.</p>
                <a href="{{ $baseUrl }}" class="btn btn-ghost">Réinitialiser les filtres</a>
            </section>
        @else
            {{-- Courbe du profit cumulé --}}
            <section class="panel">
                <h2 class="panel-title">Évolution du profit cumulé</h2>
                <div class="chart-box">
                    <canvas id="profit-chart" role="img"
                            aria-label="Profit cumulé sur {{ $stats->count }} sessions, jusqu'à {{ Money::format($stats->profit) }}"></canvas>
                </div>
            </section>

            {{-- Tableau des sessions --}}
            <section class="panel">
                <h2 class="panel-title">Sessions <span class="count">{{ $stats->count }}</span></h2>
                <div class="table-wrap table-cards">
                    <table @class(['sessions', 'is-readonly' => $demo])>
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Format</th>
                                <th scope="col" class="num">Durée</th>
                                <th scope="col" class="num">Résultat</th>
                                <th scope="col">Notes</th>
                                @unless ($demo)
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr>
                                    <td class="date">
                                        {{ $session->played_at->format('d/m/Y') }}
                                        <small>{{ $session->played_at->format('H:i') }}</small>
                                    </td>
                                    <td class="fmt">
                                        <span class="format format-{{ $session->format->value }}">{{ $session->format->label() }}</span>
                                        @if ($session->isImported())
                                            <span class="badge-import" title="Générée par un import d'historique">importée</span>
                                        @endif
                                    </td>
                                    <td class="num duration">{{ Money::duration($session->duration_minutes) }}</td>
                                    <td @class(['num', 'result', 'is-up' => $session->net_result > 0, 'is-down' => $session->net_result < 0])>
                                        {{ Money::format($session->net_result) }}
                                    </td>
                                    <td class="notes">{{ $session->notes }}</td>
                                    @if ($demo)
                                    @elseif ($session->isImported())
                                    {{-- Recalculée à chaque import : on la consulte via ses parties importées --}}
                                    <td class="actions">
                                        <a href="{{ route('imports.index', ['from' => $session->played_at->toDateString(), 'to' => $session->played_at->toDateString()]) }}#parties"
                                           class="icon-btn" title="Voir les parties importées">
                                            <span class="sr-only">Voir les parties importées du {{ $session->played_at->format('d/m/Y') }}</span>→
                                        </a>
                                    </td>
                                    @else
                                    <td class="actions">
                                        <a href="{{ route('sessions.edit', $session) }}" class="icon-btn" title="Modifier">
                                            <span class="sr-only">Modifier la session du {{ $session->played_at->format('d/m/Y') }}</span>✎
                                        </a>
                                        <form method="POST" action="{{ route('sessions.destroy', $session) }}"
                                              data-confirm="Supprimer la session du {{ $session->played_at->format('d/m/Y') }} ({{ Money::format($session->net_result) }}) ?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn is-danger" title="Supprimer">
                                                <span class="sr-only">Supprimer la session du {{ $session->played_at->format('d/m/Y') }}</span>✕
                                            </button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
@endsection

@push('scripts')
    @if ($stats->count > 0)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script>
            window.RIVERLOG_CURVE = @json($stats->curve);
        </script>
    @endif
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
