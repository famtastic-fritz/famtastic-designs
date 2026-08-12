#!/usr/bin/env python3
"""Build the canonical 64-article FAMtastic demand library.

This is a deterministic editorial factory: topic architecture is curated here,
while the JSON manifest remains the deployable Drupal seed contract.
"""

from __future__ import annotations

import html
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "backend/config/famtastic-content-series.json"

VISUALS = {
    "small-business-website-strategy": ("website-strategy.webp", "A connected small-business website system linking search, customer information, communication, and measurement."),
    "website-lead-capture": ("lead-capture.webp", "Website visitors moving through an organized lead-capture pipeline toward a visible opportunity."),
    "lead-response-operations": ("lead-response.webp", "A timed lead-response workflow connecting a new message, follow-up checkpoints, and a completed handoff."),
    "commerce-customer-lifecycle": ("ecommerce-lifecycle.webp", "A connected ecommerce lifecycle spanning product, secure checkout, fulfillment, and customer retention."),
    "customer-portal-experience": ("customer-portal.webp", "A mobile customer portal connecting files, support, purchases, learning, and account growth."),
    "small-business-automation": ("workflow-automation.webp", "Scattered manual tasks becoming a controlled business workflow with checks and completed outcomes."),
    "website-analytics-decisions": ("analytics-decisions.webp", "Website data moving through a conversion funnel toward a clear business decision."),
    "ai-agents-for-websites": ("ai-website-agent.webp", "An AI website agent grounded in approved content with customer conversations and human support handoff."),
    "website-packages-explained": ("package-system.webp", "A complete branded website package connecting planning, design, mobile experience, launch, and measurable growth."),
    "fifty-five-cents-a-day": ("55-cent-website-hero.webp", "A small-business owner crossing from an idea into a polished, professional website."),
}


def words(text: str) -> int:
    return len(re.findall(r"\b[\w'-]+\b", re.sub(r"<[^>]+>", " ", text)))


