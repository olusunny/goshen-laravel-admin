#!/usr/bin/env bash
set -euo pipefail

app_dir="${APP_DIR:-/home/goshenretreat/apps/portal/current}"
php_bin="${PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
log_file="${LOG_FILE:-/home/goshenretreat/apps/portal/shared/storage/logs/scheduler.log}"

case "$app_dir" in
  /home/*/apps/*/current) ;;
  *)
    echo "Refusing unsafe APP_DIR: $app_dir" >&2
    exit 1
    ;;
esac

if [[ ! -x "$php_bin" && "$(command -v "$php_bin" || true)" == "" ]]; then
  echo "PHP binary not found: $php_bin" >&2
  exit 1
fi

mkdir -p "$(dirname "$log_file")"

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

{
  crontab -l 2>/dev/null | grep -vF "$app_dir &&" | grep -vF "artisan schedule:run" || true
  echo "* * * * * cd $app_dir && $php_bin artisan schedule:run >> $log_file 2>&1"
} > "$tmp"

crontab "$tmp"
echo "Installed Laravel scheduler cron for $app_dir"
