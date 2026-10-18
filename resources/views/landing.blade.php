@php
    $repo = 'https://github.com/PierreWZK/RiverLog';

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
    <meta name="description" content="RiverLog suit votre bankroll poker : sessions, profit net, taux horaire, import des historiques Betclic, ROI et statistiques de tournois.">
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
            <a href="{{ route('challenges.index') }}" class="nav-link">Défis du jour</a>
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
            <p class="badge"><span class="badge-dot"></span> Gratuit · Import Betclic</p>
            <h1>Vos sessions de poker, <span>enfin en chiffres.</span></h1>
            <p class="lead">
                RiverLog suit votre bankroll et analyse vos tournois : profit net, taux horaire,
                ROI et évolution de vos résultats, à partir de vos sessions ou de vos historiques de mains.
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
        <section class="section container" id="fonctionnalites">
            <h2>Tout ce que fait RiverLog</h2>
            <p class="section-lead">Saisissez vos sessions à la main ou importez vos historiques de mains : RiverLog fait les calculs, vous gardez la tête au jeu.</p>

            <div class="features">
                <article class="feature">
                    <span class="feature-icon chip-red">♠</span>
                    <h3>Sessions de jeu</h3>
                    <p>
                        Date, format, durée, résultat et notes, en quelques secondes.
                        <span class="pill">Cash Game</span><span class="pill">MTT</span><span class="pill">Sit &amp; Go</span><span class="pill">Spin</span>
                    </p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-gold">♦</span>
                    <h3>Dashboard de bankroll</h3>
                    <p>Profit net, taux horaire, nombre de sessions et temps joué, avec la courbe de votre profit cumulé.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-blue">♣</span>
                    <h3>Filtres qui recalculent tout</h3>
                    <p>Par format, période et résultat. Les cartes, la courbe et le tableau suivent, et l'URL garde vos filtres.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-green">♥</span>
                    <h3>Import des historiques Betclic</h3>
                    <p>Déposez vos exports en .txt ou .zip : tournois, résultats et sessions sont créés automatiquement, sans doublon.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-red">♠</span>
                    <h3>Statistiques de tournois</h3>
                    <p>ROI, victoires, places finales, conversion en heads-up, résultats par multiplicateur et par buy-in, ROI hors jackpots.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-gold">♦</span>
                    <h3>Détail main par main</h3>
                    <p>Vos cartes, le board, votre tapis en big blinds et les jetons gagnés ou perdus, avec la courbe de votre tapis.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-blue">♣</span>
                    <h3>Sessions automatiques</h3>
                    <p>Les tournois joués à moins de 30 minutes d'écart forment une session, ajoutée d'office à votre dashboard.</p>
                </article>
                <article class="feature">
                    <span class="feature-icon chip-green">♥</span>
                    <h3>Un compte, des données privées</h3>
                    <p>Chacun ne voit que ses propres sessions, tournois et mains. Vos fichiers importés restent privés.</p>
                </article>
            </div>
        </section>

        {{-- Défis du jour --}}
        <section class="section container">
            <h2>Un défi par jour</h2>
            <p class="section-lead">Les mêmes défis pour tout le monde, renouvelés chaque jour à minuit. Gardez votre série de jours avec un compte.</p>

            <div class="features">
                <a href="{{ route('challenges.show', 'qui-est-devant') }}" class="feature feature-link">
                    <span class="feature-icon chip-red">♠</span>
                    <h3>Qui est devant ?</h3>
                    <p>Deux mains à tapis, du préflop au turn : estimez la chance de gagner de chacune. 5 situations, score sur 100.</p>
                </a>
                <a href="{{ route('challenges.show', 'calcul') }}" class="feature feature-link">
                    <span class="feature-icon chip-gold">♦</span>
                    <h3>Le calcul du jour</h3>
                    <p>Cotes du pot, outs, ROI, taux horaire, bankroll : 5 calculs à choix multiples, avec l'explication de chaque réponse.</p>
                </a>
                <a href="{{ route('challenges.index') }}" class="feature feature-link">
                    <span class="feature-icon chip-green">♥</span>
                    <h3>Défi discipline</h3>
                    <p>Un objectif de gestion de bankroll par jour (stop-loss, pause, jour de repos…), vérifié sur vos vraies sessions.</p>
                </a>
            </div>
        </section>

        {{-- Import en 3 étapes --}}
        <section class="section container">
            <h2>Vos tournois analysés en 3 étapes</h2>
            <p class="section-lead">Plus besoin de recopier vos résultats : l'historique de mains contient déjà tout.</p>

            <ol class="steps">
                <li>
                    <span class="step-number">1</span>
                    <h3>Exportez</h3>
                    <p>Téléchargez l'historique de vos mains depuis votre compte Betclic.</p>
                </li>
                <li>
                    <span class="step-number">2</span>
                    <h3>Déposez</h3>
                    <p>Glissez le fichier .txt ou l'archive .zip sur la page Import. Réimporter un fichier ne crée jamais de doublon.</p>
                </li>
                <li>
                    <span class="step-number">3</span>
                    <h3>Analysez</h3>
                    <p>Chaque tournoi est compté une seule fois : buy-in, multiplicateur, place finale, gain et toutes vos mains.</p>
                </li>
            </ol>
        </section>

        {{-- Les calculs --}}
        <section class="section container">
            <h2>Des calculs fiables</h2>
            <p class="section-lead">Les mêmes règles partout : cartes, courbes et tableaux utilisent toujours le même ensemble de données, filtré côté serveur.</p>

            <div class="formulas">
                <div class="formula">
                    <span class="formula-name">Profit net</span>
                    <code>Σ résultats</code>
                    <p>Calculé au centime près : une session perdante de 50 € compte pour −50,00 €.</p>
                </div>
                <div class="formula">
                    <span class="formula-name">Taux horaire</span>
                    <code>profit ÷ heures</code>
                    <p>Affiché « — » sans heure jouée : jamais de division par zéro.</p>
                </div>
                <div class="formula">
                    <span class="formula-name">ROI</span>
                    <code>profit ÷ buy-ins</code>
                    <p>Avec un avertissement tant que l'échantillon est trop petit pour être parlant.</p>
                </div>
                <div class="formula">
                    <span class="formula-name">Mains contrôlées</span>
                    <code>Σ mises = pot</code>
                    <p>Chaque main importée est vérifiée ; une main incohérente est signalée au lieu de fausser vos chiffres.</p>
                </div>
            </div>
        </section>

        {{-- Appel final --}}
        <section class="section container">
            <div class="cta">
                <h2>Prêt à suivre votre bankroll ?</h2>
                <p>Créez votre compte gratuitement, ou explorez d'abord le dashboard de démonstration.</p>
                <div class="hero-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Ouvrir mon dashboard →</a>
                    @else
                        <a href="{{ route('register') }}" class="btn btn-primary">Créer mon compte →</a>
                    @endauth
                    <a href="{{ route('demo.tournaments.index') }}" class="btn btn-ghost">Voir les parties de démo</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer container">
        <span>RiverLog · Jour 03 du défi 30 jours, 30 projets</span>
        <span>Fait avec Laravel · <a href="{{ $repo }}" target="_blank" rel="noopener">Code sur GitHub ↗</a></span>
    </footer>
</body>
</html>