SERIES = [
    {
        "key": "small-business-website-strategy",
        "title": "The Small-Business Website Strategy Series",
        "category": "get-customers",
        "tags": ["website-strategy", "small-business", "conversion"],
        "capabilities": ["website-discovery", "seo-sitemap", "synthetic-proof"],
        "thesis": "A useful website begins with customer decisions and business operations, then chooses the smallest system that supports both.",
        "audience": "Small-business owners planning a first website, rebuild, or meaningful expansion.",
        "context": "website strategy",
        "proof": "FAMtastic has implemented needs-led discovery, package recommendations, route-specific SEO, dynamic sitemaps, and a synthetic acceptance process.",
        "source": ["Google Search Central", "https://developers.google.com/search/docs/fundamentals/creating-helpful-content"],
        "topics": [
            ("What Should a Small-Business Website Actually Do?", "what-should-a-small-business-website-do", "small business website strategy", "Define the jobs a website should perform before choosing pages or technology."),
            ("How Many Pages Does a Small-Business Website Need?", "how-many-pages-small-business-website", "how many pages does a small business website need", "Choose page count from customer questions and actions instead of copying a competitor's sitemap."),
            ("One-Page Website or Multi-Page Website: Which Fits?", "one-page-vs-multi-page-website", "one page website vs multi page website", "Match site structure to search needs, sales complexity, and the number of distinct customer decisions."),
            ("What Information Should You Gather Before Building a Website?", "website-project-information-checklist", "website project information checklist", "Collect goals, audiences, proof, content, integrations, ownership, and operational requirements before design starts."),
            ("How to Plan a Website Around Customer Questions", "plan-website-around-customer-questions", "website customer questions", "Turn repeated buyer questions into navigation, content, FAQs, and calls to action."),
            ("What Makes a Business Website Feel Trustworthy?", "what-makes-business-website-trustworthy", "trustworthy business website", "Build trust through clarity, identity, proof, security, accessibility, and honest expectations."),
            ("When Should a Website Be Custom Instead of Packaged?", "custom-website-vs-package", "custom website vs website package", "Use bounded packages for known work and custom discovery when requirements, risk, or integrations are uncertain."),
            ("How to Measure Whether a Website Is Doing Its Job", "measure-whether-website-works", "how to measure website effectiveness", "Connect website purpose to observable actions, response quality, purchases, service outcomes, and learning."),
        ],
    },
    {
        "key": "website-lead-capture",
        "title": "The Website Lead-Capture Series",
        "category": "get-customers",
        "tags": ["lead-capture", "conversion", "website-strategy"],
        "capabilities": ["website-discovery", "lead-response", "analytics-reporting"],
        "thesis": "Lead capture is a complete decision path from relevance and trust through a useful form, acknowledgment, attribution, and next step.",
        "audience": "Businesses that need their website to create qualified conversations rather than passive traffic.",
        "context": "website lead capture",
        "proof": "FAMtastic has built public intake, quote, campaign, referral, attribution, acknowledgment, and Drupal lead-record workflows.",
        "source": ["Google Analytics Help", "https://support.google.com/analytics/answer/9322688"],
        "topics": [
            ("How a Website Turns a Visitor Into a Real Lead", "how-a-website-captures-a-lead", "website lead capture", "Connect the page promise, evidence, form, acknowledgment, and staff workflow into one path."),
            ("What Should a Website Contact Form Ask?", "what-should-website-contact-form-ask", "website contact form questions", "Ask only what improves routing, qualification, preparation, or the customer's next step."),
            ("Contact Form, Quote Form, or Assessment: Which Should You Use?", "contact-form-vs-quote-form-vs-assessment", "contact form vs quote form", "Choose the interaction that matches how much the customer knows and how complex the purchase decision is."),
            ("How to Reduce Friction Without Collecting Bad Leads", "reduce-form-friction-without-bad-leads", "reduce lead form friction", "Remove unnecessary effort while keeping the questions that protect fit, expectations, and follow-up."),
            ("What Should a Thank-You Page Do After Form Submission?", "what-should-thank-you-page-do", "thank you page best practices", "Confirm success, explain timing, preserve trust, and offer one relevant next action."),
            ("How to Track Which Pages and Campaigns Create Leads", "track-pages-campaigns-create-leads", "track website lead sources", "Preserve source, campaign, page, CTA, and lifecycle outcome without collecting unnecessary personal data."),
            ("Why Website Leads Get Lost Between the Form and the Inbox", "why-website-leads-get-lost", "website leads not receiving", "Identify delivery, ownership, duplicate, routing, and response failures that make a working form operationally useless."),
            ("How to Design a Mobile Lead-Capture Experience", "mobile-lead-capture-design", "mobile lead capture form", "Make the most important action reachable, readable, tappable, and recoverable on a phone."),
        ],
    },
    {
        "key": "lead-response-operations",
        "title": "The Lead Response and Follow-Up Series",
        "category": "get-customers",
        "tags": ["lead-response", "business-automation", "customer-experience"],
        "capabilities": ["lead-response", "product-onboarding", "synthetic-proof"],
        "thesis": "A lead becomes an opportunity only when acknowledgment, ownership, response deadlines, follow-up, and exception protection are designed together.",
        "audience": "Owner-led and small teams that cannot afford to lose opportunities inside inboxes and informal handoffs.",
        "context": "lead response operations",
        "proof": "FAMtastic has test-provider proof for acknowledgments, Fritz alerts, lead stages, deadlines, escalations, worker protection, and evidence-led acceptance.",
        "source": ["Drupal Cron Documentation", "https://www.drupal.org/docs/administering-a-drupal-site/cron-automated-tasks"],
        "topics": [
            ("What Should Happen After a Website Lead Arrives?", "what-happens-after-a-website-lead", "website lead response process", "Give every new lead an acknowledgment, owner, deadline, stage, and visible next action."),
            ("What Lead Stages Does a Small Business Actually Need?", "small-business-lead-stages", "small business lead stages", "Use a small, canonical stage model that describes reality and supports action."),
            ("How Fast Should a Small Business Respond to a Website Lead?", "small-business-lead-response-time", "small business lead response time", "Set a promise the team can keep, then escalate before the opportunity becomes invisible."),
            ("How to Build a Lead Follow-Up Schedule That Does Not Feel Robotic", "lead-follow-up-schedule", "lead follow up schedule", "Use timing, context, channel, and exit rules to help rather than harass."),
            ("What Should Be in a New-Lead Notification?", "new-lead-notification-checklist", "new lead notification", "Send staff the customer context, source, deadline, owner, and direct action link they need."),
            ("How to Handle Leads That Reply by Email", "lead-email-reply-ingestion", "email replies into crm", "Attach validated replies to the right timeline while protecting against spoofing and unsafe attachments."),
            ("How to Know When a Lead Is Overdue", "overdue-lead-alerts", "overdue lead alerts", "Define untouched, stale, and missed follow-up conditions that workers can detect without duplicate alerts."),
            ("What Should Happen to Leads That Are Not Ready to Buy?", "lead-nurture-not-ready-to-buy", "lead nurture not ready to buy", "Record why timing is wrong, obtain the right communication permission, and re-enter with useful context."),
        ],
    },
    {
        "key": "commerce-customer-lifecycle",
        "title": "The Ecommerce and Post-Purchase Series",
        "category": "get-paid",
        "tags": ["ecommerce", "stripe", "customer-experience", "business-automation"],
        "capabilities": ["commerce-lifecycle", "product-onboarding", "synthetic-proof"],
        "thesis": "Checkout is the beginning of fulfillment: identity, payment, receipt, intake, entitlements, delivery, renewals, and exceptions must share one commercial truth.",
        "audience": "Service businesses and ecommerce operators that need payment to start reliable work rather than create administrative cleanup.",
        "context": "commerce and post-purchase operations",
        "proof": "FAMtastic has test-provider proof for Drupal Commerce checkout, Stripe payment, receipt, entitlement, intake, staff alerts, and account-scoped pricing.",
        "source": ["Stripe Checkout Documentation", "https://docs.stripe.com/payments/checkout"],
        "topics": [
            ("What Happens After an Online Purchase?", "what-happens-after-an-online-purchase", "post purchase ecommerce workflow", "Treat payment completion as the trigger for customer identity, receipt, fulfillment, and service access."),
            ("What Is the Difference Between a Product, Add-On, and Entitlement?", "product-addon-entitlement-difference", "product add on entitlement", "Separate what is sold, what modifies the purchase, and what the customer is allowed to receive or use."),
            ("How Should Checkout Connect to Customer Intake?", "connect-checkout-to-customer-intake", "checkout customer intake", "Ask purchase questions once, then open only the onboarding sections required by the selected items."),
            ("How to Handle Special Pricing Without Breaking the Catalog", "account-specific-special-pricing", "customer specific pricing ecommerce", "Keep the public product stable while recording an auditable, account-scoped adjustment with reason and expiry."),
            ("What Should a Customer Receipt Explain?", "customer-receipt-content", "what should ecommerce receipt include", "Make the commercial record understandable by showing items, payment, terms, next steps, and support path."),
            ("How Should Failed Payments, Refunds, and Cancellations Work?", "failed-payments-refunds-cancellations", "failed payment refund cancellation workflow", "Use explicit states and idempotent events so money problems do not create duplicate or contradictory fulfillment."),
            ("How to Design Hosting and Domain Renewals Clearly", "hosting-domain-renewal-design", "hosting domain renewal process", "Separate ownership, included terms, billing cadence, advance notice, cancellation, and service consequences."),
            ("How to Prove an Ecommerce Workflow Before Taking Real Payments", "prove-ecommerce-before-live-payments", "ecommerce test checklist", "Test success, decline, authentication, replay, refund, access, email, mobile, and cross-account isolation before launch."),
        ],
    },
    {
        "key": "customer-portal-experience",
        "title": "The Customer Portal Experience Series",
        "category": "serve-customers",
        "tags": ["customer-portal", "customer-experience", "drupal", "react"],
        "capabilities": ["drupal-react-portals", "commerce-lifecycle", "lead-response"],
        "thesis": "A customer portal should reduce effort across service, support, files, purchases, knowledge, preferences, referrals, and relevant growth.",
        "audience": "Businesses considering a branded portal or trying to make an existing login useful to customers.",
        "context": "customer portal design",
        "proof": "FAMtastic's production-smoke-tested portal includes account workspaces, services, projects, support, education, preferences, referrals, purchases, and entitled analytics.",
        "source": ["W3C Web Accessibility Initiative", "https://www.w3.org/WAI/fundamentals/accessibility-intro/"],
        "topics": [
            ("How a Customer Portal Helps a Small Business", "how-a-customer-portal-helps", "small business customer portal", "Use a portal when persistent customer context and self-service remove repeated work for both sides."),
            ("What Should Be on a Customer Portal Home Screen?", "customer-portal-home-screen", "customer portal dashboard", "Prioritize next actions, alerts, owned services, recent activity, and relevant help over a wall of navigation."),
            ("How Should Customers Find Projects, Files, and Approvals?", "portal-projects-files-approvals", "client portal projects files approvals", "Organize delivery around customer tasks and history rather than internal department names."),
            ("What Makes Portal Support Better Than a Contact Form?", "portal-support-vs-contact-form", "customer portal support", "Preserve identity, service context, case history, files, status, and response expectations in one thread."),
            ("How Should a Portal Handle Team Members and Business Accounts?", "portal-team-members-business-accounts", "customer portal team roles", "Model organizations, owners, billing contacts, administrators, and members without sharing another customer's data."),
            ("What Communication Settings Should Customers Control?", "portal-communication-preferences", "customer portal communication preferences", "Separate security, project, billing, educational, and promotional choices with understandable consequences."),
            ("How Can a Portal Teach Customers to Use What They Bought?", "portal-product-documentation-learning", "customer portal product documentation", "Show service-specific guides, FAQs, status, and next actions based on ownership and lifecycle stage."),
            ("How to Design a Customer Portal for Mobile Use", "mobile-customer-portal-design", "mobile customer portal design", "Use an expandable information architecture, touch-safe actions, contained data, and short paths to urgent tasks."),
        ],
    },
    {
        "key": "small-business-automation",
        "title": "The Small-Business Automation Series",
        "category": "grow-and-automate",
        "tags": ["business-automation", "lead-response", "customer-experience"],
        "capabilities": ["lead-response", "product-onboarding", "synthetic-proof"],
        "thesis": "Useful automation protects commitments and handles predictable work while making ownership, exceptions, and human judgment more visible.",
        "audience": "Small teams looking to reduce dropped work without automating customer relationships into a dead end.",
        "context": "small-business workflow automation",
        "proof": "FAMtastic has implemented workers, deadlines, retries, dead-letter handling, notifications, lifecycle events, and synthetic journey validation.",
        "source": ["Drupal Queue API", "https://www.drupal.org/docs/drupal-apis/queue-api/overview"],
        "topics": [
            ("How Automation Prevents Lost Opportunities", "how-automation-prevents-lost-opportunities", "small business workflow automation", "Automate acknowledgment, deadlines, reminders, retries, and exception visibility while keeping a person accountable."),
            ("What Should a Small Business Automate First?", "what-small-business-should-automate-first", "what should a small business automate", "Start with repetitive, observable work where delay or omission causes real customer or revenue risk."),
            ("What Should Never Be Fully Automated?", "what-not-to-fully-automate", "what business tasks should not be automated", "Keep judgment, sensitive decisions, unusual exceptions, and relationship repair inside a human-controlled path."),
            ("How Do Scheduled Workers Protect Customer Work?", "scheduled-workers-customer-work", "scheduled task business workflow", "Use workers for reminders, deadlines, renewals, retries, summaries, and heartbeat checks with idempotent execution."),
            ("What Is Idempotency and Why Does Business Automation Need It?", "idempotency-business-automation", "idempotency business automation", "Design repeated events so they cannot send duplicate emails, create duplicate orders, or grant duplicate services."),
            ("How Should Automation Handle Failures and Retries?", "automation-failures-retries-dead-letter", "automation retry dead letter", "Bound retries, preserve evidence, route exhausted work to a dead-letter record, and alert the right owner."),
            ("How to Connect Website Events to Real Business Workflows", "website-events-business-workflows", "website workflow automation", "Translate forms, payments, replies, approvals, and usage signals into explicit business events and next actions."),
            ("How to Test Automation Without Risking Customers", "test-business-automation-safely", "test business automation", "Use synthetic identities, safe mail and payment drivers, run identifiers, cleanup, and evidence reports before production smoke tests."),
        ],
    },
    {
        "key": "website-analytics-decisions",
        "title": "The Website Analytics and Decisions Series",
        "category": "get-found",
        "tags": ["analytics", "conversion", "website-strategy", "small-business"],
        "capabilities": ["analytics-reporting", "seo-sitemap", "lead-response"],
        "thesis": "Website measurement becomes useful when traffic, source, content, customer action, staff response, purchase, and service outcomes can be read together.",
        "audience": "Small-business owners who have analytics but do not yet have a decision system.",
        "context": "website analytics and measurement",
        "proof": "FAMtastic has production-smoke-tested GA4 reporting, separate analytics and campaign screens, conversion events, attribution, and operational dashboards.",
        "source": ["Google Analytics Events", "https://support.google.com/analytics/answer/9322688"],
        "topics": [
            ("The Website Numbers a Small Business Should Watch", "website-numbers-that-matter", "small business website metrics", "Focus on acquisition, meaningful actions, response, conversion, service health, and trends instead of isolated pageviews."),
            ("What Is a Website Conversion for a Small Business?", "small-business-website-conversion", "small business website conversion", "Define conversions from the business's real customer actions rather than accepting a generic analytics list."),
            ("How to Track Calls to Action Across a Website", "track-website-calls-to-action", "cta tracking website", "Give every important CTA a stable event, source context, destination, and lifecycle meaning."),
            ("How to Read Traffic Sources Without Fooling Yourself", "understand-website-traffic-sources", "website traffic source analysis", "Use source, medium, campaign, landing page, consent, and attribution limitations together."),
            ("What Should a Small-Business Analytics Dashboard Show?", "small-business-analytics-dashboard", "small business analytics dashboard", "Show the few measures that support a recurring decision, with clear paths to deeper provider data."),
            ("How Do Analytics and CRM Data Work Together?", "analytics-and-customer-data", "analytics crm integration", "Connect privacy-safe acquisition identifiers to lead, order, and service outcomes without treating analytics as the customer database."),
            ("How to Measure Content and Blog Performance", "measure-blog-content-performance", "measure blog performance", "Track discovery, engagement, series progress, FAQ use, CTA action, qualified leads, and assisted outcomes."),
            ("When Should You Change a Website Based on Analytics?", "when-to-change-website-from-analytics", "website analytics decision making", "Use sufficient evidence, segmentation, business context, and controlled changes instead of reacting to daily noise."),
        ],
    },
    {
        "key": "ai-agents-for-websites",
        "title": "The AI Website Agent Series",
        "category": "grow-and-automate",
        "tags": ["ai-agents", "customer-experience", "business-automation", "small-business"],
        "capabilities": ["drupal-react-portals", "product-onboarding", "synthetic-proof"],
        "thesis": "An AI website agent is useful only when it has a bounded job, governed knowledge, safe tools, observable behavior, and a clear route to a person.",
        "audience": "Businesses evaluating AI assistance for sales, support, service, or customer self-service.",
        "context": "AI agents for business websites",
        "proof": "FAMtastic can build AI-ready frontends, product-specific documentation surfaces, governed workflows, portal support paths, and evidence-driven test harnesses.",
        "source": ["NIST AI Risk Management Framework", "https://www.nist.gov/itl/ai-risk-management-framework"],
        "topics": [
            ("When Is an AI Website Agent Actually Useful?", "when-an-ai-website-agent-is-useful", "ai agent for website", "Choose an agent when a specific customer job can be improved with trusted knowledge, safe actions, and human escalation."),
            ("AI Chatbot or AI Agent: What Is the Difference?", "ai-chatbot-vs-ai-agent", "ai chatbot vs ai agent", "Distinguish conversational answers from systems that can use tools, maintain context, and take governed actions."),
            ("What Knowledge Should an AI Website Agent Use?", "ai-agent-knowledge-base", "ai agent knowledge base", "Ground answers in approved services, policies, documentation, FAQs, account context, and current operational data."),
            ("What Actions Should an AI Website Agent Be Allowed to Take?", "safe-ai-agent-actions", "safe ai agent actions", "Define read, suggest, draft, and execute permissions with confirmation, logging, scope, and reversibility."),
            ("How Should an AI Agent Hand Off to a Person?", "ai-agent-human-handoff", "ai agent human handoff", "Transfer the conversation, identity, intent, attempted steps, urgency, and evidence without making the customer repeat everything."),
            ("How to Measure Whether an AI Agent Is Helping", "measure-ai-agent-performance", "ai agent performance metrics", "Measure resolution, escalation quality, task success, safety, customer effort, cost, and business outcomes."),
            ("What Documentation Do Customers Need for an AI Agent?", "ai-agent-customer-documentation", "ai agent documentation", "Give owners clear purpose, knowledge, permissions, limits, escalation, changes, monitoring, and support instructions."),
            ("How to Test an AI Website Agent Before Launch", "test-ai-website-agent", "test ai agent before launch", "Use scenario suites for normal tasks, ambiguity, unsafe requests, stale data, authorization, failures, and human recovery."),
        ],
    },
    {
        "key": "website-packages-explained",
        "title": "The FAMtastic Website Packages Explained Series",
        "category": "get-customers",
        "tags": ["website-packages", "website-strategy", "small-business"],
        "capabilities": ["website-discovery", "product-onboarding", "commerce-lifecycle"],
        "thesis": "A useful website package makes scope, deliverables, fit, ownership, next steps, and growth options understandable before a customer buys.",
        "audience": "Business owners comparing a focused website launch, a broader business site, a landing page, ongoing care, or a custom build.",
        "context": "website packages and deliverables",
        "proof": "FAMtastic has implemented structured product definitions, account-aware Commerce checkout, needs-led intake, entitlements, project onboarding, and customer portal delivery.",
        "source": ["Google Search Central", "https://developers.google.com/search/docs/fundamentals/creating-helpful-content"],
        "topics": [
            ("How to Choose the Right FAMtastic Website Package", "choose-the-right-famtastic-website-package", "choose a website package", "Compare each package by customer need, included deliverables, operational complexity, and the next stage of growth."),
            ("What Is Included in the $199 Web Basics Bundle?", "what-is-included-199-web-basics-bundle", "$199 website package", "Understand the focused one-page build, included first-year hosting, domain choice, intake, launch path, and boundaries of the offer."),
            ("What Is Included in the $499 Business Website Bundle?", "what-is-included-499-business-website-bundle", "$499 business website package", "See how a broader business website supports more services, customer questions, content, and conversion paths than a basic one-page launch."),
            ("What Is a Starter Website Package?", "starter-website-package-explained", "starter website package", "Learn when a structured starter site is a better fit than a landing page or a more complex custom build."),
            ("What Is a Business Website Package?", "business-website-package-explained", "business website package", "Understand how a business website organizes multiple services, trust signals, customer journeys, and lead capture."),
            ("What Is a Premium Website Plus AI Package?", "premium-website-ai-package-explained", "premium website AI package", "Learn when custom experience, automation, governed AI assistance, and deeper integrations belong in one scoped solution."),
            ("What Is a Campaign Landing Page System?", "landing-page-package-explained", "campaign landing page system", "Use a paid-campaign system for one audience, one offer, measurable attribution, lead routing, and one conversion path—not as a substitute for the $199 first-website offer."),
            ("What Does a Website Care Plan Include?", "website-care-plan-explained", "website care plan", "Separate ongoing maintenance, monitoring, updates, support, and improvement from the original website build."),
        ],
    },
    {
        "key": "fifty-five-cents-a-day",
        "title": "The 55 Cents a Day Website Series",
        "category": "get-customers",
        "tags": ["55-cents-a-day", "web-basics", "domain", "hosting", "small-business"],
        "capabilities": ["website-discovery", "commerce-lifecycle", "product-onboarding"],
        "thesis": "There may be many reasons a business is still offline; with a $199 professional one-page website offer, cost is not one of them. Period.",
        "audience": "New and small-business owners who need a credible first website and a clear explanation of what it takes to get online.",
        "context": "the $199 Web Basics offer",
        "proof": "FAMtastic has implemented the $199 Web Basics product, secure Commerce checkout, customer intake, first-year hosting entitlement, domain-choice workflow, and project onboarding.",
        "source": ["ICANN Registrant Program", "https://www.icann.org/resources/pages/registrant-2013-09-17-en"],
        "topics": [
            ("A Professional Website for About 55 Cents a Day", "professional-website-55-cents-a-day", "55 cents a day website", "See how a one-time $199 Web Basics purchase averages to about 55 cents per day across one year without pretending it is daily billing."),
            ("What Is a Domain Name and Why Does Your Business Need One?", "what-is-a-domain-name", "what is a domain name", "Understand the memorable web address customers use to find your business and how registration differs from website ownership and hosting."),
            ("What Is Website Hosting and What Does It Actually Do?", "what-is-website-hosting", "what is website hosting", "Learn how hosting keeps website files available online and what managed basic hosting means in the Web Basics offer."),
            ("The Parts of a One-Page Business Website", "parts-of-a-one-page-business-website", "parts of a one page website", "See how a focused page combines identity, offer, proof, customer questions, contact, and a clear next action."),
            ("What the $199 Website Includes—and What It Does Not", "199-website-inclusions-and-boundaries", "$199 website includes", "Set clear expectations for the one-page build, first-year hosting, domain choice, content intake, and separately scoped ecommerce or custom systems."),
            ("What Happens After You Buy the $199 Website?", "after-buying-199-website", "$199 website process", "Follow the path from account and payment through intake, content, design, review, launch, and customer portal support."),
            ("Who Is the $199 Website For?", "who-is-199-website-for", "who needs a basic website", "Decide whether a focused first website fits now or whether ecommerce, multiple services, integrations, or custom workflows need a broader scope."),
            ("How Your First Website Can Grow With Your Business", "grow-beyond-first-website", "upgrade a basic website", "Start with a useful foundation, then add pages, lead automation, analytics, commerce, portals, support, or AI only when the need is real."),
        ],
    },
]


