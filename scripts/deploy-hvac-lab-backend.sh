#!/usr/bin/env bash
# Scoped native Lab lane reached only through deploy-backend-godaddy.sh.
# No schema/catalog/cron/outbox/recipient/send/AI/provider state mutation.
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"
apply=false
case "${1:-}" in '') ;; --apply) apply=true ;; *) exit 2 ;; esac
[[ -z "$(git status --porcelain)" ]] || { echo 'Clean source required.' >&2; exit 1; }
revision="$(git rev-parse HEAD)"
base="${FAMTASTIC_HVAC_BASE_REVISION:-91216e942ae9623197bcb2450f06095e02269fc2}"
[[ "$base" =~ ^[a-f0-9]{40}$ ]] || exit 1
git merge-base --is-ancestor "$base" "$revision"
[[ "$revision" == "$(git ls-remote https://github.com/famtastic-fritz/famtastic-designs.git refs/heads/main | awk '{print $1}')" ]] || { echo 'Current main required.' >&2; exit 1; }
prefix=backend/web/modules/custom/famtastic_pipeline
owned=(src/Service/AcquisitionHvacTemplate.php src/Service/AcquisitionSampleGuard.php src/Service/AcquisitionSampleService.php src/Controller/AcquisitionSampleController.php src/Controller/AcquisitionIndustryPreviewController.php)
while IFS= read -r changed; do
 [[ -z "$changed" || "$changed" == "$prefix/tests/"* ]] && continue
 found=false
 for file in "${owned[@]}"; do [[ "$changed" != "$prefix/$file" ]] || found=true; done
 [[ "$found" == true ]] || { echo "Out-of-scope backend delta: $changed" >&2; exit 1; }
done < <(git diff --name-only "$base" "$revision" -- backend)
spec=''
for file in "${owned[@]}"; do
 expected=absent
 if git cat-file -e "$base:$prefix/$file" 2>/dev/null; then expected="$(git show "$base:$prefix/$file" | shasum -a 256 | awk '{print $1}')"; fi
 current="$(shasum -a 256 "$prefix/$file" | awk '{print $1}')"
 spec+="$file $expected $current"$'\n'
done
encoded="$(printf '%s' "$spec" | base64 | tr -d '\n')"
manifest_hash="$(shasum -a 256 marketing/campaigns/acquisition-199/industry-previews/hvac-coastal-current/manifest.json | awk '{print $1}')"
ssh -T "${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}" bash -s -- "$revision" "$base" "$apply" "$encoded" "$manifest_hash" <<'REMOTE'
set -euo pipefail
revision="$1"; base="$2"; apply="$3"; spec="$(printf '%s' "$4" | base64 --decode)"; manifest_hash="$5"
production="$HOME/public_html"; module="$production/web/modules/custom/famtastic_pipeline"; deploy="$HOME/deploy/famtastic-designs"; release="$deploy/releases/$revision"; source="$release/hvac-source"
php=/usr/local/bin/php
cron_before="$(crontab -l | sha256sum | awk '{print $1}')"
cd "$production"
state_before="$($php vendor/bin/drush.php php:eval '$keys=["famtastic_pipeline.settings","smtp.settings"]; $data=[];foreach($keys as $key)$data[$key]=\Drupal::config($key)->getRawData();print hash("sha256",serialize($data));')"
private="$($php vendor/bin/drush.php php:eval 'print realpath(\Drupal\Core\Site\Settings::get("file_private_path"));')"
[[ "$private" == "$HOME/private_files" ]]
settings="$production/web/sites/default/settings.local.php"
[[ -f "$settings" && ! -L "$settings" ]]
settings_hash="$(sha256sum "$settings" | awk '{print $1}')"
"$php" -r 'foreach(token_get_all(file_get_contents($argv[1])) as $t) if(is_array($t)&&$t[0]===T_CLOSE_TAG) throw new RuntimeException("Settings must remain in PHP mode");' "$settings"
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 if [[ "$expected" == absent ]]; then [[ ! -e "$module/$file" ]] || { echo "New target exists: $file" >&2; exit 1; }
 else [[ "$(sha256sum "$module/$file" | awk '{print $1}')" == "$expected" ]] || { echo "Baseline mismatch: $file" >&2; exit 1; }; fi
