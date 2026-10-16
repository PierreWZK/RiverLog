@extends('layouts.app')

@use('App\Support\Cards')
@use('App\Support\Money')

@section('title', $tournament->game_name)

@php
    $back = $demo ? route('demo.tournaments.index') : route('tournaments.index');
    $place = $tournament->finish_position;
    $positions = ['BTN' => 'Bouton', 'SB' => 'Petite blinde', 'BB' => 'Grosse blinde'];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <a href="{{ $back }}" class="back">← Tournois</a>
            <h1>{{ $tournament->game_name }} <span class="mult mult-lg">{{ $tournament->multiplierLabel() }}</span></h1>
            <p class="page-sub">
                {{ $tournament->started_at->translatedFormat('d/m/Y') }} de {{ $tournament->started_at->format('H:i') }} à {{ $tournament->ended_at->format('H:i') }}
                · buy-in {{ Money::format($tournament->buy_in, signed: false) }}
                @if ($tournament->prize_pool) · prize pool {{ Money::format($tournament->prize_pool, signed: false) }} @endif
            </p>
        </div>
    </div>

    <dl class="stats app-stats">
        <div class="stat">
            <dt>Place finale</dt>
            <dd>{{ $place ? ($place === 1 ? '1re' : $place.'e').' / '.$tournament->players_count : 'Incomplet' }}</dd>
        </div>
        <div class="stat">
            <dt>Résultat net</dt>
            <dd @class(['is-up' => $tournament->net_result > 0, 'is-down' => $tournament->net_result < 0])>
                {{ $tournament->net_result === null ? '—' : Money::format($tournament->net_result) }}
            </dd>
        </div>
        <div class="stat">
            <dt>Mains</dt>
            <dd>{{ $tournament->hands_count }}</dd>
        </div>
        <div class="stat">
            <dt>Durée</dt>
            <dd>{{ Money::duration($tournament->durationMinutes()) }}</dd>
        </div>
    </dl>

    @unless ($tournament->is_complete)
        <p class="sample-warning">La dernière main de ce tournoi n'a pas encore été importée : il est exclu des statistiques. Importez un export plus récent pour le compléter.</p>
    @endunless

    {{-- Tapis du Hero, main par main, en big blinds --}}
    <section class="panel">
        <h2 class="panel-title">Votre tapis, main par main <small class="panel-unit">en big blinds</small></h2>
        <div class="chart-box chart-box-sm">
            <canvas id="stack-chart" role="img" aria-label="Évolution du tapis en big blinds sur {{ $hands->count() }} mains"></canvas>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Mains <span class="count">{{ $hands->count() }}</span></h2>
        <div class="table-wrap">
            <table class="hands">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Heure</th>
                        <th scope="col">Blindes</th>
                        <th scope="col">Position</th>
                        <th scope="col">Cartes</th>
                        <th scope="col" class="num">Tapis</th>
                        <th scope="col">Board</th>
                        <th scope="col" class="num">Jetons</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hands as $hand)
                        <tr @class(['is-final' => $hand->hero_finish_position])>
                            <td class="muted">{{ $loop->iteration }}</td>
                            <td class="date">{{ $hand->played_at->format('H:i:s') }}</td>
                            <td class="muted">{{ $hand->small_blind }}/{{ $hand->big_blind }}</td>
                            <td>
                                <span class="pos" title="{{ $positions[$hand->hero_position] ?? 'Autre' }}">{{ $hand->hero_position ?? '—' }}</span>
                                @if ($hand->players_count === 2)<small class="muted">HU</small>@endif
                            </td>
                            <td>{{ Cards::html($hand->hero_cards) }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $hand->hero_stack_bb, 1, ',', ''), '0'), ',') }}&nbsp;BB</td>
                            <td>{{ Cards::html($hand->board) }}</td>
                            <td @class(['num', 'result', 'is-up' => $hand->hero_net > 0, 'is-down' => $hand->hero_net < 0])>
                                {{ $hand->hero_net > 0 ? '+' : ($hand->hero_net < 0 ? '−' : '') }}{{ number_format(abs($hand->hero_net), 0, ',', ' ') }}
                                <span class="tags">
                                    @if ($hand->hero_all_in)<span class="tag tag-allin">tapis</span>@endif
                                    @if ($hand->went_to_showdown)<span class="tag">abattage</span>@endif
                                    @if ($hand->hero_finish_position)
                                        <span class="tag tag-final">{{ $hand->hero_finish_position === 1 ? 'victoire' : 'sortie '.$hand->hero_finish_position.'e' }}</span>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        window.RIVERLOG_STACK = @json($hands->map(fn ($h, $i) => ['label' => 'Main '.($i + 1).' · '.$h->small_blind.'/'.$h->big_blind, 'value' => (float) $h->hero_stack_bb])->values());
    </script>
    <script src="{{ asset('js/tournament.js') }}"></script>
@endpush