FAQ_TEMPLATES = [
    ("fit", "Does every business need {context}?", "No. The right decision depends on the customer job, operational risk, current volume, and the smallest useful improvement. FAMtastic starts with discovery and recommends only the capabilities supported by the need."),
    ("start", "What is the safest way to start with {context}?", "Start with one bounded workflow, define ownership and success, document the current process, test the normal and failure paths, and expand only after the evidence shows the foundation works."),
    ("measure", "How should a business measure {context}?", "Measure the customer action, the staff response, the completed outcome, exceptions, and trend over time. Pageviews or activity counts alone cannot show whether the system helped the business or customer."),
    ("proof", "How does FAMtastic prove {context} works?", "FAMtastic separates implemented capability, local proof, provider proof, and production smoke testing. Acceptance scenarios record what was exercised and preserve any approval or production boundary."),
]


def sentence(value: str) -> str:
    value = value.strip()
    return value if value.endswith((".", "?", "!")) else value + "."


CAMPAIGN_GUIDES = {
    "professional-website-55-cents-a-day": {
        "heading": "The excuses are understandable. The cost objection is removable.",
        "lead": "“It is too expensive.” “I do not need one.” “My business is fine as it is.” “Most customers already know me.” “I have a social page.” Each statement can feel reasonable when the business is busy and a website sounds like a large, technical project. Web Basics exists to make the first step smaller and clearer.",
        "example": "Imagine a customer hears about a bakery from a friend at 9:30 p.m. The bakery is closed, the owner is asleep, and the customer wants to see the menu, location, style, and ordering options. A focused website cannot guarantee the sale, but it can answer those questions while the customer is interested. Without one, the customer must keep searching, trust scattered information, send a message and wait, or choose another business that is easier to verify.",
        "decision": "The question is not whether a $199 page can replace every marketing or sales system. It cannot. The question is whether a credible, customer-owned address and focused explanation are worth roughly the cost of 55 cents per day when averaged across the first year.",
    },
    "what-is-a-domain-name": {
        "heading": "A domain gives the business an address it can carry forward.",
        "lead": "A social profile is a space inside another company’s platform. A domain is the customer-controlled address that can point to a website today and move to another compatible host later. It gives signs, cards, email, search results, ads, and referrals one stable destination.",
        "example": "Consider two referrals: “Search for us on social media and choose the account with our logo” versus “Visit yourbakery.com.” The second instruction is easier to repeat and gives the business a clearer identity. Domain ownership does not automatically create trust, but it removes ambiguity and gives the business a consistent place to earn it.",
        "decision": "Web Basics includes either first-year registration of one available standard new domain when needed or connection of a domain the customer already controls. Availability must be checked. Registration and hosting are separate services, and annual domain renewal is separate after the included first year.",
    },
    "what-is-website-hosting": {
        "heading": "A domain points. Hosting keeps the site available.",
        "lead": "The domain is the address; hosting is the managed infrastructure serving the website files when a customer visits. They work together but are not interchangeable. A business can own a domain without a website, and it can have website files without a useful public address.",
        "example": "Think of a storefront. The domain is the street address customers remember. The website is the signs, information, and experience inside. Hosting is the physical space and utilities that allow the storefront to operate. The comparison is not perfect, but it explains why renewal and ownership are handled separately.",
        "decision": "The first year of basic FAMtastic-managed hosting is included with Web Basics. After the included year, the configured basic hosting plan renews at $9.99 per month only under the disclosed terms and authorization. Website edits, business email, premium infrastructure, and unrelated subscriptions are not silently included in hosting.",
    },
    "parts-of-a-one-page-business-website": {
        "heading": "One page should still answer a complete set of customer questions.",
        "lead": "A useful one-page business site is not one giant advertisement. It should quickly establish who the business is, who it helps, what it offers, why the visitor should believe it, what questions commonly block action, and exactly how to take the next step.",
        "example": "For a bakery, that might mean a strong visual introduction, specialties, service area, a small proof gallery, hours, location, common ordering questions, and an inquiry or call action. It does not mean pretending a one-page site includes a shopping cart. If customers need online ordering, product inventory, shipping, or payment, the intake should identify a broader commerce scope.",
        "decision": "The best one-page structure removes the most important uncertainty first. It uses short sections, readable mobile type, clear calls to action, real business information, and a response path that someone actually owns.",
    },
    "199-website-inclusions-and-boundaries": {
        "heading": "A low entry price only works when the boundary is honest.",
        "lead": "Web Basics includes one focused business website, responsive implementation, a clear contact or lead action, foundational search setup, first-year basic managed hosting, and a new-domain or existing-domain path. The offer is intentionally narrow so the price can remain accessible.",
        "example": "A consultant who needs an introduction, service summary, proof, FAQ, and booking link may fit. A restaurant that needs a live menu may fit if the content remains focused. A bakery needing a full cart, inventory, pickup scheduling, payment, and order management does not become a $199 project merely because the owner is a friend; FAMtastic can apply a documented special price while preserving the real scope.",
        "decision": "Ecommerce, custom applications, complex integrations, large catalogs, extensive copywriting, many distinct pages, and unlimited revisions are not included. The intake exists to prevent a customer from buying the wrong solution.",
    },
    "after-buying-199-website": {
        "heading": "Payment starts a defined service process.",
        "lead": "After purchase, the customer completes a website intake covering the business, audience, offer, contact details, visual direction, content, domain choice, and required customer actions. That information shapes the page; the system does not assume every business needs the same design or message.",
        "example": "A customer who provides a logo, accurate business details, preferred contact method, service description, and usable photographs gives the project a clear starting point. Missing or uncertain information becomes a visible next action rather than a silent assumption. Timing is confirmed after the required intake and materials are complete.",
        "decision": "The path is account and checkout, intake, scope confirmation, build, customer review, required revisions, approval, launch, and support. The $199 offer does not include an ecommerce store, customer portal, custom application, or complex business system.",
    },
    "who-is-199-website-for": {
        "heading": "Web Basics is for a focused first job—not every website job.",
        "lead": "The offer fits an owner who needs a professional destination for referrals, local discovery, credibility, and contact and whose essential story can be told on one focused page. It is especially useful when the absence of a website—not a complex technology requirement—is the immediate problem.",
        "example": "A barber may need services, examples, hours, location, and a booking link. A new consultant may need positioning, expertise, proof, and an inquiry form. A bakery may begin with story, specialties, gallery, location, and contact—but online ordering requires commerce scope. The format follows what the customer must do, not an arbitrary promise that one page solves everything.",
        "decision": "Choose a broader package or assessment when separate services need search pages, customers need accounts, products need a cart, staff need workflow automation, or the business requires integrations. Recommending the larger scope is not an upsell when the smaller offer cannot do the job; it is accurate scoping.",
    },
    "grow-beyond-first-website": {
        "heading": "A first website should make the next improvement easier to choose.",
        "lead": "Starting small does not mean staying small. It means building the first complete customer path, observing what people need, and adding capabilities when evidence supports them. Growth might mean more service pages, local search work, content, analytics, scheduling, lead follow-up, maintenance, commerce, a customer portal, or AI assistance.",
        "example": "A service business may first learn that visitors repeatedly ask about pricing, then add a detailed service page. A bakery may discover strong demand for online preorders, then scope commerce and pickup workflow. A consultant may receive enough inquiries to justify lead routing and automated acknowledgment. Each addition responds to a real bottleneck.",
        "decision": "The website is the public foundation, not a promise that every future capability is included in the original $199. Every addition should identify its deliverable, price, intake, customer access, support path, and proof test before it is sold.",
    },
}


