# Phone owner workflow — release-matched acceptance

The campaign leads with running useful business work from a phone. Sample Lab includes a local interactive owner walkthrough tailored to the three approved inquiry types. It reuses the current owner/request/status/content interaction vocabulary, not a second connected business platform.

Source evidence: `frontend/src/components/owner-desk/OwnerDesk.jsx` and `frontend/src/api/bookingOwnerAdapter.js` provide linked-site request status actions; `PortalBookingRequestsView.jsx` requires a linked owner-operated site. `usePortalInbox.js` provides authenticated conversations with FAMtastic. `CustomerPortalDashboard.jsx` and `PortalPageContentFields.jsx` save website content submissions and review project state. These source contracts do not establish a prospect's connected inbox or inclusion of every feature in the $199 package.

The walkthrough deliberately uses one fictional customer and page-local state. Open a request, change status, save a sample reply and save inquiry instructions. Every screen states that no message is sent, no website is published, and reload resets the local changes. Customer-inquiry reply delivery is illustrative here; calendar sync, ecommerce, subscriptions and broader management scope remain separately confirmed. Actual connected customer workflows must be accepted against their own release.

| Task | Saved result | Public/provider consequence | Developer evidence | Owner result |
| --- | --- | --- | --- | --- |
| Open sample inquiry | Page-local opened state | None | Playwright actual interactions at 390x844 and1440x1000 | Pending |
| Change request to Reviewing | Page-local status | None | Control changed and visible result checked | Pending |
| Save sample reply | Page-local text with explicit unsent notice | None | Form filled/submitted; notice checked; zero API writes | Pending |
| Save website inquiry instructions | Page-local content with unpublished notice | None | Form filled/submitted; notice checked; zero API writes | Pending |
| Reload | All practice edits reset | None | By design; owner to verify | Pending |
| Continue to account/interview | Exact invitation email and durable server context | Registration/verification only; sending held | Separate Drupal evidence package | Pending |

Owner acceptance: open the reviewed Sample Lab release on a real phone, complete the four actions without coaching, describe where each result is saved, reload to confirm practice reset, then continue into the authenticated interview. Record exact commit, viewport/device, screenshots, observed saved result and any confusion. Static screenshots are not acceptance. Developer browser emulation is labeled as such; a physical phone result is not yet recorded.

Review classification: changed source candidate. No hosted release or owner acceptance is claimed.
