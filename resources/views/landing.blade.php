@php
    $repo = 'https://github.com/PierreWZK/RiverLog';
    $spec = 'https://github.com/PierreWZK/RiverLog/blob/master/CAHIER_DES_CHARGES.md';

    // Formats d'affichage (français) : +342,50 € · 8,56 €/h
    $money = fn (float $v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '')).number_format(abs($v), 2, ',', ' ').' €';
    $rate = fn (?float $v) => $v === null ? '—' : number_format($v, 2, ',', ' ').' €/h';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RiverLog · Suivi de bankroll poker</title>
    <meta name="description" content="RiverLog suit vos sessions de poker : profit net, taux horaire, courbe de résultats et filtres par format et période.">
    <link rel="icon" href="{{ asset('riverlog.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body>
    <header class="nav container">
        <a href="{{ route('landing') }}" class="logo">
            <img src="{{ asset('riverlog.svg') }}" alt="" width="30" height="30">
            RiverLog
        </a>
        <nav class="nav-actions">
            <a href="{{ $repo }}" class="nav-link nav-github" target="_blank" rel="noopener">GitHub ↗</a>
            @auth
                <a href="{{ route('dashboard') }}" class="nav-link">Mon dashboard</a>
            @else
                <a href="{{ route('login') }}" class="nav-link">Connexion</a>
            @endauth
        </nav>
    </header>

    <main>
        {{-- Hero --}}
        <section class="hero container">
            <p class="badge"><span class="badge-dot"></span> En développement · Jour 3/30</p>
            <h1>Vos sessions de poker, <span>enfin en chiffres.</span></h1>
            <p class="lead">
                RiverLog enregistre chaque session et calcule ce qui compte vraiment :
                votre profit net, votre taux horaire et l'évolution de vos résultats,
                format par format.
            </p>
            <div class="hero-actions">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Ouvrir mon dashboard →</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary">Créer mon compte →</a>
                @endauth
                <a href="{{ route('demo') }}" class="btn btn-ghost">Voir un exemple de dashboard</a>
            </div>

            {{-- Aperçu du dashboard, avec des données fictives --}}
            <figure class="preview">
                <div class="preview-head">
                    <span class="preview-title">Bankroll Overview</span>
                    <span class="preview-tag">Données fictives</span>
                </div>

                <dl class="stats">
                    <div class="stat">
                        <dt>Profit net</dt>
                        <dd class="{{ $demo['profit'] >= 0 ? 'is-up' : 'is-down' }}">{{ $money($demo['profit']) }}</dd>
                    </div>
                    <div class="stat">
                        <dt>Taux horaire</dt>
                        <dd>{{ $rate($demo['hourly']) }}</dd>
                    </div>
                    <div class="stat">
                        <dt>Sessions</dt>
                        <dd>{{ $demo['sessions'] }}</dd>
                    </div>
                    <div class="stat">
                        <dt>Temps joué</dt>
                        <dd>{{ number_format($demo['hours'], 0, ',', ' ') }} h</dd>
                    </div>
                </dl>

                <div class="chart">
                    <p class="chart-title">Évolution du profit cumulé</p>
                    <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" preserveAspectRatio="none" role="img"
                         aria-label="Courbe du profit cumulé sur 24 sessions fictives, de 0 à {{ $money($demo['profit']) }}">
                        <defs>
                            <linearGradient id="area" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#2fbf71" stop-opacity="0.35" />
                                <stop offset="1" stop-color="#2fbf71" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <line x1="0" x2="{{ $chart['width'] }}" y1="{{ $chart['zeroY'] }}" y2="{{ $chart['zeroY'] }}" class="chart-zero" />
                        <path d="{{ $chart['area'] }}" fill="url(#area)" />
                        <path d="{{ $chart['line'] }}" class="chart-line" />
                    </svg>
                    <div class="chart-axis"><span>Début de période</span><span>Fin de période</span></div>
                </div>
            </figure>
        </section>

        {{-- Fonctionnalités --}}
        <section class="section container">
            <h2>Ce que fera l'outil</h2>
            <p class="section-lead">Créez votre compte : vous saisissez vos sessions, RiverLog fait les calculs. Vos données restent privées, visibles par vous seul.</p>

            <div class="features">
                <article class="feature">
                    <span class="feature-icon chip-red">♠</span>
                    <h3>Historique des sessions</h3>
                    <p>Date, format, durée, résultat net et notes. Ajout, modification et suppression, de la plus récente à la plus ancienne.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-gold">♦</span>
                    <h3>Statistiques claires</h3>
                    <p>Profit net, nombre de sessions, temps joué et taux horaire, calculés côté serveur sur vos vraies données.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-blue">♣</span>
                    <h3>Filtres qui recalculent tout</h3>
                    <p>
                        Par format
                        <span class="pill">Cash Game</span><span class="pill">MTT</span><span class="pill">Sit &amp; Go</span>,
                        par période et par résultat. Les chiffres, la courbe et le tableau suivent.
                    </p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-green">♥</span>
                    <h3>Courbe de profit</h3>
                    <p>L'évolution de votre résultat cumulé, session après session, dans l'ordre chronologique.</p>
                </article>
            </div>
        </section>

        {{-- Les calculs --}}
        <section class="section container">
            <h2>Des calculs fiables</h2>
            <p class="section-lead">Le cœur de l'outil. Les mêmes règles partout : cartes, courbe et tableau utilisent toujours le même ensemble de sessions.</p>

            <div class="formulas">
                <div class="formula">
                    <span class="formula-name">Profit net</span>
                    <code>Σ résultats</code>
                    <p>Une session perdante de 50 € compte pour −50,00 €.</p>
                </div>
                <div class="formula">
                    <span class="formula-name">Temps joué</span>
                    <code>Σ durées ÷ 60</code>
                    <p>Les durées sont saisies en minutes, affichées en heures.</p>
                </div>
                <div class="formula">
                    <span class="formula-name">Taux horaire</span>
                    <code>profit ÷ heures</code>
                    <p>Affiché « — » sans heure jouée : jamais de division par zéro.</p>
                </div>
                <div class="formula">
                    <span class="formula-name">Profit cumulé</span>
                    <code>C<sub>k</sub> = Σ<sub>i≤k</sub> résultat<sub>i</sub></code>
                    <p>Calculé dans l'ordre chronologique, pour tracer la courbe.</p>
                </div>
            </div>
        </section>

        {{-- Feuille de route --}}
        <section class="section container">
            <h2>Feuille de route</h2>
            <p class="section-lead">Le projet avance en public, étape par étape. Il tourne en local pour l'instant, la mise en ligne viendra ensuite.</p>

            <ol class="roadmap">
                <li class="is-done"><span>Projet Laravel installé et cahier des charges rédigé</span></li>
                <li class="is-done"><span>Page vitrine</span></li>
                <li><span>Sessions : base de données, ajout, modification, suppression</span></li>
                <li><span>Comptes personnels : chacun ses sessions, plus un dashboard de démonstration</span></li>
                <li><span>Calculs : profit net, temps joué, taux horaire</span></li>
                <li><span>Dashboard : cartes statistiques, tableau et courbe Chart.js</span></li>
                <li><span>Filtres par format, période et résultat, dans l'URL</span></li>
                <li><span>Tests, documentation et données de démonstration</span></li>
                <li class="is-later">
                    <span>Mise en production : l'outil en ligne, avec une vraie base de données</span>
                    <em class="tag-later">Plus tard</em>
                </li>
            </ol>

            <p class="bonus">Et ensuite, si le temps le permet : export CSV, objectif de bankroll, comparaison des formats, import CSV.</p>
        </section>
    </main>

    <footer class="footer container">
        <span>RiverLog · Jour 03 du défi 30 jours, 30 projets</span>
        <span>Laravel · Blade · Chart.js</span>
    </footer>
</body>
</html>