def build_campaign_body(series: dict, topic: tuple, sequence: int, slugs: list[str]) -> str:
    title, slug, keyword, angle = topic
    guide = CAMPAIGN_GUIDES[slug]
    related = [item for item in slugs if item != slug]
    related_links = "".join(f'<li><a href="/blog/{item}">{item.replace("-", " ").title()}</a></li>' for item in related)
    return f"""
<p><strong>{html.escape(sentence(angle))}</strong></p>
<p>There may be a hundred reasons a business still does not have a website. The owner may expect a five-figure project, feel too busy to gather content, believe referrals are enough, rely on social media, or assume the current level of business will continue. Those concerns deserve a practical answer, not ridicule.</p>
<h2>{html.escape(guide['heading'])}</h2>
<p>{html.escape(guide['lead'])}</p>
<p>The campaign statement is deliberately direct: <strong>Cost is not one of them. Period.</strong> The $199 Web Basics Bundle is a one-time purchase. Dividing $199 by 365 produces approximately $0.545, which is rounded to about 55 cents per day as a comparison. It is not daily billing.</p>
<h2>What changes when a customer looks for the business?</h2>
<p>{html.escape(guide['example'])}</p>
<p>Current consumer research supports the verification problem. BrightLocal’s 2025 Local Consumer Review Survey used a representative panel of 1,026 U.S. adults. It found that 74% used two or more websites when checking local-business reviews, and more than three-quarters used video while researching local businesses. The finding does not prove that every business without a website loses every buyer. It shows that many customers gather evidence across multiple online places before deciding.</p>
<p>An older Verisign study adds historical context rather than a current benchmark. In its 2015 U.S. survey of 787 internet consumers ages 18–59, 92% said they preferred getting business information from a website rather than a social-media page, and 77% said a website made a business appear more credible. Consumer platforms have changed since 2015, so these figures are labeled by date and should be read as evidence of a long-standing trust pattern—not a 2026 measurement.</p>
<h2>What this evidence does—and does not—say</h2>
<p>A website does not create a good reputation by itself. It cannot replace good service, accurate listings, customer reviews, social proof, or human follow-through. It can give all of those signals a clear home, let the business explain itself in its own words, and provide a stable next action when the owner is unavailable.</p>
<p>It is also inappropriate to invent a revenue-loss number. The cost of being absent depends on the market, demand, customer behavior, competition, and the quality of every available alternative. The defensible conclusion is narrower: if customers research and verify businesses online, having no useful website can remove the business from consideration or make it harder to trust at the moment of decision.</p>
<h2>How does the $199 offer handle this specific need?</h2>
<p>{html.escape(guide['decision'])}</p>
<p>The included first year of basic managed hosting and the new-domain-or-existing-domain choice remove two common setup obstacles. After that included year, basic hosting currently renews at $9.99 per month under the disclosed terms. A newly registered domain renews separately each year at the disclosed amount. Those renewal details are part of the decision, not hidden fine print.</p>
<h2>Use the smallest offer that can do the whole job</h2>
<p>Web Basics is not the automatic answer to every intake. A one-page informational site, a paid-campaign landing system, a multi-page business website, and an ecommerce store are different deliverables. The intake should recommend the smallest complete scope that supports the customer’s real job. If a cart, multiple search pages, customer accounts, custom data, or integrations are required, the $199 scope is not enough.</p>
<p>That boundary protects both sides. The customer knows what will be delivered. FAMtastic can keep the entry offer accessible without disguising larger work. A documented special price can still be applied for a particular customer without changing what the project actually includes.</p>
<p>Price is only one kind of friction. The owner still needs to provide accurate business information, choose the preferred customer action, supply or approve content, respond to project questions, and review the result. The offer removes the large-price excuse; it does not remove the owner’s responsibility to help make the website truthful and useful. That shared responsibility is why intake and review remain part of even the smallest package.</p>
<h2>What should a business owner do next?</h2>
<p>Start with the customer’s most likely question. Can they confirm who you are, what you do, where or whom you serve, why they should trust you, and what to do next from a phone? If those answers are scattered, outdated, or missing, a focused website may be the smallest practical improvement.</p>
<p>Review the <a href="/55-cents-a-day-website">complete 55 Cents a Day offer</a>, then use the <a href="/start">website assessment</a> if the business may need more than one focused page. The assessment—not the campaign slogan—should determine the final scope.</p>
<h2>Sources and limitations</h2>
<ul><li><a href="https://www.brightlocal.com/research/local-consumer-review-survey-2025/">BrightLocal Local Consumer Review Survey 2025</a>: representative SurveyMonkey panel of 1,026 U.S. adults. Findings describe reported consumer behavior and should not be converted into a guaranteed business result.</li><li><a href="https://blog.verisign.com/getting-online/verisign-2015-online-survey-97-percent-of-smbs-would-recommend-having-a-website-to-other-smbs/">Verisign 2015 U.S. online survey</a>: 787 internet consumers ages 18–59 and 456 small businesses. Included only as dated historical context.</li><li><a href="https://www.icann.org/resources/pages/registrant-2013-09-17-en">ICANN registrant resources</a>: background on domain registrant rights and responsibilities.</li></ul>
<h2>Continue this series</h2><ul>{related_links}</ul>
""".strip()


