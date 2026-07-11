#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/deploy-release.sh staging [commit]
  scripts/deploy-release.sh production [commit]

Environment variables:
  REPO_URL      Git repository URL. Defaults to https://github.com/olusunny/goshen-laravel-admin.git
  BRANCH        Branch to clone when resolving a commit. Defaults to main
  PROJECTS_DIR  Server projects directory. Defaults to /home/cels/projects

This script creates a new Git-backed release, links shared .env and storage,
runs Laravel production build steps, then atomically points the domain folder at
the prepared release. Existing non-Git site folders are moved into the release
legacy folder during the first migration.
USAGE
}

environment="${1:-}"
commit="${2:-}"

case "$environment" in
  staging)
    domain="staging-goshen.shotfaz.com"
    ;;
  production|prod)
    domain="goshen.shotfaz.com"
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
projects_dir="${PROJECTS_DIR:-/home/cels/projects}"
site="$projects_dir/$domain"
deploy_root="$projects_dir/.git-deploy/$domain"
shared="$deploy_root/shared"
releases="$deploy_root/releases"
legacy_root="$deploy_root/legacy"
stamp="$(date +%Y%m%d%H%M%S)"

case "$site" in
  "$projects_dir"/*) ;;
  *)
    echo "Refusing unsafe site path: $site" >&2
    exit 1
    ;;
esac

mkdir -p "$shared" "$releases" "$legacy_root"

if [[ -z "$commit" ]]; then
  commit="$(git ls-remote "$repo_url" "refs/heads/$branch" | awk '{ print $1 }')"
fi

if [[ -z "$commit" ]]; then
  echo "Unable to resolve commit for $repo_url $branch" >&2
  exit 1
fi

short_commit="${commit:0:7}"
release="$releases/$stamp-$short_commit"

if [[ ! -e "$shared/.env" ]]; then
  if [[ ! -e "$site/.env" ]]; then
    echo "Missing .env at $site/.env; create $shared/.env before deploying." >&2
    exit 1
  fi
  cp -p "$site/.env" "$shared/.env"
fi

if [[ ! -e "$shared/storage" ]]; then
  if [[ ! -e "$site/storage" ]]; then
    echo "Missing storage at $site/storage; create $shared/storage before deploying." >&2
    exit 1
  fi
  mv "$site/storage" "$shared/storage"
  ln -s "$shared/storage" "$site/storage"
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
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
  php artisan migrate --force --no-interaction
  php artisan optimize:clear --no-interaction
  php artisan config:cache --no-interaction
  php artisan route:cache --no-interaction
  php artisan view:cache --no-interaction
)

next_link="$projects_dir/.${domain}.next-$stamp"
ln -s "$release" "$next_link"

if [[ -L "$site" ]]; then
  mv -Tf "$next_link" "$site"
else
  legacy="$legacy_root/$stamp-pre-git-release"
  mv "$site" "$legacy"
  mv -T "$next_link" "$site"
  printf '%s\n' "$legacy" > "$deploy_root/last_legacy_path"
fi

printf '%s\n' "$release" > "$deploy_root/current_release_path"
echo "Deployed $domain at $commit"
echo "Current release: $release"
