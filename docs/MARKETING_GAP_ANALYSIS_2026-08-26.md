# FAMtastic Designs — Marketing & Operations Gap Analysis

**Date:** 2026-08-26  
**Sources:** awesome-marketing, coreyhaines31/marketingskills, hyperfx.ai marketing skills ranking, user strategic dump  
**Scope:** Revenue path, lead operations, marketing platform, email center, campaign execution

---

## Executive Summary

FAMtastic Designs has the **foundation** (Drupal backend, product catalog, Stripe, Postiz, 300+ leads, proof workflow) but is missing the **connective tissue** that turns leads into revenue. The gaps cluster into 7 work streams. This document maps what EXISTS, what's MISSING, and the build priority based on revenue impact.

---

## Stream 1: Revenue / Checkout Path 🔴 CRITICAL

### What EXISTS
| Component | Location | State |
|---|---|---|
| Product catalog | `backend/config/famtastic-products.json` | 15 products defined |
| Stripe integration | Live in prod | `sk_live_...` in settings.local.php |
| Purchase page | `frontend/src/pages/PurchasePage.jsx` | Works for `?request=` flow only |
| Commerce fulfillment | `CommerceLifecycleService.php` | Creates entitlements, projects, intake |
| Add-on definitions | `famtastic-products.json` | 13 add-ons with pricing |

### What's MISSING / BROKEN
| Gap | Impact | Fix Complexity |
|---|---|---|
| **Direct add-on purchase** — `PurchasePage` only accepts `?request=` param. Existing customers can't buy add-ons without a website request. | 🔴 Blocks upsell revenue | Medium |
| **Order #15 proof gap** — Direct Commerce purchases bypass proof generation because `enqueueProjectProofs()` requires `website_request_public_id` + `proof_review_status='selected'` | 🔴 Customer gets no proofs | Medium |
| **No post-purchase upsell UI** — `PaymentReturnPage` has no "upgrade to Business" or "add revision round" prompt | 🟡 Missed revenue | Low |
| **No purchase history in portal** — Billing tab shows orders but not SKU breakdown | 🟡 Customer confusion | Low |
| **No Stripe Customer Portal link** — You confirmed it's configured, but portal Billing tab has no "Manage subscription" button | 🟡 Support burden | Low |
| **"FAMtastic by the Numbers" not in catalog** — Separate app, not wired into commerce | 🟡 Can't sell through same checkout | Low |

### Recommended Fix Order
1. Add `?sku=` support to `PurchasePage` for direct add-on purchase
2. Add fallback proof generation path for direct Commerce purchases
3. Add Stripe Customer Portal link to Billing tab
4. Add purchase history with SKU breakdown
5. Add post-purchase upsell banner

---

## Stream 2: Lead Capture & Intake 🔴 CRITICAL

### What EXISTS
| Component | Location | State |
|---|---|---|
| Solution finder | Frontend React | Collects business name, industry, location, budget, pages, timeline, email, phone |
| Prospect table | `famtastic_prospect` | 300+ leads with discovery data |
| Prospect intake | `IntakeListBuilder.php` | Admin UI for viewing intakes |
| Lead ingestion | `LeadIngestionService.php` | Imports leads into prospect table |

### What's MISSING
| Gap | Impact | Fix Complexity |
|---|---|---|
| **No lead magnet / free tool** — No calculator, audit, or downloadable to capture emails before purchase intent | 🔴 300 leads but no nurture mechanism | Medium |
| **No intake forms for add-ons** — AI chatbot, shopping cart, hosting-only have no intake schemas or forms | 🔴 Can't fulfill add-on orders | Medium |
| **No service-specific intake** — Each service (AI agent, scheduling, SEO) needs its own intake form with relevant questions | 🟡 Generic intake misses details | Medium |
| **Prospect discovery → website request conversion** — Valerie has discovery answers but no `famtastic_project_request` record | 🟡 Portal shows "no request connected" | Low |
| **No lead scoring** — All 300 leads treated equally | 🟡 Can't prioritize outreach | Low |
| **No contact form submission viewer** — Admin can't see raw form entries | 🟡 Manual lookup only | Low |

