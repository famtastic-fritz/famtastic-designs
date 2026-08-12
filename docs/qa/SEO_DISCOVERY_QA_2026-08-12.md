# FAMtastic SEO and discovery QA — 2026-08-12

This is an independent repository-and-production audit. Scores are internal QA heuristics, not Google rankings.

## Executive result

- Production sitemap: **103 valid HTTPS URLs**, all audited individually.
- Canonical content manifest: **80 articles**, all audited individually.
- Initial-HTML page score: **85.1/100**.
- Canonical article SEO/content score: **93.8/100**.
- Cannibalization candidates requiring human/GSC confirmation: **0**.
- Robots declares the working sitemap and excludes customer/admin routes.

## Sitewide findings

1. **Critical content is still client-rendered.** Every sitemap route has metadata shells, but primary H1/body content is absent from initial HTML. Pre-rendering remains the largest technical SEO opportunity.
2. **Dynamic social metadata was inconsistent in generated shells.** The source fix in this QA pass now synchronizes Open Graph/Twitter descriptions and types for service, package, case-study, and blog shells.
3. **Structured data needed route-level coverage.** The source fix now emits initial-HTML Organization/WebSite, WebPage, BreadcrumbList, BlogPosting, Service, Product, or Article entities as appropriate.
4. **Several descriptions are mechanically written or truncated.** These are listed page-by-page below and should be rewritten before the next content promotion cycle.
5. **Cannibalization risk is concentrated in closely related package, portal, analytics, and website-strategy topics.** Do not merge solely from this heuristic; verify competing queries in Search Console first.

## Production page scorecard

