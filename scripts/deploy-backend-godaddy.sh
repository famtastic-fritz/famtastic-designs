#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SSH_TARGET="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
REMOTE_ROOT="${FAMTASTIC_REMOTE_ROOT:-public_html}"
REMOTE_DEPLOY_BASE="${FAMTASTIC_REMOTE_DEPLOY_BASE:-deploy/famtastic-designs}"
REPOSITORY_URL="${FAMTASTIC_REPOSITORY_URL:-https://github.com/famtastic-fritz/famtastic-designs.git}"
# A public-preview pilot must never rely on the general lifecycle runner: that
# runner claims every eligible automation job and notification in the shared
# queues.  The normal deployment path retains its existing scheduler behavior;
# an operator must explicitly opt into this narrower release mode.
PILOT_EXACT_DISPATCH_ONLY="${FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY:-0}"
# These are deliberately separate from the exact-dispatch declaration. They
# are explicit, marker-bound operations actions for one checked-in scheduler
# entry each; neither flag may become a broad crontab editor.
PILOT_SUSPEND_MARKED_LIFECYCLE_CRON="${FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON:-0}"
PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM="${FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM:-}"
PILOT_SUSPEND_MARKED_DRUPAL_CRON="${FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON:-0}"
PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM="${FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM:-}"
PILOT_SUSPEND_MARKED_JOBS_RUN_CRON="${FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON:-0}"
PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM="${FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM:-}"
# The stale bypass queue is never changed implicitly. A pilot apply may use
# these two repeated exact values to call the existing narrow quarantine
# command; otherwise a non-zero stale queue is a release blocker.
PILOT_LEGACY_QUARANTINE_CAMPAIGN="${FAMTASTIC_PILOT_LEGACY_QUARANTINE_CAMPAIGN:-}"
PILOT_LEGACY_QUARANTINE_CONFIRM="${FAMTASTIC_PILOT_LEGACY_QUARANTINE_CONFIRM:-}"
PILOT_LEGACY_CAMPAIGN_KEY='cold-260-aug-2026'
APPLY=false

usage() {
  cat <<USAGE
Usage: $0 [--apply]

Without --apply, performs read-only local and remote preflight checks.
With --apply, validates the exact current main commit in a private server
worktree, backs up the database and current custom code, promotes the module and
admin theme, imports the demand-library field configuration, seeds the governed
draft library, runs Drupal database updates and cache rebuild, and records the
release.

Set FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 only for an owner-gated public-preview
pilot. In that mode the deployment writes and verifies the durable Drupal
pilot lock before promotion. Fresh cPanel drush cron processes therefore
skip this module's general automation, outbox, SLA, and lifecycle behavior
even though they do not inherit this deployment shell's environment. Exact
owner-approved preview delivery must use famtastic:preview-delivery-dispatch.

Before this pilot promotes code, every active broad scheduler must be absent or
be explicitly suspended. The deployer never guesses at an unmarked line. An
authorized apply may suspend only one of these exact marker/command pairs:

  FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1
  FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM=FAMTASTIC_LIFECYCLE_CRON_V1

  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON=1
  FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM=FAMTASTIC_DRUPAL_CRON_V1

  FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON=1
  FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM=FAMTASTIC_JOBS_RUN_CRON_V1

Preflight validates the exact marker and the immediately following byte-exact
command. Apply stores a mode-0600 full-crontab backup under the private deploy
directory, removes only authorized pairs, and proves no active broad
famtastic:lifecycle-run or drush cron entry remains before old code is
promoted. The scheduler remains suspended after both success and failure;
restoration is a separate owner-authorized end-pilot operation so a stale backup
can never overwrite later crontab changes or reopen shared dispatch implicitly.

Before a pilot can apply, the deployer verifies that the historical
cold-260-aug-2026 generic proof queue is empty. It never quarantines it by
default. To authorize only that exact pre-pilot quarantine, repeat both values:
  FAMTASTIC_PILOT_LEGACY_QUARANTINE_CAMPAIGN=cold-260-aug-2026
  FAMTASTIC_PILOT_LEGACY_QUARANTINE_CONFIRM=cold-260-aug-2026
The apply records a private receipt and still verifies the queued count is zero.
USAGE
}

case "${1:-}" in
  "") ;;
  --apply) APPLY=true ;;
  -h|--help) usage; exit 0 ;;
  *) usage >&2; exit 2 ;;
esac

case "$PILOT_EXACT_DISPATCH_ONLY" in
  0|1) ;;
  *)
    echo "FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY must be 0 or 1." >&2
    exit 2
    ;;
esac

case "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON" in
  0|1) ;;
  *)
    echo "FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON must be 0 or 1." >&2
    exit 2
    ;;
esac

case "$PILOT_SUSPEND_MARKED_DRUPAL_CRON" in
  0|1) ;;
  *)
    echo "FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON must be 0 or 1." >&2
    exit 2
    ;;
esac

case "$PILOT_SUSPEND_MARKED_JOBS_RUN_CRON" in
  0|1) ;;
  *)
    echo "FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON must be 0 or 1." >&2
    exit 2
    ;;
esac

if [[ "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON" == "1" ]]; then
  if [[ "$PILOT_EXACT_DISPATCH_ONLY" != "1" ]]; then
    echo "FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1 requires FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1." >&2
    exit 2
  fi
  if [[ "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM" != "FAMTASTIC_LIFECYCLE_CRON_V1" ]]; then
    echo "Marked lifecycle suspension requires FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM=FAMTASTIC_LIFECYCLE_CRON_V1." >&2
    exit 2
  fi
elif [[ -n "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM" ]]; then
  echo "FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM requires FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1." >&2
  exit 2
fi

if [[ "$PILOT_SUSPEND_MARKED_DRUPAL_CRON" == "1" ]]; then
  if [[ "$PILOT_EXACT_DISPATCH_ONLY" != "1" ]]; then
    echo "FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON=1 requires FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1." >&2
    exit 2
  fi
  if [[ "$PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM" != "FAMTASTIC_DRUPAL_CRON_V1" ]]; then
    echo "Marked Drupal cron suspension requires FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM=FAMTASTIC_DRUPAL_CRON_V1." >&2
    exit 2
  fi
elif [[ -n "$PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM" ]]; then
  echo "FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM requires FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON=1." >&2
  exit 2
fi

if [[ "$PILOT_SUSPEND_MARKED_JOBS_RUN_CRON" == "1" ]]; then
  if [[ "$PILOT_EXACT_DISPATCH_ONLY" != "1" ]]; then
    echo "FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON=1 requires FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1." >&2
    exit 2
  fi
  if [[ "$PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM" != "FAMTASTIC_JOBS_RUN_CRON_V1" ]]; then
    echo "Marked jobs-run suspension requires FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM=FAMTASTIC_JOBS_RUN_CRON_V1." >&2
    exit 2
  fi
elif [[ -n "$PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM" ]]; then
  echo "FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM requires FAMTASTIC_PILOT_SUSPEND_MARKED_JOBS_RUN_CRON=1." >&2
  exit 2
fi

if [[ -n "$PILOT_LEGACY_QUARANTINE_CAMPAIGN$PILOT_LEGACY_QUARANTINE_CONFIRM" ]]; then
  if [[ "$PILOT_EXACT_DISPATCH_ONLY" != "1" ]]; then
    echo "Legacy cold-proof quarantine variables require FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1." >&2
    exit 2
  fi
  if [[ "$PILOT_LEGACY_QUARANTINE_CAMPAIGN" != "$PILOT_LEGACY_CAMPAIGN_KEY" || "$PILOT_LEGACY_QUARANTINE_CONFIRM" != "$PILOT_LEGACY_CAMPAIGN_KEY" ]]; then
    echo "Legacy cold-proof quarantine requires both variables to exactly equal $PILOT_LEGACY_CAMPAIGN_KEY." >&2
    exit 2
  fi
fi

for required_command in git ssh; do
  command -v "$required_command" >/dev/null || {
    echo "Missing required command: $required_command" >&2
    exit 1
  }
done

cd "$REPO_ROOT"
if [[ -n "$(git status --porcelain)" ]]; then
  echo "Refusing deployment from a dirty Git worktree." >&2
  git status --short >&2
  exit 1
fi

COMMIT_SHA="$(git rev-parse HEAD)"
REMOTE_MAIN_SHA="$(git ls-remote "$REPOSITORY_URL" refs/heads/main | awk '{print $1}')"
if [[ "$COMMIT_SHA" != "$REMOTE_MAIN_SHA" ]]; then
  echo "Refusing deployment: local HEAD is not the current origin/main commit." >&2
  echo "local HEAD:  $COMMIT_SHA" >&2
  echo "origin/main: $REMOTE_MAIN_SHA" >&2
  exit 1
fi

