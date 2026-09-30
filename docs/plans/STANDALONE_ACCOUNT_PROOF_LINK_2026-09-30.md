# Standalone account proof link

A customer account may have a single owner-selected review proof without a paid order or a generated three-direction campaign. The portal already serializes the project’s `proof_url`, but its project card omitted that link and displayed Paid plus a fallback count of three concepts.

## Change

Show one Open proof action for a valid HTTPS proof URL when there is no populated variant set. Use Project saved, actual concept counts or Proof available/pending, and the saved project label. Suppress the contradictory empty-request panel when account projects exist. Preserve a recorded zero revision limit. Do not add checkout, approval, email, account-creation or backend actions.

## Local verification

- Base: GitHub main `f750a163d418f8fbda4aa601165fe70c31f4545f`, freshly fetched.
- Node 22.23.2, committed npm lockfile.
- Production build passed, including SEO shells and creator-credit inventory (221 HTML outputs). Existing large-bundle warning remains. Initial sandbox-only build could not resolve the existing SEO source host; authorized network rerun passed.
- Portal Design DNA validator: 34 passed.
- Chromium: six cases passed across desktop and mobile, with mocked account API responses and no real account changes. Covers an unpaid single proof with usable 44px link/no overflow/no invented payment or count; rejection of javascript URL; existing three-direction count and live URL preservation.
- The dedicated local Vite server was stopped after testing.

## Release boundary

Source is prepared in an isolated checkout. No push, deployment, customer message, billing change or record creation was performed by this worker. The parent release task owns account binding, production verification and documentation update after release.

## Documentation mirror

Repository changelog, capability registry and both learning surfaces are updated. A Google Drive mirror was not written because this bounded worker’s writes are restricted to the isolated /tmp checkout; the parent release task must mirror the final verified outcome. No cloud-sync result is claimed.
