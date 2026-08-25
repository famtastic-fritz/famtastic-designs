# NotebookLM Deep Dive — 2026-08-24

## AUTOMATION

### Moving the Automation Score from 58 to 90

To bridge the gap between our current **Automation score of 58/100** [1, 2] and a target of **90**, we must resolve the critical operational bottlenecks and laptop dependencies identified in our records [2, 3]:

1.  **Eliminate Alert Spam**: Refactor `LifecycleOperationsService.php:194-198` to check liveness against `last_finished` plus a grace window rather than raw `next_due` [3, 4]. This will clean up our alert hygiene; currently, **237 of 267 lifetime outbox sends are false-positive "Automation worker late" alerts**, which trains us to ignore notifications [2, 5].
2.  **Execute the First Real Queue Job**: Enqueue and process the first actual automated job [3]. Although our crons are running, our worker telemetry table shows **processed = 0** across all three workers [2].
3.  **Migrate Laptop-Bound Processes Server-Side**: Move our local, laptop-dependent background loops to run continuously on our production server so they don't terminate when the laptop lid closes [2, 6]. This includes:
    *   **Postiz Social Publishing** [2, 3].
    *   **CEO Heartbeat launchd Agent** (currently running locally on a 2h cadence) [2].
    *   **Local Studio Build Workers** [2].