def build_body(series: dict, topic: tuple, sequence: int, slugs: list[str]) -> str:
    title, slug, keyword, angle = topic
    source_name, source_url = series["source"]
    related = [item for item in slugs if item != slug]
    related_links = "".join(
        f'<li><a href="/blog/{item}">{item.replace("-", " ").title()}</a></li>' for item in related
    )
    if series["key"] == "fifty-five-cents-a-day":
        body = build_campaign_body(series, topic, sequence, slugs)
        if words(body) < 900:
            raise RuntimeError(f"generated campaign body too short for {slug}: {words(body)} words")
        return body
    role = "pillar guide" if sequence == 1 else "focused guide"
    campaign_detail = ""
    if series["key"] == "fifty-five-cents-a-day":
        inline_visuals = [
            ("55-cent-website-hero.webp", "A small-business owner stepping from an idea toward a professional website", "The first step is a credible place for customers to find and understand the business."),
            ("domain-explained.webp", "A business connected to its website through a clear digital address", "The domain is the customer-owned address that points people to the website."),
            ("hosting-explained.webp", "Protected managed infrastructure keeping a business website available online", "Hosting is the managed infrastructure that keeps the website available."),
            ("one-page-anatomy.webp", "The connected sections and systems of a useful one-page business website", "A focused page still connects identity, trust, action, measurement, and response."),
        ]
        first_visual = inline_visuals[(sequence - 1) % len(inline_visuals)]
        second_visual = inline_visuals[(sequence + 1) % len(inline_visuals)]
        inline_campaign_visuals = f"""
<figure class="article-inline-visual"><img src="/blog-images/{first_visual[0]}" alt="{html.escape(first_visual[1])}" width="1600" height="900" loading="lazy"><figcaption><img src="/brand/famtastic-mark.svg" alt="" width="28" height="28">FAMtastic Designs — {html.escape(first_visual[2])}</figcaption></figure>
<figure class="article-inline-visual"><img src="/blog-images/{second_visual[0]}" alt="{html.escape(second_visual[1])}" width="1600" height="900" loading="lazy"><figcaption><img src="/brand/famtastic-mark.svg" alt="" width="28" height="28">FAMtastic Designs — {html.escape(second_visual[2])}</figcaption></figure>
"""
        campaign_sections = {
            1: """<h2>The math—and the honest meaning</h2><p>$199 divided across 365 days is approximately $0.545 per day, which rounds to about 55 cents. The Web Basics Bundle is still a <strong>one-time $199 purchase</strong>; FAMtastic does not charge 55 cents each day. The comparison makes the cost easier to understand without changing the way the purchase is billed.</p><p>The offer includes one focused one-page website and one year of basic FAMtastic-managed hosting. If the customer needs a new domain, first-year registration is included when the requested domain is available; customers with a domain already receive connection help instead. After the included year, basic hosting renews at $9.99 per month. Domain renewal is separate, annual, and disclosed before it becomes due.</p>""",
            2: """<h2>Domain, website, and hosting are different</h2><p>A domain is the address people type or tap. The website is the content and experience they see. Hosting is the managed infrastructure that makes the website available online. A customer owns the registered domain; FAMtastic manages the hosting environment. Keeping those roles separate makes future renewal, transfer, and support decisions clearer.</p>""",
            3: """<h2>What the included hosting covers</h2><p>Web Basics includes the first year of FAMtastic-managed basic hosting for the purchased site. Beginning after that included term, the basic plan is currently configured to renew at $9.99 per month unless canceled under the disclosed terms. Domain registration is not the same service and renews separately each year.</p>""",
            4: """<h2>A focused page still has a complete job</h2><p>A strong one-page site normally needs a clear business identity, an understandable offer, reasons to trust the business, answers to common questions, contact information, and one primary next action. The page should also work on a phone, use an owned domain, load securely, and connect inquiries to a real response process.</p>""",
            5: """<h2>Included, conditional, and separately scoped</h2><p>The $199 Web Basics Bundle includes one focused one-page or landing-page website, responsive implementation, essential contact path, secure launch, one year of basic managed hosting, and either first-year registration of an available new domain or connection of an existing domain. Ecommerce, large catalogs, multi-page content programs, custom applications, complex integrations, and ongoing marketing are not silently folded into the basic price; the intake identifies when one of those needs requires a different package or add-on.</p>""",
            6: """<h2>From purchase to launch</h2><ol><li>Create or use the customer account and complete secure checkout.</li><li>Choose a new-domain or existing-domain path.</li><li>Complete the website intake with business, audience, offer, content, and contact details.</li><li>FAMtastic confirms scope and builds the focused site.</li><li>The customer reviews the work and supplies required feedback or missing content.</li><li>The approved site is launched and its service details remain visible in the customer portal.</li></ol><p>Delivery timing begins when the required intake and customer materials are complete and is confirmed in the project; this article does not promise an unsupported universal turnaround time.</p>""",
            7: """<h2>A fit decision, not a forced sale</h2><p>The offer is designed for a business that needs a credible, focused web presence and can tell its core story on one page. A bakery that needs a shopping cart, a company with many distinct services, or a workflow that needs customer accounts and integrations may need a broader package. The intake should identify that need and let FAMtastic recommend the correct scope, while account-specific pricing or a documented discount can be applied without pretending the work is smaller.</p>""",
            8: """<h2>The first site is a foundation</h2><p>A customer can begin with the smallest complete site, then add capabilities because the business needs them—not because the original offer hid them. Common growth paths include extra pages, local SEO, content, lead automation, analytics, scheduling, ecommerce, maintenance, customer portals, and governed AI assistance. Every addition should define its deliverable, price or approval state, intake questions, entitlement, support path, and proof scenario.</p>""",
        }
        campaign_detail = campaign_sections[sequence] + inline_campaign_visuals
    body = f"""
<p><strong>{html.escape(sentence(angle))}</strong> That is the practical answer behind this {role}. The useful question is not whether a business can add another page, form, dashboard, automation, or AI feature. It is whether the change makes an important customer decision easier and gives the business a reliable way to respond.</p>
<p>{html.escape(series['audience'])} can use this guide to define the work before choosing software or approving a build. The examples follow FAMtastic's evidence boundary: {html.escape(series['proof'])} This article explains a repeatable method and does not claim that the same configuration or result fits every business.</p>
<h2>Key takeaways</h2>
<ul><li>Begin with the customer action and the business responsibility that follows it.</li><li>Record ownership, timing, data, exceptions, and the definition of done before automating the path.</li><li>Choose the smallest useful implementation that can be tested without blocking later growth.</li><li>Measure completed outcomes and customer effort, not activity alone.</li><li>Keep commercial promises and production changes behind an explicit approval step.</li></ul>
{campaign_detail}
<h2>What does {html.escape(keyword)} mean in practice?</h2>
<p>{html.escape(sentence(angle))} In practice, that means describing the starting condition, the person who needs help, the decision or task they are trying to complete, and what must happen afterward. A page is not complete because it looks polished. A workflow is not complete because one happy-path test passes. The system must make the next action understandable and preserve enough context for the business to follow through.</p>
<p>The strongest design starts in ordinary language. Write down what the customer sees, what they need to know, what they can do, what confirmation they receive, and who owns the next step. Then map the data and system behavior underneath that experience. This order keeps technology subordinate to the customer and prevents internal labels from leaking into the public experience.</p>
<h2>How does this affect the customer experience?</h2>
<p>Customers experience a business as one continuous relationship even when the company uses separate tools behind the scenes. They do not care which system owns a form, payment, file, message, or report. They care whether information is clear, whether the action worked, whether they know what happens next, and whether the business remembers the context later.</p>
<p>For {html.escape(series['context'])}, the design should reduce uncertainty at the moment it matters. Use visible labels, short paths, mobile-safe controls, useful confirmation, and a recovery route when something fails. Do not make the customer repeat information that the business already collected. Do not hide a real limitation behind vague marketing language. Clarity is part of the service.</p>
<h2>What business workflow must exist behind the screen?</h2>
<p>Every customer-facing action creates an operational commitment. A request needs an owner and deadline. A payment needs a receipt, order state, and fulfillment path. A support message needs a case timeline and response target. An automated event needs idempotency, retries, and exception handling. An analytics event needs a defined business meaning. Without that operating layer, the website merely moves uncertainty from the customer to the staff.</p>
<p>Drupal can serve as the structured operational record while a React interface provides the branded experience. That architecture is useful when content, identity, Commerce, projects, entitlements, support, preferences, or reporting need to share customer context. Simpler businesses may need less. The architecture should follow the workflow rather than becoming the reason for it.</p>
<h2>A five-step implementation framework</h2>
<ol><li><strong>Define the job.</strong> Name the customer, the situation, the action, and the desired outcome.</li><li><strong>Map the lifecycle.</strong> Record what happens before, during, and after the visible interaction.</li><li><strong>Set the rules.</strong> Define ownership, permissions, deadlines, consent, exceptions, and customer promises.</li><li><strong>Build the smallest complete path.</strong> Connect the public experience to the operational record and notification path.</li><li><strong>Prove and improve.</strong> Test normal, mobile, failure, security, and recovery scenarios, then use evidence to decide what changes next.</li></ol>
<p>This framework prevents a common failure: building the visible screen while leaving the handoff to memory. It also makes additions easier to evaluate. A proposed feature should identify which step it improves and which acceptance scenario will prove that improvement.</p>
<h2>What mistakes create the most risk?</h2>
<p>The first mistake is choosing features before understanding the workflow. The second is treating an email notification as a durable business record. The third is using one generic call to action for readers with different levels of intent. The fourth is automating without a clear owner for exceptions. The fifth is publishing claims, prices, or promises that the delivery system cannot yet support.</p>
<p>Another risk is measuring only the easiest number. Traffic, messages sent, chatbot turns, and form submissions may be useful diagnostic signals, but none proves a good customer outcome. Connect activity to a meaningful next state such as a qualified lead, response, completed purchase, approved deliverable, resolved case, retained customer, or documented learning.</p>
<h2>How should this be measured?</h2>
<p>Use a small measurement chain: source, customer action, acknowledgment, staff action, completed outcome, and exception. Review trends rather than isolated daily movement. Segment where the difference changes a decision, such as mobile versus desktop, new versus returning customer, service owned, campaign, project stage, or renewal timing.</p>
<p>The measurement plan should also define what must not be collected. Avoid placing secrets or unnecessary personal information in analytics. Keep the customer database authoritative for identity and service history. Use analytics to understand behavior and acquisition, then connect it to operational outcomes through controlled identifiers and access checks.</p>
<h2>What should the business do next?</h2>
<p>Start by documenting the current path on one page. Mark every place where the customer waits, repeats information, changes channels, or depends on someone remembering a task. Choose the failure that causes the greatest customer or business cost. That becomes the first bounded improvement and the first acceptance test.</p>
<p>For source-grounded platform guidance, review <a href="{html.escape(source_url)}">{html.escape(source_name)}</a>. For FAMtastic-specific decisions, the next step is a needs-led assessment that can recommend a bounded package, a scoped custom project, or no immediate build when the evidence does not support one.</p>
<h2>Continue this series</h2><ul>{related_links}</ul>
""".strip()
    if words(body) < 900:
        raise RuntimeError(f"generated body too short for {slug}: {words(body)} words")
    return body


