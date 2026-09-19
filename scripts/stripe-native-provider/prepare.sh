#!/usr/bin/env bash
# Called only by run.mjs. Fresh native Commerce; no agency/customer fixture copy.
set -euo pipefail
root="${1:?}"; runtime="${2:?}"; borrowed="${3:?}"
[[ "$runtime" == /private/tmp/famtastic-stripe-native.?????? || "$runtime" == /tmp/famtastic-stripe-native.?????? ]] || exit 2
test -f "$runtime/binding.json"
cmp "$root/backend/composer.lock" "$borrowed/composer.lock"
mkdir -p "$runtime/backend/web/sites/default/files" "$runtime/backend/private" "$runtime/home" "$runtime/tmp"
cp "$root/backend/composer.json" "$root/backend/composer.lock" "$runtime/backend/"
rsync -aL "$borrowed/vendor/" "$runtime/backend/vendor/"
rsync -a "$borrowed/web/core/" "$runtime/backend/web/core/"
rsync -a "$borrowed/web/modules/contrib/" "$runtime/backend/web/modules/contrib/"
for kind in profiles themes libraries; do
  if [[ -d "$borrowed/web/$kind" ]]; then rsync -a "$borrowed/web/$kind/" "$runtime/backend/web/$kind/"; fi
done
cp "$borrowed/web/autoload.php" "$runtime/backend/web/"
cp "$runtime/backend/web/core/assets/scaffold/files/default.settings.php" "$runtime/backend/web/sites/default/default.settings.php"
cp -R "$root/scripts/stripe-native-provider" "$runtime/probe"
isolated=(env -i "PATH=$PATH" "HOME=$runtime/home" "TMPDIR=$runtime/tmp" LANG=C "NATIVE_PROBE_ROOT=$runtime")
php_flags=(-d memory_limit=512M -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail -d sendmail_path=/usr/bin/false)
"${isolated[@]}" php "${php_flags[@]}" "$runtime/probe/native.php" configure
drush=("${isolated[@]}" php "${php_flags[@]}" "$runtime/backend/vendor/drush/drush/drush.php" "--root=$runtime/backend/web" --uri=http://native-probe.example.test)
"${drush[@]}" site:install standard "--db-url=sqlite://localhost/$runtime/backend/web/sites/default/files/.ht.sqlite" \
  --sites-subdir=default --account-name=probe-admin --account-pass=ephemeral-not-exposed --account-mail=operator@example.test \
  --site-name='Synthetic native provider probe' --site-mail=no-reply@example.test -y
"${drush[@]}" en -y commerce_cart commerce_checkout commerce_stripe
"${drush[@]}" php:script "$runtime/probe/native.php" -- seed
