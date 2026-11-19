#!/usr/bin/env bash
# Runs on the Hostinger server after the code (and public/build) is in place.
# Usage: bash deploy/server-update.sh            (updates)
#        bash deploy/server-update.sh --first    (first install: key, seed)
# Hostinger: PHP=/opt/alt/php85/usr/bin/php \
#            COMPOSER_BIN="/opt/alt/php85/usr/bin/php /usr/local/bin/composer" \
#            bash deploy/server-update.sh
# (Not COMPOSER: Composer reads that variable as the path to composer.json.)
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

if [ ! -f public/build/manifest.json ]; then
    echo "public/build is missing: run 'npm run build' on your computer, commit public/build and push." >&2
    exit 1
fi

$PHP artisan down --retry=15 || true
trap '$PHP artisan up' ERR

if [ -d .git ]; then
    git pull --ff-only
fi

$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ "${1:-}" = "--first" ]; then
    # Only a missing key is generated: a new key would log everyone out and
    # make encrypted values unreadable.
    if ! grep -qE '^APP_KEY=.+' .env; then
        $PHP artisan key:generate --force
    fi
    $PHP artisan migrate --force
    $PHP artisan db:seed --force
else
    $PHP artisan migrate --force
fi

$PHP artisan storage:link 2>/dev/null || true
$PHP artisan finance:ensure-periods

$PHP artisan optimize:clear
$PHP artisan optimize
$PHP artisan up

echo "Deployed $(git rev-parse --short HEAD 2>/dev/null || echo 'release')."
