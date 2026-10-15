# RiverLog · Suivi de bankroll poker

> Jour 03 du défi **30 jours, 30 projets** · Laravel, Blade et Chart.js · 🚧 **En développement**

![Page vitrine de RiverLog](docs/preview.png)

RiverLog enregistre chaque session de poker et calcule ce qui compte vraiment : **le profit net, le taux horaire et l'évolution des résultats**, format par format.

Le cœur du projet n'est pas le CRUD, mais **des statistiques fiables** : cartes, courbe et tableau utilisent toujours le même ensemble de sessions filtrées, calculé côté serveur.

📄 Le détail de la V1 est dans le [cahier des charges](CAHIER_DES_CHARGES.md).

## Fonctionnalités prévues (V1)

- **Sessions** : date et heure, format (Cash Game, MTT, Sit & Go), durée en minutes, résultat net (négatif pour une perte) et notes. Ajout, modification et suppression.
- **Tableau de bord** : profit net, nombre de sessions, temps joué et taux horaire, plus une courbe du profit cumulé (Chart.js).
- **Filtres** par format, période et résultat, transmis dans l'adresse (`?format=mtt&from=…&to=…&result=win`) : les statistiques, la courbe et le tableau sont recalculés.
- **Des calculs sans piège** : jamais de division par zéro, taux horaire négatif possible, ordre chronologique respecté.

## Avancement

- [x] Projet Laravel installé et cahier des charges rédigé
- [x] Page vitrine
- [ ] Sessions : base de données, ajout, modification, suppression
- [ ] Calculs : profit net, temps joué, taux horaire (`BankrollStatsService`)
- [ ] Dashboard : cartes statistiques, tableau et courbe Chart.js
- [ ] Filtres par format, période et résultat, dans l'URL
- [ ] Tests, documentation et données de démonstration
- [ ] Mise en production, avec une vraie base de données (plus tard)

## Lancer en local

Prérequis : PHP 8.3 ou plus récent avec les extensions `pdo_sqlite` et `sqlite3` activées dans `php.ini`, et Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Puis ouvrir http://127.0.0.1:8000.

## Hébergement

Le projet est **maintenu en local pour le moment**. Le site du défi est hébergé sur Netlify, qui ne peut pas exécuter de PHP. Si une solution d'hébergement simple se présente, RiverLog sera mis en ligne avec une base de données fonctionnelle.

## Technologies

Laravel 13, Blade, SQLite, Chart.js (à venir). Polices Space Grotesk, Inter et JetBrains Mono (Google Fonts).
