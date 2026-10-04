#!/usr/bin/env bash
# Read-only release gate: after activation, silently losing this clock blocks releases.
set -euo pipefail
ssh -T "${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}" bash -s <<'REMOTE'
set -euo pipefail
cd "$HOME/public_html"
active="$(/usr/local/bin/php vendor/bin/drush.php php:eval 'print \Drupal::state()->get("famtastic.inbound_mail.activation") ? "enabled" : "disabled";')"
if [[ "$active" == enabled ]]; then
 /usr/local/bin/php vendor/bin/drush.php famtastic:mail-schedule
else
 echo 'Ingress clock not yet activated; dedicated repair release required.'
fi
REMOTE
