# FAMtastic marketing operator guide

**Date:** August 19, 2026
**Status:** Draft production is ready; public distribution remains approval-gated.
**Audience:** Fritz, Shay, Codex, Claude, and future campaign operators.

## What exists today

FAMtastic has a versioned campaign contract, a 68-moment starter campaign, brand configuration, local model routing, reusable media tooling, approval fields, UTMs, campaign QA, two live demonstration experiences, and Drupal/GA4 integration points. It does not yet have a finished customer-facing Campaigns screen or connected social scheduler.

The practical rule is simple: the system can prepare campaigns and posts now. Fritz still approves the release, and the first posts are delivered through an assisted channel workflow until provider OAuth, verification, retry, alert, and rollback evidence is complete.

## The operator path

```text
Campaign idea
  -> evidence-backed brief
  -> content manifest with stable IDs
  -> channel-specific drafts and media
  -> content, claim, brand, crop, link, and accessibility QA
  -> Fritz approval packet
  -> schedule or assisted post
  -> verify the live post and rollback path
  -> measure GA4 and Drupal attribution
  -> record the lesson and adjust the next batch
```

### 1. Ask for a campaign

Give Shay or an agent:

- the business goal;
- the audience;
- the offer or capability to prove;
- the channels;
- the desired dates;
- the FAMtastic intensity from 0 to 10;
- any customer media, references, restrictions, or required language;
- whether the request is drafts only or should stop at an approval gate.

The operator must ground claims in `docs/CAPABILITY_REGISTRY.md`, product truth, or authoritative research. A campaign may demonstrate a capability, but it may not silently invent prices, testimonials, guarantees, scarcity, or customer outcomes.

### 2. Build the release packet

Every content item receives one stable `content_id`. That ID follows the item through its source record, filename, scheduler record, `utm_content`, GA4 event, Drupal attribution, live URL, verification evidence, and performance report.

The release packet must contain:

- campaign brief and audience;
- content manifest and channel adaptations;
- copy and media assets;
- claim/evidence ledger;
- CTA and destination;
- UTM map;
- crop and accessibility checks;
- content, media, and publish approvals;
- intended schedule;
- rollback/delete instructions;
- expected measurement events.

### 3. Review in three decisions

Fritz reviews three separate decisions:

1. **Content:** Is the message accurate, useful, and on brand?
2. **Media:** Is the image/video the right quality and safe to use?
3. **Publish:** May this exact item go to this exact channel and audience at this time?

Approval of a concept is not approval of a public send. Public posting, promotional email, advertising spend, live-price changes, and broad publication remain explicit gates.

### 4. Publish and verify

Until the channel proof is complete, use assisted publishing:

1. prepare days 1–3;
2. authorize one official channel connection at a time;
3. submit private/draft test posts where supported;
4. verify crop, caption, link, UTM, provider ID, and deletion;
5. approve the first public batch;
6. save the public URL, timestamp, screenshot, and measurement join.

The system must fail closed if approval is missing, provider availability changes, a duplicate content ID appears, or a publish result cannot be verified.

## What Fritz can request right now

Use plain requests such as:

- “Create a seven-day campaign for the FAMtastic Lab aimed at local service businesses. Instagram, Facebook, and email. Intensity 8. Drafts only.”
- “Turn this proof run into three posts, one Reel script, and a blog outline. Prepare the approval packet but do not publish.”
- “Show me days 1–3 of the 55-cent campaign, with final crops and tracked links, for approval.”
- “Create a campaign around our new portfolio experience. Use the demos only within their recorded rights boundaries.”

The operator should return a reviewable packet, not ask Fritz to reconstruct a campaign from folders.

## Current commands

From the repository root:

```bash
python3 scripts/campaign-readiness.py
agent-skills/famtastic-demand-engine/scripts/run-demand-checks.sh "$PWD"
```

The first command proves the 68-record manifest, three approval gates, stable UTMs, model/tool availability, and public-publish-off default. The second validates the content library and builds the public frontend. Passing either command does not authorize a public post.

## Missing product surface

The FAMtastic portal still needs a staff-only Marketing workspace with:

- Campaigns, Content Queue, Media Library, Approvals, Calendar, Channels, Results, and Lessons;
- a campaign wizard that produces the canonical brief and manifest;
- content/media/publish approval controls;
- preview at real channel sizes;
- provider availability and fallback status;
- schedule, retry, duplicate protection, failure alerts, live verification, and rollback;
- stable joins to Drupal leads, GA4 conversions, email, and purchases;
- reusable portfolio/demo asset selection with rights status.

That workspace is an operator shell over the existing contracts. It must not create a second campaign truth.

## Evidence boundary

As of August 19, 2026:

- draft production readiness: **locally proven**;
- content library and frontend build: **locally proven**;
- public FAMtastic Lab and Rattler Lifers experience: **production smoke-tested**;
- connected social scheduling and public-post lifecycle: **not yet proven**;
- promotional email campaign delivery: **approval-gated and not certified by this guide**;
- fully unattended campaign operation: **not yet certified**.