### Recommended Fix Order
1. Auto-create website request from prospect discovery data on registration
2. Build intake schemas for all add-on products (`ai_site_agent_v1`, `appointment_scheduling_v1`, etc.)
3. Add lead scoring (budget, timeline, business type, engagement)
4. Build admin contact form submission viewer
5. Create a lead magnet (free website audit, local SEO checker, etc.)

---

## Stream 3: Email Center 🔴 CRITICAL

### What EXISTS
| Component | Location | State |
|---|---|---|
| Notification outbox | `famtastic_notification_outbox` table | Queued/retry/dead_letter status |
| Operations dashboard | `/admin/famtastic/metric/notifications` | Lists notifications with retry controls |
| Mail sending | `hook_mail()`, PHPMailer→SMTP | Functional |
| Support draft | `SupportDraftService.php` | L0 draft generation |

### What's MISSING
| Gap | Impact | Fix Complexity |
|---|---|---|
| **No email template library** — All emails hardcoded in PHP (`CommerceLifecycleService::queueNotifications()`) | 🔴 Can't create/edit templates without code deploy | Medium |
| **No lead nurture sequences** — 300 leads, no automated email flow | 🔴 Leads go cold | Medium |
| **No sequence builder** — No "Day 0 welcome → Day 3 value → Day 7 offer" logic | 🔴 No systematic follow-up | Medium |
| **No email preview/test send** — Can't see what emails look like before they go out | 🟡 Risk of broken emails | Low |
| **No campaign email management** — 17-day campaign has records but no email content tied to them | 🟡 Campaign is shell-only | Medium |
| **No manual nudge capability** — "I want to nudge a potential client" has no UI | 🟡 Manual SQL or nothing | Low |

### What a Good Email Center Needs (from awesome-marketing + marketingskills research)

**Core capabilities:**
1. **Template library** — CRUD for transactional, operational, marketing emails
2. **Sequence builder** — Visual flow: trigger → delay → email → condition → branch
3. **Segmentation** — By lead source, status, engagement, product interest
4. **A/B testing** — Subject lines, send times, content variants
5. **Analytics** — Open rates, click rates, conversion attribution
6. **Compliance** — Unsubscribe, GDPR, CAN-SPAM