done <<< "$spec"
if [[ "$apply" != true ]]; then printf 'Scoped HVAC preflight passed revision=%s base=%s runtime=five-PHP-files bundle=private settings=HVAC-only no-state-mutation\n' "$revision" "$base";exit 0;fi
lock="$deploy/.hvac-lab-release-lock";mkdir "$lock";trap 'rmdir "$lock"' EXIT
mirror="$deploy/repository.git";git --git-dir="$mirror" fetch origin
[[ "$(git --git-dir="$mirror" rev-parse refs/heads/main)" == "$revision" ]]
mkdir -p "$release"
if [[ ! -e "$source/.git" ]];then git --git-dir="$mirror" worktree add --detach --no-checkout "$source" "$revision";fi
[[ "$(git -C "$source" rev-parse HEAD)" == "$revision" ]]
[[ -z "$(git -C "$source" status --porcelain)" ]]
git -C "$source" sparse-checkout set backend/web/modules/custom/famtastic_pipeline marketing/campaigns/acquisition-199/industry-previews/hvac-coastal-current scripts
 git -C "$source" read-tree -mu HEAD
new="$source/backend/web/modules/custom/famtastic_pipeline"; assets="$source/marketing/campaigns/acquisition-199/industry-previews/hvac-coastal-current"
[[ "$(sha256sum "$assets/manifest.json" | awk '{print $1}')" == "$manifest_hash" ]]
backup="$release/hvac-backup-$(date -u +%Y%m%dT%H%M%SZ)-$$";mkdir -m0700 "$backup";cp -p "$settings" "$backup/settings.local.php"
while read -r file expected current; do
 [[ -n "$file" ]] || continue
 [[ "$(sha256sum "$new/$file" | awk '{print $1}')" == "$current" ]];"$php" -l "$new/$file"
 if [[ "$expected" != absent ]];then mkdir -p "$backup/$(dirname "$file")";cp -p "$module/$file" "$backup/$file";fi
done <<< "$spec"
bundle="$private/acquisition-199/hvac/$revision"
mkdir -m0700 -p "$(dirname "$bundle")"
if [[ ! -e "$bundle" ]];then
 stage="$(mktemp -d "$private/acquisition-199/hvac/.stage.XXXXXX")"
 "$php" -r '$src=$argv[1];$dst=$argv[2];$m=json_decode(file_get_contents($src."/manifest.json"),true,32,JSON_THROW_ON_ERROR);$files=array_merge(["manifest.json"],array_keys($m["source_hashes"]+$m["asset_hashes"]),$m["fonts"]["licenses"]);foreach($files as $name){if(!preg_match("/^[a-z0-9][a-z0-9_.-]*$/D",$name)||is_link($src."/".$name)||!copy($src."/".$name,$dst."/".$name))throw new RuntimeException("Bundle copy invalid");}' "$assets" "$stage"
 mv "$stage" "$bundle"
