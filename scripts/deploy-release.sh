#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/deploy-release.sh staging [commit]
  scripts/deploy-release.sh production [commit]
  scripts/deploy-release.sh portal [commit]

Environment variables:
  REPO_URL      Git repository URL. Defaults to https://github.com/olusunny/goshen-laravel-admin.git
  BRANCH        Branch to clone when resolving a commit. Defaults to main
  APP_ROOT      Laravel app release root. Defaults per environment.
  WEB_ROOT      cPanel/public web root. Defaults per environment.
  PHP_BIN       PHP CLI binary. Defaults to php.
  COMPOSER_BIN  Composer binary. If unavailable, vendor/ is copied from the previous release.
  HEALTH_URL    Public health URL checked after switching releases.

This script creates a new Git-backed release, links shared .env and storage,
runs Laravel production build steps, then atomically points the app current
symlink at the prepared release. For cPanel deployments the web root remains in
place and its front controller points at APP_ROOT/current.
USAGE
}

environment="${1:-}"
commit="${2:-}"

case "$environment" in
  staging)
    domain="staging-goshen.shotfaz.com"
    app_root="${APP_ROOT:-/home/cels/projects/.git-deploy/$domain}"
    web_root="${WEB_ROOT:-/home/cels/projects/$domain}"
    ;;
  production|prod)
    domain="goshen.shotfaz.com"
    app_root="${APP_ROOT:-/home/cels/projects/.git-deploy/$domain}"
    web_root="${WEB_ROOT:-/home/cels/projects/$domain}"
    ;;
  portal|portal-production|goshenretreat)
    domain="portal.goshenretreat.uk"
    app_root="${APP_ROOT:-/home/goshenretreat/apps/portal}"
    web_root="${WEB_ROOT:-/home/goshenretreat/portal.goshenretreat.uk}"
    ;;
  -h|--help|"")
    usage
    exit 0
    ;;
  *)
    echo "Unknown environment: $environment" >&2
    usage >&2
    exit 1
    ;;
esac

repo_url="${REPO_URL:-https://github.com/olusunny/goshen-laravel-admin.git}"
branch="${BRANCH:-main}"
php_bin="${PHP_BIN:-php}"
composer_bin="${COMPOSER_BIN:-composer}"
health_url="${HEALTH_URL:-https://$domain/up}"
current="$app_root/current"
shared="$app_root/shared"
releases="$app_root/releases"
stamp="$(date +%Y%m%d%H%M%S)"

case "$app_root" in
  /home/*/apps/*|/home/*/.git-deploy/*) ;;
  *)
    echo "Refusing unsafe app root path: $app_root" >&2
    exit 1
    ;;
esac

case "$web_root" in
  /home/*/*|/home/*/public_html) ;;
  *)
    echo "Refusing unsafe web root path: $web_root" >&2
    exit 1
    ;;
esac

mkdir -p "$shared" "$releases" "$web_root"

if [[ -z "$commit" ]]; then
  commit="$(git ls-remote "$repo_url" "refs/heads/$branch" | awk '{ print $1 }')"
fi

if [[ -z "$commit" ]]; then
  echo "Unable to resolve commit for $repo_url $branch" >&2
  exit 1
fi

short_commit="${commit:0:7}"
release="$releases/$stamp-$short_commit"
previous_release=""

if [[ -L "$current" ]]; then
  previous_release="$(readlink -f "$current")"
fi

if [[ ! -e "$shared/.env" ]]; then
  if [[ -e "$current/.env" ]]; then
    cp -p "$current/.env" "$shared/.env"
  elif [[ -e "$web_root/.env" ]]; then
    cp -p "$web_root/.env" "$shared/.env"
  else
    echo "Missing .env; create $shared/.env before deploying." >&2
    exit 1
  fi
fi

git clone --quiet --branch "$branch" --single-branch "$repo_url" "$release"
git -C "$release" checkout --quiet --detach "$commit"

rm -rf "$release/storage" "$release/public/storage"
ln -s "$shared/storage" "$release/storage"
ln -s "$shared/.env" "$release/.env"
mkdir -p \
  "$shared/storage/app/public" \
  "$shared/storage/framework/cache" \
  "$shared/storage/framework/sessions" \
  "$shared/storage/framework/views" \
  "$shared/storage/logs"
ln -s "$shared/storage/app/public" "$release/public/storage"
printf '%s\n' "$commit" > "$release/.codex_deploy_revision"

(
  cd "$release"
  if command -v "$composer_bin" >/dev/null 2>&1; then
    "$composer_bin" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
  elif [[ -n "$previous_release" && -d "$previous_release/vendor" ]]; then
    echo "Composer is unavailable; reusing vendor/ from $previous_release"
    rsync -a "$previous_release/vendor/" "$release/vendor/"
  else
    echo "Composer is unavailable and no previous vendor/ directory exists." >&2
    exit 1
  fi

  php artisan migrate --force --no-interaction
  php artisan optimize:clear --no-interaction
  rm -f bootstrap/cache/config.php
  php artisan route:cache --no-interaction
  php artisan view:cache --no-interaction
  php artisan about --only=environment --no-interaction
)

rsync -a --delete \
  --exclude='/index.php' \
  --exclude='/.htaccess' \
  --exclude='/storage' \
  --exclude='/error_log' \
  --exclude='/.well-known' \
  "$release/public/" "$web_root/"

if [[ ! -f "$web_root/index.php" ]]; then
  cat > "$web_root/index.php" <<PHP
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

\$appBase = __DIR__.'/../apps/portal/current';

if (file_exists(\$maintenance = \$appBase.'/storage/framework/maintenance.php')) {
    require \$maintenance;
}

require \$appBase.'/vendor/autoload.php';

/** @var Application \$app */
\$app = require_once \$appBase.'/bootstrap/app.php';

\$app->handleRequest(Request::capture());
PHP
fi

next_link="$app_root/.current-next-$stamp"
ln -s "$release" "$next_link"
mv -Tf "$next_link" "$current"

if command -v curl >/dev/null 2>&1; then
  if ! curl --fail --silent --show-error --max-time 20 "$health_url" >/dev/null; then
    echo "Health check failed for $health_url" >&2

    if [[ -n "$previous_release" && -d "$previous_release" ]]; then
      rollback_link="$app_root/.current-rollback-$stamp"
      ln -s "$previous_release" "$rollback_link"
      mv -Tf "$rollback_link" "$current"
      echo "Rolled back current symlink to $previous_release" >&2
    fi

    if command -v pkill >/dev/null 2>&1; then
      pkill -u "$(id -un)" -f '^lsphp' || true
    fi

    exit 1
  fi
fi

printf '%s\n' "$release" > "$app_root/current_release_path"
printf '%s\n' "$commit" > "$app_root/.app_commit"
echo "Deployed $domain at $commit"
echo "Current release: $release"
