# Client messaging design preview

Approved by Fritz on September 14, 2026 before messaging implementation began.

The preview follows the repository root `design.md`: dark canvas, readable two-pane conversations, explicit next actor, linked project/proof context, minimum 44px controls and one primary glow. It is a sample conversation, not a production record.

Provider: built-in image generation tool. The tool does not report its underlying model identity. OpenArt GPT Image 2.5 Flare was requested first at 2K, 16:9, high quality, one image; its quoted cost was 170 credits and it rejected the request for insufficient balance. Fritz explicitly authorized another available route. No claim is made that the fallback image used GPT Image 2.5.

Artifact: `mockup.png`.

## Prompt

Use case: ui-mockup. Create one high-fidelity FAMtastic Designs messaging-system product mockup board, widescreen 16:9 at 2K. This is a proposed interface, not evidence of a deployed product. Top small board label must read "DESIGN PREVIEW · Sample conversation". Show a large desktop admin application occupying about 75% of width and a clean mobile customer portal occupying 25%, side by side, no device photorealism or dramatic perspective. Crisp straight-on SaaS UI with readable Inter typography.

Follow exact root design.md tokens: canvas #070907; panels #101310 to #141814; borders #252b25; primary text near-white; secondary text muted gray-green; primary action lime #7cfc00. Minimum 44px controls and restrained 18px corners. ONE subtle lime glow for the active Send reply button only, no other glows. No gradients, decorative neon, charts, fake metrics, ornamental hero, giant cards or floating 3D art.

Desktop header: FAMtastic | Inbox. Small account label Fritz Medine. Left navigation rail: Today, Inbox (selected with small unread dot), Projects, Orders, Customers. Main workspace title "Messages". Subtitle "Know who needs a reply." Tabs "Needs reply", "Unread", "Waiting", "All". Desktop conversation hub has left thread list with one selected example named "Kakes By Kesline", small source "Contact form", outlined "Needs your reply" label, short neutral placeholder "Website inquiry". Beneath is a quiet empty-state line "All other conversations appear here." Center thread header "Kakes By Kesline" with subtitle "Website inquiry · Contact form", and a clear handoff strip "Your turn to reply". A context line links "Website project" and "View proofs". Show a single incoming sample bubble labelled "Kesline · Customer" and body "Sample message — the original contact inquiry appears here." A small timeline entry "Contact form received". Composer has tabs "Reply to client" (selected) and "Internal note". Composer placeholder "Write a reply to Kesline…" with attachment icon, near footer text "Reply appears in the client portal. Email status is tracked separately." One lime primary button "Send reply". A modest context panel on the right of the desktop section reads "Website project", "Proofs: Not attached", "Delivery: Not queued", and a plain link "Open project". Keep these honest current proof state labels, never claim sent or ready, and do not show fabricated actual proof art.

Mobile customer portal header "FAMtastic" and title "Messages". Below "Kakes By Kesline" and text "Your conversation with FAMtastic". Show the same neutral sample inquiry in customer authorship, status "Waiting for FAMtastic", a compact linked row "Website project →", composer "Write a message…" and a muted outlined Send button. Bottom navigation "Home", "Projects", "Messages" (selected), "Account". Footer below entire board "One conversation · Clear next action · Admin and portal". Beautiful compact useful information hierarchy, pixel-perfect spacing, large enough type to read.
