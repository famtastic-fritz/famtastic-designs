# Brief — what-happens-when-first-year-hosting-ends

## Target reader
A Web Basics ($199) or Business Website ($499) customer approaching the end
of their included first year of hosting, or a prospective customer asking
"what happens after the free year" before they buy.

## Search intent
Direct/transactional-adjacent question: "what happens after my free hosting
year", "does my website stop working after a year", "how much does hosting
cost after the first year". They want the exact numbers and the exact
mechanism, not a vague "don't worry about it."

## Key takeaway
Nothing happens automatically and nothing gets charged without explicit,
separate authorization first. After the included 365 days, basic managed
hosting becomes a $9.99/month recurring charge (or $19.99/month for the
Business bundle) — but it only activates after the customer separately
authorizes amount, interval, start date, and cancellation method. Domain
renewal is billed separately from hosting, at the real registrar price
disclosed before payment (since registry pricing varies), and only applies
if FAMtastic registered a new domain — customers who connected their own
existing domain never get a FAMtastic domain-renewal charge at all.

## Internal links (>=3 to services/packages)
1. https://famtasticdesigns.com/packages/199-quick-start
2. https://famtasticdesigns.com/packages
3. https://famtasticdesigns.com/contact
4. (in-body) companion post: /blog/what-does-199-website-include

## Evidence list (verified live)
- docs/CUSTOMER_TERMS_AND_LAUNCH_APPROVAL.md "Web Basics Bundle decision
  record": 12 months hosting included from launch; new-domain path includes
  first-year registration of one standard domain; existing-domain path
  creates no FAMtastic domain-renewal charge; Month 13 hosting is $9.99
  monthly, "activated only after separate affirmative authorization showing
  amount, interval, start date, and cancellation method"; Year 2 domain
  renewal is due prepaid at the disclosed registrar price, separate from
  hosting.
- backend/config/famtastic-products.json: FAM-HOST-999 ($9.99/mo, activation
  "after_included_period") and FAM-HOST-BUSINESS-1999 ($19.99/mo) confirm
  the recurring hosting SKUs and prices match the terms doc exactly.
- docs/CUSTOMER_TERMS_AND_LAUNCH_APPROVAL.md cancellation policy: recurring
  services may be cancelled before the next billing date; cancellation stops
  future charges but the current paid period is not prorated.

## Claims policy
No invented renewal price beyond the documented $9.99/$19.99 hosting figures
and the explicit statement that domain renewal price is disclosed at time of
renewal (not fixed here, since registry pricing varies — this article does
not invent a domain price). No uptime/speed promises. All terms sourced only
from docs/CUSTOMER_TERMS_AND_LAUNCH_APPROVAL.md and
backend/config/famtastic-products.json.
