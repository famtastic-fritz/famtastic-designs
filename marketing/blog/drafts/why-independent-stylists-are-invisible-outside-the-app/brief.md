# Brief — why-independent-stylists-are-invisible-outside-the-app

## Target reader
An independent hair stylist, braider, barber, nail tech, or esthetician whose entire online presence is a booking-app profile plus an Instagram account. Expert at their trade, beginner on anything web. Busy, not failing — which is exactly why the problem is invisible to them.

## Search intent
Problem-aware but not solution-aware: "why can't people find my salon on Google", "do I need a website if I have Booksy", "how do new clients find a stylist". They want to understand why their name does not appear, not to be sold a website.

## Key takeaway
An independent stylist whose only presence is a booking-app profile is not invisible because the profile is unindexed — marketplace profiles often do appear in search. They are invisible because nothing in that result is theirs: the web address, the layout, and the search credit all belong to the app, and the other stylists on the platform compete on the same page. One page on a domain you own changes what a stranger finds and who owns the relationship that follows.

## Series position
Order 1 of "The Booking App and Your Own Website Series". This is the doorway post and the only one in the series written to a named trade, because it is the landing page for the Ghost Town campaign, whose tracked links already point at this slug. Orders 2-4 deepen the same argument for any business booked through an app rather than rotating to a different trade — rotating trades would produce four posts making one argument to four audiences, which is cannibalization, not a series.

## Internal links (>=3 to services/packages)
1. https://famtasticdesigns.com/packages/199-quick-start
2. https://famtasticdesigns.com/packages
3. https://famtasticdesigns.com/contact
4. https://famtasticdesigns.com/blog/why-running-business-on-gmail-and-linktree-costs-revenue

All four returned HTTP 200 on 2026-09-04 before being written into the draft.

## Evidence list (verified live)
- Scope, price, and deliverables of the Web Basics Bundle: https://famtasticdesigns.com/packages/199-quick-start (live page states one focused one-page website, mobile-responsive design, lead-capture form, foundational search and indexing setup, first-year basic managed hosting, and new-domain registration for year one or existing-domain connection).
- Renewal terms: `backend/config/famtastic-products.json` — FAM-FOOT-199 one-time with a 365-day included period, renewal SKU FAM-HOST-999 at $9.99/month, `domain_renewal_separate: true`.
- Appointment scheduling is a separate add-on: `backend/config/famtastic-products.json` — FAM-SCHEDULING, $149, one-time, type `add_on`.
- The "you own none of the traffic" and "profile competes on the same page" points are described as mechanisms, not backed by any statistic, matching this project's no-invented-numbers rule.

## Claims policy
- No statistics. Nothing about what percentage of stylists lack a website, or what a search would return.
- Two corrections to the campaign seed article, both made deliberately: it claimed a **branded email address** was included in Web Basics (it is not — Business Email Setup is a separate $99 add-on, FAM-BUSINESS-EMAIL) and it implied the bundle provides **direct booking** (it does not — the bundle includes a lead-capture form; Appointment Scheduling is a $149 add-on).
- The seed article's "there's nothing for Google to find" was softened, because marketplace profile pages frequently are indexed. The honest mechanism is ownership, not absence. Overclaiming here would have been correctable by any reader who searched their own profile name.
- Booking apps are named once, neutrally, for reader recognition only. No evaluative or comparative claim is made about any named company, matching how the Linktree pattern is handled in the gmail/linktree post.
- Renewal terms disclosed in full wherever the first-year price appears.
- No ranking, traffic, speed, or revenue promise anywhere in the post.
