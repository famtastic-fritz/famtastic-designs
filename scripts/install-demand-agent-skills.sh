#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(git -C "$(dirname "${BASH_SOURCE[0]}")/.." rev-parse --show-toplevel)"
tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-demand-skills.XXXXXX")"
trap 'rm -rf "$tmp_dir"' EXIT

blog_sha="aec971ac511370c6216cd93776c9cf2fec97b32a"
seo_sha="09d37c7b66ed3ca9c6efbdb765a805a6c76a8f01"
marketing_sha="f186d5369222b5a085b3cbaba5fe71576819558f"

git clone --quiet https://github.com/AgriciDaniel/claude-blog.git "$tmp_dir/blog"
git -C "$tmp_dir/blog" checkout --quiet "$blog_sha"
git clone --quiet https://github.com/AgriciDaniel/claude-seo.git "$tmp_dir/seo"
git -C "$tmp_dir/seo" checkout --quiet "$seo_sha"
git clone --quiet https://github.com/robertbstillwell/marketing-skills.git "$tmp_dir/marketing"
git -C "$tmp_dir/marketing" checkout --quiet "$marketing_sha"

blog_skills=(blog blog-write blog-brief blog-cluster blog-taxonomy blog-seo-check blog-factcheck blog-schema blog-strategy blog-calendar blog-audit blog-geo blog-brand)
seo_skills=(seo seo-audit seo-content-brief seo-content seo-schema seo-sitemap seo-local seo-ecommerce seo-technical seo-cluster)
marketing_skills=(content-strategy copywriting pricing-strategy product-marketing-context free-tool-strategy email-sequence analytics-tracking launch-strategy)
destinations=("${HOME}/.codex/skills" "${HOME}/.claude/skills" "${HOME}/.shay/skills/marketing")

install_group() {
  local source_root="$1"
  shift
  local skill destination
  for skill in "$@"; do
    for destination in "${destinations[@]}"; do
      mkdir -p "$destination/$skill"
      rsync -a --delete "$source_root/$skill/" "$destination/$skill/"
    done
  done
}

install_group "$tmp_dir/blog/skills" "${blog_skills[@]}"
install_group "$tmp_dir/seo/skills" "${seo_skills[@]}"
install_group "$tmp_dir/marketing" "${marketing_skills[@]}"

for destination in "${destinations[@]}"; do
  mkdir -p "$destination/famtastic-demand-engine"
  rsync -a --delete "$repo_dir/agent-skills/famtastic-demand-engine/" "$destination/famtastic-demand-engine/"
done

# Codex skill frontmatter supports name and description; remove upstream-only
# metadata without changing the instruction bodies used by Claude and Shay.
python3 - "${HOME}/.codex/skills" "${blog_skills[@]}" -- "${seo_skills[@]}" -- "${marketing_skills[@]}" <<'PY'
from pathlib import Path
import sys

root = Path(sys.argv[1])
skills = [value for value in sys.argv[2:] if value != '--']
allowed = {'name', 'description'}
for skill in skills:
    path = root / skill / 'SKILL.md'
    text = path.read_text()
    if not text.startswith('---\n'):
        continue
    _, frontmatter, body = text.split('---', 2)
    kept = []
    for line in frontmatter.strip().splitlines():
        key = line.split(':', 1)[0].strip()
        if key in allowed or line.startswith((' ', '\t')):
            kept.append(line)
    path.write_text('---\n' + '\n'.join(kept) + '\n---' + body)
PY

validator="${HOME}/.codex/skills/.system/skill-creator/scripts/quick_validate.py"
if [[ -f "$validator" ]]; then
  for skill in "${blog_skills[@]}" "${seo_skills[@]}" "${marketing_skills[@]}" famtastic-demand-engine; do
    uv run --quiet --with pyyaml python "$validator" "${HOME}/.codex/skills/$skill" >/dev/null
  done
fi

printf 'Installed %s selected specialist skills plus famtastic-demand-engine for Codex, Claude, and Shay.\n' "$(( ${#blog_skills[@]} + ${#seo_skills[@]} + ${#marketing_skills[@]} ))"
