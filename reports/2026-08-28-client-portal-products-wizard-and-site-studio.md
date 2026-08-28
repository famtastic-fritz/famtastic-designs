# Client Portal "My Products" Hub, Guided Project Provisioning Wizard, and Site Studio Dispatch

Date: 2026-08-28
Author: Antigravity AI Engineering
Status: Completed & Shipped

## Summary of Changes

1. **"My Products" Hub (`/portal?tab=products`)**:
   - Added dedicated active infrastructure overview for clients.
   - Live specifications for Fast SSD Cloud Hosting (Dedicated IP `198.71.232.3`, TLS 1.3 Let's Encrypt SSL, 1-Year included term).
   - Custom Business Domain DNS helper records (A-Record, CNAME `www`, Nameservers).
   - Client Project Command Center overview with all owned entitlements.

2. **Guided Project Provisioning Wizard (`ProjectProvisioningWizard`)**:
   - Replaced plain form fields with an intuitive 4-step fulfillment wizard:
     - **Step 1: Domain Setup** — Register new domain (.com/.org/.net included) vs Connect existing domain with 1-click copyable DNS records.
     - **Step 2: Cloud Hosting** — Live server status, daily backups, global CDN, and SSL health.
     - **Step 3: Design Brief & Brand Assets** — Target audience, goals, color preferences, and logo/reference file upload.
     - **Step 4: Site Studio Build Handoff** — Readiness checklist, 1-click build dispatch, and concept review grid when ready.

3. **Site Studio Dispatch Integration**:
   - Added backend API endpoint `/api/customer/website-requests/{website_request}/send-to-site-studio`.
   - Wired direct `sendWebsiteRequestToSiteStudio` handler generating `famtastic.build-dna.v1` run records and triggering concept generation routines.

4. **Modular Architecture & Standards**:
   - Rebuilt portal into 15 modular subviews under `frontend/src/components/portal/`.
   - Maintained strict commit attribution rules (no AI trailers) and 4-surface documentation sync.
