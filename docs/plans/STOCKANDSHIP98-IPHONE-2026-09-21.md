# iPhone proof walkthrough — September 21, 2026

The owner reported that the customer could not find the three concepts on his
iPhone. He authorized one screenshot walkthrough to the existing verified
customer and requested both that email and the preceding reminder copied to him.

**Completed:** the original reminder's exact plain text and rendered HTML were
copied to the verified owner mailbox at **21:42:24 UTC**. Gmail message
`1a0c5eb8f769e20f` was verified in that inbox. The original outbox 842 was not resent
to the customer or changed.

The walkthrough, **Finding your 3 StockandShip98 concepts on iPhone**, was sent to
the verified customer with the verified owner copied at **21:54:47 UTC** (5:54 PM
Eastern). Gmail message `1a0c5f6e78302602` was read back with the correct To/CC and
all four inline JPEG filenames, MIME types and byte sizes; the owner inbox copy
was confirmed. The customer's inbox, readership and successful navigation remain
unverified.

## Finding the concepts

In the portal, tap **Projects** at the bottom, or **Menu → My Projects**. Open
**Stockandship98.com** if a project list appears. Use **Review 3 directions** or
**Concepts**, then scroll down past the research and growth-plan text to the cards.
Tap **Open working concept** under **The Exchange**. The full concept opens in a
new tab; return to the portal tab to compare. Swipe left across the cards to reach
**Curated Finds**, then **The Drop**. Choosing a direction is a separate action;
the walkthrough did not select one for the customer.

## Evidence and limits

The production customer session and workspace controllers were invoked under
customer 15's account permissions using a temporary process-local account switch.
Both returned 200, with request 17 unarchived, customer-ready and unselected, and
all three variants present. All three protected HTML documents and six declared
assets were retrieved under those permissions; asset hashes matched.

The screenshots replay those customer responses through the currently deployed
frontend files on a local read-only server. Contact labels were hidden. No live
customer browser session or physical iPhone/Safari was used. The email explicitly
labels the images as iPhone-size guide screenshots from a read-only preview.
No custom view-as-customer route was found in current application source; the
production Drupal masquerade module was disabled. This is a support-tool gap,
not permission to create a customer login or change credentials.

The current layout places a long research section before the concepts and shows
the cards in a horizontally scrolling row on phones. Those are likely points of
confusion; the customer's exact browser state is not yet known. The screenshots
show the existing interface, not a deployed usability repair.

The email uses the existing deployed **BrandedEmail** shell, the canonical PNG
and footer credit, one named account-project button, plain-text fallback and Shay
signature. Gmail sent the support email with four inline screenshot MIME parts.
Browser checks at 1280px and 390px found all eight images loaded, no horizontal
overflow and a 56px project button. Actual mail-client rendering was not tested.

[Redacted delivery evidence and screenshot manifest](../evidence/stockandship98-iphone-2026-09-21/delivery-receipt.redacted.json).
The original screenshots are alongside that receipt. Private email bodies,
recipient details and raw customer payloads remain outside Git.
