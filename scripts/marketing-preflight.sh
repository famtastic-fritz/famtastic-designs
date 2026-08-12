#!/usr/bin/env bash
set -euo pipefail

repo="$(git rev-parse --show-toplevel)"
cd "$repo"

status=0
check_command() {
  local name="$1"
  if command -v "$name" >/dev/null 2>&1; then
    printf 'PASS command %-10s %s\n' "$name" "$(command -v "$name")"
  else
    printf 'MISS command %-10s\n' "$name"
    status=1
  fi
}

check_command git
check_command python3
check_command ffmpeg
check_command ollama

if curl --fail --silent --max-time 3 http://127.0.0.1:11434/api/tags >/dev/null; then
  printf 'PASS service ollama\n'
else
  printf 'MISS service ollama (run: brew services start ollama)\n'
  status=1
fi

if ollama list 2>/dev/null | awk 'NR > 1 {print $1}' | grep -Eq '^qwen3:8b$'; then
  printf 'PASS model   qwen3:8b\n'
else
  printf 'MISS model   qwen3:8b (run: ollama pull qwen3:8b)\n'
  status=1
fi

if [[ "${FAMTASTIC_MARKETING_PUBLISH:-false}" == "true" ]]; then
  printf 'BLOCK publishing is enabled; this preflight is draft-only\n'
  exit 2
fi

printf 'PASS safety  public publishing disabled\n'
printf 'INFO optional Poe=%s HeyGen=%s Postiz=%s\n' \
  "$([[ -n "${POE_API_KEY:-}" ]] && printf configured || printf absent)" \
  "$([[ -n "${HEYGEN_API_KEY:-}" ]] && printf configured || printf absent)" \
  "$([[ -n "${POSTIZ_API_KEY:-}" ]] && printf configured || printf absent)"

exit "$status"