echo "Backend deployment candidate: $COMMIT_SHA"
echo "Private validation source:    ~/$REMOTE_DEPLOY_BASE/releases/$COMMIT_SHA/source/backend"
echo "Drupal runtime:               ~/$REMOTE_ROOT"
if [[ "$PILOT_EXACT_DISPATCH_ONLY" == "1" ]]; then
  echo "Dispatch mode:                exact owner-approved preview only (all broad schedulers forbidden)"
  if [[ "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON" == "1" ]]; then
    echo "Scheduler action:             suspend only the known marked lifecycle cron during apply"
  fi
  if [[ "$PILOT_SUSPEND_MARKED_DRUPAL_CRON" == "1" ]]; then
    echo "Scheduler action:             suspend only the known marked Drupal cron during apply"
  fi
  if [[ "$PILOT_SUSPEND_MARKED_JOBS_RUN_CRON" == "1" ]]; then
    echo "Scheduler action:             suspend only the known marked jobs-run cron during apply"
  fi
fi

remote_mode="preflight"
[[ "$APPLY" == true ]] && remote_mode="apply"

encode_remote_arg() {
  # OpenSSH serializes its remote command as shell text, which drops empty
  # positional arguments. Pilot confirmations are deliberately optional, so
  # transmit every value as a nonempty base64 token instead.
  if [[ -z "$1" ]]; then
    # `_` cannot occur in standard base64 output and stays a shell-safe token.
    printf '_'
    return
  fi
  printf '%s' "$1" | base64 | tr -d '\n'
}

ssh -T "$SSH_TARGET" bash -s -- \
  "$(encode_remote_arg "$remote_mode")" "$(encode_remote_arg "$REMOTE_ROOT")" "$(encode_remote_arg "$REMOTE_DEPLOY_BASE")" "$(encode_remote_arg "$REPOSITORY_URL")" "$(encode_remote_arg "$COMMIT_SHA")" "$(encode_remote_arg "$PILOT_EXACT_DISPATCH_ONLY")" "$(encode_remote_arg "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON")" "$(encode_remote_arg "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON_CONFIRM")" "$(encode_remote_arg "$PILOT_SUSPEND_MARKED_DRUPAL_CRON")" "$(encode_remote_arg "$PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM")" "$(encode_remote_arg "$PILOT_SUSPEND_MARKED_JOBS_RUN_CRON")" "$(encode_remote_arg "$PILOT_SUSPEND_MARKED_JOBS_RUN_CRON_CONFIRM")" "$(encode_remote_arg "$PILOT_LEGACY_QUARANTINE_CAMPAIGN")" "$(encode_remote_arg "$PILOT_LEGACY_QUARANTINE_CONFIRM")" <<'REMOTE'
set -euo pipefail

decode_remote_arg() {
  if [[ "$1" == "_" ]]; then
    return
  fi
  printf '%s' "$1" | base64 -d
}

mode="$(decode_remote_arg "$1")"
remote_root="$(decode_remote_arg "$2")"
deploy_base="$(decode_remote_arg "$3")"
repository_url="$(decode_remote_arg "$4")"
commit_sha="$(decode_remote_arg "$5")"
pilot_exact_dispatch_only="$(decode_remote_arg "$6")"
pilot_suspend_marked_lifecycle_cron="$(decode_remote_arg "$7")"
pilot_suspend_marked_lifecycle_cron_confirm="$(decode_remote_arg "$8")"
pilot_suspend_marked_drupal_cron="$(decode_remote_arg "$9")"
pilot_suspend_marked_drupal_cron_confirm="$(decode_remote_arg "${10}")"
pilot_suspend_marked_jobs_run_cron="$(decode_remote_arg "${11}")"
pilot_suspend_marked_jobs_run_cron_confirm="$(decode_remote_arg "${12}")"
pilot_legacy_quarantine_campaign="$(decode_remote_arg "${13}")"
pilot_legacy_quarantine_confirm="$(decode_remote_arg "${14}")"
production_dir="$HOME/$remote_root"
deploy_dir="$HOME/$deploy_base"
mirror_dir="$deploy_dir/repository.git"
release_dir="$deploy_dir/releases/$commit_sha"
source_dir="$release_dir/source"
backend_dir="$source_dir/backend"
source_module="$backend_dir/web/modules/custom/famtastic_pipeline"
production_module="$production_dir/web/modules/custom/famtastic_pipeline"
source_admin_theme="$backend_dir/web/themes/custom/famtastic_admin"
production_admin_theme="$production_dir/web/themes/custom/famtastic_admin"
source_customer_theme="$backend_dir/web/themes/custom/famtastic_customer"
production_customer_theme="$production_dir/web/themes/custom/famtastic_customer"
source_services="$backend_dir/web/sites/default/services.yml"
production_services="$production_dir/web/sites/default/services.yml"
source_product_config="$backend_dir/config/famtastic-products.json"
source_deal_config="$backend_dir/config/famtastic-deal-terms.json"
source_demand_manifest="$backend_dir/config/famtastic-content-series.json"
source_demand_fields="$backend_dir/scripts/install-demand-content-fields.php"
source_demand_seed="$backend_dir/scripts/seed-demand-content.php"
source_package_normalizer="$backend_dir/scripts/normalize-package-ladder.php"
production_config_dir="$production_dir/config"
drush="$production_dir/vendor/bin/drush"
legacy_cold_campaign_key='cold-260-aug-2026'

case "$pilot_exact_dispatch_only" in
  0|1) ;;
  *)
    echo "Remote pilot-dispatch mode is invalid." >&2
    exit 2
    ;;
esac
case "$pilot_suspend_marked_lifecycle_cron" in
  0|1) ;;
  *)
    echo "Remote pilot scheduler mode is invalid." >&2
    exit 2
    ;;
esac
case "$pilot_suspend_marked_drupal_cron" in
  0|1) ;;
  *)
    echo "Remote Drupal cron scheduler mode is invalid." >&2
    exit 2
    ;;
esac
case "$pilot_suspend_marked_jobs_run_cron" in
  0|1) ;;
  *)
    echo "Remote jobs-run cron scheduler mode is invalid." >&2
    exit 2
    ;;
esac
if [[ "$pilot_suspend_marked_lifecycle_cron" == "1" ]]; then
  if [[ "$pilot_exact_dispatch_only" != "1" || "$pilot_suspend_marked_lifecycle_cron_confirm" != "FAMTASTIC_LIFECYCLE_CRON_V1" ]]; then
    echo "Remote lifecycle scheduler suspension requires exact-dispatch-only mode and its exact marker confirmation." >&2
    exit 2
  fi
elif [[ -n "$pilot_suspend_marked_lifecycle_cron_confirm" ]]; then
  echo "Remote lifecycle scheduler confirmation was supplied without its suspension declaration." >&2
  exit 2
fi
if [[ "$pilot_suspend_marked_drupal_cron" == "1" ]]; then
  if [[ "$pilot_exact_dispatch_only" != "1" || "$pilot_suspend_marked_drupal_cron_confirm" != "FAMTASTIC_DRUPAL_CRON_V1" ]]; then
    echo "Remote Drupal cron suspension requires exact-dispatch-only mode and its exact marker confirmation." >&2
    exit 2
  fi
elif [[ -n "$pilot_suspend_marked_drupal_cron_confirm" ]]; then
  echo "Remote Drupal cron confirmation was supplied without its suspension declaration." >&2
  exit 2
fi
if [[ "$pilot_suspend_marked_jobs_run_cron" == "1" ]]; then
  if [[ "$pilot_exact_dispatch_only" != "1" || "$pilot_suspend_marked_jobs_run_cron_confirm" != "FAMTASTIC_JOBS_RUN_CRON_V1" ]]; then
    echo "Remote jobs-run cron suspension requires exact-dispatch-only mode and its exact marker confirmation." >&2
    exit 2
  fi
elif [[ -n "$pilot_suspend_marked_jobs_run_cron_confirm" ]]; then
  echo "Remote jobs-run cron confirmation was supplied without its suspension declaration." >&2
  exit 2
fi
if [[ -n "$pilot_legacy_quarantine_campaign$pilot_legacy_quarantine_confirm" ]]; then
  if [[ "$pilot_exact_dispatch_only" != "1" || "$pilot_legacy_quarantine_campaign" != "$legacy_cold_campaign_key" || "$pilot_legacy_quarantine_confirm" != "$legacy_cold_campaign_key" ]]; then
    echo "Remote legacy cold-proof quarantine authorization is invalid." >&2
    exit 2
  fi
fi

# The general lifecycle runner claims all eligible jobs and notifications. It
# is intentionally prohibited for a controlled exact-ID preview pilot, where
# `famtastic:preview-delivery-dispatch` is the only allowed mail entry point.
# Reading the scheduler is not optional: an unreadable non-empty crontab is
# unsafe because the release cannot prove the broad worker is inactive.
current_crontab=''
scheduler_cron_backup=''
scheduler_cron_record='not_suspended'
scheduler_cron_suspended=0
scheduler_timestamp=''
pilot_dispatch_lock_before=''
pilot_dispatch_lock_record=''
pilot_public_bases_record='not_checked'
active_drupal_cron_count='not_inspected'
active_lifecycle_cron_count='not_inspected'
active_jobs_run_cron_count='not_inspected'
active_direct_automation_cron_count='not_inspected'
active_broad_scheduler_process_count='not_inspected'
legacy_cold_queue_before='not_checked'
legacy_cold_queue_after='not_checked'
legacy_cold_active_before='not_checked'
legacy_cold_active_after='not_checked'
legacy_cold_claimable_jobs_before='not_checked'
legacy_cold_claimable_jobs_after='not_checked'
legacy_cold_claimable_messages_before='not_checked'
legacy_cold_claimable_messages_after='not_checked'
legacy_cold_active_messages_before='not_checked'
legacy_cold_active_messages_after='not_checked'
legacy_cold_unknown_work_before='not_checked'
legacy_cold_unknown_work_after='not_checked'
legacy_cold_quarantine_receipt='none'

