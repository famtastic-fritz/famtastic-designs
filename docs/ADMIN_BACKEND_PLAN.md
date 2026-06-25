# Admin backend plan

Status
- /admin-proof is local proof only.
- It exists to prove editable content and local lead review during development.
- It must not be treated as a production-secure admin backend.

What /admin-proof is for
- local content override proof
- local lead review proof
- proof-mode verification that the public site consumes merged content from the content API

What /admin-proof is NOT
- not the production CMS
- not a secure production admin surface
- not the long-term lead backend
- not a substitute for role-based auth, audit logs, content revisioning, or real user management

Production backend recommendation
- Preferred admin host: admin.famtasticdesigns.com
- Acceptable alternate host: cms.famtasticdesigns.com
- Recommended backend: Directus first, or another selected CMS if Directus is rejected later

Production admin should own
- homepage
- services
- packages
- FAQs
- legal pages
- SEO
- leads
- payment settings
- booking settings

Security stance
- /admin-proof uses a local env gate and PIN for proofing only.
- That is enough for local proof, not enough for production security.
- Production admin needs real authentication, role separation, secure storage, auditability, and deployment-aware access control.

Operational rule
- /admin-proof should stay disabled by default.
- It should only work when ENABLE_ADMIN_PROOF=true and ADMIN_PROOF_PIN are explicitly set for local proof/dev usage.