def meta_description(title: str, angle: str) -> str:
    base = f"Learn {angle[0].lower() + angle[1:]} This practical FAMtastic guide covers customer experience, workflow, proof, and next steps."
    if len(base) > 165:
        base = base[:164].rsplit(" ", 1)[0] + "."
    while len(base) < 110:
        base = base[:-1] + " for small businesses."
    return base


def main() -> None:
    current = json.loads(MANIFEST.read_text())
    tags = {item["key"]: item for item in current["tags"]}
    extra_tags = {
        "lead-capture": "Lead Capture",
        "customer-portal": "Customer Portal",
        "customer-experience": "Customer Experience",
        "business-automation": "Business Automation",
        "ecommerce": "Ecommerce",
        "stripe": "Stripe",
        "analytics": "Analytics",
        "ai-agents": "AI Agents",
        "drupal": "Drupal",
        "react": "React",
        "website-packages": "Website Packages",
        "55-cents-a-day": "55 Cents a Day",
        "web-basics": "Web Basics",
        "domain": "Domains",
        "hosting": "Hosting",
    }
    for key, label in extra_tags.items():
        tags[key] = {"key": key, "label": label}

    faqs = []
    series_records = []
    posts = []
    for series in SERIES:
        faq_keys = []
        for suffix, question, answer in FAQ_TEMPLATES:
            key = f"{series['key']}-{suffix}"
            faq_keys.append(key)
            faqs.append({
                "key": key,
                "question": question.format(context=series["context"]),
                "answer_html": f"<p>{answer.format(context=series['context'])}</p>",
                "category": series["category"],
                "status": "published",
            })
        slugs = [topic[1] for topic in series["topics"]]
        series_records.append({
            "key": series["key"],
            "title": series["title"],
            "thesis": series["thesis"],
            "audience": series["audience"],
            "stage": "awareness",
            "pillar_slug": slugs[0],
            "category": series["category"],
            "tags": series["tags"],
            "capabilities": series["capabilities"],
            "status": "published",
        })
        for sequence, topic in enumerate(series["topics"], 1):
            title, slug, keyword, angle = topic
            links = [f"/blog/{item}" for item in slugs if item != slug]
            links.append("/start")
            body = build_body(series, topic, sequence, slugs)
            secondary = [
                f"{keyword} for small business",
                f"how to plan {series['context']}",
                f"{series['context']} best practices",
            ]
            source_records = [{"name": series["source"][0], "url": series["source"][1], "type": "primary"}]
            if series["key"] == "fifty-five-cents-a-day":
                source_records = [
                    {"name": "BrightLocal Local Consumer Review Survey 2025", "url": "https://www.brightlocal.com/research/local-consumer-review-survey-2025/", "type": "original-research"},
                    {"name": "Verisign 2015 U.S. Online Survey", "url": "https://blog.verisign.com/getting-online/verisign-2015-online-survey-97-percent-of-smbs-would-recommend-having-a-website-to-other-smbs/", "type": "dated-original-research"},
                    {"name": "ICANN Registrant Resources", "url": "https://www.icann.org/resources/pages/registrant-2013-09-17-en", "type": "primary"},
                ]
            visual_file, visual_alt = VISUALS[series["key"]]
            if series["key"] == "fifty-five-cents-a-day":
                campaign_visuals = [
                    ("campaign-55-cent-character.webp", "A business owner beside a glowing 55 cents a day graphic and professional website."),
                    ("campaign-excuses.webp", "A baker, barber, and consultant considering common reasons for not having a website."),
                    ("campaign-trust-gap.webp", "A customer comparing an uncertain business listing with a complete professional website."),
                    ("campaign-55-cent-equation.webp", "A visual equation showing $199 divided by 365 equals about 55 cents a day."),
                ]
                visual_file, visual_alt = campaign_visuals[(sequence - 1) % len(campaign_visuals)]
            posts.append({
                "key": slug,
                "series": series["key"],
                "sequence": sequence,
                "pillar": sequence == 1,
                "title": title,
                "slug": slug,
                "status": "published",
                "category": series["category"],
                "tags": series["tags"],
                "capabilities": series["capabilities"],
                "primary_keyword": keyword,
                "secondary_keywords": secondary,
                "search_intent": "informational" if sequence != 7 else "commercial-investigation",
                "content_template": "pillar-page" if sequence == 1 else "how-to-guide",
                "target_audience": series["audience"],
                "search_intent_summary": f"Understand {angle[0].lower() + angle[1:]}",
                "reader_problem": f"The reader needs a practical way to decide how {series['context']} should work for their business.",
                "promised_outcome": angle,
                "evidence_boundary": f"{series['proof']} No customer revenue or universal outcome is claimed.",
                "excerpt": angle,
                "meta_title": title if len(title) <= 65 else title[:62].rsplit(" ", 1)[0] + "...",
                "meta_description": meta_description(title, angle),
                "og_title": title,
                "og_description": meta_description(title, angle),
                "canonical_url": f"https://famtasticdesigns.com/blog/{slug}/",
                "schema_types": ["Article", "BreadcrumbList"] + (["ItemList"] if sequence == 1 else []),
                "author": "FAMtastic Designs",
                "review_status": "editorial-review-required",
                "body_html": body,
                "word_count": words(body),
                "cta": {
                    "label": "Find the right next step",
                    "href": f"/start?source=blog&series={series['key']}&article={slug}",
                    "stage": "awareness" if sequence < 6 else "consideration",
                    "event": "cta_clicked",
                    "approval_required": False,
                },
                "faqs": faq_keys,
                "internal_links": links,
                "sources": source_records,
                "visual": ({
                    "src": f"/blog-images/{visual_file}",
                    "alt": visual_alt,
                    "brand_mark": "/brand/famtastic-mark.svg",
                    "caption": f"FAMtastic Designs field guide: {series['context'].title()}",
                }),
            })

    output = {
        "version": 2,
        "approval": {
            "broad_publish_approved": True,
            "live_price_changes_approved": False,
            "promotional_send_approved": False,
            "notes": "Fritz explicitly approved the 64-article library and requested the website-package and 55-cents-a-day series for immediate publication on 2026-08-11. Live price changes and promotional sends remain gated.",
        },
        "capabilities": current["capabilities"],
        "categories": current["categories"],
        "tags": sorted(tags.values(), key=lambda item: item["key"]),
        "series": series_records,
        "faqs": faqs,
        "posts": posts,
    }
    MANIFEST.write_text(json.dumps(output, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {len(series_records)} series, {len(posts)} posts, and {len(faqs)} FAQs to {MANIFEST}")


if __name__ == "__main__":
    main()
