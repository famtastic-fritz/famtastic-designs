#!/usr/bin/env bash
set -euo pipefail

# cPanel delivers base and plus-addressed support mail into Maildir folders.
# Import a bounded batch, then move only accepted messages from new/ to cur/.
mail_root="${FAMTASTIC_SUPPORT_MAILDIR:-$HOME/mail/famtasticdesigns.com/support}"
pipe="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/inbound-mail-pipe.php"

test -d "$mail_root" || exit 0
test -x "$pipe" || exit 78

processed=0
while IFS= read -r -d '' message; do
  new_dir="$(dirname "$message")"
  test "$(basename "$new_dir")" = "new" || continue
  folder="$(dirname "$new_dir")"
  cur_dir="$folder/cur"
  if "$pipe" < "$message"; then
    mkdir -p "$cur_dir"
    destination="$cur_dir/$(basename "$message"):2,S"
    mv -- "$message" "$destination"
  fi
  processed=$((processed + 1))
  test "$processed" -lt 50 || break
done < <(find "$mail_root" -type f -path '*/new/*' -print0 | sort -z)