**Sequences needed for FAMtastic:**
- **Lead nurture** (prospects who haven't purchased): Day 0 welcome, Day 3 case study, Day 7 proof offer, Day 14 last chance
- **Post-purchase onboarding** (new customers): Day 0 receipt + intake link, Day 3 intake reminder, Day 7 proof ready
- **Renewal** (existing customers): 30 days before expiry, 7 days before, day of, post-renewal thank you
- **Win-back** (lapsed): 30 days after expiry, 60 days, 90 days

### Recommended Fix Order
1. Create `famtastic_email_template` entity/table with CRUD admin UI
2. Build sequence engine (hardcoded V1 for lead nurture)
3. Connect sequence triggers to prospect/customer lifecycle events
4. Add email preview + test send
5. Build campaign email editor for the 17-day campaign

---

## Stream 4: Marketing Platform / Campaign Execution 🟡 HIGH

### What EXISTS
| Component | Location | State |
|---|---|---|
| Postiz | Local via ngrok | 5 channels connected (TikTok, X, IG, FB, YouTube) |
| Campaign records | `famtastic_social_record` | 68 records, 12 bound to Facebook, 56 unbound |
| Channel health card | Campaign Operations dashboard | Shows live per-channel state |
| Postiz API key | Prod settings.local.php | Minted, working |

### What's MISSING
| Gap | Impact | Fix Complexity |
|---|---|---|
| **No content pipeline** — 56 unbound campaign records have no content/media assets | 🟡 Can't execute multi-channel campaign | High |
| **No AI blogger** — No automated blog generation | 🟡 No organic content engine | Medium |
| **No asset generation workflow** — HyperFrames, Remotion, OpenArt mentioned in CAPABILITY_REGISTRY but not wired | 🟡 Asset creation is one-off manual | High |
| **No copy recipes/templates** — No reusable templates for social posts by channel | 🟡 Inconsistent messaging | Low |
| **No UTM tracking** — Can't track which campaign/ channel drove which lead | 🟡 No attribution | Low |
| **No campaign calendar** — No editorial calendar connecting blog → social → email | 🟡 Disjointed execution | Medium |

### What a Good Marketing Platform Needs (from research)

**Content factory:**
1. **Blog engine** — AI-assisted drafting, human approval, scheduled publishing
2. **Social repurposing** — One blog → 5+ platform-native posts
3. **Asset pipeline** — Blog → video script → video asset → thumbnail
4. **Copy library** — Templates by channel, tone, CTA

**Campaign management:**
1. **Campaign builder** — Goal, audience, channels, timeline, budget
2. **Asset assignment** — Link records to actual content
3. **Scheduling** — Postiz integration for queueing
4. **Tracking** — UTM parameters, conversion pixels

**Analytics:**
1. **Channel performance** — Per-channel engagement, reach, clicks
2. **Lead attribution** — Which campaign/channel sourced each lead
3. **ROI tracking** — Campaign cost vs. revenue generated

### Recommended Fix Order
1. Build asset assignment UI for the 56 unbound campaign records
2. Create UTM parameter system for all outbound links
3. Add copy recipe library (hardcoded V1 templates)
4. Wire HyperFrames/Remotion for blog→video pipeline
5. Build editorial calendar connecting blog → social → email

---

## Stream 5: Pricing & Packaging Strategy 🟡 HIGH

### What EXISTS
| Component | Location | State |
|---|---|---|
| Product catalog | `famtastic-products.json` | 15 products with prices |
| Deal terms | `famtastic-deal-terms.json` | Policy + per-SKU deal definitions |
| Renewal SKUs | In catalog | FAM-HOST-999, FAM-HOST-BUSINESS-1999 |

### What's MISSING
| Gap | Impact | Fix Complexity |
|---|---|---|
| **No pricing experiments** — All prices are static | 🟡 Can't test $199 vs $249 vs $299 | Low |
| **No bundling logic** — "Website + SEO + Analytics" as a package discount | 🟡 Missed bundle revenue | Medium |
| **No dynamic pricing** — No private offers, no grant codes beyond basic | 🟡 Can't flex for enterprise | Low |
| **No competitor pricing intelligence** — No tracking of what others charge | 🟡 Pricing in a vacuum | Low |

### Insights from Research (marketingskills `pricing` skill)

**Pricing strategy frameworks:**
1. **Value-based pricing** — Price based on customer ROI, not cost-plus
2. **Anchoring** — Show $499 Business next to $199 Web Basics to make $199 feel like a deal
3. **Decoy pricing** — Add a middle tier that makes the target tier obvious
4. **Freemium / free tool** — Lead magnet that demonstrates value before purchase
5. **Annual prepay discount** — 2 months free for annual hosting

**FAMtastic-specific recommendations:**
- The $199 special is good anchor pricing
- Add a "Popular" badge to Business ($499) to drive upsell perception
- Create a "Complete Digital Presence" bundle ($799) that includes website + SEO + analytics + scheduling
- Offer annual hosting at $99/year (vs $9.99/month = $119.88) for prepay discount

---

## Stream 6: CRO & Landing Page Optimization 🟡 HIGH

### What EXISTS
| Component | Location | State |
|---|---|---|
| React frontend | `frontend/src/pages/` | Home, Services, Packages, Contact, Proof, etc. |
| Solution finder | Frontend | Multi-step intake form |
| Google Analytics | Configured | Property ID in settings |

### What's MISSING
| Gap | Impact | Fix Complexity |
|---|---|---|
| **No A/B testing** — Can't test headlines, CTAs, pricing display | 🟡 Blind to what converts | Medium |
| **No exit-intent capture** — Visitors leaving without converting have no recovery | 🟡 Lost leads | Low |
| **No social proof integration** — No testimonials, case studies, trust badges on key pages | 🟡 Low conversion trust | Low |
| **No heatmap / session recording** — Don't know where users drop off | 🟡 Can't diagnose friction | Low |
| **No mobile-specific optimization** — Solution finder may not be mobile-optimized | 🟡 Mobile traffic lost | Medium |

### Insights from Research (marketingskills `cro`, `signup` skills)

**CRO fundamentals for FAMtastic:**
1. **Above the fold** — Value prop + CTA visible without scrolling
2. **Social proof** — "200+ websites launched", "4.9/5 rating", client logos
3. **Risk reversal** — Money-back guarantee, "no credit card required"
4. **Urgency** — "Limited spots this month", countdown timer
5. **Clear CTA** — One primary action per page, contrasting color

**Specific fixes for FAMtastic:**
- Add testimonial carousel to homepage
- Add trust badge bar (SSL secure, money-back guarantee, 24h support)
- Add live chat widget for real-time questions
- Add "Join 200+ happy customers" social proof

---

## Stream 7: Analytics & Attribution 🟡 HIGH

### What EXISTS
| Component | Location | State |
|---|---|---|
| Google Analytics | Configured | Property ID in prod settings |
| Operations dashboard | `/admin/famtastic/operations` | Worker status, notification counts |
| Campaign records | `famtastic_social_record` | 68 records with provider_state |

### What's MISSING
| Gap | Impact | Fix Complexity |
|---|---|---|
| **No UTM tracking** — Can't attribute leads to campaigns | 🔴 Don't know what marketing works | Low |
| **No conversion tracking** — Don't know which page/CTA drives purchases | 🟡 Can't optimize funnel | Low |
| **No cohort analysis** — Don't know LTV by acquisition channel | 🟡 Can't optimize spend | Medium |
| **No marketing dashboard** — No unified view of leads, conversions, revenue by source | 🟡 Flying blind | Medium |

### Recommended Fix Order
1. Add UTM parameter tracking to all outbound links (social, email, ads)
2. Add conversion events (solution-finder complete, purchase, proof-selection)
3. Build marketing dashboard showing: leads by source, conversion rate, revenue by channel
4. Add cohort tracking (monthly acquired customers, their LTV, churn)

---

## Consolidated Build Priority

### Phase 1: Money Path (Week 1)
1. ✅ Fix user registration + role assignment (DONE)
2. ✅ Backfill existing users (DONE)
3. ⏳ Fix `PurchasePage` for direct add-on purchase (`?sku=`)
4. ⏳ Fix proof generation for direct Commerce purchases
5. ⏳ Add Stripe Customer Portal link to Billing tab

### Phase 2: Lead-to-Revenue (Week 2)
6. ⏳ Auto-create website request from prospect discovery on registration
7. ⏳ Build intake schemas for all add-on products
8. ⏳ Create email template entity + admin UI
9. ⏳ Build lead nurture sequence (hardcoded V1)

### Phase 3: Marketing Engine (Week 3-4)
10. ⏳ Asset assignment for 56 unbound campaign records
11. ⏳ UTM tracking system
12. ⏳ Copy recipe library
13. ⏳ Editorial calendar

### Phase 4: Optimization (Week 5-6)
14. ⏳ A/B testing framework
15. ⏳ Pricing experiments (bundles, annual discount)
16. ⏳ Social proof integration
17. ⏳ Marketing dashboard

---

## Appendix: Marketing Skills Inventory

From `coreyhaines31/marketingskills` research, these skills are relevant to FAMtastic:

| Skill | Relevance | Already Have? |
|---|---|---|
| `product-marketing` | Positioning, ICP, messaging | Partial (product catalog) |
| `cro` | Landing page optimization | ❌ No |
| `copywriting` | Headlines, CTAs, value props | ❌ Hardcoded only |
| `seo-audit` | Technical/on-page SEO | Partial (Metatag module?) |
| `ai-seo` | AI search optimization | ❌ No |
| `emails` | Email sequences, automation | ❌ No |
| `social` | Social content, scheduling | Partial (Postiz connected) |
| `analytics` | Event tracking, attribution | Partial (GA configured) |
| `pricing` | Packaging, monetization | ❌ Static only |
| `content-strategy` | Blog strategy, calendar | ❌ No |
| `customer-research` | Personas, interviews | Partial (prospect data) |
| `competitors` | Comparison pages | ❌ No |
| `referrals` | Referral program | ❌ No |
| `launch` | Product launches | ❌ No |
| `lead-magnets` | Free tools for lead gen | ❌ No |

**Conclusion:** FAMtastic has ~30% of a full marketing stack. The missing 70% is what converts the 300 leads and 5 social channels into revenue.