load_current_crontab() {
  if ! current_crontab="$(crontab -l 2>&1)"; then
    if ! printf '%s\n' "$current_crontab" | grep -qi 'no crontab'; then
      echo "Pilot exact-dispatch-only deployment refused: unable to inspect the current crontab." >&2
      return 1
    fi
    current_crontab=''
  fi
}

# Drupal's hook_cron path is a second, independent broad worker. It must be
# stopped before old code is promoted because old code cannot honor the new
# durable lock yet.
active_global_drupal_cron_count() {
  printf '%s\n' "$current_crontab" | awk '
    /^[[:space:]]*#/ { next }
    /(^|[[:space:]])[^[:space:]]*drush(\.php)?[[:space:]]+cron([[:space:]]|$)/ { count++ }
    END { print count + 0 }
  '
}

# `famtastic:jobs-run` can claim the same durable queue without going through
# lifecycle-run. Treat both its documented command and alias as broad
# schedulers during a governed pilot.
active_global_jobs_run_cron_count() {
  printf '%s\n' "$current_crontab" | awk '
    /^[[:space:]]*#/ { next }
    /famtastic:jobs-run/ { count++; next }
    /(^|[[:space:]])fjr([[:space:]]|$)/ { count++; next }
    END { print count + 0 }
  '
}

# A generic PHP evaluator/script can call services directly and therefore sit
# outside every named Drush command guard. There is no safe byte-exact generic
# form to remove automatically, so pilot preflight detects these lines and
# requires the operator to make the crontab empty of them before apply.
active_direct_automation_cron_count() {
  printf '%s\n' "$current_crontab" | awk '
    /^[[:space:]]*#/ { next }
    /(^|[[:space:]])[^[:space:]]*drush(\.php)?[[:space:]]+(php:eval|php:script|ev)([[:space:]]|$)/ { count++; next }
    /automation[_:-]?worker/ { count++ }
    END { print count + 0 }
  '
}

# A removed crontab line only prevents the next start. A broad process that was
# already running can still execute old code while promotion happens, so this
# separate process snapshot is mandatory before and after the suspension window.
# The deployment itself invokes only short non-worker Drush evals and is not a
# match for these broad command shapes.
active_broad_scheduler_process_count() {
  local processes
  if ! processes="$(ps -u "$(id -u)" -o args= 2>&1)"; then
    echo "Pilot deployment refused: unable to inspect current user processes for broad scheduler work." >&2
    return 1
  fi
  printf '%s\n' "$processes" | awk '
    /(^|[[:space:]])[^[:space:]]*drush(\.php)?[[:space:]]+cron([[:space:]]|$)/ { count++; next }
    /famtastic:lifecycle-run/ { count++; next }
    /famtastic:jobs-run/ { count++; next }
    /(^|[[:space:]])fjr([[:space:]]|$)/ { count++; next }
    /(^|[[:space:]])[^[:space:]]*drush(\.php)?[[:space:]]+(php:eval|php:script|ev)([[:space:]]|$)/ { count++; next }
    /automation[_:-]?worker/ { count++ }
    END { print count + 0 }
  '
}

# Read/write an intentional, auditable Drupal configuration value. This is
# deliberately not based on getenv(): cPanel starts a new process for every
# scheduled Drush command and does not inherit the deploy shell.
read_pilot_dispatch_lock() {
  local value
  value="$("$drush" config:get famtastic_pipeline.settings pilot_exact_dispatch_only --format=string 2>/dev/null || true)"
  case "$(printf '%s' "$value" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')" in
    1|true|yes|on) printf '1\n' ;;
    ''|0|false|no|off|null) printf '0\n' ;;
    *)
      echo "Pilot exact-dispatch lock has an invalid Drupal config value: $value" >&2
      return 1
      ;;
  esac
}

assert_pilot_dispatch_lock() {
  local expected="$1" actual
  actual="$(read_pilot_dispatch_lock)"
  if [[ "$actual" != "$expected" ]]; then
    echo "Pilot exact-dispatch lock verification failed (expected $expected, got $actual)." >&2
    return 1
  fi
}

set_pilot_dispatch_lock() {
  local desired="$1"
  case "$desired" in
    0|1) ;;
    *) echo "Invalid desired pilot exact-dispatch lock value." >&2; return 1 ;;
  esac
  "$drush" config:set famtastic_pipeline.settings pilot_exact_dispatch_only "$desired" -y >/dev/null
  "$drush" cr >/dev/null
  assert_pilot_dispatch_lock "$desired"
  pilot_dispatch_lock_record="durable-config:$desired"
  echo "Pilot exact-dispatch durable Drupal lock verified: $desired."
}

# The module's install default is localhost and same-origin staging bases are
# valid in development. A production commercial pilot must be stricter: every
# generated customer link must resolve to the canonical production origin and
# Drupal API mount. This preflight deliberately does not overwrite global
# config; an incorrect live base is an operator correction, not a deploy guess.
assert_canonical_pilot_public_bases() {
  local result
  result="$("$drush" php:eval '
    $config = \Drupal::config("famtastic_pipeline.settings");
    $frontend = rtrim(trim((string) $config->get("frontend_base_url")), "/");
    $api = rtrim(trim((string) $config->get("public_api_base_url")), "/");
    if ($frontend !== "https://famtasticdesigns.com" || $api !== "https://famtasticdesigns.com/web") {
      throw new \RuntimeException("Pilot requires canonical frontend_base_url=https://famtasticdesigns.com and public_api_base_url=https://famtasticdesigns.com/web.");
    }
    print "canonical-public-bases-ok";
  ' 2>&1)" || {
    echo "$result" >&2
    echo "Pilot deployment refused: live public base configuration is not canonical." >&2
    return 1
  }
  if [[ "$result" != *"canonical-public-bases-ok"* ]]; then
    echo "Pilot deployment refused: canonical public base assertion returned an unexpected result." >&2
    return 1
  fi
  pilot_public_bases_record='https://famtasticdesigns.com|https://famtasticdesigns.com/web'
  echo "Pilot exact-dispatch-only: canonical frontend and public API bases verified."
}

