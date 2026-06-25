# Auth architecture

Three surfaces

1. Public website
- Host: famtasticdesigns.com
- Purpose: marketing, lead capture, services, packages, work, legal pages, and public SEO surface
- Audience: visitors, prospects, referral traffic

2. Admin/CMS backend
- Host: admin.famtasticdesigns.com or cms.famtasticdesigns.com
- Purpose: site content editing, SEO editing, legal page updates, lead review, booking/payment setting updates, and backend operations
- Audience: owner/admin/editor roles only
- Recommended implementation: Directus first, or another selected CMS

3. Client portal
- Host: portal.famtasticdesigns.com
- Purpose: client-facing project updates, files, invoices, approvals, and workflow visibility
- Audience: invited clients only
- Important distinction: client portal is not the admin backend

Current proof state
- /admin-proof is only a local proof admin helper.
- /portal and /client-portal-login are client-facing preview/mock surfaces.
- Production auth is not complete in this branch.

Separation rule
- Public website handles marketing and intake.
- Admin backend handles site operations and content management.
- Client portal handles client-specific authenticated experiences.
- These are separate surfaces and should not collapse into one route or one auth model.
