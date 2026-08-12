# HeyGen campaign automation

## Decision

Codex is the primary campaign-production orchestrator for FAMtastic because the
approved content, brand assets, QA checks, publishing manifests, and deployment
evidence already live in this repository. HeyGen is connected to Codex through
its OAuth plugin. Claude Code and Shay may use the same official remote MCP at
`https://mcp.heygen.com/mcp/v1/`; they must consume the same briefs, approvals,
asset registry, and evidence rather than creating a second campaign system.

Repository-installed official HeyGen skills:

- `.agents/skills/heygen-avatar`
- `.agents/skills/heygen-video`
- `.agents/skills/heygen-translate`

## Automation boundary

```text
approved campaign concept
  -> capability and product truth check
  -> platform-specific script variants
  -> human approval of master message and offer
  -> HeyGen draft render
  -> visual, caption, safe-area, audio, and claim QA
  -> approved asset library
  -> scheduled publishing queue
  -> UTM attribution and campaign ledger
  -> engagement, lead, checkout, and sale reporting
  -> revision or retirement
```

Video generation may be automated after a master message is approved. Real
social publishing remains approval-gated until connected accounts, posting
limits, rollback/deletion procedures, and a dry-run queue are provider-proven.
Advertising spend is always a separate approval.

## 55 Cents campaign fields

Every video job records: campaign key, content-series slug, hook, objection,
approved claim boundary, offer terms version, audience, aspect ratio, duration,
presenter/avatar, script, caption text, CTA, landing URL, UTM values, HeyGen job
and asset identifiers, QA state, publishing state, platform post IDs, and
performance outcomes.

## Platform outputs

- TikTok and Instagram Reels: 9:16, captioned, hook in the first two seconds.
- Instagram feed and Facebook: 4:5, strong branded cover, readable without audio.
- YouTube Shorts: 9:16, searchable title and description, direct next step.
- Website/blog: 16:9 companion explainer when it materially improves the article.

Each approved master concept produces variants; it does not create unrelated
messages for each platform. Generated output must preserve the one-time $199
purchase, included first-year basic hosting, conditional first-year available
new domain or existing-domain connection, separate renewals, and the rule that
Web Basics is not the correct scope for every project.

## Launch gates

1. HeyGen identity/avatar and voice approved.
2. One branded template or repeatable style approved in 9:16, 4:5, and 16:9.
3. Test render passes crop, captions, spelling, price, CTA, audio, and claim QA.
4. Destination URL and UTM event appear in analytics.
5. Social connectors prove draft creation without public posting.
6. Fritz approves the first scheduled batch.
7. Automation starts with a daily cap and failure alert; no silent retries that
   can duplicate a public post.

## Claude Code setup

Claude Code can use the same OAuth transport:

```bash
claude mcp add --transport http heygen https://mcp.heygen.com/mcp/v1/
```

The first HeyGen call opens OAuth. API keys must never be committed. If direct
CI-style automation is later required, store the key in the deployment secret
provider and use the official HeyGen CLI; MCP remains preferred for interactive
plan-credit use.