# Count all exact-campaign work that can become a proof or generic commercial
# email. The notification outbox is intentionally excluded: it carries no
# campaign/prospect foreign key, so cancellation there would be heuristic rather
# than an exact-campaign action. The pilot lock holds that global dispatcher;
# inventory it manually before any later normal release unlocks it.
legacy_cold_work_counts() {
  "$drush" php:eval '
    $database = \Drupal::database();
    $campaign_id = (int) $database->select("famtastic_campaign", "c")
      ->fields("c", ["id"])
      ->condition("campaign_key", "cold-260-aug-2026")
      ->execute()
      ->fetchField();
    if ($campaign_id < 1) {
      throw new \RuntimeException("The exact cold-260 campaign record is absent.");
    }
    $prospect_ids = array_values(array_unique(array_map("intval", \Drupal::entityQuery("famtastic_prospect")
      ->accessCheck(FALSE)
      ->condition("campaign", "cold-260-aug-2026")
      ->execute())));
    $jobs = [];
    if ($prospect_ids !== []) {
      foreach ([["proof.generate", "proof.generate:prospect:%"], ["outreach.prepare", "outreach.prepare:prospect:%"]] as [$type, $pattern]) {
        foreach ($database->select("famtastic_job", "j")->fields("j", ["id", "job_type", "status"])
          ->condition("job_type", $type)
          ->condition("job_key", $pattern, "LIKE")
          ->condition("prospect_id", $prospect_ids, "IN")
          ->execute()
          ->fetchAll(\PDO::FETCH_ASSOC) as $row) {
          $jobs[(int) $row["id"]] = $row;
        }
      }
    }
    $messages = $database->select("famtastic_email_message", "m")
      ->fields("m", ["id", "template_key", "status"])
      ->condition("campaign_id", $campaign_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $message_ids = array_map(static fn (array $row): int => (int) $row["id"], $messages);
    if ($message_ids !== []) {
      $send_keys = array_map(static fn (int $id): string => "outreach.send:message:" . $id, $message_ids);
      foreach ($database->select("famtastic_job", "j")->fields("j", ["id", "job_type", "status"])
        ->condition("job_type", "outreach.send")
        ->condition("job_key", $send_keys, "IN")
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $jobs[(int) $row["id"]] = $row;
      }
    }
    $job_claimable = ["queued", "retry"];
    $job_active = ["running", "claimed", "processing", "dispatching"];
    $job_terminal = ["completed", "failed", "quarantined"];
    $message_claimable = ["staged", "queued", "held", "retry"];
    $message_active = ["running", "claimed", "processing", "dispatching", "sending"];
    $message_terminal = ["sent", "delivered", "opened", "clicked", "bounced", "complained", "unsubscribed", "suppressed", "failed", "quarantined"];
    $claimable_jobs = $active_jobs = $claimable_messages = $active_messages = $unknown = 0;
    foreach ($jobs as $row) {
      $status = strtolower(trim((string) $row["status"]));
      if (in_array($status, $job_claimable, TRUE)) $claimable_jobs++;
      elseif (in_array($status, $job_active, TRUE)) $active_jobs++;
      elseif (!in_array($status, $job_terminal, TRUE)) $unknown++;
    }
    foreach ($messages as $row) {
      $status = strtolower(trim((string) $row["status"]));
      if (in_array($status, $message_claimable, TRUE)) $claimable_messages++;
      elseif (in_array($status, $message_active, TRUE)) $active_messages++;
      elseif (!in_array($status, $message_terminal, TRUE)) $unknown++;
    }
    print "claimable_jobs=$claimable_jobs\n";
    print "active_jobs=$active_jobs\n";
    print "claimable_messages=$claimable_messages\n";
    print "active_messages=$active_messages\n";
    print "unknown=$unknown\n";
  ' | tr -d '\r'
}

read_legacy_cold_work_counts() {
  local phase="$1" raw claimable_jobs active_jobs claimable_messages active_messages unknown
  raw="$(legacy_cold_work_counts)"
  claimable_jobs="$(printf '%s\n' "$raw" | awk -F= '$1 == "claimable_jobs" { value = $2; seen++ } END { if (seen == 1) print value }')"
  active_jobs="$(printf '%s\n' "$raw" | awk -F= '$1 == "active_jobs" { value = $2; seen++ } END { if (seen == 1) print value }')"
  claimable_messages="$(printf '%s\n' "$raw" | awk -F= '$1 == "claimable_messages" { value = $2; seen++ } END { if (seen == 1) print value }')"
  active_messages="$(printf '%s\n' "$raw" | awk -F= '$1 == "active_messages" { value = $2; seen++ } END { if (seen == 1) print value }')"
  unknown="$(printf '%s\n' "$raw" | awk -F= '$1 == "unknown" { value = $2; seen++ } END { if (seen == 1) print value }')"
  for value in "$claimable_jobs" "$active_jobs" "$claimable_messages" "$active_messages" "$unknown"; do
    case "$value" in
      ''|*[!0-9]*)
        echo "Pilot deployment refused: exact cold-260 work inventory did not return five numeric counts." >&2
        return 1
        ;;
    esac
  done
  case "$phase" in
    before)
      legacy_cold_claimable_jobs_before="$claimable_jobs"
      legacy_cold_active_before="$active_jobs"
      legacy_cold_claimable_messages_before="$claimable_messages"
      legacy_cold_active_messages_before="$active_messages"
      legacy_cold_unknown_work_before="$unknown"
      ;;
    after)
      legacy_cold_claimable_jobs_after="$claimable_jobs"
      legacy_cold_active_after="$active_jobs"
      legacy_cold_claimable_messages_after="$claimable_messages"
      legacy_cold_active_messages_after="$active_messages"
      legacy_cold_unknown_work_after="$unknown"
      ;;
    *)
      echo "Pilot deployment refused: invalid exact cold-260 inventory phase." >&2
      return 1
      ;;
  esac
}

# This gate runs before promotion without invoking the quarantine command. A
# production host may be one release behind this command, so execution must
# wait until the new module/dependencies/cache are active. It still fails early
# without explicit authorization so a pilot cannot cross the code boundary
# carrying a known old generic queue by accident.
preflight_legacy_cold_queue_gate() {
  read_legacy_cold_work_counts before
  if [[ "$legacy_cold_active_before" != "0" || "$legacy_cold_active_messages_before" != "0" || "$legacy_cold_unknown_work_before" != "0" ]]; then
    echo "Pilot deployment refused: historical cold-260 has active or unrecognized proof/mail work (active jobs=$legacy_cold_active_before, active messages=$legacy_cold_active_messages_before, unknown=$legacy_cold_unknown_work_before). Do not quarantine or promote until it is reconciled." >&2
    return 1
  fi
  legacy_cold_queue_before="$((legacy_cold_claimable_jobs_before + legacy_cold_claimable_messages_before))"
  if [[ "$legacy_cold_queue_before" == "0" ]]; then
    echo "Pilot exact-dispatch-only preflight: historical cold-260 has no claimable proof jobs or generic email records."
    return 0
  fi
  if [[ "$pilot_legacy_quarantine_campaign" != "$legacy_cold_campaign_key" || "$pilot_legacy_quarantine_confirm" != "$legacy_cold_campaign_key" ]]; then
    echo "Pilot deployment refused: cold-260 has $legacy_cold_claimable_jobs_before claimable job(s) and $legacy_cold_claimable_messages_before claimable generic email record(s); no exact quarantine authorization was supplied." >&2
    return 1
  fi
  if [[ "$mode" == "apply" ]]; then
    echo "Pilot exact-dispatch-only: $legacy_cold_claimable_jobs_before historical cold job(s) and $legacy_cold_claimable_messages_before generic email record(s) will be quarantined only after the new module is promoted, cache-rebuilt, and durable lock verified."
  else
    echo "Pilot exact-dispatch-only preflight: $legacy_cold_claimable_jobs_before historical cold job(s) and $legacy_cold_claimable_messages_before generic email record(s) would be quarantined only during an authorized apply after module promotion."
  fi
}

# Executes the explicit narrow command only after promotion. Keeping this
# separate from the preflight gate avoids assuming that a production host
# already contains the just-introduced Drush command.
quarantine_legacy_cold_queue_after_promotion() {
  local reason receipt
  read_legacy_cold_work_counts after
  if [[ "$legacy_cold_active_after" != "0" || "$legacy_cold_active_messages_after" != "0" || "$legacy_cold_unknown_work_after" != "0" ]]; then
    echo "Pilot deployment refused: cold-260 developed active or unrecognized proof/mail work during promotion (active jobs=$legacy_cold_active_after, active messages=$legacy_cold_active_messages_after, unknown=$legacy_cold_unknown_work_after). Stop for reconciliation." >&2
    return 1
  fi
  legacy_cold_queue_before="$((legacy_cold_claimable_jobs_after + legacy_cold_claimable_messages_after))"
  if [[ "$legacy_cold_queue_before" == "0" ]]; then
    legacy_cold_queue_after='0'
    echo "Pilot exact-dispatch-only: historical cold-260 proof jobs and generic email records remain non-claimable after promotion."
    return 0
  fi
  if [[ "$pilot_legacy_quarantine_campaign" != "$legacy_cold_campaign_key" || "$pilot_legacy_quarantine_confirm" != "$legacy_cold_campaign_key" ]]; then
    echo "Pilot deployment refused: historical cold-260 generic proof queue changed during promotion and lacks exact quarantine authorization." >&2
    return 1
  fi
  reason='Pre-pilot exact-dispatch safety quarantine for the legacy generic proof queue.'
  receipt="$deploy_dir/tmp/cold-260-quarantine-$commit_sha-$(date -u +%Y%m%dT%H%M%SZ).log"
  (umask 077; "$drush" famtastic:campaign-proof-quarantine --campaign="$legacy_cold_campaign_key" --confirm="$legacy_cold_campaign_key" --reason="$reason" > "$receipt" 2>&1)
  test -s "$receipt" || {
    echo "Pilot deployment refused: the explicit legacy quarantine produced no auditable receipt." >&2
    return 1
  }
  legacy_cold_quarantine_receipt="$receipt"
  read_legacy_cold_work_counts after
  legacy_cold_queue_after="$((legacy_cold_claimable_jobs_after + legacy_cold_claimable_messages_after))"
  if [[ "$legacy_cold_queue_after" != "0" ]]; then
    echo "Pilot deployment refused: explicit legacy quarantine did not reduce all exact historical proof/mail work to zero claimable rows. Receipt: $receipt" >&2
    return 1
  fi
  if [[ "$legacy_cold_active_after" != "0" || "$legacy_cold_active_messages_after" != "0" || "$legacy_cold_unknown_work_after" != "0" ]]; then
    echo "Pilot deployment refused: historical cold-260 proof/mail work became active or unrecognized during quarantine. Receipt: $receipt" >&2
    return 1
  fi
  echo "Pilot exact-dispatch-only: quarantined historical cold proof/mail work; receipt: $receipt"
}

active_global_lifecycle_count() {
  printf '%s\n' "$current_crontab" | awk '
    /^[[:space:]]*#/ { next }
    /famtastic:lifecycle-run/ { count++ }
    END { print count + 0 }
  '
}

