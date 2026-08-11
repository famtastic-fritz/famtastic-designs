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


def build_body(series: dict, topic: tuple, sequence: int, slugs: list[str]) -> str:
    title, slug, keyword, angle = topic
    source_name, source_url = series["source"]
    related = [item for item in slugs if item != slug]
    related_links = "".join(
        f'<li><a href="/blog/{item}">{item.replace("-", " ").title()}</a></li>' for item in related
    )
    role = "pillar guide" if sequence == 1 else "focused guide"
    body = f"""
<p><strong>{html.escape(sentence(angle))}</strong> That is the practical answer behind this {role}. The useful question is not whether a business can add another page, form, dashboard, automation, or AI feature. It is whether the change makes an important customer decision easier and gives the business a reliable way to respond.</p>
<p>{html.escape(series['audience'])} can use this guide to define the work before choosing software or approving a build. The examples follow FAMtastic's evidence boundary: {html.escape(series['proof'])} This article explains a repeatable method and does not claim that the same configuration or result fits every business.</p>
<h2>Key takeaways</h2>
<ul><li>Begin with the customer action and the business responsibility that follows it.</li><li>Record ownership, timing, data, exceptions, and the definition of done before automating the path.</li><li>Choose the smallest useful implementation that can be tested without blocking later growth.</li><li>Measure completed outcomes and customer effort, not activity alone.</li><li>Keep commercial promises and production changes behind an explicit approval step.</li></ul>
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
                "sources": [{"name": series["source"][0], "url": series["source"][1], "type": "primary"}],
                "visual": ({
                    "src": f"/blog-images/{VISUALS[series['key']][0]}",
                    "alt": VISUALS[series["key"]][1],
                    "brand_mark": "/brand/famtastic-mark.svg",
                    "caption": f"FAMtastic Designs field guide: {series['context'].title()}",
                } if sequence in {1, 3, 5, 7} else None),
            })

    output = {
        "version": 2,
        "approval": {
            "broad_publish_approved": True,
            "live_price_changes_approved": False,
            "promotional_send_approved": False,
            "notes": "Fritz explicitly approved publication of all 64 articles and their supporting FAQs on 2026-08-11. Live price changes and promotional sends remain gated.",
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
