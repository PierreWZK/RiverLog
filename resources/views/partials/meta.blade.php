{{-- Description et aperçu du lien quand on le partage (Open Graph, Twitter) --}}
@php($description = 'RiverLog suit votre bankroll poker : sessions, profit net, taux horaire, import des historiques Betclic, ROI et statistiques de tournois.')
<meta name="description" content="{{ $description }}">
<meta name="theme-color" content="#0b1410">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="RiverLog">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('og.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="RiverLog · aperçu du site">
<meta name="twitter:card" content="summary_large_image">
