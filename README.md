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

Prérequis : PHP 8.3 ou plus récent avec l'extension `pdo_mysql` (et `zip` pour importer des archives), Composer, et un serveur **MySQL 8.4** (sous Windows : `winget install Oracle.MySQL`).

**1. Créer les bases et l'utilisateur** (dans `mysql -u root -p` ou MySQL Workbench) :

```sql
CREATE DATABASE riverlog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE riverlog_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'riverlog'@'localhost' IDENTIFIED BY 'un-mot-de-passe';
GRANT ALL PRIVILEGES ON riverlog.* TO 'riverlog'@'localhost';
GRANT ALL PRIVILEGES ON riverlog_test.* TO 'riverlog'@'localhost';
```

**2. Installer l'application :**

```bash
composer install
cp .env.example .env          # puis renseigner DB_PASSWORD
php artisan key:generate
php artisan migrate --seed    # tables + compte de démonstration
php artisan serve
```

Puis ouvrir http://127.0.0.1:8000 (la démo publique est sur `/demo`).

**Tests** : `php artisan test`. Ils utilisent la base `riverlog_test`, vidée à chaque test : la base de l'application n'est jamais touchée.

## Hébergement

Le projet est **maintenu en local pour le moment**. Le site du défi est hébergé sur Netlify, qui ne peut pas exécuter de PHP. Si une solution d'hébergement simple se présente, RiverLog sera mis en ligne avec une base de données fonctionnelle.

## Technologies

Laravel 13, Blade, MySQL 8.4, Chart.js. Polices Space Grotesk, Inter et JetBrains Mono (Google Fonts).
