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
- **Défis du jour** (`/defis`), les mêmes pour tout le monde, renouvelés à minuit :
  - *Qui est devant ?* : estimer l'équité de deux mains à tapis (évaluateur de mains et calcul d'équité maison) ;
  - *Le calcul du jour* : cotes du pot, outs, ROI, taux horaire, bankroll, avec l'explication de chaque réponse ;
  - *Défi discipline* : un objectif de bankroll par jour (stop-loss, pause, jour de repos…), vérifié sur les vraies sessions du compte, avec une série de jours réussis.
- **Qualité** : 92 tests automatisés sur MySQL, CI/CD GitHub Actions (tests puis déploiement automatique sur Alwaysdata).

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

## Mise en ligne

RiverLog est hébergé sur **[Alwaysdata](https://www.alwaysdata.com)** : **https://riverlog.alwaysdata.net**.

```
 push sur master ──► GitHub Actions ──► tests (PHP 8.3 + MySQL 8.4)
                                          │ s'ils passent
                                          ▼
                     connexion SSH ──► Alwaysdata : deploy.sh ──► site à jour
```

### 1. Le site et la base de données sur Alwaysdata

Tout se configure dans l'administration d'Alwaysdata (compte `riverlog`) :

| Élément | Réglage |
| --- | --- |
| **PHP** | Version 8.4 (*Environnement*), Laravel 13 demande au moins PHP 8.3 |
| **Base de données** | Base MySQL (MariaDB) créée dans *Bases de données → MySQL*, avec un utilisateur dédié ; hôte `mysql-riverlog.alwaysdata.net` |
| **Site** | *Web → Sites* : type PHP, adresse `riverlog.alwaysdata.net`, **répertoire racine `/www/riverlog/public`** (jamais la racine du projet, sinon le `.env` serait lisible depuis le web) |
| **HTTPS** | Certificat Let's Encrypt généré et renouvelé automatiquement par Alwaysdata ; Laravel fait confiance à son proxy (`trustProxies` dans `bootstrap/app.php`) pour que les liens restent en `https://` |
| **SSH** | Activé dans *Accès distant → SSH* : `ssh riverlog@ssh-riverlog.alwaysdata.net` |

### 2. Installation du site en SSH (une seule fois)

```bash
ssh riverlog@ssh-riverlog.alwaysdata.net

cd ~/www
git clone https://github.com/PierreWZK/RiverLog.git riverlog
cd riverlog
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
nano .env                          # valeurs de production, voir ci-dessous
php artisan migrate --force --seed # tables + compte de démonstration
php artisan optimize               # caches de configuration, routes et vues
```

Le `.env` du serveur n'est jamais versionné :

```
APP_ENV=production
APP_DEBUG=false                    # indispensable : sinon une erreur afficherait les mots de passe
APP_URL=https://riverlog.alwaysdata.net
DB_CONNECTION=mysql
DB_HOST=mysql-riverlog.alwaysdata.net
DB_DATABASE=riverlog_…
DB_USERNAME=…
DB_PASSWORD=…
SESSION_SECURE_COOKIE=true
```

`--force` est obligatoire en production : Laravel refuse sinon de lancer migrations et seeders, par sécurité.

### 3. Déploiement continu avec GitHub Actions

Le workflow [`.github/workflows/main.yml`](.github/workflows/main.yml) a deux étapes :

1. **Tests** (à chaque push et pull request) : PHP 8.3 et une base MySQL 8.4 temporaire sont installés sur GitHub, puis tous les tests sont lancés.
2. **Déploiement** (uniquement sur `master`, et seulement si les tests passent) : GitHub se connecte en SSH à Alwaysdata et exécute [`deploy.sh`](deploy.sh), qui :
   - affiche une page de maintenance pendant la mise à jour (retirée même en cas d'échec) ;
   - récupère le dernier code de `master` ;
   - installe les dépendances sans les outils de développement (`composer install --no-dev`) ;
   - applique les migrations (`php artisan migrate --force`) et reconstruit les caches.

   Un dernier contrôle vérifie que la page d'accueil répond. Le `.env` et le dossier `storage/` du serveur ne sont jamais touchés.

Le bouton **Actions → CI / CD → Run workflow** permet aussi de redéployer à la main.

**Connexion SSH sécurisée** : une clé SSH dédiée au déploiement a été créée. Sa partie publique est ajoutée à `~/.ssh/authorized_keys` sur Alwaysdata ; sa partie privée n'existe que dans les secrets GitHub. Le workflow vérifie aussi l'empreinte du serveur, pour ne jamais se connecter à un faux serveur.

| Réglage du dépôt (*Settings → Secrets and variables → Actions*) | Valeur |
| --- | --- |
| Secret `SSH_PRIVATE_KEY` | Clé privée de déploiement |
| Secret `SSH_KNOWN_HOSTS` | Empreinte du serveur (`ssh-keyscan ssh-riverlog.alwaysdata.net`) |
| Variable `DEPLOY_HOST` | `ssh-riverlog.alwaysdata.net` |
| Variable `DEPLOY_USER` | `riverlog` |
| Variable `DEPLOY_PATH` | `www/riverlog` |

Pour révoquer l'accès de GitHub au serveur, il suffit de supprimer la ligne de la clé dans `~/.ssh/authorized_keys`.

## Technologies

Laravel 13, Blade, MySQL 8.4, Chart.js. Hébergement Alwaysdata, CI/CD GitHub Actions. Polices Space Grotesk, Inter et JetBrains Mono (Google Fonts).