# These marker names are a crontab ownership contract. A new marker is not
# inserted by this deployer: an operator must deliberately put it immediately
# above the byte-exact command during a separately authorized maintenance step.
lifecycle_cron_marker='# FAMTASTIC_LIFECYCLE_CRON_V1'
drupal_cron_marker='# FAMTASTIC_DRUPAL_CRON_V1'
jobs_run_cron_marker='# FAMTASTIC_JOBS_RUN_CRON_V1'

expected_lifecycle_cron_line() {
  printf '*/5 * * * * cd %q && %q famtastic:lifecycle-run --limit=50 >/dev/null 2>&1' "$production_dir" "$drush"
}

expected_drupal_cron_line() {
  printf '*/5 * * * * cd %q && %q cron >/dev/null 2>&1' "$production_dir" "$drush"
}

expected_jobs_run_cron_line() {
  printf '*/5 * * * * cd %q && %q famtastic:jobs-run --limit=50 >/dev/null 2>&1' "$production_dir" "$drush"
}

report_active_broad_schedulers() {
  load_current_crontab
  active_lifecycle_cron_count="$(active_global_lifecycle_count)"
  active_drupal_cron_count="$(active_global_drupal_cron_count)"
  active_jobs_run_cron_count="$(active_global_jobs_run_cron_count)"
  active_direct_automation_cron_count="$(active_direct_automation_cron_count)"
  active_broad_scheduler_process_count="$(active_broad_scheduler_process_count)"
  echo "Pilot exact-dispatch-only: discovered active broad scheduler entries: lifecycle=$active_lifecycle_cron_count, drush-cron=$active_drupal_cron_count, jobs-run=$active_jobs_run_cron_count, direct-automation=$active_direct_automation_cron_count; matching active processes=$active_broad_scheduler_process_count."
}

assert_no_active_broad_scheduler_cron() {
  report_active_broad_schedulers
  if [[ "$active_lifecycle_cron_count" != "0" || "$active_drupal_cron_count" != "0" || "$active_jobs_run_cron_count" != "0" || "$active_direct_automation_cron_count" != "0" || "$active_broad_scheduler_process_count" != "0" ]]; then
    echo "Pilot exact-dispatch-only deployment refused: an active broad scheduler entry or process remains. Old code cannot honor the durable pilot lock during promotion." >&2
    echo "Suspend only an exact marker-owned pair with the matching explicit confirmation, or stop the scheduler manually and rerun preflight." >&2
    return 1
  fi
  echo "Pilot exact-dispatch-only: no active broad scheduler entry remains."
}

active_count_for_scheduler() {
  case "$1" in
    lifecycle) active_global_lifecycle_count ;;
    drupal) active_global_drupal_cron_count ;;
    jobs) active_global_jobs_run_cron_count ;;
    *) echo "Unknown pilot scheduler kind: $1" >&2; return 2 ;;
  esac
}

marker_for_scheduler() {
  case "$1" in
    lifecycle) printf '%s\n' "$lifecycle_cron_marker" ;;
    drupal) printf '%s\n' "$drupal_cron_marker" ;;
    jobs) printf '%s\n' "$jobs_run_cron_marker" ;;
    *) echo "Unknown pilot scheduler kind: $1" >&2; return 2 ;;
  esac
}

expected_line_for_scheduler() {
  case "$1" in
    lifecycle) expected_lifecycle_cron_line ;;
    drupal) expected_drupal_cron_line ;;
    jobs) expected_jobs_run_cron_line ;;
    *) echo "Unknown pilot scheduler kind: $1" >&2; return 2 ;;
  esac
}

# A marker alone is insufficient. There must be exactly one active command of
# the requested kind, exactly one matching marker, and the marker's next line
# must byte-match the checked-in command. This refuses unmarked, duplicate, and
# changed crontab entries rather than trying to infer ownership.
validate_marked_scheduler_pair() {
  local kind="$1" active_count marker expected marker_count marker_line next_line
  load_current_crontab
  active_count="$(active_count_for_scheduler "$kind")"
  if [[ "$active_count" != "1" ]]; then
    echo "Pilot scheduler suspension refused: expected exactly one active $kind scheduler entry, found $active_count." >&2
    return 1
  fi
  marker="$(marker_for_scheduler "$kind")"
  expected="$(expected_line_for_scheduler "$kind")"
  marker_count="$(printf '%s\n' "$current_crontab" | awk -v marker="$marker" '$0 == marker { count++ } END { print count + 0 }')"
  if [[ "$marker_count" != "1" ]]; then
    echo "Pilot scheduler suspension refused: active $kind scheduler needs exactly one $marker marker." >&2
    return 1
  fi
  marker_line="$(printf '%s\n' "$current_crontab" | awk -v marker="$marker" '$0 == marker { print NR; exit }')"
  next_line="$(printf '%s\n' "$current_crontab" | awk -v target="$((marker_line + 1))" 'NR == target { print; exit }')"
  if [[ "$next_line" != "$expected" ]]; then
    echo "Pilot scheduler suspension refused: $marker is not followed immediately by its byte-exact checked-in $kind command." >&2
    return 1
  fi
}

validate_requested_broad_scheduler_suspensions() {
  local lifecycle_count drupal_count jobs_count direct_count process_count
  report_active_broad_schedulers
  lifecycle_count="$active_lifecycle_cron_count"
  drupal_count="$active_drupal_cron_count"
  jobs_count="$active_jobs_run_cron_count"
  direct_count="$active_direct_automation_cron_count"
  process_count="$active_broad_scheduler_process_count"

  if [[ "$lifecycle_count" != "0" ]]; then
    if [[ "$pilot_suspend_marked_lifecycle_cron" != "1" ]]; then
      echo "Pilot deployment refused: $lifecycle_count active lifecycle scheduler entry exists without its exact suspension declaration." >&2
      return 1
    fi
    validate_marked_scheduler_pair lifecycle
  elif [[ "$pilot_suspend_marked_lifecycle_cron" == "1" ]]; then
    echo "Pilot scheduler suspension refused: lifecycle suspension was requested but no active exact target exists." >&2
    return 1
  fi

  if [[ "$drupal_count" != "0" ]]; then
    if [[ "$pilot_suspend_marked_drupal_cron" != "1" ]]; then
      echo "Pilot deployment refused: $drupal_count active drush cron scheduler entr$( [[ "$drupal_count" == "1" ]] && printf 'y' || printf 'ies' ) exist without an exact marker-owned suspension declaration." >&2
      return 1
    fi
    validate_marked_scheduler_pair drupal
  elif [[ "$pilot_suspend_marked_drupal_cron" == "1" ]]; then
    echo "Pilot scheduler suspension refused: Drupal cron suspension was requested but no active exact target exists." >&2
    return 1
  fi

  if [[ "$jobs_count" != "0" ]]; then
    if [[ "$pilot_suspend_marked_jobs_run_cron" != "1" ]]; then
      echo "Pilot deployment refused: $jobs_count active jobs-run or automation-worker scheduler entr$( [[ "$jobs_count" == "1" ]] && printf 'y' || printf 'ies' ) exist without an exact marker-owned suspension declaration." >&2
      return 1
    fi
    validate_marked_scheduler_pair jobs
  elif [[ "$pilot_suspend_marked_jobs_run_cron" == "1" ]]; then
    echo "Pilot scheduler suspension refused: jobs-run suspension was requested but no active exact target exists." >&2
    return 1
  fi

  if [[ "$direct_count" != "0" ]]; then
    echo "Pilot deployment refused: $direct_count active direct php:eval/php:script/automation-worker scheduler entr$( [[ "$direct_count" == "1" ]] && printf 'y' || printf 'ies' ) exist. They require a manual-empty assertion; automatic marker suspension is intentionally unavailable for arbitrary evaluator code." >&2
    return 1
  fi
  if [[ "$process_count" != "0" ]]; then
    echo "Pilot deployment refused: $process_count matching broad scheduler process(es) are already running. Wait for them to exit or stop them manually; a crontab edit cannot make an in-flight old-code process safe." >&2
    return 1
  fi
}

