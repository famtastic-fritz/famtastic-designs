# FAMtastic Designs proofing guide

Repo path
- `~/famtastic/sites/site-famtastic-designs`

Local URL
- Dev: `http://localhost:3001`
- Preview: `http://localhost:3300`

Pages to proof
- `/`
- `/services`
- `/pricing`
- `/work`
- `/get-started`
- `/thank-you`
- `/contact`
- `/portal`
- `/client-portal-login`

Mobile proof checklist
- 360px: hero, nav, CTAs, cards, form spacing
- 390px: pricing cards and CTA buttons
- 768px: section spacing and stacked-to-grid transitions
- Desktop: hero composition, services grid, pricing grid, portal preview, footer

Get Started form test steps
1. Open `/get-started`.
2. Fill business name, name, email, and at least one project detail.
3. Submit.
4. Confirm redirect to `/thank-you`.
5. Confirm a record exists in `.data/famtastic-leads.json`.

What is working
- Local marketing site routes
- Local lead persistence
- `pnpm typecheck`
- `pnpm lint`
- `pnpm build`
- Preview build on `http://localhost:3300`
- Portal preview UI
- Mock payment CTA flow

What is mocked
- Real Stripe checkout
- Real authenticated portal
- Live CMS data unless Directus mode is configured

Current verification notes
- Dev was verified from the target repo on port `3001` because another local process already owned `3000`.
- Preview was verified from the target repo on port `3300`.
- Directus did not come up in this session because the local Docker daemon was not running.
- Lead persistence was verified through `POST /api/leads` and `.data/famtastic-leads.json`.
