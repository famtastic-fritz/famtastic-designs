# Tighten Up Your Locs — client-specific proof set v2

**Private owner build. Not published. Nothing here connects a calendar, collects payment, registers a domain, or changes the client record.**

The root page is now a Shay-first, mobile public-site build. It keeps the normal website foundation visible—branded domain treatment, book, contact, find-us, services, and a phone quick-action dock—while the owner command center is a separate, honest surface.

The earlier direction pages are retained as internal visual-route evidence, not surfaced to Shay’s site visitors:

| Direction | What it is proving | Conversion path |
| --- | --- | --- |
| [The Retightening Ledger](ledger/index.html) | A Sisterlock client needs a clear rhythm through every stage of care. | Self-contained owner-reviewed request |
| [The Appointment Room](room/index.html) | The real growth lever is an easy, owner-controlled opening when availability changes. | Availability request; no calendar claim |
| [The Established Archive](archive/index.html) | Her work should feel like personal, enduring craft—not a commodity service list. | Service orientation + direct contact path |

The generated photographs are clearly **private concept visuals**. They are not Shay's portfolio, testimonials, reviews, results, or client imagery. Replace them with authorized work before launch.

The internal [business growth plan](shay-growth-plan.md) turns the research into a staged “tech + grow” plan and maps each move to a FAMtastic capability. The exact build and backend boundary are in [build-dna.json](build-dna.json).

## Functional boundary

The visual request form validates on the client and is wired to the new `/api/booking-request/tighten-up-your-locs` backend contract only when a separately approved deployed API is configured. The endpoint is opt-in: no site is enabled by code or migration. A request is stored for owner review; it never creates a calendar event or takes payment. The public availability layer is likewise disabled until the authenticated Owner Desk and launch configuration are approved.
