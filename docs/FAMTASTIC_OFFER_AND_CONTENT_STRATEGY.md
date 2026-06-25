# FAMtastic offer and content strategy

Positioning
- FAMtastic Designs is framed as a practical website and client-systems partner for businesses that need cleaner presentation, lead capture, and operational clarity.

Target buyers
- Local service businesses
- Growth-stage small businesses
- Founders with scattered web presence
- Owners who need websites plus workflow support, not just visuals

Offer ladder
1. Starter Website
2. Business Website
3. Growth Website + CMS
4. Landing Page Campaign Package
5. Premium Website + Automation
6. Client Portal / Workflow Setup
7. Website Care Plan

Package strategy
- Each package has a clear best-fit statement
- Price labels stay directional, not overpromised
- CTAs route into consultation/intake rather than fake instant checkout

Service categories
- Website design and build
- Landing pages and campaign support
- CMS/content editing setup
- Lead capture and intake systems
- Client portal / workflow support
- Care-plan support

Add-ons
- Add-ons should stay practical: content entry, analytics setup, copy support, extra page expansion, reporting, automation increments

Lead flow
- Short consultation form for fast routing
- Full intake form for higher-context projects
- Thank-you page closes the loop cleanly
- Local proof saves to .data/famtastic-leads.json
- Admin proof lead viewer gives local verification now

Admin-editable content model
- Base content lives in data/famtastic/*
- data/famtastic/content.ts is the structured aggregation layer
- /api/famtastic-content returns merged content
- /admin-proof writes local overrides to the same pipeline the public pages read
- Directus remains a later lane, not a false current claim

PayPal-first direction
- Proof remains mock mode
- Future direction is PayPal first for checkout links, invoice flows, deposit collection, and care-plan payment paths
- Stripe is optional/future only

Scheduling direction
- Proof defaults to manual/mock booking
- Public CTAs route safely to /get-started unless a real approved booking URL exists
- Self-hosted scheduling can be chosen later after owner review

Demo/proof content areas
- Offer ladder
- Services and deliverables
- Portfolio/demo positioning
- FAQs
- Legal starter pages
- Admin-proof content editing path

What must be replaced or approved before final production
- Real contact email and phone if different
- Any demo portfolio/status labels owner does not want exposed
- Any expectation/testimonial language the owner wants tightened
- Real booking path if scheduling is activated
- Real payment path if payments are activated
- Final legal review
- Final SEO/canonical domain review

Owner review items
- Homepage headline/subheadline
- Package names and price labels
- Service wording
- Portfolio/demo wording
- Contact options
- Payment direction copy
- Scheduling direction copy