fi
[[ ! -L "$bundle" ]]
[[ "$(sha256sum "$bundle/manifest.json" | awk '{print $1}')" == "$manifest_hash" ]]
"$php" -r '$p=$argv[1];$m=json_decode(file_get_contents($p."/manifest.json"),true,32,JSON_THROW_ON_ERROR);$allowed=array_merge(["manifest.json","hvac-settings.php"],array_keys($m["source_hashes"]+$m["asset_hashes"]),$m["fonts"]["licenses"]);foreach(scandir($p) as $f)if($f!=="."&&$f!==".."&&!in_array($f,$allowed,true))throw new RuntimeException("Unlisted bundle file");' "$bundle"
"$php" -r '$p=$argv[1];$m=json_decode(file_get_contents($p."/manifest.json"),true,32,JSON_THROW_ON_ERROR);foreach($m["source_hashes"]+$m["asset_hashes"] as $file=>$hash){if(!hash_equals($hash,hash_file("sha256",$p."/".$file)))throw new RuntimeException("Asset hash mismatch");}' "$bundle"
printf '<?php\n$settings["famtastic_acquisition_hvac_bundle_root"] = "%s";\n$settings["famtastic_acquisition_hvac_bundle_sha256"] = "%s";\n$settings["famtastic_acquisition_hvac_preview_enabled"] = TRUE;\n' "$bundle" "$manifest_hash" > "$bundle/hvac-settings.php"
rollback(){
 trap - ERR INT TERM HUP
 if [[ "$(sha256sum "$settings" | awk '{print $1}')" == "$promoted_settings_hash" ]];then
   cp -p "$backup/settings.local.php" "$settings.rollback.tmp";mv "$settings.rollback.tmp" "$settings"
 elif [[ "$(sha256sum "$settings" | awk '{print $1}')" != "$settings_hash" ]];then
   echo 'Concurrent settings edit preserved; manual reconciliation required.' >&2
 fi
 while read -r file expected current;do [[ -n "$file" ]] || continue;if [[ -f "$module/$file" && "$(sha256sum "$module/$file" | awk '{print $1}')" == "$current" ]];then if [[ "$expected" != absent ]];then cp -p "$backup/$file" "$module/$file";else rm -f "$module/$file";fi;fi;done <<< "$spec"
 echo "HVAC promotion failed; five-file/settings rollback performed; backup=$backup" >&2;exit 1
}
promoted_settings_hash="$settings_hash"
trap rollback ERR INT TERM HUP
[[ "$(sha256sum "$settings" | awk '{print $1}')" == "$settings_hash" ]]
while read -r file expected current;do [[ -n "$file" ]] || continue;install -m0644 "$new/$file" "$module/$file";done <<< "$spec"
cp -p "$settings" "$settings.hvac.tmp"
printf '\n// Scoped immutable HVAC Lab release; no campaign state changes.\nrequire "%s/hvac-settings.php";\n' "$bundle" >> "$settings.hvac.tmp"
"$php" -l "$settings.hvac.tmp"
promoted_settings_hash="$(sha256sum "$settings.hvac.tmp" | awk '{print $1}')"
[[ "$(sha256sum "$settings" | awk '{print $1}')" == "$settings_hash" ]]
mv "$settings.hvac.tmp" "$settings"
"$php" -l "$settings"
"$php" vendor/bin/drush.php php:eval '$r=\Drupal\famtastic_pipeline\Service\AcquisitionHvacTemplate::nativeRecipe();$f=\Drupal\famtastic_pipeline\Service\AcquisitionHvacTemplate::freezeLab(str_repeat("a",64));if($r["id"]!=="coastal_current_hvac_v1"||!$f["interactive"]||$f["email_send_authorized"]!==false)throw new RuntimeException("Native HVAC release invalid");print "HVAC native recipe and immutable snapshot verified\n";'
"$php" vendor/bin/drush.php php:eval 'if(\Drupal\Core\Site\Settings::get("famtastic_acquisition_hvac_preview_enabled")!==TRUE)throw new RuntimeException("HVAC preview flag missing");'
[[ "$(sha256sum "$settings" | awk '{print $1}')" == "$promoted_settings_hash" ]]
state_after="$($php vendor/bin/drush.php php:eval '$keys=["famtastic_pipeline.settings","smtp.settings"]; $data=[];foreach($keys as $key)$data[$key]=\Drupal::config($key)->getRawData();print hash("sha256",serialize($data));')"
[[ "$state_before" == "$state_after" ]]
[[ "$cron_before" == "$(crontab -l | sha256sum | awk '{print $1}')" ]]
while read -r file expected current;do [[ -n "$file" ]] || continue;[[ "$(sha256sum "$module/$file" | awk '{print $1}')" == "$current" ]];done <<< "$spec"
printf 'commit=%s\nbase=%s\ndeployed_at=%s\nbackup=%s\nmanifest_sha256=%s\nscope=five-runtime-files-private-assets-HVAC-settings\ncron_unchanged=1\npipeline_smtp_config_unchanged=1\n' "$revision" "$base" "$(date -u +%FT%TZ)" "$backup" "$manifest_hash" > "$production/.hvac-lab-release.tmp"
mv "$production/.hvac-lab-release.tmp" "$production/.hvac-lab-release"
trap - ERR INT TERM HUP
cat "$production/.hvac-lab-release"
REMOTE
