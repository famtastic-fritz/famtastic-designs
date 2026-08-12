#!/usr/bin/env bash
set -euo pipefail

if (($# < 2)); then
  printf 'Usage: %s <draft_copy|multilingual_copy|image_review|caption_variants> <prompt>\n' "$0" >&2
  exit 64
fi

task="$1"
prompt="$2"

case "$task" in
  draft_copy|caption_variants) model="qwen3:8b" ;;
  multilingual_copy) model="glm4:9b" ;;
  image_review) model="gemma3:4b" ;;
  final_claim_approval|public_publish_approval)
    printf 'BLOCK: %s cannot be delegated to a local model.\n' "$task" >&2
    exit 2
    ;;
  *)
    printf 'Unknown task: %s\n' "$task" >&2
    exit 64
    ;;
esac

if [[ "$model" == *":cloud" ]]; then
  printf 'BLOCK: cloud aliases cannot be represented as local inference.\n' >&2
  exit 2
fi

if ! ollama list | awk 'NR > 1 {print $1}' | grep -Fxq "$model"; then
  printf 'Missing model %s. Run: ollama pull %s\n' "$model" "$model" >&2
  exit 1
fi

printf 'ROUTE task=%s execution=local model=%s\n' "$task" "$model" >&2
exec ollama run "$model" "$prompt"

