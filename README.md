# RiverLog · Suivi de bankroll poker

> Jour 03 du défi **30 jours, 30 projets** · Laravel, Blade, MySQL et Chart.js · 🟢 **En ligne : [riverlog.alwaysdata.net](https://riverlog.alwaysdata.net)**

![Page vitrine de RiverLog](docs/preview.png)

RiverLog suit votre bankroll poker et analyse vos tournois : **profit net, taux horaire, ROI et évolution des résultats**, à partir de sessions saisies à la main ou d'historiques de mains importés.

Le cœur du projet n'est pas le CRUD, mais **des statistiques fiables** : cartes, courbes et tableaux utilisent toujours le même ensemble de données filtrées, calculé côté serveur, et chaque main importée est contrôlée.

📄 Le détail est dans le [cahier des charges](CAHIER_DES_CHARGES.md).

## Fonctionnalités

- **Comptes** : inscription, connexion ; chacun ne voit que ses propres données. Une **démo publique** en lecture seule montre un dashboard et des tournois fictifs (`/demo`).
- **Sessions** : date, format (Cash Game, MTT, Sit & Go, Spin), durée, résultat et notes. Ajout, modification, suppression.
- **Dashboard** : profit net, taux horaire, sessions, temps joué et courbe du profit cumulé, avec des **filtres** par format, période et résultat gardés dans l'URL.
- **Import Betclic** : fichiers `.txt` ou `.zip`, doublons ignorés, réimport sans risque. Les tournois proches sont regroupés en **sessions automatiques**.
- **Tournois** : ROI, victoires, places finales, conversion en heads-up, résultats par multiplicateur et par buy-in, ROI hors jackpots.
- **Détail d'un tournoi** : chaque main avec cartes, board, tapis en big blinds et jetons gagnés ou perdus.
- **Qualité** : 70 tests automatisés sur MySQL, CI/CD GitHub Actions (tests puis déploiement automatique sur Alwaysdata).

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

RiverLog est hébergé sur **[Alwaysdata](https://www.alwaysdata.com)** (PHP 8.4, MySQL) : **https://riverlog.alwaysdata.net**.

Chaque push sur `master` lance le workflow [CI / CD](.github/workflows/main.yml) : les tests tournent sur PHP 8.3 + MySQL 8.4, puis, s'ils passent, le serveur est mis à jour en SSH par [deploy.sh](deploy.sh) (code, dépendances, migrations, caches). Les secrets (clé SSH, empreinte du serveur) sont stockés dans GitHub, jamais dans le dépôt.

## Technologies

Laravel 13, Blade, MySQL 8.4, Chart.js. Polices Space Grotesk, Inter et JetBrains Mono (Google Fonts).
