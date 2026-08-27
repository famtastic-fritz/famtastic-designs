#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DEPLOYER="$REPO_ROOT/scripts/deploy-backend-godaddy.sh"

test "${FAMTASTIC_PREPROMOTION_CRON_GUARD_CONFIRM:-}" = "LOCAL_ONLY" || {
  echo "Refusing to run without FAMTASTIC_PREPROMOTION_CRON_GUARD_CONFIRM=LOCAL_ONLY." >&2
  exit 2
}

fixture_root="$(mktemp -d /tmp/famtastic-prepromotion-cron-guard.XXXXXX)"
fixture_bin="$fixture_root/bin"
fixture_home="$fixture_root/home"
fixture_crontab="$fixture_root/crontab"
fixture_sha='0123456789abcdef0123456789abcdef01234567'
mkdir -p "$fixture_bin" "$fixture_home/public_html/vendor/bin" \
  "$fixture_home/public_html/web/modules/custom/famtastic_pipeline" \
  "$fixture_home/public_html/web/themes/custom/famtastic_admin" \
  "$fixture_home/public_html/web/themes/custom/famtastic_customer" \
  "$fixture_home/public_html/web/sites/default"
trap 'rm -rf "$fixture_root"' EXIT

printf '%s\n' \
  '#!/usr/bin/env bash' \
  'set -euo pipefail' \
  'case "$1" in' \
  '  status) exit 0 ;;' \
  '  rev-parse) printf "%s\n" "$FAMTASTIC_FIXTURE_SHA" ;;' \
  '  ls-remote) printf "%s\trefs/heads/main\n" "$FAMTASTIC_FIXTURE_SHA" ;;' \
  '  clone) exit "$FAMTASTIC_FIXTURE_GIT_CLONE_EXIT" ;;' \
  '  *) exit 0 ;;' \
  'esac' > "$fixture_bin/git"
chmod +x "$fixture_bin/git"

printf '%s\n' \
  '#!/usr/bin/env bash' \
  'set -euo pipefail' \
  'test "$1" = "-T" && shift' \
  'shift' \
  'test "$1" = "bash" && test "$2" = "-s" && test "$3" = "--"' \
  'shift 3' \
  'remote_command="bash -s --"' \
  'for argument in "$@"; do remote_command+=" $argument"; done' \
  'HOME="$FAMTASTIC_FIXTURE_HOME" PATH="$FAMTASTIC_FIXTURE_BIN:$PATH" bash -c "$remote_command"' > "$fixture_bin/ssh"
chmod +x "$fixture_bin/ssh"

printf '%s\n' \
  '#!/usr/bin/env bash' \
  'set -euo pipefail' \
  'state="$FAMTASTIC_FIXTURE_CRONTAB"' \
  'if [[ "$1" = "-l" ]]; then' \
  '  if [[ -f "$state" ]]; then cat "$state"; exit 0; fi' \
  '  echo "no crontab for fixture" >&2; exit 1' \
  'fi' \
  'test "$#" = 1' \
  'cp "$1" "$state"' > "$fixture_bin/crontab"
chmod +x "$fixture_bin/crontab"

printf '%s\n' \
  '#!/usr/bin/env bash' \
  'set -euo pipefail' \
  'state="$FAMTASTIC_FIXTURE_HOME/pilot-lock"' \
  'case "$1" in' \
  '  status|cr|pm:enable|php:script|simple-sitemap:generate|updatedb) exit 0 ;;' \
  '  updatedb:status) printf "[]\n" ;;' \
  '  config:get) [[ -f "$state" ]] && cat "$state" || printf "0\n" ;;' \
  '  config:set) printf "%s\n" "$4" > "$state" ;;' \
  '  sget) printf "0\n" ;;' \
  '  sql:dump) exit 0 ;;' \
  '  php:eval)' \
  '    code="$2"' \
  '    if [[ "$code" == *canonical-public-bases-ok* ]]; then' \
  '      if [[ "$FAMTASTIC_FIXTURE_CANONICAL_BASES" = "1" ]]; then printf "canonical-public-bases-ok\n"; exit 0; fi' \
  '      echo "fixture public bases are not canonical" >&2; exit 1' \
  '    fi' \
  '    if [[ "$code" == *claimable_jobs=* ]]; then' \
  '      printf "claimable_jobs=%s\nactive_jobs=%s\nclaimable_messages=%s\nactive_messages=%s\nunknown=%s\n" "$FAMTASTIC_FIXTURE_CLAIMABLE_JOBS" "$FAMTASTIC_FIXTURE_ACTIVE_JOBS" "$FAMTASTIC_FIXTURE_CLAIMABLE_MESSAGES" "$FAMTASTIC_FIXTURE_ACTIVE_MESSAGES" "$FAMTASTIC_FIXTURE_UNKNOWN_WORK"' \
  '      exit 0' \
  '    fi' \
  '    exit 0 ;;' \
  '  *) exit 0 ;;' \
  'esac' > "$fixture_home/public_html/vendor/bin/drush"