# Make one atomic, validated crontab edit for every active target. The backup is
# intentionally never restored by this release, whether the release succeeds or
# fails: restoring a stale full crontab could overwrite a later operator change
# and would reopen the shared queues without a fresh owner decision.
suspend_authorized_broad_schedulers() {
  local cron_stage cron_backup_dir lifecycle_expected drupal_expected jobs_expected suspended_kinds='' remove_lifecycle remove_drupal remove_jobs
  validate_requested_broad_scheduler_suspensions
  if [[ "$active_lifecycle_cron_count" == "0" && "$active_drupal_cron_count" == "0" && "$active_jobs_run_cron_count" == "0" && "$active_direct_automation_cron_count" == "0" && "$active_broad_scheduler_process_count" == "0" ]]; then
    assert_no_active_broad_scheduler_cron
    return 0
  fi
  remove_lifecycle="$active_lifecycle_cron_count"
  remove_drupal="$active_drupal_cron_count"
  remove_jobs="$active_jobs_run_cron_count"
  scheduler_timestamp="${scheduler_timestamp:-$(date -u +%Y%m%dT%H%M%SZ)}"
  cron_backup_dir="$deploy_dir/cron-backups"
  scheduler_cron_backup="$cron_backup_dir/famtastic-crontab-before-pilot-suspension-$scheduler_timestamp-$commit_sha.txt"
  mkdir -p "$cron_backup_dir" "$deploy_dir/tmp"
  if [[ -e "$scheduler_cron_backup" ]]; then
    echo "Pilot scheduler suspension refused: crontab backup path already exists: $scheduler_cron_backup" >&2
    return 1
  fi
  (umask 077; printf '%s\n' "$current_crontab" > "$scheduler_cron_backup")
  chmod 0600 "$scheduler_cron_backup"
  test -s "$scheduler_cron_backup" || {
    echo "Pilot scheduler suspension refused: mode-0600 crontab backup was not written." >&2
    return 1
  }
  lifecycle_expected="$(expected_lifecycle_cron_line)"
  drupal_expected="$(expected_drupal_cron_line)"
  jobs_expected="$(expected_jobs_run_cron_line)"
  cron_stage="$deploy_dir/tmp/famtastic-crontab-pilot-suspended-$scheduler_timestamp"
  if ! printf '%s\n' "$current_crontab" | awk \
    -v remove_lifecycle="$remove_lifecycle" \
    -v lifecycle_marker="$lifecycle_cron_marker" \
    -v lifecycle_expected="$lifecycle_expected" \
    -v remove_drupal="$remove_drupal" \
    -v drupal_marker="$drupal_cron_marker" \
    -v drupal_expected="$drupal_expected" \
    -v remove_jobs="$remove_jobs" \
    -v jobs_marker="$jobs_run_cron_marker" \
    -v jobs_expected="$jobs_expected" '
      remove_lifecycle == 1 && $0 == lifecycle_marker {
        if ((getline next_line) <= 0 || next_line != lifecycle_expected) exit 70
        removed_lifecycle++
        next
      }
      remove_drupal == 1 && $0 == drupal_marker {
        if ((getline next_line) <= 0 || next_line != drupal_expected) exit 71
        removed_drupal++
        next
      }
      remove_jobs == 1 && $0 == jobs_marker {
        if ((getline next_line) <= 0 || next_line != jobs_expected) exit 72
        removed_jobs++
        next
      }
      { print }
      END {
        if ((remove_lifecycle == 1 && removed_lifecycle != 1) || (remove_drupal == 1 && removed_drupal != 1) || (remove_jobs == 1 && removed_jobs != 1)) exit 73
      }
    ' > "$cron_stage"; then
    rm -f "$cron_stage"
    echo "Pilot scheduler suspension refused: an exact marker-owned cron pair changed before it could be removed." >&2
    return 1
  fi
  if ! crontab "$cron_stage"; then
    rm -f "$cron_stage"
    echo "Pilot scheduler suspension failed before the new crontab could be installed; backup retained at $scheduler_cron_backup." >&2
    return 1
  fi
  rm -f "$cron_stage"
  assert_no_active_broad_scheduler_cron
  [[ "$remove_lifecycle" == "1" ]] && suspended_kinds='lifecycle'
  if [[ "$remove_drupal" == "1" ]]; then
    suspended_kinds="${suspended_kinds:+$suspended_kinds,}drush-cron"
  fi
  if [[ "$remove_jobs" == "1" ]]; then
    suspended_kinds="${suspended_kinds:+$suspended_kinds,}jobs-run"
  fi
  scheduler_cron_suspended=1
  scheduler_cron_record="suspended:$suspended_kinds"
  echo "Pilot exact-dispatch-only: suspended only $suspended_kinds; backup retained at $scheduler_cron_backup. It remains suspended until an explicit end-pilot restore."
}

prepare_pilot_scheduler_mode() {
  validate_requested_broad_scheduler_suspensions
  if [[ "$mode" == "apply" ]]; then
    suspend_authorized_broad_schedulers
  else
    if [[ "$active_lifecycle_cron_count" == "0" && "$active_drupal_cron_count" == "0" && "$active_jobs_run_cron_count" == "0" && "$active_direct_automation_cron_count" == "0" && "$active_broad_scheduler_process_count" == "0" ]]; then
      echo "Pilot exact-dispatch-only preflight: no active broad scheduler requires suspension."
    else
      echo "Pilot exact-dispatch-only preflight: only the validated marker-owned broad scheduler pair(s) would be suspended during an authorized apply; no unmarked scheduler is accepted."
    fi
  fi
}

for command_name in git php composer tar rsync crontab; do
  command -v "$command_name" >/dev/null || {
    echo "Remote prerequisite missing: $command_name" >&2
    exit 1
  }
done
if [[ "$pilot_exact_dispatch_only" == "1" ]]; then
  command -v ps >/dev/null || {
    echo "Remote prerequisite missing for exact-ID pilot: ps." >&2
    exit 1
  }
fi
test -d "$production_dir" || {
  echo "Remote Drupal root missing: $production_dir" >&2
  exit 1
}
test -x "$drush" || {
  echo "Remote Drush missing: $drush" >&2
  exit 1
}
test -d "$production_module" || {
  echo "Production custom module missing: $production_module" >&2
  exit 1
}
test -d "$production_admin_theme" || {
  echo "Production custom admin theme missing: $production_admin_theme" >&2
  exit 1
}
test -f "$production_services" || {
  echo "Production Drupal services file missing: $production_services" >&2
  exit 1
}
remote_sha="$(git ls-remote "$repository_url" refs/heads/main | awk '{print $1}')"
test "$remote_sha" = "$commit_sha" || {
  echo "Remote cannot resolve requested commit as current main." >&2
  exit 1
}

cd "$production_dir"
"$drush" status --fields=bootstrap,db-status,drupal-version --format=list
pilot_dispatch_lock_before="$(read_pilot_dispatch_lock)"
if [[ "$pilot_exact_dispatch_only" == "1" ]]; then
  assert_canonical_pilot_public_bases
  preflight_legacy_cold_queue_gate
  # Both broad routes must be absent before old production code can be touched:
  # the current module cannot honor the new durable lock until after promotion.
  # Unmarked or altered cron lines fail closed rather than being guessed at.
  prepare_pilot_scheduler_mode
  if [[ "$mode" == "apply" ]]; then
    # Persist before promotion. The currently deployed module may not know
    # this setting yet, but the new module reads it immediately after the
    # code/cache promotion. A failed pilot deployment leaves it fail-closed.
    set_pilot_dispatch_lock 1
  else
    echo "Pilot exact-dispatch-only preflight: durable Drupal lock is currently $pilot_dispatch_lock_before; apply will set and verify it as 1."
  fi
else
  echo "Ordinary deployment preflight: durable pilot lock is currently $pilot_dispatch_lock_before; an authorized apply will set and verify it as 0 before enabling lifecycle scheduling."
fi
# A deployment must never land on (or silently leave) a maintenance-mode site.
# Maintenance mode lives in STATE (not config) - Drupal core key.
maint="$("$drush" sget system.maintenance_mode --format=string 2>/dev/null || echo 0)"
if [ "$maint" = "1" ] || [ "$maint" = "true" ]; then
  if [ "$mode" = "apply" ]; then
    "$drush" sset system.maintenance_mode 0 >/dev/null
    echo "Maintenance mode was ON - disabled before deployment."
  else
    echo "WARNING: site is in MAINTENANCE MODE (preflight only - not changed)."
  fi
fi
printf 'Remote PHP: %s\n' "$(php -r 'echo PHP_VERSION;')"
printf 'Free space: %s\n' "$(df -h "$HOME" | awk 'NR == 2 {print $4}')"
printf 'Current backend release: '
if test -f "$production_dir/.backend-release"; then
  tr '\n' ' ' < "$production_dir/.backend-release"
  echo
else
  echo "unrecorded"
fi

if [[ "$mode" == "preflight" ]]; then
  echo "Preflight passed. No production files changed."
  echo "Apply plan: exact Git SHA -> locked Composer validation -> database/code/dependency backups -> dependency and code promotion -> updatedb -> cache rebuild -> release record."
  exit 0
fi

mkdir -p "$deploy_dir/releases" "$deploy_dir/tmp" "$HOME/backups"
if [[ ! -d "$mirror_dir" ]]; then
  git clone --mirror "$repository_url" "$mirror_dir"
else
  git --git-dir="$mirror_dir" remote set-url origin "$repository_url"
  git --git-dir="$mirror_dir" fetch --prune origin
fi
git --git-dir="$mirror_dir" cat-file -e "$commit_sha^{commit}"
resolved_main="$(git --git-dir="$mirror_dir" rev-parse refs/heads/main)"
[[ "$resolved_main" == "$commit_sha" ]] || {
  echo "Refusing deployment: requested commit is no longer current main." >&2
  exit 1
}
if [[ ! -e "$source_dir/.git" ]]; then
  rm -rf "$release_dir"
  mkdir -p "$release_dir"
  git --git-dir="$mirror_dir" worktree add --detach "$source_dir" "$commit_sha"
