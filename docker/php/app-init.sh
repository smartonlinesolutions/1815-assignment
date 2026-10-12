#!/bin/sh
# Idempotent: every step is skipped when it has already been done.
set -eu

APP=/var/www/app
SCAFFOLD=/opt/scaffold
cd "$APP"

log() { printf '[init] %s\n' "$*"; }

# 1. Empty ./app: create a Composer-managed WordPress project (Roots Bedrock).
if [ ! -f composer.json ]; then
  log "No project in ./app yet. Scaffolding roots/bedrock..."
  TMP="$(mktemp -d)"
  composer create-project roots/bedrock "$TMP/bedrock" --no-install --no-scripts
  cp -a "$TMP/bedrock/." "$APP/"
  rm -rf "$TMP"

  log "Adding Havenpost starter files (config/services.yaml, src/, mu-plugin)..."
  cp -a "$SCAFFOLD/files/." "$APP/"
  php "$SCAFFOLD/patch-composer.php" "$APP/composer.json"
  printf '\n/var/\n' >> .gitignore
fi

# 2. The Symfony DI container for WordPress. It reads config/services.yaml but
#    does not depend on symfony/yaml itself, so that is required explicitly.
MISSING=""
for pkg in "achttienvijftien/service-container:^1.2" "symfony/yaml:^6.4"; do
  grep -q "\"${pkg%%:*}\"" composer.json || MISSING="$MISSING $pkg"
done
if [ -n "$MISSING" ]; then
  log "Installing WordPress + container packages:$MISSING (first run takes a few minutes)..."
  composer require $MISSING
fi

# 3. Dependencies (e.g. after cloning the repository on another machine).
if [ ! -f vendor/autoload.php ]; then
  log "Running composer install..."
  composer install
fi

# The container compiles itself into var/cache.
mkdir -p var/cache var/log web/app/uploads

# 4. WordPress itself.
WP="wp --path=web/wp"
if $WP core is-installed >/dev/null 2>&1; then
  log "WordPress is already installed."
else
  log "Installing WordPress at ${WP_HOME}..."
  $WP core install \
    --url="$WP_HOME" \
    --title="${WP_TITLE:-Havenpost}" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD:-admin}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@example.com}" \
    --skip-email
fi

log "Ready: ${WP_HOME}  (admin: ${WP_HOME}/wp/wp-admin)"