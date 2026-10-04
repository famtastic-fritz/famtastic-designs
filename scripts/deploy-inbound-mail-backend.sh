#!/usr/bin/env bash
# Invoked by the canonical deployment primitive with INBOUND_MAIL_ONLY=1.
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"
apply=false
case "${1:-}" in '') ;; --apply) apply=true ;; *) exit 2 ;; esac
[[ -z "$(git status --porcelain)" ]] || { echo 'Clean committed source required.' >&2; exit 1; }
revision="$(git rev-parse HEAD)"
base="${FAMTASTIC_INBOUND_MAIL_BASE_REVISION:-$(git rev-parse HEAD^)}"
[[ "$base" =~ ^[a-f0-9]{40}$ ]] && git merge-base --is-ancestor "$base" "$revision"
repository='https://github.com/famtastic-fritz/famtastic-designs.git'
[[ "$revision" == "$(git ls-remote "$repository" refs/heads/main | awk '{print $1}')" ]] || { echo 'Current main required.' >&2; exit 1; }
prefix='backend/web/modules/custom/famtastic_pipeline'
owned=(
 src/Service/InboundEnvelope.php
 src/Service/InboundReplyCorrelation.php
 src/Service/InboundMailboxService.php
 src/Service/InboundMailboxSchedule.php
 src/Service/LifecycleOperationsService.php
 src/Drush/Commands/InboundMailboxCommands.php
 bin/process-support-maildir.sh
 bin/inbound-mail-pipe.php
)
while IFS= read -r changed; do
 [[ -z "$changed" || "$changed" == "$prefix/tests/"* || "$changed" == backend/scripts/e2e-inbound-mail.php || "$changed" == backend/scripts/prove-inbound-mail.php ]] && continue
 found=false
 for file in "${owned[@]}"; do [[ "$changed" != "$prefix/$file" ]] || found=true; done
 [[ "$found" == true ]] || { echo "Out-of-scope backend change: $changed" >&2; exit 1; }
done < <(git diff --name-only "$base" "$revision" -- backend)
spec=''
for file in "${owned[@]}"; do
 expected='absent'
 if git cat-file -e "$base:$prefix/$file" 2>/dev/null; then expected="$(git show "$base:$prefix/$file" | shasum -a 256 | awk '{print $1}')"; fi
 current="$(shasum -a 256 "$prefix/$file" | awk '{print $1}')"
 spec+="$file $expected $current"$'\n'
done
encoded="$(printf '%s' "$spec" | base64 | tr -d '\n')"
ssh -T "${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}" bash -s -- "$revision" "$base" "$apply" "$encoded" <<'REMOTE'
set -euo pipefail
umask 077
revision="$1"; base="$2"; apply="$3"; spec="$(printf '%s' "$4" | base64 --decode)"
production="$HOME/public_html"; module="$production/web/modules/custom/famtastic_pipeline"
deploy="$HOME/deploy/famtastic-designs"; release="$deploy/releases/$revision"
source="$release/inbound-source"; php='/usr/local/bin/php'
test -x "$php"
"$php" -r 'exit(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 80400 ? 0 : 1);'
cron_before="$(crontab -l)"
# The old importer must be absent. Unknown schedules require reconciliation.
if printf '%s\n' "$cron_before" | awk '/^[[:space:]]*#/ {next} /process-support-maildir|inbound-mail-pipe|famtastic:mail-tick/ {found=1} END {exit found ? 0 : 1}'; then
 echo 'An ingress clock already exists; reconcile before this first repair release.' >&2; exit 1
fi
cd "$production"
lock_before="$("$php" vendor/bin/drush.php php:eval 'print \Drupal::config("famtastic_pipeline.settings")->get("pilot_exact_dispatch_only") ? "1" : "0";')"
check_baseline() {
 while read -r file expected current; do
  [[ -n "$file" ]] || continue
  if [[ "$expected" == absent ]]; then [[ ! -e "$module/$file" ]];
  else [[ -f "$module/$file" && ! -L "$module/$file" && "$(sha256sum "$module/$file" | awk '{print $1}')" == "$expected" ]]; fi
 done <<< "$spec"
}
check_baseline || { echo 'Deployed baseline mismatch; no mutation.' >&2; exit 1; }
if [[ "$apply" != true ]]; then
 printf 'Ingress preflight passed: revision=%s base=%s exact_dispatch_lock=%s scheduler=preserved\n' "$revision" "$base" "$lock_before"; exit 0
