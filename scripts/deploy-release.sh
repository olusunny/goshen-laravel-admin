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
    web_root_mode="${WEB_ROOT_MODE:-symlink}"
    ;;
  production|prod)
    domain="goshen.shotfaz.com"
    app_root="${APP_ROOT:-/home/cels/projects/.git-deploy/$domain}"
    web_root="${WEB_ROOT:-/home/cels/projects/$domain}"
    web_root_mode="${WEB_ROOT_MODE:-symlink}"
    ;;
  portal|portal-production|goshenretreat)
    domain="portal.goshenretreat.uk"
    app_root="${APP_ROOT:-/home/goshenretreat/apps/portal}"
    web_root="${WEB_ROOT:-/home/goshenretreat/portal.goshenretreat.uk}"
    web_root_mode="${WEB_ROOT_MODE:-public}"
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

case "$web_root_mode" in
  public|symlink) ;;
  *)
    echo "Unknown web root mode: $web_root_mode" >&2
    exit 1
    ;;
esac

mkdir -p "$shared" "$releases"

if [[ "$web_root_mode" == "symlink" ]]; then
  mkdir -p "$(dirname "$web_root")"
else
  mkdir -p "$web_root"
fi

copy_directory_contents() {
  local source_dir="$1"
  local target_dir="$2"

  mkdir -p "$target_dir"

  if command -v rsync >/dev/null 2>&1; then
    rsync -a "$source_dir/" "$target_dir/"
  else
    cp -a "$source_dir/." "$target_dir/"
  fi
}

sync_public_assets() {
  local source_dir="$1"
  local target_dir="$2"

  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete \
      --exclude='/index.php' \
      --exclude='/.htaccess' \
      --exclude='/storage' \
      --exclude='/error_log' \
      --exclude='/.well-known' \
      "$source_dir/" "$target_dir/"

    return
  fi

  find "$target_dir" -mindepth 1 -maxdepth 1 \
    ! -name 'index.php' \
    ! -name '.htaccess' \
    ! -name 'storage' \
    ! -name 'error_log' \
    ! -name '.well-known' \
    -exec rm -rf {} +

  shopt -s dotglob nullglob
  for item in "$source_dir"/*; do
    case "$(basename "$item")" in
      index.php|.htaccess|storage|error_log|.well-known)
        continue
        ;;
    esac

    cp -a "$item" "$target_dir/"
  done
  shopt -u dotglob nullglob
}

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
previous_web_release=""

if [[ -L "$current" ]]; then
  previous_release="$(readlink -f "$current")"
fi

if [[ "$web_root_mode" == "symlink" && -L "$web_root" ]]; then
  previous_web_release="$(readlink -f "$web_root")"
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
    copy_directory_contents "$previous_release/vendor" "$release/vendor"
  else
    echo "Composer is unavailable and no previous vendor/ directory exists." >&2
    exit 1
  fi

  php artisan migrate --force --no-interaction
  php artisan optimize:clear --no-interaction
  php artisan config:cache --no-interaction
  php artisan route:cache --no-interaction
  php artisan view:cache --no-interaction
  php artisan about --only=environment --no-interaction
)

if [[ "$web_root_mode" == "public" ]]; then
  sync_public_assets "$release/public" "$web_root"

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
fi

next_link="$app_root/.current-next-$stamp"
ln -s "$release" "$next_link"
mv -Tf "$next_link" "$current"

if [[ "$web_root_mode" == "symlink" ]]; then
  next_web_link="$app_root/.webroot-next-$stamp"
  ln -s "$release" "$next_web_link"
  mv -Tf "$next_web_link" "$web_root"
fi

if command -v curl >/dev/null 2>&1; then
  if ! curl --fail --silent --show-error --max-time 20 "$health_url" >/dev/null; then
    echo "Health check failed for $health_url" >&2

    if [[ -n "$previous_release" && -d "$previous_release" ]]; then
      rollback_link="$app_root/.current-rollback-$stamp"
      ln -s "$previous_release" "$rollback_link"
      mv -Tf "$rollback_link" "$current"
      echo "Rolled back current symlink to $previous_release" >&2
    fi

    if [[ "$web_root_mode" == "symlink" && -n "$previous_web_release" && -d "$previous_web_release" ]]; then
      rollback_web_link="$app_root/.webroot-rollback-$stamp"
      ln -s "$previous_web_release" "$rollback_web_link"
      mv -Tf "$rollback_web_link" "$web_root"
      echo "Rolled back web root symlink to $previous_web_release" >&2
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
