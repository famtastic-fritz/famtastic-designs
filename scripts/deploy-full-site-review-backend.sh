#!/usr/bin/env bash
# Invoked through deploy-backend-godaddy.sh with FULL_SITE_REVIEW_ONLY=1.
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"
apply=false
case "${1:-}" in '') ;; --apply) apply=true ;; *) exit 2 ;; esac
[[ -z "$(git status --porcelain)" ]] || { echo 'Clean source required.' >&2; exit 1; }
revision="$(git rev-parse HEAD)"
base="$(git rev-parse HEAD^)"
repository='https://github.com/famtastic-fritz/famtastic-designs.git'
[[ "$revision" == "$(git ls-remote "$repository" refs/heads/main | awk '{print $1}')" ]] || { echo 'Current main required.' >&2; exit 1; }
prefix='backend/web/modules/custom/famtastic_pipeline'
owned=(
 src/Service/FullSiteReviewPackage.php
 src/Service/FullSiteReviewRenderer.php
 src/Service/FullSiteReviewService.php
 src/Controller/FullSiteReviewController.php
 src/Drush/Commands/FullSiteReviewCommands.php
 src/Service/CustomerPortalService.php
 famtastic_pipeline.services.yml
 famtastic_pipeline.routing.yml
)
# Require this release's entire backend runtime delta to fit the exact allowlist.
while IFS= read -r changed; do
 [[ -z "$changed" || "$changed" == "$prefix/tests/"* ]] && continue
 found=false
 for file in "${owned[@]}"; do [[ "$changed" != "$prefix/$file" ]] || found=true; done
 [[ "$found" == true ]] || { echo "Out-of-scope backend delta: $changed" >&2; exit 1; }
done < <(git diff --name-only "$base" "$revision" -- backend)
spec=''
for file in "${owned[@]}"; do
 expected='absent'
 if git cat-file -e "$base:$prefix/$file" 2>/dev/null; then expected="$(git show "$base:$prefix/$file" | shasum -a 256 | awk '{print $1}')"; fi
 current="$(shasum -a 256 "$prefix/$file" | awk '{print $1}')"
 spec+="$file $expected $current"$'\n'
done
spec_encoded="$(printf '%s' "$spec" | base64 | tr -d '\n')"
ssh -T "${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}" bash -s -- "$revision" "$base" "$apply" "$spec_encoded" <<'REMOTE'
set -euo pipefail
revision="$1"; base="$2"; apply="$3"; spec="$(printf '%s' "$4" | base64 --decode)"
production="$HOME/public_html"
module="$production/web/modules/custom/famtastic_pipeline"
deploy="$HOME/deploy/famtastic-designs"
release="$deploy/releases/$revision"
source="$release/source"
php='/usr/local/bin/php'
test -x "$php"
"$php" -r 'exit(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 80400 ? 0 : 1);'
cron_before="$(crontab -l)"
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 if [[ "$expected" == absent ]]; then
   [[ ! -e "$module/$file" ]] || { echo "New target already exists: $file" >&2; exit 1; }
 else
   [[ -f "$module/$file" && ! -L "$module/$file" ]]
   [[ "$(sha256sum "$module/$file" | awk '{print $1}')" == "$expected" ]] || { echo "Baseline mismatch: $file" >&2; exit 1; }
 fi
done <<< "$spec"
if [[ "$apply" != true ]]; then
 printf 'Read-only preflight passed: revision=%s base=%s scope=eight-runtime-files-and-cache\n' "$revision" "$base"
 exit 0
fi
lock="$deploy/.full-site-review-release-lock"
mkdir "$lock" || { echo 'Another scoped release holds the lock.' >&2; exit 1; }
trap 'rmdir "$lock"' EXIT
mirror="$deploy/repository.git"
test -d "$mirror"
git --git-dir="$mirror" fetch origin
[[ "$(git --git-dir="$mirror" rev-parse refs/heads/main)" == "$revision" ]]
mkdir -p "$release"
if [[ ! -e "$source/.git" ]]; then
 git --git-dir="$mirror" worktree add --detach --no-checkout "$source" "$revision"
 git -C "$source" sparse-checkout set backend/web/modules/custom/famtastic_pipeline scripts frontend
 git -C "$source" read-tree -mu HEAD
fi
[[ "$(git -C "$source" rev-parse HEAD)" == "$revision" ]]
new="$source/backend/web/modules/custom/famtastic_pipeline"
backup="$release/full-site-review-backup"
test ! -e "$backup"
mkdir -m 0700 "$backup"
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 [[ "$(sha256sum "$new/$file" | awk '{print $1}')" == "$current" ]]
 [[ "$file" != *.php ]] || "$php" -l "$new/$file"
 if [[ "$expected" != absent ]]; then mkdir -p "$backup/$(dirname "$file")"; cp -p "$module/$file" "$backup/$file"; fi
done <<< "$spec"
rollback() {
 code="${1:-1}"
 trap - ERR INT TERM HUP
 restored=true
 # Restore existing callers before removing their new class dependencies.
 while read -r file expected current; do
   [[ -n "$file" && "$expected" != absent ]] || continue
   cp -p "$backup/$file" "$module/$file" || restored=false
 done <<< "$spec"
 while read -r file expected current; do
   [[ -n "$file" ]] || continue
   if [[ "$expected" == absent ]]; then
     rm -f -- "$module/$file" || restored=false
     [[ ! -e "$module/$file" ]] || restored=false
   else
     [[ "$(sha256sum "$module/$file" | awk '{print $1}')" == "$expected" ]] || restored=false
   fi
 done <<< "$spec"
 cd "$production"
 cache_restored=true
 "$php" vendor/bin/drush.php cache:rebuild || cache_restored=false
 printf 'Scoped release failed. source_restored=%s cache_restored=%s backup=%s\n' "$restored" "$cache_restored" "$backup" >&2
 exit "$code"
}
# Recheck immediately before the first promoted byte. Other release lanes may
# have changed production while this private source/backup was being prepared.
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 if [[ "$expected" == absent ]]; then [[ ! -e "$module/$file" ]];
 else [[ "$(sha256sum "$module/$file" | awk '{print $1}')" == "$expected" ]]; fi
done <<< "$spec"
trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM
trap 'rollback 129' HUP
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 install -m 0644 "$new/$file" "$module/$file"
done <<< "$spec"
cd "$production"
"$php" vendor/bin/drush.php cache:rebuild
"$php" vendor/bin/drush.php php:eval '\Drupal::service("famtastic_pipeline.full_site_review"); print "full_site_review_service=available\n";'
"$php" vendor/bin/drush.php list --filter=full-site-review
[[ "$(crontab -l)" == "$cron_before" ]]
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 [[ "$(sha256sum "$module/$file" | awk '{print $1}')" == "$current" ]]
done <<< "$spec"
{
 printf 'commit=%s\nbase=%s\ndeployed_at=%s\nscope=full-site-review-code-and-cache-only\nbackup=%s\n' "$revision" "$base" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$backup"
 printf 'scheduler=unchanged\nmail=not-invoked\ncommerce=unchanged\n'
} > "$release/full-site-review-release.next"
mv "$release/full-site-review-release.next" "$production/.full-site-review-release"
trap - ERR INT TERM HUP
cat "$production/.full-site-review-release"
REMOTE
