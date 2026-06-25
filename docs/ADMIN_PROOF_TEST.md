# Admin proof test

Date
- 2026-06-24

URLs
- Admin proof: http://127.0.0.1:3001/admin-proof
- Public API: http://127.0.0.1:3001/api/famtastic-content
- Public site: http://127.0.0.1:3001

PIN used for local proof
- 1234

Verified flow
1. Start preview with local proof env vars.
2. Open /admin-proof.
3. Save a unique hero headline override through the admin proof content lane.
4. Confirm /api/famtastic-content returns that new hero headline.
5. Refresh homepage and confirm the visible H1 matches the saved override.
6. Save a unique package price override through the same admin proof content lane.
7. Confirm /api/famtastic-content returns the package override.
8. Refresh /pricing and /packages.
9. Confirm both pages visibly render the overridden price label.

Verified override values
- Hero headline: Proof Hero Visible 2026-06-24T20-override
- Starter package price: Proof Price Visible $1,901+

Lead proof
- /api/admin-proof/leads?pin=1234 returned saved local leads.
- .data/famtastic-leads.json continued to receive full_intake and consultation payloads.

Result
- Admin-proof content path is sufficient for local production-proof editing.
- Public pages now consume merged API/admin-proof content instead of sticking on fallback/base content.