fi
lock="$deploy/.inbound-mail-release-lock"
mkdir "$lock" || { echo 'Ingress release already running.' >&2; exit 1; }
trap 'rmdir "$lock"' EXIT
mirror="$deploy/repository.git"
test -d "$mirror"
git --git-dir="$mirror" fetch origin
[[ "$(git --git-dir="$mirror" rev-parse refs/heads/main)" == "$revision" ]]
mkdir -p "$source"
git --git-dir="$mirror" archive "$revision" backend | tar -x -C "$source"
new="$source/backend/web/modules/custom/famtastic_pipeline"
backup="$release/inbound-mail-backup"
mkdir -m 0700 "$backup"
printf '%s\n' "$cron_before" > "$backup/crontab.before"
"$php" vendor/bin/drush.php sql:dump --result-file="$backup/database.sql" --gzip >/dev/null
# Settings and activation/baseline are inside the private database backup;
# the existing inbound signing secret is preserved, never printed or exported.
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 [[ "$(sha256sum "$new/$file" | awk '{print $1}')" == "$current" ]]
 [[ "$file" != *.php ]] || "$php" -l "$new/$file" >/dev/null
 if [[ "$expected" != absent ]]; then mkdir -p "$backup/$(dirname "$file")"; cp -p "$module/$file" "$backup/$file"; fi
done <<< "$spec"
rollback() {
 code="${1:-1}"; trap - ERR INT TERM HUP
 # Stop only our exact new clock, preserving unrelated scheduler changes.
 "$php" -r 'require $argv[1]; $class="Drupal\\famtastic_pipeline\\Service\\InboundMailboxSchedule"; $cron=shell_exec("crontab -l"); file_put_contents($argv[2],$class::remove($cron,$argv[3]));' "$new/src/Service/InboundMailboxSchedule.php" "$backup/crontab.rollback" "$HOME" && crontab "$backup/crontab.rollback" || true
 restored=true
 while read -r file expected current; do
  [[ -n "$file" ]] || continue
  if [[ "$expected" == absent ]]; then rm -f -- "$module/$file" || restored=false;
  else cp -p "$backup/$file" "$module/$file" || restored=false; fi
 done <<< "$spec"
 "$php" vendor/bin/drush.php cache:rebuild || restored=false
 printf 'Ingress release failed: code_restored=%s backup=%s database=retained_not_restored\n' "$restored" "$backup" >&2
 exit "$code"
}
check_baseline
[[ "$(crontab -l)" == "$cron_before" ]]
trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM
trap 'rollback 129' HUP
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 mode=0644; [[ "$file" != bin/* ]] || mode=0755
 install -m "$mode" "$new/$file" "$module/$file"
done <<< "$spec"
"$php" vendor/bin/drush.php cache:rebuild
"$php" vendor/bin/drush.php famtastic:mail-activate --confirm=preserve-existing-mail > "$backup/activation.json"
[[ "$(crontab -l)" == "$cron_before" ]]
"$php" vendor/bin/drush.php famtastic:mail-schedule --install --confirm=FAMTASTIC_INBOUND_MAIL_CRON_V1
"$php" vendor/bin/drush.php famtastic:mail-schedule
# Verify unrelated clocks are byte-equivalent after removing the owned pair.
"$php" -r 'require $argv[1]; $class="Drupal\\famtastic_pipeline\\Service\\InboundMailboxSchedule"; $cron=shell_exec("crontab -l"); file_put_contents($argv[2],$class::remove($cron,$argv[3]));' "$new/src/Service/InboundMailboxSchedule.php" "$backup/crontab.unrelated.after" "$HOME"
[[ "$(cat "$backup/crontab.unrelated.after")" == "$cron_before" ]]
[[ "$("$php" vendor/bin/drush.php php:eval 'print \Drupal::config("famtastic_pipeline.settings")->get("pilot_exact_dispatch_only") ? "1" : "0";')" == "$lock_before" ]]
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 [[ "$(sha256sum "$module/$file" | awk '{print $1}')" == "$current" ]]
done <<< "$spec"
{
 printf 'commit=%s\nbase=%s\ndeployed_at=%s\nscope=ingress-only\nbackup=%s\n' "$revision" "$base" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$backup"
 printf 'scheduler=FAMTASTIC_INBOUND_MAIL_CRON_V1\nother_schedulers=preserved\nexact_dispatch_lock=%s\nmail_sent=0\n' "$lock_before"
} > "$production/.inbound-mail-release.next"
mv "$production/.inbound-mail-release.next" "$production/.inbound-mail-release"
trap - ERR INT TERM HUP
cat "$production/.inbound-mail-release"
REMOTE