chmod +x "$fixture_home/public_html/vendor/bin/drush"

for command in composer tar rsync; do
  printf '%s\n' '#!/usr/bin/env bash' 'exit 0' > "$fixture_bin/$command"
  chmod +x "$fixture_bin/$command"
done
printf '%s\n' \
  '#!/usr/bin/env bash' \
  'if [[ "${FAMTASTIC_FIXTURE_BROAD_PROCESS:-0}" = "1" ]]; then' \
  '  printf "%s\\n" "/usr/local/bin/php /fixture/public_html/vendor/bin/drush cron"' \
  'fi' > "$fixture_bin/ps"
chmod +x "$fixture_bin/ps"
printf '%s\n' '#!/usr/bin/env bash' 'if [[ "$1" = "-r" ]]; then printf "8.2.0"; fi' > "$fixture_bin/php"
chmod +x "$fixture_bin/php"

printf '%s\n' '# fixture services' > "$fixture_home/public_html/web/sites/default/services.yml"
printf '%s\n' '# fixture module' > "$fixture_home/public_html/web/modules/custom/famtastic_pipeline/famtastic_pipeline.info.yml"
printf '%s\n' '# fixture theme' > "$fixture_home/public_html/web/themes/custom/famtastic_admin/famtastic_admin.info.yml"

run_fixture() {
  PATH="$fixture_bin:$PATH" \
  FAMTASTIC_FIXTURE_HOME="$fixture_home" \
  FAMTASTIC_FIXTURE_BIN="$fixture_bin" \
  FAMTASTIC_FIXTURE_CRONTAB="$fixture_crontab" \
  FAMTASTIC_FIXTURE_SHA="$fixture_sha" \
  FAMTASTIC_FIXTURE_GIT_CLONE_EXIT="${FAMTASTIC_FIXTURE_GIT_CLONE_EXIT:-79}" \
  FAMTASTIC_FIXTURE_CLAIMABLE_JOBS="${FAMTASTIC_FIXTURE_CLAIMABLE_JOBS:-0}" \
  FAMTASTIC_FIXTURE_ACTIVE_JOBS="${FAMTASTIC_FIXTURE_ACTIVE_JOBS:-0}" \
  FAMTASTIC_FIXTURE_CLAIMABLE_MESSAGES="${FAMTASTIC_FIXTURE_CLAIMABLE_MESSAGES:-0}" \
  FAMTASTIC_FIXTURE_ACTIVE_MESSAGES="${FAMTASTIC_FIXTURE_ACTIVE_MESSAGES:-0}" \
  FAMTASTIC_FIXTURE_UNKNOWN_WORK="${FAMTASTIC_FIXTURE_UNKNOWN_WORK:-0}" \
  FAMTASTIC_FIXTURE_CANONICAL_BASES="${FAMTASTIC_FIXTURE_CANONICAL_BASES:-1}" \
  FAMTASTIC_FIXTURE_BROAD_PROCESS="${FAMTASTIC_FIXTURE_BROAD_PROCESS:-0}" \
  FAMTASTIC_SSH_TARGET='fixture@local' \
  FAMTASTIC_REPOSITORY_URL='fixture://repository' \
  "$DEPLOYER" "$@"
}

write_exact_drupal_cron() {
  printf '%s\n' \
    '# FAMTASTIC_DRUPAL_CRON_V1' \
    "*/5 * * * * cd $fixture_home/public_html && $fixture_home/public_html/vendor/bin/drush cron >/dev/null 2>&1" > "$fixture_crontab"
}

write_unmarked_drupal_cron() {
  printf '%s\n' \
    "*/5 * * * * cd $fixture_home/public_html && /usr/local/bin/php $fixture_home/public_html/vendor/bin/drush.php cron >/dev/null 2>&1" > "$fixture_crontab"
}

write_unmarked_lifecycle_cron() {
  printf '%s\n' \
    "*/5 * * * * cd $fixture_home/public_html && $fixture_home/public_html/vendor/bin/drush famtastic:lifecycle-run --limit=50 >/dev/null 2>&1" > "$fixture_crontab"
}

write_unmarked_jobs_run_cron() {
  printf '%s\n' \
    "*/5 * * * * cd $fixture_home/public_html && $fixture_home/public_html/vendor/bin/drush famtastic:jobs-run --limit=50 >/dev/null 2>&1" > "$fixture_crontab"
}

write_direct_php_evaluator_cron() {
  printf '%s\n' \
    "*/5 * * * * cd $fixture_home/public_html && $fixture_home/public_html/vendor/bin/drush php:eval 'Drupal::service(\"famtastic_pipeline.automation_worker\")->run();' >/dev/null 2>&1" > "$fixture_crontab"
}

assert_contains() {
  [[ "$1" == *"$2"* ]] || {
    echo "Expected output to contain: $2" >&2
    exit 1
  }
}

