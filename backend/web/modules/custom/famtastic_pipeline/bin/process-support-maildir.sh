#!/usr/bin/env bash
set -euo pipefail
# Compatibility entry only. The marker-owned scheduler invokes Drush directly.
cd "$HOME/public_html"
exec /usr/local/bin/php "$HOME/public_html/vendor/bin/drush.php" famtastic:mail-tick
