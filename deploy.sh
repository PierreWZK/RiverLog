#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Mise à jour de RiverLog sur le serveur Alwaysdata.
#
# Lancé automatiquement par GitHub Actions (.github/workflows/main.yml),
# ou à la main en SSH :  DEPLOY_PATH=~/www/riverlog bash deploy.sh
#
# Le fichier .env et le dossier storage/ du serveur ne sont jamais touchés
# (ils ne sont pas suivis par git).
# ---------------------------------------------------------------------------
set -euo pipefail

cd "${DEPLOY_PATH:-$HOME/www/riverlog}"
echo "→ Dossier : $(pwd)"

# Page de maintenance pendant la mise à jour, retirée quoi qu'il arrive à la fin
php artisan down --retry=15 || true
trap 'php artisan up' EXIT

echo "→ Récupération du code (master)"
git fetch --prune origin master
git reset --hard origin/master

echo "→ Dépendances PHP (sans les outils de développement)"
composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

echo "→ Base de données"
php artisan migrate --force

echo "→ Caches (configuration, routes, vues)"
php artisan optimize:clear
php artisan optimize

echo "✓ RiverLog est à jour : $(git log -1 --format='%h · %s')"
