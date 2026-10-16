{{-- Onglets de l'application : espace personnel ou démo publique --}}
<nav class="tabs" aria-label="Sections">
    @if ($demo ?? false)
        <a href="{{ route('demo') }}" @class(['tab', 'is-current' => request()->routeIs('demo')])>Sessions</a>
        <a href="{{ route('demo.tournaments.index') }}" @class(['tab', 'is-current' => request()->routeIs('demo.tournaments.*')])>Parties importées</a>
    @else
        <a href="{{ route('dashboard') }}" @class(['tab', 'is-current' => request()->routeIs('dashboard')])>Sessions</a>
        <a href="{{ route('imports.index') }}" @class(['tab', 'is-current' => request()->routeIs('imports.*', 'tournaments.*')])>Import</a>
        <a href="{{ route('challenges.index') }}" @class(['tab', 'is-current' => request()->routeIs('challenges.*')])>Défis</a>
    @endif
</nav>