fi
test -f "$backend_dir/composer.lock"
test -f "$source_module/famtastic_pipeline.info.yml"
test -f "$source_admin_theme/famtastic_admin.info.yml"
test -f "$source_services"
test -f "$source_product_config"
test -f "$source_deal_config"
test -f "$source_demand_manifest"
test -f "$source_demand_fields"
test -f "$source_demand_seed"
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$backend_dir" validate \
  --no-check-publish --no-interaction
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$backend_dir" check-platform-reqs \
  --lock --no-dev
find "$source_module" -type f -name '*.php' -print0 |
  xargs -0 -n1 php -l >/dev/null

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
module_backup="$HOME/backups/famtastic-pipeline-$timestamp-$commit_sha.tgz"
admin_theme_backup="$HOME/backups/famtastic-admin-$timestamp-$commit_sha.tgz"
customer_theme_backup="$HOME/backups/famtastic-customer-$timestamp-$commit_sha.tgz"
services_backup="$HOME/backups/famtastic-services-$timestamp-$commit_sha.yml"
commercial_config_backup="$HOME/backups/famtastic-commercial-config-$timestamp-$commit_sha.tgz"
commercial_config_backup_stage="$deploy_dir/tmp/commercial-config-$timestamp"
database_backup="$HOME/backups/famtastic-database-$timestamp-$commit_sha.sql.gz"
database_dump_target="${database_backup%.gz}"
dependency_backup="$HOME/backups/famtastic-dependencies-$timestamp-$commit_sha.tgz"
stage_module="$production_dir/web/modules/custom/.famtastic_pipeline-$commit_sha"
stage_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-$commit_sha"
stage_customer_theme="$production_dir/web/themes/custom/.famtastic_customer-$commit_sha"
settings_dir="$production_dir/web/sites/default"
settings_mode="$(stat -c '%a' "$settings_dir")"
stage_services="$production_dir/web/sites/default/.services-$commit_sha.yml"
previous_module="$production_dir/web/modules/custom/.famtastic_pipeline-previous-$timestamp"
previous_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-previous-$timestamp"
previous_customer_theme="$production_dir/web/themes/custom/.famtastic_customer-previous-$timestamp"
previous_services="$production_dir/web/sites/default/.services-previous-$timestamp.yml"

tar -C "$(dirname "$production_module")" -czf "$module_backup" "$(basename "$production_module")"
tar -C "$(dirname "$production_admin_theme")" -czf "$admin_theme_backup" "$(basename "$production_admin_theme")"
tar -C "$(dirname "$production_customer_theme")" -czf "$customer_theme_backup" "$(basename "$production_customer_theme")" 2>/dev/null || true
tar -C "$production_dir" -czf "$dependency_backup" vendor web/core web/modules/contrib composer.json composer.lock
cp -p "$production_services" "$services_backup"
mkdir -p "$production_config_dir"
rm -rf "$commercial_config_backup_stage"
mkdir -p "$commercial_config_backup_stage"
test ! -f "$production_config_dir/famtastic-products.json" || cp -p "$production_config_dir/famtastic-products.json" "$commercial_config_backup_stage/"
test ! -f "$production_config_dir/famtastic-deal-terms.json" || cp -p "$production_config_dir/famtastic-deal-terms.json" "$commercial_config_backup_stage/"
tar -C "$commercial_config_backup_stage" -czf "$commercial_config_backup" .
rm -rf "$commercial_config_backup_stage"
cd "$production_dir"
"$drush" sql:dump --gzip --result-file="$database_dump_target"
test -s "$database_backup" || {
  echo "Database backup was not created at the recorded rollback path: $database_backup" >&2
  exit 1
}

rm -rf "$stage_module"
mkdir -p "$stage_module"
rsync -a "$source_module/" "$stage_module/"
rm -rf "$stage_admin_theme"
mkdir -p "$stage_admin_theme"
rsync -a "$source_admin_theme/" "$stage_admin_theme/"
rsync -a "$source_customer_theme/" "$stage_customer_theme/"
chmod u+w "$settings_dir"
trap 'chmod "$settings_mode" "$settings_dir" 2>/dev/null || true' ERR
install -m 0644 "$source_services" "$stage_services"
if [[ "$pilot_exact_dispatch_only" == "1" ]]; then
  # The scheduler may have been suspended several minutes ago while source,
  # Composer, and rollback backups were prepared. Snapshot it immediately
  # before the actual old-code replacement, not just at preflight.
  assert_no_active_broad_scheduler_cron
fi
mv "$production_module" "$previous_module"
mv "$stage_module" "$production_module"
mv "$production_admin_theme" "$previous_admin_theme" 2>/dev/null || true
mv "$stage_admin_theme" "$production_admin_theme"
mv "$production_customer_theme" "$previous_customer_theme" 2>/dev/null || true
mv "$stage_customer_theme" "$production_customer_theme"
mv "$production_services" "$previous_services"
mv "$stage_services" "$production_services"
install -m 0644 "$source_product_config" "$production_config_dir/famtastic-products.json"
install -m 0644 "$source_deal_config" "$production_config_dir/famtastic-deal-terms.json"
chmod "$settings_mode" "$settings_dir"
trap - ERR

rollback_code() {
  # Failed deploys still leave releases + backups behind; prune to the same
  # retention as success paths or repeated failures re-exhaust quota.
  (
    trap - ERR
    set +e
    cd "$deploy_dir/releases" 2>/dev/null || exit 0
    keep=( "$commit_sha" )
    previous=$(ls -td */ 2>/dev/null | grep -v "^$commit_sha/" | head -1 | tr -d '/')
    [ -n "$previous" ] && keep+=( "$previous" )
    for d in */; do
      sha="${d%/}"
      [[ " ${keep[*]} " == *" $sha "* ]] || rm -rf "$sha"
    done
    cd "$HOME/backups"
    ls -t famtastic-database-*.sql.gz 2>/dev/null | tail -n +3 | xargs -r rm -f 2>/dev/null
    for btype in dependencies module admin_theme services commercial_config; do
      ls -t famtastic-${btype}-*.tgz 2>/dev/null | tail -n +2 | xargs -r rm -f 2>/dev/null
    done
  ) 2>/dev/null || true
  if [[ -d "$previous_module" ]]; then
    chmod u+w "$settings_dir" 2>/dev/null || true
    failed_module="$production_dir/web/modules/custom/.famtastic_pipeline-failed-$timestamp"
    mv "$production_module" "$failed_module" 2>/dev/null || true
    mv "$previous_module" "$production_module"
    if [[ -d "$previous_admin_theme" ]]; then
      failed_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-failed-$timestamp"
      mv "$production_admin_theme" "$failed_admin_theme" 2>/dev/null || true
      mv "$previous_admin_theme" "$production_admin_theme"
    fi
    if [[ -d "$previous_customer_theme" ]]; then
      # Keep the customer-facing theme paired with the restored backend code.
      # A failed promotion must not leave its new portal/proof UI live.
      failed_customer_theme="$production_dir/web/themes/custom/.famtastic_customer-failed-$timestamp"
      mv "$production_customer_theme" "$failed_customer_theme" 2>/dev/null || true
      mv "$previous_customer_theme" "$production_customer_theme"
    fi
    tar -C "$production_dir" -xzf "$dependency_backup" vendor web/core web/modules/contrib composer.json composer.lock 2>/dev/null || true
    if [[ -f "$previous_services" ]]; then
      mv "$production_services" "$production_services.failed-$timestamp" 2>/dev/null || true
      mv "$previous_services" "$production_services"
    fi
    rm -f "$production_config_dir/famtastic-products.json" "$production_config_dir/famtastic-deal-terms.json"
    tar -C "$production_config_dir" -xzf "$commercial_config_backup" 2>/dev/null || true
    chmod "$settings_mode" "$settings_dir" 2>/dev/null || true
    "$drush" cr >/dev/null 2>&1 || true
  fi
  echo "Code was restored after a failed deployment." >&2
  echo "Database backup (manual restore if an update partially ran): $database_backup" >&2
}
trap rollback_code ERR

install -m 0644 "$backend_dir/composer.json" "$production_dir/composer.json"
install -m 0644 "$backend_dir/composer.lock" "$production_dir/composer.lock"
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$production_dir" install \
  --no-dev --no-interaction --prefer-dist --optimize-autoloader
echo "Backend dependencies promoted."