write_exact_drupal_cron
if mismatch_output="$(
  FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON=1 \
  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM=wrong-marker \
  run_fixture 2>&1
)"; then
  echo "Expected mismatched marker confirmation to fail." >&2
  exit 1
fi
assert_contains "$mismatch_output" 'requires FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM=FAMTASTIC_DRUPAL_CRON_V1'

write_unmarked_drupal_cron
if unmarked_output="$(FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 run_fixture 2>&1)"; then
  echo "Expected active unmarked drush cron to fail pilot preflight." >&2
  exit 1
fi
assert_contains "$unmarked_output" 'active drush cron scheduler'

write_unmarked_lifecycle_cron
if lifecycle_output="$(FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 run_fixture 2>&1)"; then
  echo "Expected active unmarked lifecycle runner to fail pilot preflight." >&2
  exit 1
fi
assert_contains "$lifecycle_output" 'active lifecycle scheduler entry'

write_unmarked_jobs_run_cron
if jobs_run_output="$(FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 run_fixture 2>&1)"; then
  echo "Expected active unmarked jobs-run to fail pilot preflight." >&2
  exit 1
fi
assert_contains "$jobs_run_output" 'active jobs-run or automation-worker scheduler'

write_direct_php_evaluator_cron
if direct_evaluator_output="$(FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 run_fixture 2>&1)"; then
  echo "Expected active direct PHP evaluator to fail pilot preflight." >&2
  exit 1
fi
assert_contains "$direct_evaluator_output" 'active direct php:eval/php:script/automation-worker scheduler'

: > "$fixture_crontab"
if process_output="$(
  FAMTASTIC_FIXTURE_BROAD_PROCESS=1 \
  FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
  run_fixture 2>&1
)"; then
  echo "Expected an in-flight broad scheduler process to fail pilot preflight." >&2
  exit 1
fi
assert_contains "$process_output" 'matching broad scheduler process(es) are already running'

write_exact_drupal_cron
if active_message_output="$(
  FAMTASTIC_FIXTURE_ACTIVE_MESSAGES=1 \
  FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
  run_fixture 2>&1
)"; then
  echo "Expected active legacy generic email work to fail pilot preflight." >&2
  exit 1
fi
assert_contains "$active_message_output" 'active messages=1'

write_exact_drupal_cron
if bad_base_output="$(
  FAMTASTIC_FIXTURE_CANONICAL_BASES=0 \
  FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
  run_fixture 2>&1
)"; then
  echo "Expected noncanonical public bases to fail pilot preflight." >&2
  exit 1
fi
assert_contains "$bad_base_output" 'live public base configuration is not canonical'

write_exact_drupal_cron
preflight_before="$(< "$fixture_crontab")"
preflight_output="$(
  FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON=1 \
  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM=FAMTASTIC_DRUPAL_CRON_V1 \
  run_fixture 2>&1
)"
assert_contains "$preflight_output" 'would be suspended during an authorized apply'
test "$(< "$fixture_crontab")" = "$preflight_before" || {
  echo "Read-only preflight modified the fixture crontab." >&2
  exit 1
}

if failure_output="$(
  FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON=1 \
  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM=FAMTASTIC_DRUPAL_CRON_V1 \
  run_fixture --apply 2>&1
)"; then
  echo "Expected fixture apply to fail after scheduler suspension." >&2
  exit 1
fi
if grep -q 'drush cron' "$fixture_crontab"; then
  echo "Broad Drupal cron was restored after failed promotion." >&2
  exit 1
fi
backup="$(find "$fixture_home/deploy/famtastic-designs/cron-backups" -type f -name 'famtastic-crontab-before-pilot-suspension-*' -print -quit)"
test -n "$backup" && test -f "$backup" || {
  echo "Expected mode-0600 crontab backup after exact scheduler suspension." >&2
  exit 1
}
test "$(stat -f '%Lp' "$backup")" = '600' || {
  echo "Crontab backup was not mode 0600." >&2
  exit 1
}
assert_contains "$failure_output" 'Pilot exact-dispatch-only: suspended only drush-cron'

# The owner ruling intentionally rejects a stale full-crontab auto-restore on
# both success and failure. An explicit end-pilot review owns restoration.
if rg -F 'crontab "$scheduler_cron_backup"' "$DEPLOYER" >/dev/null; then
  echo "Pilot deployer must not automatically restore a full crontab backup." >&2
  exit 1
fi
rg -F 'scheduler remains suspended after both success and failure' "$DEPLOYER" >/dev/null
rg -F 'assert_no_active_broad_scheduler_cron' "$DEPLOYER" >/dev/null

echo "PASS: prepromotion cron guard fixtures covered marker confirmation, unmarked lifecycle/Drupal/jobs-run/direct-evaluator and in-flight-process refusal, noncanonical-base refusal, active generic-email refusal, read-only preflight, suspension-before-promotion, mode-0600 backup, and no automatic restore."