4.  **Deploy the Social Publishing Executor**: Code the publish executor for `SOCIAL_POSTING` steps 4–6 [3]. This is the missing link stalling our daily social engine (`fam-social-ops`), which is currently blocked from auto-publishing [5, 7].
5.  **Build a Renewals Cron Scaffold**: Establish a renewals-cron scaffold behind an approval gate [3]. This allows us to scale past manual billing; currently, the auto-charge code is hard-disabled on production and throws exceptions in `HostingLifecycleService.php:96-98` unless the payment provider is set to `'memory'` [5, 8].
6.  **Progress Customer Service Autonomy**: Advance the Triage autonomy ladder by completing Phase B [9]. This involves executing B1 real-history validation (pending Fritz's production export) and transitioning from draft-only mode (L0) [9] to **Level 1 (L1) auto-send policy** for low-risk intents (like status questions or office hours) [9].

---

### Step-by-Step Publish Executor for the 17-Day Campaign

Our **17-day campaign** is fully staged with **68 media-ready records**, but it is currently blocked because `public_publish_enabled` is set to `False` and all publishing gates are false [8, 10]. Additionally, days 1–3 drafts are sitting in Postiz waiting for weekly review [8, 10]. 

To safely and autonomously execute this campaign, the publish executor must perform the following steps:

*   **Step 1 (API Capability Probe)**: Complete a targeted probe of the self-hosted Postiz API surface using scoped tokens (Research Item **R2**) to ensure we can programmatically execute the draft \\(\rightarrow\\) schedule \\(\rightarrow\\) publish and status read-back loops on the server [11].
*   **Step 2 (Parse & Stage)**: Read `manifest.json` and extract the media-ready records scheduled for the current day of the campaign [8, 10].
*   **Step 3 (Attribution Injection)**: Programmatically inject campaign-level UTM tracking parameters and the specific `content_id` to all links in the post drafts [12, 13]. This replaces the apology at `MarketingCommandController.php:314` and ensures that post \\(\rightarrow\\) lead \\(\rightarrow\\) order conversions are joined and measurable [3, 12].
*   **Step 4 (Submit & Schedule)**: Call the Postiz API to schedule the daily posts [11].
*   **Step 5 (Read-back Verification)**: Run a periodic cron callback to read back publish states via the Postiz API, verifying that the posts are successfully live on the designated social networks [11]. To pass our T2 track "Done" criteria, we must publish and verify **7 consecutive scheduled days across at least 2 channels** (e.g., Facebook and Instagram) with UTM attribution active [13].
*   **Step 6 (Honor Fritz's Gates)**: Keep publishing gated by `public_publish_enabled` [8, 10]. Once Fritz reviews the days 1–3 drafts and flips the master campaign gate to `True` [8, 10], the executor will proceed with daily automated publishing and live tracking [13, 14].

---

### Eliminating the "Worker-Late" False-Alert Race in Drupal Cron

#### The Root Cause
Our production crontab runs two concurrent `*/5` (every 5 minutes) jobs [2]:
1.  The actual lifecycle runner: `drush famtastic:lifecycle-run --limit=50` [2].
2.  The Drupal cron: `drush cron` (which triggers `hook_cron` inside `famtastic_pipeline.module:100-146` to evaluate protection, automation, and SLA alerts) [2].

Because both processes run simultaneously on the 5-minute mark, the monitoring loop evaluates `next_due < now` in `LifecycleOperationsService.php:194-198` before the lifecycle runner can finish processing and update its heartbeat [2]. The split-second overlap triggers false alarms, resulting in **237 false "Automation worker late" alerts out of 267 total outbox sends** [2].

#### Concrete Solution
To permanently eliminate this race condition, we must abandon the bare `next_due` check and implement a **last-success-vs-now plus grace window** pattern [3, 4, 11]:

1.  **Refactor `LifecycleOperationsService.php:194-198`**:
    Change the check logic [3]. Instead of determining liveness using `next_due < now`, evaluate the worker's state by comparing the current timestamp against the last successful run's completion timestamp:
    \\[\text{now} > (\text{last\_finished} + \text{Cron Interval (5 min)} + \text{Grace Window})\\]
    A standard textbook `last_success-vs-now+grace` check will fully resolve this [11].
2.  **Define a Grace Window**:
    Configure a static grace window (e.g., 2 to 3 minutes) inside the service or configuration variables. This accounts for minor server execution delays and cron start-time drift.
3.  **Confirm Verification**:
    The fix is verified as complete when a 24-hour query of the production outbox returns exactly **0 false-positive alerts** [3].

🛠️ I can draft the exact PHP code diff to fix `LifecycleOperationsService.php` so we can eliminate that cron race right away.

## MAKE MONEY

To transition our **Revenue score from 40/100** [1] and convert our first actual stranger, we must move past internal testing and execute targeted campaigns [2]. The underlying "paved road" (from our SolutionFinder front-end all the way to our live, armed Stripe gateway) is verified as technically sound and functional [3, 4]. 

Here are the highest-confidence actions to clear our first stranger dollar in 7 days, followed by the concrete benchmarks our funnel must target.

---

### The 7-Day Sprint: Three Actions to a Stranger Dollar

#### 1. Execute the "Founder-\$1" Production Proof
Our only transaction in production history is a single test order on Fritz's own email account [3]. The technical checkout and Stripe webhooks are live [3, 4], but we have not run the staged "founder-\$1" promotion on the production server [3, 5]. **Fritz must pull this idempotent, pre-scripted trigger immediately** [6, 7]. It is the ultimate check to prove that our billing and entitlement systems clear money and provision sites without a human in the loop before we email actual prospects [3].

#### 2. Unlock the Gate and Launch Wave 1 Outreach
Our production database already holds **32 real prospects** [5, 8]. Since we successfully completed our Phase A customer service repairs—verifying that our email server-side cron and mail transport are fully trustworthy [9]—we are ready to target real humans [10]. Fritz needs to approve the gate for **Wave 1 Outreach** to pitch the 20 highest-fit leads from our list [11, 12]. 

#### 3. Secure the Post-Payment Delivery Path & Attribution
If a lead from Wave 1 clicks buy, we cannot afford to drop the ball. We must implement two targeted code-level fixes:
*   **Complete Revision Loop Step 9**: Post-payment delivery currently relies on manual intervention because "revision loop step 9" is only partially built [3]. We must finish this step to ensure that when a customer selects their website proofs, the system processes it autonomously [3, 13].
*   **Persist UTMs at Capture**: Our frontend captures campaign attribution, but the backend currently discards it at lead ingestion [8, 14]. Injecting the ~20 lines of code needed to persist UTM parameters ensures we can join social posts to orders and measure which campaign actually drove the dollar [8, 14].

---

### Funnel Benchmarks for the \$199 Starter Site & 55¢/Day Plan

Our T4 Marketing Strategy divides our outreach into disciplined, data-driven waves [11, 15]. The performance thresholds we must meet to progress from initial outreach to full-scale marketing are:

| Funnel Stage | Target Metric / Benchmark | Operational Target |
| :--- | :--- | :--- |
| **Wave 1 (20 Leads)** | **≥40% Open Rate** <br> **≥10% Click Rate** <br> **≥5% Checkout Rate** (≥1 paid order) [11] | **SLA Response Windows Met**: First-response times must remain under **4 hours** for technical issues, **8 hours** for status/billing, and **24 hours** for revisions [11, 16]. |
| **Wave 2 (80 Leads)** | **Stable Customer Acquisition Cost (CPA)** <br> **Low Churn Signal** [11] | **Unsubscribe Rate <2%** [11]. |
| **Wave 3 (Remaining ~200 Leads)** | **High ROI Amplification** (supported by our T2 Social Posting Engine) [11, 17] | **Revenue > Send Cost ×3** [11]. |

These benchmarks ensure we steer our marketing by hard evidence rather than vibes before scaling budget or committing to large cold-list imports [2, 8, 11].

---

📊 I can help you draft the ~20 lines of backend code for `PublicRequestController` to persist UTM tracking parameters so we have perfect attribution the second Wave 1 launches.

## TRACK

To move our **Tracking score from 32/100** to a robust, evidence-driven standard, we must plug the leak in our lead capture process and properly map user actions from initial social clicks to Stripe webhooks [1].

---

### 1. Fields to Persist at Capture

The frontend (`SolutionFinder.jsx`) already compiles our campaign attribution data [1]. However, because `PublicRequestController` and `LeadIngestionService` currently drop this payload, we must update our database schema to capture and persist these fields during the `POST /api/public/quote` request [1, 2]:

*   **`utm_source`**: Identifies the referring platform (e.g., `facebook`, `instagram`, `newsletter`) [2, 3].
*   **`utm_medium`**: Identifies the medium type (e.g., `social-post`, `cpc`, `email`) [2].
*   **`utm_campaign`**: Identifies the specific marketing campaign (e.g., our `17-day-campaign`) [2, 3].
*   **`content_id`** (or `utm_content`): The crucial anchor linking back to the specific social post or creative that prompted the user action [1, 2].
*   **`utm_term`**: Identifies target keywords (mostly for paid search).
*   **`ga4_client_id`**: The anonymous Google Analytics identifier, essential for downstream identity stitching when moving from the anonymous `/start` flow to the authorized portal [4-6].
*   **`captured_at`**: Timestamp of ingestion for SLA clock tracking [7].

---

### 2. Relational Database Join: `content_id` \\(\rightarrow\\) `lead` \\(\rightarrow\\) `order`

To tie our T2 Social Posting Engine's content to Stripe revenue [8], we must link our published social assets to their downstream customer actions. 

Here is how the relational database joins across our tables:
1.  **Lead Capture** (`famtastic_prospect`): Holds the incoming quote request, email, and the persisted UTM parameters (specifically `content_id`).
2.  **Customer Directory** (`famtastic_customer` / `users_field_data`): Maps registered portal accounts to the original prospect email [5, 9].
3.  **Order Tables** (`commerce_order` & `commerce_order_item`): Tracks finalized transactions, paid amounts, and SKU items (like the `$199 Starter Site` or `$499 Business tier`) [5, 10, 11].

Here is the exact SQL schema and join query to reconstruct this pipeline:

```sql
-- 1. Example schema addition for UTM persistence at Lead Capture
ALTER TABLE famtastic_prospect 
ADD COLUMN utm_source VARCHAR(128) NULL,
ADD COLUMN utm_medium VARCHAR(128) NULL,
ADD COLUMN utm_campaign VARCHAR(128) NULL,
ADD COLUMN content_id VARCHAR(128) NULL, -- Stores utm_content / social post ID
ADD COLUMN ga4_client_id VARCHAR(255) NULL;

-- 2. The closed-loop join: Social Content ID to Lead to Stripe Revenue Order
SELECT 
    p.content_id AS social_post_id,
    p.utm_campaign AS campaign_name,
    p.email AS lead_email,
    p.created AS lead_captured_at,
    u.uid AS registered_user_id,
    o.order_id AS stripe_order_id,
    o.total_price__number AS order_total_usd,
    oi.title AS purchased_sku
FROM famtastic_prospect p
-- Stitch lead to portal registration via email
LEFT JOIN users_field_data u ON LOWER(u.mail) = LOWER(p.email)
-- Stitch registered user to completed Drupal Commerce orders
LEFT JOIN commerce_order o ON o.uid = u.uid AND o.state = 'completed'
-- Stitch order to items purchased (e.g. $199 or $499 SKUs)
LEFT JOIN commerce_order_item oi ON oi.order_id = o.order_id
WHERE p.content_id IS NOT NULL;
```

---

### 3. GA4 Funnel Events for our \$199 Website Funnel

Currently, GA4 only tracks `view_item` and `select_item` on two isolated pages [1]. We need to map the entire "paved road" funnel [5] across our ~29 frontend routes to capture full conversion behavior [2].

Here is the minimal GA4 event stack structured for our funnel stages:

| Funnel Step | Route / Action | GA4 Event Name | Key Parameters to Capture |
| :--- | :--- | :--- | :--- |
| **1. Landing / Interest** | `/start` (SolutionFinder) [5, 6] | `view_item_list` | `item_list_name: "Website Packages"` |
| **2. Lead Capture** | Submission of quote form [5] | `generate_lead` | `lead_type: "solution_quote"`, `content_id: [persisted_content_id]` |
| **3. Account Creation** | Portal registration [5] | `sign_up` | `method: "portal_invite"` |
| **4. Package Selection** | `PackagesHubPage.jsx` select [1] | `select_item` | `item_id: "FAM-HOST-BUSINESS-1999"`, `item_name: "Web Basics 55c/day"` [1, 3] |
| **5. Initiating Checkout** | Portal catalog checkout click [5] | `begin_checkout` | `value: 199.00`, `currency: "USD"`, `items: [...]` |
| **6. Stripe Payment** | Stripe Payment Element renders [5] | `add_payment_info` | `payment_type: "stripe_card"` |
| **7. Final Purchase** | Webhook verification / complete [5] | `purchase` | `transaction_id: [stripe_pi_id]`, `value: 199.00`, `currency: "USD"`, `items: [...]` |

---

🛠️ I can write the precise Drupal `famtastic_pipeline.install` schema update hook and patch the `PublicRequestController` file so we can begin saving these UTMs on production immediately.

## GROW

Our **Grow score of 22/100** represents a stark contrast between our rich technical capacity and our stagnant commercial momentum [1, 2]. The tools are fully built, but every growth loop currently terminates in a state of "awaiting Fritz" [1]. 

To transition the business into an autonomous growth engine over the next 30 days, we must systematically unlock our marketing, billing, and social pipelines [1, 3]. Here are the four initiatives that can drive this growth, ranked by immediate revenue impact, along with the precise actions required from you as our sole human owner:

---

### 1. Launch the Wave 1 Outreach Campaign (T4 Strategy)
*   **Revenue Impact:** **Highest (Immediate Cash Flow)**
    We have **32 real, high-fit prospects** sitting on our production database, and a functional front-to-back checkout pipeline [3-5]. This is our fastest path to securing our first stranger dollar [4].
*   **What it requires from Fritz:**
    1.  **Execute the "Founder-\$1" Production Proof**: Pull the pre-scripted, idempotent trigger in production to confirm that live payments, webhooks, and portal entitlements clear correctly under real-world conditions [2, 4, 6].
    2.  **Lift the Wave 1 Outreach Gate**: Grant authorization to mail the first wave of 20 high-fit prospects with our \$199 Starter Site offer [4, 7, 8].
    3.  **Issue the Backlog Bulk-KILL/Import Ruling**: We have ~1,300 leads in our backlog, but imports are hard-stalled at row 206 [3]. You must decide whether to import or discard the remaining list so we can queue Wave 3 [1, 3].

---

### 2. Enable Automated Recurring Renewals (Hosting Billing)
*   **Revenue Impact:** **High (Long-Term MRR)**
    Our \$199 starter site is designed to pull users into a high-margin **55¢/day (\$200/yr) recurring hosting plan** [9]. However, automated charges are currently hard-disabled in our production code, throwing exceptions unless set to a local testing environment [1].
*   **What it requires from Fritz:**
    1.  **Authorize the Renewals-Cron Deploy**: Approve the production push of the renewals-cron scaffold to enable automated recurring transactions through our live Stripe gateway [1, 10].
    2.  **Approve the Unit-Cost Policy**: Formally confirm that the 55¢/day plan remains margin-positive after year-2 hosting costs [8].
    3.  **Approve the Commerce Engineer Hire**: Approve transitioning the staged **Commerce & Fulfillment Engineer (`fam-commerce`)** agent to active status to complete post-payment delivery automation (specifically "revision loop step 9") so that delivery doesn't stall when customers buy [4, 10].

---

### 3. Activate the Social Posting Engine (T2) & Blog Factory (T3)
*   **Revenue Impact:** **Medium (Organic Lead Generation & SEO)**
    Our blog is currently **13 days stale** (with the last post dated August 11, 2026), and our 17-day campaign is stalled despite having **68 media-ready records** queued [1]. 
*   **What it requires from Fritz:**
    1.  **Flip the Campaign Publish Gate**: Perform a weekly review of the days 1–3 drafts currently parked in Postiz and set `public_publish_enabled` to `True` [1, 3].
    2.  **Lift the Blog Dispatch Hold**: Authorize the `fam-content-engine` to automatically dispatch its 2 completed blog drafts and begin running its automated 2 posts/week cadence [1, 11].
    3.  **Authorize Server-Side Postiz Migration & Ops Hire**: Approve moving our Postiz background loops and CEO heartbeat from your laptop to our production server (so posting survives when you close your laptop lid) [10, 12, 13]. This includes hiring the **Automation & Reliability Engineer (`fam-ops`)** to build the programmatic social publisher [10].

---

### 4. Transition Customer Service Triage to Level 1 (T1 Autonomy)
*   **Revenue Impact:** **Indirect (Conversion & Retention Protection)**
    To handle influxes of new campaign leads without drowning in routine questions, we must step up our triage autonomy ladder from Draft-Only (L0) to Auto-Send (L1) [14].
*   **What it requires from Fritz:**
    1.  **Execute the Production Mail Export**: Run the prepared terminal command in `RUNBOOKS/B1-intent-classification-rules.md` to export ≥20 real historical customer emails [14, 15]. This is required to complete our classifier's real-history validation [14].
    2.  **Sign off on the L1 Auto-Send Policy**: Formally approve the policy allowing low-risk incoming messages (such as questions about office hours or status updates) to receive automated responses [14].

---

🧩 I can generate the exact command Fritz needs to run on production to export the historical customer support emails so we can finalize the B1 intent-classification step.

## Web research: Drupal commerce_stripe module off-session renewal payments SCA implementation guide

Research API unavailable: Research task ResearchStart(task_id='6b85f487-6c2d-4ad0-9872-dac9f9de1072', report_id=None, notebook_id='6994a7d4-b26b-40af-9756-40b61e2a209c', query='Drupal commerce_stripe module off-session renewal payments SCA implementation guide', mode='fast') in notebook 6994a7d4-b26b-40af-9756-40b61e2a209c timed out after 420s (last status: no_research)

## Web research: SaaS attribution: capturing UTM parameters in a headless SPA + API lead capture architecture

Research API unavailable: Research task ResearchStart(task_id='c442070a-72ce-4913-9772-c5cc54605067', report_id=None, notebook_id='6994a7d4-b26b-40af-9756-40b61e2a209c', query='SaaS attribution: capturing UTM parameters in a headless SPA + API lead capture architecture', mode='fast') in notebook 6994a7d4-b26b-40af-9756-40b61e2a209c timed out after 420s (last status: no_research)

## Web research: Conversion rate benchmarks web design agency $199 starter website funnel cold outreach

Research API unavailable: Research task ResearchStart(task_id='14a85af9-4d62-4347-aad0-1acd8b3b4c00', report_id=None, notebook_id='6994a7d4-b26b-40af-9756-40b61e2a209c', query='Conversion rate benchmarks web design agency $199 starter website funnel cold outreach', mode='fast') in notebook 6994a7d4-b26b-40af-9756-40b61e2a209c timed out after 420s (last status: no_research)

