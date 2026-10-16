@extends('layouts.app')

@section('title', 'Parties importées (démo)')

{{-- Page réservée à la démo : un vrai compte retrouve ses parties sur la page Import --}}

@section('content')
    <aside class="demo-banner">
        <div>
            <strong>Exemple de parties importées</strong>
            <span>Spins fictifs, générés pour la démo. Avec un compte, importez vos propres historiques Betclic.</span>
        </div>
        @guest
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Créer mon compte</a>
        @endguest
    </aside>

    <div class="page-head">
        <div>
            <h1>Parties importées</h1>
            <p class="page-sub">Calculées à partir des historiques de mains importés.</p>
        </div>
    </div>

    @if (! $hasTournaments)
        <section class="empty">
            <span class="feature-icon chip-blue">♣</span>
            <h2>Aucune partie importée</h2>
            <p>Importez un historique de mains Betclic : ROI, victoires, multiplicateurs et places finales seront calculés automatiquement.</p>
            <div class="empty-actions">
                <a href="{{ route('register') }}" class="btn btn-primary">Créer mon compte</a>
            </div>
        </section>
    @else
        @include('tournaments._results', ['baseUrl' => route('demo.tournaments.index'), 'showRoute' => 'demo.tournaments.show'])
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/rows.js') }}"></script>
@endpush
