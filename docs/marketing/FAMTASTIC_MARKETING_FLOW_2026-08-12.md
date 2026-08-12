# FAMtastic Marketing Flow

Date: 2026-08-12

## Decision

FAMtastic will use a hybrid, approval-controlled marketing system. Local tools
handle repeatable drafting, motion graphics, encoding, captions, and QA. HeyGen
is an optional presenter layer, not a required monthly dependency. Poe is an
optional cloud-model bench. Drupal and the repository remain the sources of
truth for offers, content, leads, consent, campaign records, and evidence.

## Flow

```text
approved offer and campaign truth
  -> 17-day campaign manifest (68 content moments)
  -> local draft or selected cloud-model draft
  -> content and product-logic QA
  -> SEO/discovery QA
  -> image, motion, screen demo, founder, or optional HeyGen production
  -> crop, caption, audio, brand, accessibility, and claim QA
  -> Fritz approval queue
  -> channel scheduler
  -> delivery verification
  -> GA4/Drupal attribution and lead handling
  -> daily learning and next-batch adjustment
```

No generated item may move directly from draft to public. The canonical states
are `idea`, `briefed`, `drafted`, `content_qa`, `seo_qa`, `media_ready`,
`approved`, `scheduled`, `published`, `verified`, `measured`, and `learned`.

## Recommended stack

| Job | Default | Optional upgrade | Why |
|---|---|---|---|
| Campaign truth and records | Drupal + repository JSON | None | Keeps offers, consent, leads, and evidence connected. |
| Drafting and variations | Ollama + Qwen3 8B, challenged by GLM4 9B | Poe API / Codex | Local, private, and no per-call cost for routine work. |
| Final factual and offer QA | Repository validators + agent review | Strong cloud model | A small local model is an assistant, not final authority. |
| Branded motion | FFmpeg now; Remotion templates next | Managed render service | Better for price cards, diagrams, captions, timelines, and CTAs than an avatar. |
| Presenter video | Founder footage or voiceover | HeyGen eight-video pilot | Use only when a face and consistent delivery increase trust. |
| Local image review | Gemma 3 4B | Codex/cloud vision | Adds a small multimodal lane without exhausting workstation memory. |
| Local speech/captions | Kokoro + whisper.cpp evaluation | Cloud voice/caption service | Small permissive components; voice and dependency licenses still require recording. |
| Scheduling | Approval manifest first | Patched Postiz pilot | Platform OAuth and audits remain unavoidable. |
| Workflow automation | Existing Drupal workers/scripts | Activepieces Community Edition | Add only when the manifest workflow is stable. |
| Measurement | Existing GA4 + Drupal campaign/lead ledger | PostHog later | Avoid a second analytics system until it answers a distinct question. |
| Email | Existing transactional pipeline; consented promotional list | Dedicated bulk provider later | Transactional and promotional messages stay separate. |

## HeyGen decision

Do not commit to a paid HeyGen plan yet. Use the connected official workflow to
produce one approved 9:16 pilot and evaluate presenter quality, editing time,
crop safety, brand consistency, and measured retention. If the free/available
credits cannot support that proof, pause rather than subscribe automatically.
The campaign can run without HeyGen using motion graphics, scenario imagery,
screen demonstrations, and founder voiceover.

## Open-source video findings

- **Remotion:** recommended for reusable branded video templates. Its current
  license permits free commercial use for individuals and for-profit companies
  with up to three employees; recheck eligibility as FAMtastic grows.
- **MuseTalk:** credible lip-sync research with MIT code and commercially usable
  published model weights, but its demonstrated real-time target is an NVIDIA
  V100. It is not the default for this 16 GB Apple Silicon computer.
- **LivePortrait:** useful portrait animation, but commercial use requires
  replacing the bundled non-commercial InsightFace detection models. It is an
  experiment, not a clean drop-in commercial default.
- **FFmpeg:** installed locally and selected as the dependable encoding,
  composition, crop, audio, and export foundation.
- **Wav2Lip:** excluded from the commercial pipeline because its commonly used
  release/model has non-commercial restrictions.

## Local models and Kimi

Ollama and Qwen3 8B are installed for routine outlines, caption variants,
summaries, classification, and first-pass QA. This machine has 16 GB unified
memory; an 8B quantized model leaves room for the operating system and editing
tools. Do not give it unattended publishing authority.

Kimi K2 is open, but the official model has one trillion total parameters and
32 billion active parameters. Kimi K3 is larger: 2.8 trillion total and about
104 billion activated parameters. Neither is a realistic local model for this
Mac. Ollama's official Kimi K3 entry is `kimi-k3:cloud`, currently requires a
Pro or Max plan, and consumes extra usage credits. Kimi can still be evaluated
through hosted access if its cost and output beat existing tools. Open weights
does not mean fits locally, and a local CLI does not guarantee local inference.

## Poe

Poe offers an OpenAI-compatible API using subscription points. Keep it for one
billing cycle only if it provides a measurable role: inexpensive second
opinions, model comparisons, or burst generation. Do not make campaign records,
approval state, scheduling, or secrets depend on Poe. Store `POE_API_KEY` only
in the local environment and set a campaign point budget before automation.

## Distribution reality

No scheduler bypasses platform approval. TikTok Direct Post requires approved
scope and unaudited clients are private-only. New unverified YouTube API
projects also upload privately. Meta and other networks require their own
business-account/OAuth setup. Therefore rollout is:

1. Produce and approve days 1–3.
2. Connect one channel at a time with least-privilege OAuth.
3. Create private/draft test posts and record provider IDs.
4. Verify crop, captions, links, UTM attribution, and deletion/rollback.
5. Approve the first public batch.
6. Add automated posting only after bounded retries and Fritz alerts work.

Postiz remains the best self-hosted scheduling candidate, but it is not being
installed on the rebuilt laptop yet: Docker is absent, self-hosting exposes
social tokens, and recent security advisories make version pinning and patch
discipline mandatory. A disposable non-production pilot should use a version at
or above every published fix, TLS, strong secrets, backups, and no public posts.

## 17-day operating rules

- Four content moments per day means 68 canonical ideas, not four identical
  blasts to every network.
- Email is not sent four times per day. Use a short launch/nurture sequence and
  behavior-based follow-up.
- Each content record carries a stable content ID into filename, scheduler,
  `utm_content`, GA4, Drupal, and reporting.
- Each day has Teach, Challenge, Prove, and Invite moments.
- Adapt format and copy for Instagram, TikTok, Facebook, YouTube Shorts, and
  Stories; deepen only strong topics into long-form YouTube and blogs.
- Days 1–3 are assisted publishing. Later automation is earned by evidence.

## What requires Fritz

- Approve avatar/voice identity and any HeyGen paid plan.
- Authorize each social account through its official OAuth screen.
- Approve the first public batch and any advertising spend.
- Approve promotional-recipient policy and final sends.
- Decide whether Poe earns renewal after the measured trial.

Everything before those gates—briefs, manifests, draft assets, variants, QA,
private test jobs, UTM construction, and reports—can be automated.
