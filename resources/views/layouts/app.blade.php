<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') · RiverLog</title>
    <link rel="icon" href="{{ asset('riverlog.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    {{-- Base commune avec la page vitrine (couleurs, boutons, cartes), puis les styles de l'application --}}
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body class="app">
    <header class="nav container">
        <a href="{{ route('landing') }}" class="logo">
            <img src="{{ asset('riverlog.svg') }}" alt="" width="30" height="30">
            <span class="logo-text">RiverLog</span>
        </a>
        <nav class="app-nav">
            @auth
                <a href="{{ route('sessions.create') }}" class="btn btn-primary btn-sm">+ Session</a>
                {{-- Menu du compte : <details> s'ouvre et se ferme sans JavaScript --}}
                <details class="account">
                    <summary class="avatar" title="Mon compte">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}<span class="sr-only">Mon compte</span>
                    </summary>
                    <div class="account-menu">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ auth()->user()->email }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">Se déconnecter</button>
                        </form>
                    </div>
                </details>
            @else
                <a href="{{ route('login') }}" @class(['nav-link', 'is-current' => request()->routeIs('login')])>Connexion</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Créer un compte</a>
            @endauth
        </nav>
    </header>

    <main class="container app-main">
        {{-- Onglets Sessions / Import / Défis (ou ceux de la démo) --}}
        @if (($demo ?? false) || (auth()->check() && ! request()->routeIs('login', 'register')))
            @include('partials.tabs', ['demo' => $demo ?? false])
        @endif

        @if (session('status'))
            <p class="flash" role="status">{{ session('status') }}</p>
        @endif

        @yield('content')
    </main>

    <footer class="footer container">
        <span>RiverLog · Jour 03 du défi 30 jours, 30 projets</span>
        <span>Fait avec Laravel · <a href="https://github.com/PierreWZK/RiverLog" target="_blank" rel="noopener">Code sur GitHub ↗</a></span>
    </footer>

    <script src="{{ asset('js/password-toggle.js') }}"></script>
    @stack('scripts')
</body>
</html>
