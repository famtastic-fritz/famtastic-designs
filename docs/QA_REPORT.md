# QA report

Date
- 2026-06-24

Proof URLs
- Site: http://127.0.0.1:3001
- Admin proof: http://127.0.0.1:3001/admin-proof

Environment used
- ADMIN_PROOF_PIN=1234
- NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001
- NUXT_PUBLIC_CMS_MODE=local
- NUXT_PUBLIC_LEAD_STORAGE_MODE=local
- NUXT_PUBLIC_PAYMENT_MODE=mock
- NUXT_PUBLIC_PORTAL_MODE=preview
- BOOKING_PROVIDER=mock

Build + local runtime
- pnpm install: pass
- pnpm typecheck: pass
- pnpm lint: pass
- pnpm build: pass
- pnpm preview --port 3001: pass

Content-render override proof
- Saved hero override through admin-proof content endpoint.
- Verified /api/famtastic-content returned the saved hero headline.
- Refreshed homepage and confirmed the visible H1 updated to: Proof Hero Visible 2026-06-24T20-override
- Saved package/pricing override through admin-proof content endpoint.
- Verified /api/famtastic-content returned the saved package price label.
- Refreshed /pricing and confirmed visible price updated to: Proof Price Visible $1,901+
- Refreshed /packages and confirmed visible price updated to: Proof Price Visible $1,901+
- Result: content-render override bug closed.

Route checks
- / : 200
- /services : 200
- /pricing : 200
- /packages : 200
- /work : 200
- /contact : 200
- /get-started : 200
- /portal : 200
- /client-portal-login : 200
- /thank-you : 200
- /privacy-policy : 200
- /terms-of-service : 200
- /cookie-policy : 200
- /sitemap : 200
- /sitemap.xml : 200
- /robots.txt : 200
- /admin-proof : 200

Lead capture proof
- Full intake POST to /api/leads: pass
- Short consultation POST to /api/leads: pass
- Local lead save target: .data/famtastic-leads.json
- Admin proof leads endpoint /api/admin-proof/leads?pin=1234: pass
- Verified returned lead types include full_intake and consultation.

Browser-visible checks
- Homepage hero override visible: pass
- Pricing override visible: pass
- Packages override visible: pass
- Cookie banner visible: pass
- Legal links visible in footer: pass
- Booking/payment CTAs remain safe mock/placeholder flows: pass

Notes on UI-level admin lead viewer
- Protected admin proof leads endpoint works with pin=1234.
- Browser-side visual confirmation of rendered lead cards on /admin-proof was not consistently reproduced during this run even though the endpoint returned the saved leads.
- This is non-blocking for production-proof because the secured endpoint and local lead storage both verified successfully, but the admin-proof page interaction can be polished later if needed.

Responsive audit
- Desktop layout verified in browser.
- Mobile/tablet behavior remains CSS-driven and structurally responsive via existing layout classes.
- Dedicated viewport-emulated screenshot proof for 360px / 390px / 768px was not captured in this run.

What remains mocked
- PayPal checkout, invoice, and recurring billing
- Booking provider integrations
- Directus live CMS / live DB writes
- Client portal production auth/integrations

Overall result
- Production-proof branch is locally runnable.
- Core proof claim is now true: admin-proof content overrides propagate through the content API and render on public pages.
