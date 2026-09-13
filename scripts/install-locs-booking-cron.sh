#!/usr/bin/env bash
# Add only the exact Locs owner-alert worker; never repair/activate broad queues.
set -euo pipefail
mode="${1:-inspect}"
release="${2:-}"
[[ "$mode" == inspect || "$mode" == --apply ]] || exit 2
[[ "$release" =~ ^[a-f0-9]{40}$ ]] || exit 2
ssh -o BatchMode=yes xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net bash -s -- "$mode" "$release" <<'REMOTE'
set -euo pipefail
mode="$1"; release="$2"
root=/home/xrdj7j99xhzt/public_html
base=/home/xrdj7j99xhzt/deploy/famtastic-designs
worker="$base/releases/$release/source/scripts/locs-booking-worker.php"
stable_worker="$base/workers/locs-booking-worker.php"
[[ -f "$worker" && -f "$root/vendor/drush/drush/drush.php" ]] || exit 3
[[ "$(/usr/local/bin/php -r 'echo PHP_SAPI;')" == cli ]] || exit 4
marker='# FAMTASTIC_LOCS_BOOKING_CRON_V1'
line="*/5 * * * * cd $root && /usr/local/bin/php $root/vendor/drush/drush/drush.php php:script $stable_worker >> $base/logs/locs-booking-worker.log 2>&1"
current="$(crontab -l 2>/dev/null || true)"
if [[ "$current" == *"$marker"* ]]; then
  actual="$(printf '%s\n' "$current" | awk -v marker="$marker" '$0==marker {getline; print}')"
  [[ "$actual" == "$line" ]] && cmp -s "$worker" "$stable_worker" || { echo 'Existing Locs worker differs; review required.'; exit 5; }
  echo 'Exact Locs cron already installed.'; exit 0
fi
if [[ "$current" == *"locs-booking-worker.php"* ]]; then echo 'Unmarked Locs worker exists; review required.'; exit 6; fi
echo 'Verified CLI PHP and pinned source; scoped five-minute Locs worker ready.'
[[ "$mode" == --apply ]] || exit 0
umask 077
mkdir -p "$base/logs" "$base/cron-backups" "$base/workers"
# A pinned release directory is pruned by normal deployment retention. Keep an
# identical private checked-in worker outside that retention tree.
if [[ -f "$stable_worker" ]]; then
  cmp -s "$worker" "$stable_worker" || { echo 'Stable worker differs; review required.'; exit 8; }
else
  cp -p "$worker" "$stable_worker"
  chmod 600 "$stable_worker"
fi
cmp -s "$worker" "$stable_worker"
backup="$(mktemp "$base/cron-backups/locs-before.XXXXXX")"
printf '%s\n' "$current" > "$backup"
stage="$(mktemp "$base/cron-backups/locs-install.XXXXXX")"
printf '%s\n\n%s\n%s\n' "$current" "$marker" "$line" > "$stage"
[[ "$(crontab -l 2>/dev/null || true)" == "$current" ]] || { echo 'Crontab changed; not installing.'; exit 7; }
crontab "$stage"
crontab -l | awk -v marker="$marker" '$0==marker {getline; print}' | /usr/bin/grep -Fx -- "$line" >/dev/null
echo "Scoped cron installed; original preserved at $backup"
REMOTE
