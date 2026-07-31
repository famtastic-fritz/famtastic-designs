# Site Studio Proof Integration

## Modes

- Local mode: with no `SITE_STUDIO_URL`, the worker deterministically creates
  three isolated HTML proof artifacts for development and acceptance tests.
- Remote mode: the worker sends one signed, idempotent generation request to
  Site Studio and records `waiting_callback`. No placeholder is presented as a
  completed remote proof.

## Dispatch contract

Remote configuration is environment-owned:

```text
SITE_STUDIO_URL=https://studio.example/jobs
SITE_STUDIO_DISPATCH_SECRET=...
FAMTASTIC_PUBLIC_BASE_URL=https://famtasticdesigns.com
```

The JSON request includes:

- `schema_version`;
- `idempotency_key` (`proof:<campaign_id>`);
- campaign and prospect-safe business facts;
- named directions `a`, `b`, and `c`;
- `required_variant_count: 3`;
- callback URL.

The raw JSON is signed with HMAC-SHA256 in
`X-FAMtastic-Signature`. The same idempotency key is sent in
`Idempotency-Key`. Site Studio must return a nonempty `job_id`.

## Callback contract

Site Studio posts to:

```text
POST /api/pipeline/site-studio/callback
X-FAMtastic-Signature: sha256=<HMAC-SHA256(raw body)>
```

The secret is `SITE_STUDIO_CALLBACK_SECRET`. The body contains `event_id`,
`campaign_id`, `job_id`, and exactly three variants. Each variant has one unique
`direction_id` (`a`, `b`, or `c`), HTML limited to 500 KB, and optional
`design_dna`.

The callback:

- rejects missing or duplicate directions;
- rejects active content such as scripts, iframes, event handlers, and
  JavaScript URLs;
- verifies the callback job belongs to the campaign;
- writes each artifact to its isolated
  `web/proofs/<campaign>/<direction>/index.html` path;
- creates all three proof records before marking the campaign ready;
- records callback event IDs so replay is harmless;
- appends a `proof.ready` event and queues outreach preparation.

An invalid or partial callback never marks proofs ready.

## Verification

```bash
./scripts/e2e-site-studio-callback.sh
```

The synthetic test runs a signed mock Site Studio endpoint, proves remote
dispatch state, rejects a bad signature and a partial callback, accepts exactly
three isolated artifacts, and proves callback replay idempotency.
