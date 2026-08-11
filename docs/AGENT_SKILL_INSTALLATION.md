# Shared demand-engine skill installation

## Purpose

Claude, Codex, and Shay use the same FAMtastic demand doctrine and the same reviewed specialist references. The repository-owned `famtastic-demand-engine` skill is authoritative; third-party skills are advisory.

## Pinned sources

- `AgriciDaniel/claude-blog` at `aec971ac511370c6216cd93776c9cf2fec97b32a`
- `AgriciDaniel/claude-seo` at `09d37c7b66ed3ca9c6efbdb765a805a6c76a8f01`
- `robertbstillwell/marketing-skills` at `f186d5369222b5a085b3cbaba5fe71576819558f`

The installer selects blog strategy, series, briefing, writing, taxonomy, schema, SEO, fact-checking, calendar, audit, local/ecommerce/technical SEO, content strategy, copywriting, pricing, launch, email, free-tool, product-context, and analytics skills. It does not install every upstream package.

## Destinations

- Codex: `~/.codex/skills`
- Claude: `~/.claude/skills`
- Shay: `~/.shay/skills/marketing`

Run `scripts/install-demand-agent-skills.sh` from any directory. The script resolves this repository, clones exact commits, installs the selected skill directories, installs the repository-owned skill, normalizes unsupported Codex frontmatter, and validates the resulting Codex packages.

## Authority and safety

All agents must begin with `AGENTS.md`, then use `docs/DEMAND_ENGINE_DOCTRINE.md`, `.claude/product-marketing-context.md`, and the canonical JSON manifest. Third-party skills cannot authorize publication, price changes, recurring billing, legal promises, campaign sends, ad spend, or unsupported capability claims.

Upstream changes require a reviewed commit update in the installer and this record. Do not install floating branches into the shared runtimes.
