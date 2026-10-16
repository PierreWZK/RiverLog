@extends('layouts.app')

@use('App\Support\Money')

@section('title', $demo ? 'Tournois (démo)' : 'Tournois')

@php
    $baseUrl = $demo ? route('demo.tournaments.index') : route('tournaments.index');
    $showRoute = $demo ? 'demo.tournaments.show' : 'tournaments.show';
    $ordinal = fn (int $place) => $place === 1 ? '1re' : $place.'e';
@endphp

@section('content')
    @if ($demo)
        <aside class="demo-banner">
            <div>
                <strong>Exemple de tournois importés</strong>
                <span>Spins fictifs, générés pour la démo. Avec un compte, importez vos propres historiques Betclic.</span>
            </div>
            @guest
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Créer mon compte</a>
            @endguest
        </aside>
    @endif

    <div class="page-head">
        <div>
            <h1>Tournois</h1>
            <p class="page-sub">Calculés à partir des historiques de mains importés.</p>
        </div>
    </div>

    @if (! $hasTournaments)
        <section class="empty">
            <span class="feature-icon chip-blue">♣</span>
            <h2>Aucun tournoi importé</h2>
            <p>Importez un historique de mains Betclic : ROI, victoires, multiplicateurs et places finales seront calculés automatiquement.</p>
            <div class="empty-actions">
                <a href="{{ route('imports.index') }}" class="btn btn-primary">Importer un historique</a>
                <a href="{{ route('demo.tournaments.index') }}" class="btn btn-ghost">Voir un exemple</a>
            </div>
        </section>
    @else
        <form method="GET" action="{{ $baseUrl }}" class="filters" aria-label="Filtres">
            <label class="field">
                <span>Du</span>
                <input type="date" name="from" value="{{ $filters->from?->toDateString() }}">
            </label>
            <label class="field">
                <span>Au</span>
                <input type="date" name="to" value="{{ $filters->to?->toDateString() }}">
            </label>
            <label class="field">
                <span>Multiplicateur</span>
                <select name="multiplier">
                    <option value="">Tous</option>
                    @foreach ($multipliers as $multiplier)
                        <option value="{{ $multiplier }}" @selected($filters->multiplier === $multiplier)>x{{ $multiplier }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Buy-in</span>
                <select name="buy_in">
                    <option value="">Tous</option>
                    @foreach ($buyIns as $buyIn)
                        <option value="{{ $buyIn }}" @selected($filters->buyIn === $buyIn)>{{ Money::format($buyIn, signed: false) }}</option>
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

        @if ($stats->count === 0)
            <section class="empty">
                <h2>Aucun tournoi ne correspond à ces filtres</h2>
                <a href="{{ $baseUrl }}" class="btn btn-ghost">Réinitialiser les filtres</a>
            </section>
        @else
            @if ($stats->isSmallSample())
                <p class="sample-warning">
                    <strong>Échantillon de {{ $stats->count }} tournoi{{ $stats->count > 1 ? 's' : '' }}.</strong>
                    Les résultats des Spins varient énormément : ces chiffres ne deviennent parlants qu'après plusieurs centaines de tournois.
                </p>
            @endif

            {{-- Rentabilité --}}
            <dl class="stats app-stats">
                <div class="stat">
                    <dt>Profit net</dt>
                    <dd @class(['is-up' => $stats->profit > 0, 'is-down' => $stats->profit < 0])>{{ Money::format($stats->profit) }}</dd>
                </div>
                <div class="stat">
                    <dt>ROI</dt>
                    <dd @class(['is-up' => $stats->roi() > 0, 'is-down' => $stats->roi() < 0])>{{ Money::percent($stats->roi(), signed: true) }}</dd>
                </div>
                <div class="stat">
                    <dt>Tournois</dt>
                    <dd>{{ $stats->count }}</dd>
                    @if ($stats->incomplete)
                        <small class="stat-note" title="Dernière main absente des fichiers importés : exclus des calculs">+ {{ $stats->incomplete }} incomplet{{ $stats->incomplete > 1 ? 's' : '' }}</small>
                    @endif
                </div>
                <div class="stat">
                    <dt>Victoires</dt>
                    <dd>{{ $stats->wins }} <small>{{ Money::percent($stats->winRate()) }}</small></dd>
                </div>
            </dl>

            {{-- Volume et détails --}}
            <dl class="stats app-stats stats-secondary">
                <div class="stat">
                    <dt>Buy-ins / Gains</dt>
                    <dd>{{ Money::format($stats->buyIns, signed: false) }} <small>/ {{ Money::format($stats->winnings, signed: false) }}</small></dd>
                </div>
                <div class="stat">
                    <dt>Profit moyen / tournoi</dt>
                    <dd>{{ $stats->averageProfit() === null ? '—' : Money::format($stats->averageProfit()) }}</dd>
                </div>
                <div class="stat">
                    <dt title="Heads-up gagnés ÷ heads-up atteints">Conversion heads-up</dt>
                    <dd>{{ Money::percent($stats->headsUpConversion()) }} <small>{{ $stats->headsUpWon }}/{{ $stats->headsUpReached }}</small></dd>
                </div>
                <div class="stat">
                    <dt>Mains · temps de jeu</dt>
                    <dd>{{ $stats->hands }} <small>· {{ Money::duration($stats->minutes) }}</small></dd>
                </div>
            </dl>

            <div class="panel-grid">
                {{-- Par multiplicateur --}}
                <section class="panel">
                    <h2 class="panel-title">Par multiplicateur</h2>
                    <div class="table-wrap">
                        <table class="breakdown">
                            <thead>
                                <tr>
                                    <th scope="col">Mult.</th>
                                    <th scope="col">Répartition</th>
                                    <th scope="col" class="num">Victoires</th>
                                    <th scope="col" class="num">Profit</th>
                                    <th scope="col" class="num">ROI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stats->byMultiplier as $row)
                                    <tr>
                                        <td><span class="mult">{{ $row['label'] }}</span></td>
                                        <td>
                                            <span class="bar"><span style="width: {{ $row['share'] }}%"></span></span>
                                            <small>{{ $row['count'] }} · {{ Money::percent($row['share']) }}</small>
                                        </td>
                                        <td class="num">{{ $row['wins'] }}</td>
                                        <td @class(['num', 'is-up' => $row['profit'] > 0, 'is-down' => $row['profit'] < 0])>{{ Money::format($row['profit']) }}</td>
                                        <td class="num">{{ Money::percent($row['roi'], signed: true) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="panel-note">
                        ROI hors jackpots (≥ x{{ $stats->jackpotThreshold }}) : <strong>{{ Money::percent($stats->roiWithoutJackpots, signed: true) }}</strong>
                        · part des gains due aux jackpots : <strong>{{ Money::percent($stats->jackpotShare) }}</strong>
                    </p>
                </section>

                {{-- Places finales --}}
                <section class="panel">
                    <h2 class="panel-title">Places finales</h2>
                    <ul class="positions">
                        @foreach ($stats->positions as $place => $count)
                            <li>
                                <span class="place place-{{ min($place, 3) }}">{{ $ordinal($place) }}</span>
                                <span class="bar"><span style="width: {{ round($count / $stats->count * 100, 1) }}%"></span></span>
                                <small>{{ $count }} · {{ Money::percent($count / $stats->count * 100) }}</small>
                            </li>
                        @endforeach
                    </ul>

                    @if (count($stats->byBuyIn) > 1)
                        <h2 class="panel-title panel-title-sub">Par buy-in</h2>
                        <ul class="positions">
                            @foreach ($stats->byBuyIn as $row)
                                <li>
                                    <span class="place">{{ $row['label'] }}</span>
                                    <span class="bar"><span style="width: {{ $row['share'] }}%"></span></span>
                                    <small>{{ $row['count'] }} · ROI {{ Money::percent($row['roi'], signed: true) }}</small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- Liste des tournois --}}
            <section class="panel">
                <h2 class="panel-title">Tournois <span class="count">{{ $tournaments->count() }}</span></h2>
                <div class="table-wrap">
                    <table class="tournaments">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Tournoi</th>
                                <th scope="col">Mult.</th>
                                <th scope="col" class="num">Mains</th>
                                <th scope="col" class="num">Durée</th>
                                <th scope="col">Place</th>
                                <th scope="col" class="num">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tournaments as $tournament)
                                <tr class="row-link">
                                    <td class="date">
                                        <a href="{{ route($showRoute, $tournament) }}">{{ $tournament->started_at->format('d/m/Y') }}</a>
                                        <small>{{ $tournament->started_at->format('H:i') }}</small>
                                    </td>
                                    <td>{{ $tournament->game_name }}</td>
                                    <td><span class="mult">{{ $tournament->multiplierLabel() }}</span></td>
                                    <td class="num">{{ $tournament->hands_count }}</td>
                                    <td class="num">{{ Money::duration($tournament->durationMinutes()) }}</td>
                                    <td>
                                        @if ($tournament->finish_position)
                                            <span class="place place-{{ min($tournament->finish_position, 3) }}">{{ $ordinal($tournament->finish_position) }}</span>
                                        @else
                                            <span class="badge-import" title="Dernière main absente des fichiers importés">incomplet</span>
                                        @endif
                                    </td>
                                    <td @class(['num', 'result', 'is-up' => $tournament->net_result > 0, 'is-down' => $tournament->net_result < 0])>
                                        {{ $tournament->net_result === null ? '—' : Money::format($tournament->net_result) }}
                                    </td>
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
    <script src="{{ asset('js/rows.js') }}"></script>
@endpush