# Retention: releases and per-deploy backups accumulate (~230MB per release,
# ~50MB+ per backup set). Keep the current release plus one rollback, and the
# newest backup of each type (two newest database dumps). Failure-tolerant:
# retention must never abort a deployment.
(
  trap - ERR
  set +e
  cd "$deploy_dir/releases" || exit 0
  keep=( "$commit_sha" )
  previous=$(ls -td */ 2>/dev/null | grep -v "^$commit_sha/" | head -1 | tr -d '/')
  [ -n "$previous" ] && keep+=( "$previous" )
  for d in */; do
    sha="${d%/}"
    [[ " ${keep[*]} " == *" $sha "* ]] || rm -rf "$sha"
  done
  cd "$HOME/backups"
  ls -t famtastic-database-*.sql.gz 2>/dev/null | tail -n +3 | xargs -r rm -f 2>/dev/null
  for btype in dependencies module admin_theme services commercial_config; do
    ls -t famtastic-${btype}-*.tgz 2>/dev/null | tail -n +2 | xargs -r rm -f 2>/dev/null
  done
  echo "Retention applied: releases kept=$(ls "$deploy_dir/releases" | wc -l)."
)
# Drush exits 255 on this cPanel host even when the update run succeeds. Disable
# the rollback trap only while capturing that unreliable status, then restore it
# before the authoritative pending-update check and every remaining apply step.
trap - ERR
set +e
"$drush" updatedb -y --strict=0
updatedb_exit=$?
set -e
trap rollback_code ERR
if [[ "$updatedb_exit" -ne 0 ]]; then
  echo "Database update command returned $updatedb_exit after dependency cold start; verifying authoritative pending-update status."
fi
pending_updates="$($drush updatedb:status --format=json)"
if [[ -n "$pending_updates" && "$pending_updates" != "[]" && "$pending_updates" != "{}" ]]; then
  echo "Database updates remain pending after apply: $pending_updates" >&2
  exit 1
fi
echo "Database updates verified."
"$drush" pm:enable commerce_stripe metatag redirect simple_sitemap key ai ai_dashboard ai_api_explorer ai_agents ai_automators ai_logging ai_provider_openai -y
echo "Required Drupal modules enabled."
"$drush" php:script "$source_demand_fields"
echo "Demand fields verified."
"$drush" php:script "$source_demand_seed"
echo "Demand content verified."
"$drush" php:script "$source_package_normalizer"
echo "Package ladder verified."
# Catalog drift guard: Commerce variations must always match the advertised
# catalog (BRUTAL-REVIEW-2026-08-24 critical #1 - $499 tier was unsellable).
"$drush" php:script "$backend_dir/scripts/assert-catalog-parity.php" "$backend_dir/config/famtastic-products.json"
# Proof artifacts live under the web docroot but must never be directly
# fetchable - the auth-gated API routes are the only reader
# (BRUTAL-REVIEW-2026-08-24 critical #1).
if [ -d "$production_dir/web/proofs" ]; then
  install -m 0644 "$backend_dir/config/proofs-htaccess" "$production_dir/web/proofs/.htaccess"
  echo "Proofs directory direct access denied."
fi
"$drush" cr
# A second process-level rebuild is required on this host after first-time
# module discovery; otherwise the sitemap writer can see stale router state.
"$drush" cr
"$drush" eval '\Drupal::service("router.route_provider")->getRouteByName("simple_sitemap.sitemap_xsl"); print "Sitemap route verified.\n";'
"$drush" simple-sitemap:generate
echo "Sitemap generation verified."
"$drush" eval '
  foreach (["famtastic_prospect", "famtastic_order", "famtastic_intake", "famtastic_project", "proof_campaign", "proof_variant"] as $entity_type_id) {
    \Drupal::entityTypeManager()->getDefinition($entity_type_id);
  }
  print "Pipeline entity definitions verified.\n";
'
"$drush" eval '
  foreach (["key", "ai", "ai_dashboard", "ai_api_explorer", "ai_agents", "ai_automators", "ai_logging", "ai_provider_openai"] as $module) {
    if (!\Drupal::moduleHandler()->moduleExists($module)) {
      throw new \RuntimeException("Required AI foundation module is not enabled: " . $module);
    }
  }
  print "Drupal AI foundation verified.\n";
'

if [[ "$pilot_exact_dispatch_only" == "1" ]]; then
  # Recheck immediately before release recording: another process must not
  # activate either global worker halfway through an exact-ID pilot deployment.
  # The durable config check becomes authoritative for separate cPanel drush
  # cron processes only after this code/cache promotion is complete.
  assert_pilot_dispatch_lock 1
  assert_canonical_pilot_public_bases
  quarantine_legacy_cold_queue_after_promotion
  assert_no_active_broad_scheduler_cron
  if [[ "$scheduler_cron_suspended" == "1" ]]; then
    echo "Exact preview pilot: durable general-dispatch lock verified; authorized broad scheduler pair(s) remain suspended pending an explicit end-pilot restore."
  else
    echo "Exact preview pilot: durable general-dispatch lock verified; no broad scheduler is active."
  fi
else
  # A normal release intentionally clears the durable pilot lock only after
  # the new code, database updates, and cache rebuild have all succeeded.
  # That makes this transition explicit, auditable, and reversible by the
  # next owner-approved pilot deployment.
  set_pilot_dispatch_lock 0
  # Ordinary deployments retain the independent lifecycle runner. Mailbox
  # ingestion may fail without suppressing notification dispatch, proof jobs,
  # protection, or heartbeats.
  cron_marker='# FAMTASTIC_LIFECYCLE_CRON_V1'
  cron_stage="$deploy_dir/tmp/famtastic-crontab-$timestamp"
  crontab -l > "$cron_stage" 2>/dev/null || true
  if ! grep -Fq "$cron_marker" "$cron_stage"; then
    {
      printf '\n%s\n' "$cron_marker"
      printf '*/5 * * * * cd %q && %q famtastic:lifecycle-run --limit=50 >/dev/null 2>&1\n' "$production_dir" "$drush"
    } >> "$cron_stage"
    crontab "$cron_stage"
  fi
  rm -f "$cron_stage"
  crontab -l | grep -F "$cron_marker" >/dev/null
  lifecycle_cron_record='FAMTASTIC_LIFECYCLE_CRON_V1'
  echo "Independent lifecycle scheduler verified."
fi

{
  printf 'commit=%s\n' "$commit_sha"
  printf 'deployed_at=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'php=%s\n' "$(php -r 'echo PHP_VERSION;')"
  printf 'module_backup=%s\n' "$module_backup"
  printf 'admin_theme_backup=%s\n' "$admin_theme_backup"
  printf 'customer_theme_backup=%s\n' "$customer_theme_backup"
  printf 'services_backup=%s\n' "$services_backup"
  printf 'database_backup=%s\n' "$database_backup"
  printf 'dependency_backup=%s\n' "$dependency_backup"
  printf 'commercial_config_backup=%s\n' "$commercial_config_backup"
  printf 'demand_manifest_version=2\n'
  printf 'pilot_exact_dispatch_lock=%s\n' "$pilot_dispatch_lock_record"
  printf 'pilot_exact_dispatch_lock_before=%s\n' "$pilot_dispatch_lock_before"
  printf 'pilot_canonical_public_bases=%s\n' "$pilot_public_bases_record"
  printf 'active_broad_drupal_cron=%s\n' "$active_drupal_cron_count"
  printf 'active_broad_lifecycle_cron=%s\n' "$active_lifecycle_cron_count"
  printf 'active_broad_jobs_run_cron=%s\n' "$active_jobs_run_cron_count"
  printf 'active_direct_automation_cron=%s\n' "$active_direct_automation_cron_count"
  printf 'active_broad_scheduler_processes=%s\n' "$active_broad_scheduler_process_count"
  printf 'legacy_cold_proof_queue_before=%s\n' "$legacy_cold_queue_before"
  printf 'legacy_cold_proof_queue_after=%s\n' "$legacy_cold_queue_after"
  printf 'legacy_cold_proof_active_before=%s\n' "$legacy_cold_active_before"
  printf 'legacy_cold_proof_active_after=%s\n' "$legacy_cold_active_after"
  printf 'legacy_cold_claimable_jobs_before=%s\n' "$legacy_cold_claimable_jobs_before"
  printf 'legacy_cold_claimable_jobs_after=%s\n' "$legacy_cold_claimable_jobs_after"
  printf 'legacy_cold_claimable_messages_before=%s\n' "$legacy_cold_claimable_messages_before"
  printf 'legacy_cold_claimable_messages_after=%s\n' "$legacy_cold_claimable_messages_after"
  printf 'legacy_cold_active_messages_before=%s\n' "$legacy_cold_active_messages_before"
  printf 'legacy_cold_active_messages_after=%s\n' "$legacy_cold_active_messages_after"
  printf 'legacy_cold_unknown_work_before=%s\n' "$legacy_cold_unknown_work_before"
  printf 'legacy_cold_unknown_work_after=%s\n' "$legacy_cold_unknown_work_after"
  printf 'legacy_cold_proof_quarantine_receipt=%s\n' "$legacy_cold_quarantine_receipt"
  printf 'broad_scheduler_cron=%s\n' "$scheduler_cron_record"
  printf 'broad_scheduler_cron_backup=%s\n' "${scheduler_cron_backup:-none}"
} > "$production_dir/.backend-release"

rm -rf "$previous_module"
rm -rf "$previous_admin_theme"
rm -rf "$previous_customer_theme"
chmod u+w "$settings_dir"
rm -f "$previous_services"
chmod "$settings_mode" "$settings_dir"
trap - ERR
echo "Backend deployment complete."
cat "$production_dir/.backend-release"
REMOTE