| Page | Type | Score | Result | Primary findings |
|---|---|---:|---|---|
| [/packages/business-website/](https://famtasticdesigns.com/packages/business-website/) | packages | 70 | revise | Title length is 71 characters; Meta description length is 101 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/packages/website-care-plan/](https://famtasticdesigns.com/packages/website-care-plan/) | packages | 70 | revise | Title length is 83 characters; Meta description length is 91 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/](https://famtasticdesigns.com/) | home | 80 | revise | Meta description length is 201 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/199-website-inclusions-and-boundaries/](https://famtasticdesigns.com/blog/199-website-inclusions-and-boundaries/) | blog | 80 | revise | Title length is 71 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/account-specific-special-pricing/](https://famtasticdesigns.com/blog/account-specific-special-pricing/) | blog | 80 | revise | Title length is 78 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/ai-agent-customer-documentation/](https://famtasticdesigns.com/blog/ai-agent-customer-documentation/) | blog | 80 | revise | Title length is 73 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/contact-form-vs-quote-form-vs-assessment/](https://famtasticdesigns.com/blog/contact-form-vs-quote-form-vs-assessment/) | blog | 80 | revise | Title length is 82 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/custom-website-vs-package/](https://famtasticdesigns.com/blog/custom-website-vs-package/) | blog | 80 | revise | Title length is 72 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/failed-payments-refunds-cancellations/](https://famtasticdesigns.com/blog/failed-payments-refunds-cancellations/) | blog | 80 | revise | Title length is 80 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/how-many-pages-small-business-website/](https://famtasticdesigns.com/blog/how-many-pages-small-business-website/) | blog | 80 | revise | Meta description length is 91 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/idempotency-business-automation/](https://famtasticdesigns.com/blog/idempotency-business-automation/) | blog | 80 | revise | Title length is 81 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/lead-follow-up-schedule/](https://famtasticdesigns.com/blog/lead-follow-up-schedule/) | blog | 80 | revise | Title length is 85 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/lead-nurture-not-ready-to-buy/](https://famtasticdesigns.com/blog/lead-nurture-not-ready-to-buy/) | blog | 80 | revise | Title length is 74 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/one-page-vs-multi-page-website/](https://famtasticdesigns.com/blog/one-page-vs-multi-page-website/) | blog | 80 | revise | Title length is 71 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/portal-communication-preferences/](https://famtasticdesigns.com/blog/portal-communication-preferences/) | blog | 80 | revise | Title length is 73 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/portal-product-documentation-learning/](https://famtasticdesigns.com/blog/portal-product-documentation-learning/) | blog | 80 | revise | Title length is 77 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/portal-projects-files-approvals/](https://famtasticdesigns.com/blog/portal-projects-files-approvals/) | blog | 80 | revise | Title length is 77 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/portal-support-vs-contact-form/](https://famtasticdesigns.com/blog/portal-support-vs-contact-form/) | blog | 80 | revise | Title length is 73 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/portal-team-members-business-accounts/](https://famtasticdesigns.com/blog/portal-team-members-business-accounts/) | blog | 80 | revise | Title length is 82 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/product-addon-entitlement-difference/](https://famtasticdesigns.com/blog/product-addon-entitlement-difference/) | blog | 80 | revise | Title length is 76 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/prove-ecommerce-before-live-payments/](https://famtasticdesigns.com/blog/prove-ecommerce-before-live-payments/) | blog | 80 | revise | Title length is 82 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/reduce-form-friction-without-bad-leads/](https://famtasticdesigns.com/blog/reduce-form-friction-without-bad-leads/) | blog | 80 | revise | Title length is 71 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/safe-ai-agent-actions/](https://famtasticdesigns.com/blog/safe-ai-agent-actions/) | blog | 80 | revise | Title length is 79 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/small-business-analytics-dashboard/](https://famtasticdesigns.com/blog/small-business-analytics-dashboard/) | blog | 80 | revise | Title length is 74 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/small-business-lead-response-time/](https://famtasticdesigns.com/blog/small-business-lead-response-time/) | blog | 80 | revise | Title length is 79 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/small-business-lead-stages/](https://famtasticdesigns.com/blog/small-business-lead-stages/) | blog | 80 | revise | Title length is 73 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/small-business-website-conversion/](https://famtasticdesigns.com/blog/small-business-website-conversion/) | blog | 80 | revise | Meta description length is 42 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/track-pages-campaigns-create-leads/](https://famtasticdesigns.com/blog/track-pages-campaigns-create-leads/) | blog | 80 | revise | Title length is 71 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/understand-website-traffic-sources/](https://famtasticdesigns.com/blog/understand-website-traffic-sources/) | blog | 80 | revise | Title length is 72 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/website-events-business-workflows/](https://famtasticdesigns.com/blog/website-events-business-workflows/) | blog | 80 | revise | Title length is 76 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/website-project-information-checklist/](https://famtasticdesigns.com/blog/website-project-information-checklist/) | blog | 80 | revise | Title length is 81 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-is-a-domain-name/](https://famtasticdesigns.com/blog/what-is-a-domain-name/) | blog | 80 | revise | Title length is 78 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-is-included-499-business-website-bundle/](https://famtasticdesigns.com/blog/what-is-included-499-business-website-bundle/) | blog | 80 | revise | Title length is 73 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-is-website-hosting/](https://famtasticdesigns.com/blog/what-is-website-hosting/) | blog | 80 | revise | Title length is 73 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-should-thank-you-page-do/](https://famtasticdesigns.com/blog/what-should-thank-you-page-do/) | blog | 80 | revise | Title length is 74 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-should-website-contact-form-ask/](https://famtasticdesigns.com/blog/what-should-website-contact-form-ask/) | blog | 80 | revise | Meta description length is 81 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/when-to-change-website-from-analytics/](https://famtasticdesigns.com/blog/when-to-change-website-from-analytics/) | blog | 80 | revise | Title length is 72 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/why-website-leads-get-lost/](https://famtasticdesigns.com/blog/why-website-leads-get-lost/) | blog | 80 | revise | Title length is 77 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/packages/199-quick-start/](https://famtasticdesigns.com/packages/199-quick-start/) | packages | 80 | revise | Meta description length is 69 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/packages/499-site-upgrade/](https://famtasticdesigns.com/packages/499-site-upgrade/) | packages | 80 | revise | Meta description length is 101 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/packages/landing-page/](https://famtasticdesigns.com/packages/landing-page/) | packages | 80 | revise | Title length is 77 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/packages/premium-website-ai/](https://famtasticdesigns.com/packages/premium-website-ai/) | packages | 80 | revise | Title length is 76 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/services/ai-chatbot/](https://famtasticdesigns.com/services/ai-chatbot/) | services | 80 | revise | Meta description length is 56 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/services/client-portal-systems/](https://famtasticdesigns.com/services/client-portal-systems/) | services | 80 | revise | Meta description length is 57 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/services/custom-website-development/](https://famtasticdesigns.com/services/custom-website-development/) | services | 80 | revise | Meta description length is 62 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/services/e-commerce-solutions/](https://famtasticdesigns.com/services/e-commerce-solutions/) | services | 80 | revise | Meta description length is 56 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/services/landing-page-design/](https://famtasticdesigns.com/services/landing-page-design/) | services | 80 | revise | Meta description length is 55 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/services/site-rebuild/](https://famtasticdesigns.com/services/site-rebuild/) | services | 80 | revise | Meta description length is 63 characters; Primary H1/content is absent from initial HTML (client-rendered) |
| [/55-cents-a-day-website/](https://famtasticdesigns.com/55-cents-a-day-website/) | 55-cents-a-day-website | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/about/](https://famtasticdesigns.com/about/) | about | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/](https://famtasticdesigns.com/blog/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/after-buying-199-website/](https://famtasticdesigns.com/blog/after-buying-199-website/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/ai-agent-human-handoff/](https://famtasticdesigns.com/blog/ai-agent-human-handoff/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/ai-agent-knowledge-base/](https://famtasticdesigns.com/blog/ai-agent-knowledge-base/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/ai-chatbot-vs-ai-agent/](https://famtasticdesigns.com/blog/ai-chatbot-vs-ai-agent/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/analytics-and-customer-data/](https://famtasticdesigns.com/blog/analytics-and-customer-data/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/automation-failures-retries-dead-letter/](https://famtasticdesigns.com/blog/automation-failures-retries-dead-letter/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/business-website-package-explained/](https://famtasticdesigns.com/blog/business-website-package-explained/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/choose-the-right-famtastic-website-package/](https://famtasticdesigns.com/blog/choose-the-right-famtastic-website-package/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/connect-checkout-to-customer-intake/](https://famtasticdesigns.com/blog/connect-checkout-to-customer-intake/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/customer-portal-home-screen/](https://famtasticdesigns.com/blog/customer-portal-home-screen/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/customer-receipt-content/](https://famtasticdesigns.com/blog/customer-receipt-content/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/grow-beyond-first-website/](https://famtasticdesigns.com/blog/grow-beyond-first-website/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/hosting-domain-renewal-design/](https://famtasticdesigns.com/blog/hosting-domain-renewal-design/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/how-a-customer-portal-helps/](https://famtasticdesigns.com/blog/how-a-customer-portal-helps/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/how-a-website-captures-a-lead/](https://famtasticdesigns.com/blog/how-a-website-captures-a-lead/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/how-automation-prevents-lost-opportunities/](https://famtasticdesigns.com/blog/how-automation-prevents-lost-opportunities/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/landing-page-package-explained/](https://famtasticdesigns.com/blog/landing-page-package-explained/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/lead-email-reply-ingestion/](https://famtasticdesigns.com/blog/lead-email-reply-ingestion/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/measure-ai-agent-performance/](https://famtasticdesigns.com/blog/measure-ai-agent-performance/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/measure-blog-content-performance/](https://famtasticdesigns.com/blog/measure-blog-content-performance/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/measure-whether-website-works/](https://famtasticdesigns.com/blog/measure-whether-website-works/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/mobile-customer-portal-design/](https://famtasticdesigns.com/blog/mobile-customer-portal-design/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/mobile-lead-capture-design/](https://famtasticdesigns.com/blog/mobile-lead-capture-design/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/new-lead-notification-checklist/](https://famtasticdesigns.com/blog/new-lead-notification-checklist/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/overdue-lead-alerts/](https://famtasticdesigns.com/blog/overdue-lead-alerts/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/parts-of-a-one-page-business-website/](https://famtasticdesigns.com/blog/parts-of-a-one-page-business-website/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/plan-website-around-customer-questions/](https://famtasticdesigns.com/blog/plan-website-around-customer-questions/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/premium-website-ai-package-explained/](https://famtasticdesigns.com/blog/premium-website-ai-package-explained/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/professional-website-55-cents-a-day/](https://famtasticdesigns.com/blog/professional-website-55-cents-a-day/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/scheduled-workers-customer-work/](https://famtasticdesigns.com/blog/scheduled-workers-customer-work/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/starter-website-package-explained/](https://famtasticdesigns.com/blog/starter-website-package-explained/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/test-ai-website-agent/](https://famtasticdesigns.com/blog/test-ai-website-agent/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/test-business-automation-safely/](https://famtasticdesigns.com/blog/test-business-automation-safely/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/track-website-calls-to-action/](https://famtasticdesigns.com/blog/track-website-calls-to-action/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/website-care-plan-explained/](https://famtasticdesigns.com/blog/website-care-plan-explained/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/website-numbers-that-matter/](https://famtasticdesigns.com/blog/website-numbers-that-matter/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-happens-after-a-website-lead/](https://famtasticdesigns.com/blog/what-happens-after-a-website-lead/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-happens-after-an-online-purchase/](https://famtasticdesigns.com/blog/what-happens-after-an-online-purchase/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-is-included-199-web-basics-bundle/](https://famtasticdesigns.com/blog/what-is-included-199-web-basics-bundle/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-makes-business-website-trustworthy/](https://famtasticdesigns.com/blog/what-makes-business-website-trustworthy/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-not-to-fully-automate/](https://famtasticdesigns.com/blog/what-not-to-fully-automate/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-should-a-small-business-website-do/](https://famtasticdesigns.com/blog/what-should-a-small-business-website-do/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/what-small-business-should-automate-first/](https://famtasticdesigns.com/blog/what-small-business-should-automate-first/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/when-an-ai-website-agent-is-useful/](https://famtasticdesigns.com/blog/when-an-ai-website-agent-is-useful/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/blog/who-is-199-website-for/](https://famtasticdesigns.com/blog/who-is-199-website-for/) | blog | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/contact/](https://famtasticdesigns.com/contact/) | contact | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/faq/](https://famtasticdesigns.com/faq/) | faq | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/packages/](https://famtasticdesigns.com/packages/) | packages | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/packages/starter-website/](https://famtasticdesigns.com/packages/starter-website/) | packages | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/services/](https://famtasticdesigns.com/services/) | services | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/start/](https://famtasticdesigns.com/start/) | start | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |
| [/work/](https://famtasticdesigns.com/work/) | work | 90 | pass | Primary H1/content is absent from initial HTML (client-rendered) |

## Article-by-article scorecard

| Article | Series | Score | Result | Primary findings |
|---|---|---:|---|---|
| [What Should a Customer Receipt Explain?](https://famtasticdesigns.com/blog/customer-receipt-content/) | commerce-customer-lifecycle | 88 | revise | Meta title length is 39; target 40-65; Primary keyword is not explicit in title, description, or opening copy |
| [How to Handle Leads That Reply by Email](https://famtasticdesigns.com/blog/lead-email-reply-ingestion/) | lead-response-operations | 88 | revise | Meta title length is 39; target 40-65; Primary keyword is not explicit in title, description, or opening copy |
| [How to Know When a Lead Is Overdue](https://famtasticdesigns.com/blog/overdue-lead-alerts/) | lead-response-operations | 88 | revise | Meta title length is 34; target 40-65; Primary keyword is not explicit in title, description, or opening copy |
| [What Happens After an Online Purchase?](https://famtasticdesigns.com/blog/what-happens-after-an-online-purchase/) | commerce-customer-lifecycle | 88 | revise | Meta title length is 38; target 40-65; Primary keyword is not explicit in title, description, or opening copy |
| [What Should Never Be Fully Automated?](https://famtasticdesigns.com/blog/what-not-to-fully-automate/) | small-business-automation | 88 | revise | Meta title length is 37; target 40-65; Primary keyword is not explicit in title, description, or opening copy |
| [What Should a Website Contact Form Ask?](https://famtasticdesigns.com/blog/what-should-website-contact-form-ask/) | website-lead-capture | 88 | revise | Meta title length is 39; target 40-65; Primary keyword is not explicit in title, description, or opening copy |
| [Who Is the $199 Website For?](https://famtasticdesigns.com/blog/who-is-199-website-for/) | fifty-five-cents-a-day | 88 | revise | Meta title length is 28; target 40-65; Primary keyword is not explicit in title, description, or opening copy |
| [How to Handle Special Pricing Without Breaking the Catalog](https://famtasticdesigns.com/blog/account-specific-special-pricing/) | commerce-customer-lifecycle | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Happens After You Buy the $199 Website?](https://famtasticdesigns.com/blog/after-buying-199-website/) | fifty-five-cents-a-day | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Documentation Do Customers Need for an AI Agent?](https://famtasticdesigns.com/blog/ai-agent-customer-documentation/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Should an AI Agent Hand Off to a Person?](https://famtasticdesigns.com/blog/ai-agent-human-handoff/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Knowledge Should an AI Website Agent Use?](https://famtasticdesigns.com/blog/ai-agent-knowledge-base/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [AI Chatbot or AI Agent: What Is the Difference?](https://famtasticdesigns.com/blog/ai-chatbot-vs-ai-agent/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Do Analytics and CRM Data Work Together?](https://famtasticdesigns.com/blog/analytics-and-customer-data/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Should Automation Handle Failures and Retries?](https://famtasticdesigns.com/blog/automation-failures-retries-dead-letter/) | small-business-automation | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is a Business Website Package?](https://famtasticdesigns.com/blog/business-website-package-explained/) | website-packages-explained | 94 | pass | Meta title length is 35; target 40-65 |
| [How to Choose the Right FAMtastic Website Package](https://famtasticdesigns.com/blog/choose-the-right-famtastic-website-package/) | website-packages-explained | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Should Checkout Connect to Customer Intake?](https://famtasticdesigns.com/blog/connect-checkout-to-customer-intake/) | commerce-customer-lifecycle | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [Contact Form, Quote Form, or Assessment: Which Should You Use?](https://famtasticdesigns.com/blog/contact-form-vs-quote-form-vs-assessment/) | website-lead-capture | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [When Should a Website Be Custom Instead of Packaged?](https://famtasticdesigns.com/blog/custom-website-vs-package/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Should Be on a Customer Portal Home Screen?](https://famtasticdesigns.com/blog/customer-portal-home-screen/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Should Failed Payments, Refunds, and Cancellations Work?](https://famtasticdesigns.com/blog/failed-payments-refunds-cancellations/) | commerce-customer-lifecycle | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Your First Website Can Grow With Your Business](https://famtasticdesigns.com/blog/grow-beyond-first-website/) | fifty-five-cents-a-day | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Design Hosting and Domain Renewals Clearly](https://famtasticdesigns.com/blog/hosting-domain-renewal-design/) | commerce-customer-lifecycle | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How a Customer Portal Helps a Small Business](https://famtasticdesigns.com/blog/how-a-customer-portal-helps/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How a Website Turns a Visitor Into a Real Lead](https://famtasticdesigns.com/blog/how-a-website-captures-a-lead/) | website-lead-capture | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Automation Prevents Lost Opportunities](https://famtasticdesigns.com/blog/how-automation-prevents-lost-opportunities/) | small-business-automation | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Many Pages Does a Small-Business Website Need?](https://famtasticdesigns.com/blog/how-many-pages-small-business-website/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is Idempotency and Why Does Business Automation Need It?](https://famtasticdesigns.com/blog/idempotency-business-automation/) | small-business-automation | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is a Campaign Landing Page System?](https://famtasticdesigns.com/blog/landing-page-package-explained/) | website-packages-explained | 94 | pass | Meta title length is 39; target 40-65 |
| [How to Build a Lead Follow-Up Schedule That Does Not Feel Robotic](https://famtasticdesigns.com/blog/lead-follow-up-schedule/) | lead-response-operations | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Should Happen to Leads That Are Not Ready to Buy?](https://famtasticdesigns.com/blog/lead-nurture-not-ready-to-buy/) | lead-response-operations | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Measure Whether an AI Agent Is Helping](https://famtasticdesigns.com/blog/measure-ai-agent-performance/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Measure Content and Blog Performance](https://famtasticdesigns.com/blog/measure-blog-content-performance/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Measure Whether a Website Is Doing Its Job](https://famtasticdesigns.com/blog/measure-whether-website-works/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Design a Customer Portal for Mobile Use](https://famtasticdesigns.com/blog/mobile-customer-portal-design/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Design a Mobile Lead-Capture Experience](https://famtasticdesigns.com/blog/mobile-lead-capture-design/) | website-lead-capture | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Should Be in a New-Lead Notification?](https://famtasticdesigns.com/blog/new-lead-notification-checklist/) | lead-response-operations | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [One-Page Website or Multi-Page Website: Which Fits?](https://famtasticdesigns.com/blog/one-page-vs-multi-page-website/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [The Parts of a One-Page Business Website](https://famtasticdesigns.com/blog/parts-of-a-one-page-business-website/) | fifty-five-cents-a-day | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Plan a Website Around Customer Questions](https://famtasticdesigns.com/blog/plan-website-around-customer-questions/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Communication Settings Should Customers Control?](https://famtasticdesigns.com/blog/portal-communication-preferences/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Can a Portal Teach Customers to Use What They Bought?](https://famtasticdesigns.com/blog/portal-product-documentation-learning/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Should Customers Find Projects, Files, and Approvals?](https://famtasticdesigns.com/blog/portal-projects-files-approvals/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Makes Portal Support Better Than a Contact Form?](https://famtasticdesigns.com/blog/portal-support-vs-contact-form/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Should a Portal Handle Team Members and Business Accounts?](https://famtasticdesigns.com/blog/portal-team-members-business-accounts/) | customer-portal-experience | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is a Premium Website Plus AI Package?](https://famtasticdesigns.com/blog/premium-website-ai-package-explained/) | website-packages-explained | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is the Difference Between a Product, Add-On, and Entitlement?](https://famtasticdesigns.com/blog/product-addon-entitlement-difference/) | commerce-customer-lifecycle | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [A Professional Website for About 55 Cents a Day](https://famtasticdesigns.com/blog/professional-website-55-cents-a-day/) | fifty-five-cents-a-day | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Prove an Ecommerce Workflow Before Taking Real Payments](https://famtasticdesigns.com/blog/prove-ecommerce-before-live-payments/) | commerce-customer-lifecycle | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Reduce Friction Without Collecting Bad Leads](https://famtasticdesigns.com/blog/reduce-form-friction-without-bad-leads/) | website-lead-capture | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Actions Should an AI Website Agent Be Allowed to Take?](https://famtasticdesigns.com/blog/safe-ai-agent-actions/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Do Scheduled Workers Protect Customer Work?](https://famtasticdesigns.com/blog/scheduled-workers-customer-work/) | small-business-automation | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Should a Small-Business Analytics Dashboard Show?](https://famtasticdesigns.com/blog/small-business-analytics-dashboard/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How Fast Should a Small Business Respond to a Website Lead?](https://famtasticdesigns.com/blog/small-business-lead-response-time/) | lead-response-operations | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Lead Stages Does a Small Business Actually Need?](https://famtasticdesigns.com/blog/small-business-lead-stages/) | lead-response-operations | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is a Website Conversion for a Small Business?](https://famtasticdesigns.com/blog/small-business-website-conversion/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is a Starter Website Package?](https://famtasticdesigns.com/blog/starter-website-package-explained/) | website-packages-explained | 94 | pass | Meta title length is 34; target 40-65 |
| [How to Test an AI Website Agent Before Launch](https://famtasticdesigns.com/blog/test-ai-website-agent/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Test Automation Without Risking Customers](https://famtasticdesigns.com/blog/test-business-automation-safely/) | small-business-automation | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Track Which Pages and Campaigns Create Leads](https://famtasticdesigns.com/blog/track-pages-campaigns-create-leads/) | website-lead-capture | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Track Calls to Action Across a Website](https://famtasticdesigns.com/blog/track-website-calls-to-action/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [How to Read Traffic Sources Without Fooling Yourself](https://famtasticdesigns.com/blog/understand-website-traffic-sources/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Does a Website Care Plan Include?](https://famtasticdesigns.com/blog/website-care-plan-explained/) | website-packages-explained | 94 | pass | Meta title length is 38; target 40-65 |
| [How to Connect Website Events to Real Business Workflows](https://famtasticdesigns.com/blog/website-events-business-workflows/) | small-business-automation | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [The Website Numbers a Small Business Should Watch](https://famtasticdesigns.com/blog/website-numbers-that-matter/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Information Should You Gather Before Building a Website?](https://famtasticdesigns.com/blog/website-project-information-checklist/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Should Happen After a Website Lead Arrives?](https://famtasticdesigns.com/blog/what-happens-after-a-website-lead/) | lead-response-operations | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is Included in the $199 Web Basics Bundle?](https://famtasticdesigns.com/blog/what-is-included-199-web-basics-bundle/) | website-packages-explained | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Is Included in the $499 Business Website Bundle?](https://famtasticdesigns.com/blog/what-is-included-499-business-website-bundle/) | website-packages-explained | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Makes a Business Website Feel Trustworthy?](https://famtasticdesigns.com/blog/what-makes-business-website-trustworthy/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Should a Small-Business Website Actually Do?](https://famtasticdesigns.com/blog/what-should-a-small-business-website-do/) | small-business-website-strategy | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What Should a Thank-You Page Do After Form Submission?](https://famtasticdesigns.com/blog/what-should-thank-you-page-do/) | website-lead-capture | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [When Is an AI Website Agent Actually Useful?](https://famtasticdesigns.com/blog/when-an-ai-website-agent-is-useful/) | ai-agents-for-websites | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [When Should You Change a Website Based on Analytics?](https://famtasticdesigns.com/blog/when-to-change-website-from-analytics/) | website-analytics-decisions | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [Why Website Leads Get Lost Between the Form and the Inbox](https://famtasticdesigns.com/blog/why-website-leads-get-lost/) | website-lead-capture | 94 | pass | Primary keyword is not explicit in title, description, or opening copy |
| [What the $199 Website Includes—and What It Does Not](https://famtasticdesigns.com/blog/199-website-inclusions-and-boundaries/) | fifty-five-cents-a-day | 100 | pass | Passes canonical SEO/content contract |
| [What Is a Domain Name and Why Does Your Business Need One?](https://famtasticdesigns.com/blog/what-is-a-domain-name/) | fifty-five-cents-a-day | 100 | pass | Passes canonical SEO/content contract |
| [What Is Website Hosting and What Does It Actually Do?](https://famtasticdesigns.com/blog/what-is-website-hosting/) | fifty-five-cents-a-day | 100 | pass | Passes canonical SEO/content contract |
| [What Should a Small Business Automate First?](https://famtasticdesigns.com/blog/what-small-business-should-automate-first/) | small-business-automation | 100 | pass | Passes canonical SEO/content contract |

## Cannibalization candidates

| Left | Right | Similarity | Recommended review |
|---|---|---:|---|

## Corrections made in source

- Dynamic shell Open Graph and Twitter descriptions now match each page.
- Dynamic `og:type` is `article` for blog content.
- Initial HTML now receives route-appropriate, parseable JSON-LD with stable organization and website identifiers.
- Breadcrumb schema is generated for static and dynamic public routes.

## Remaining priority order

1. Pre-render meaningful H1/body content for every public route.
2. Rewrite every description flagged as grammatical, truncated, or mechanically generic.
3. Use Search Console query-to-page data to approve differentiation, merges, or redirects.
4. Add named author/reviewer identity and original proof to priority articles.
5. Re-run rendered mobile, accessibility, and Core Web Vitals QA after corrections.

## Limitations

- Search Console, CrUX field data, backlink indexes, and live ranking data were unavailable.
- Page checks evaluate initial HTML; rendered mobile/accessibility QA is reported by the separate visual QA lane.
- Cannibalization candidates are heuristic and require Search Console query-to-page evidence before merge or redirect decisions.
