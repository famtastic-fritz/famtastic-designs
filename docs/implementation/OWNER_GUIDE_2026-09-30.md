# Fritz’s backend and mobile command-center guide

These improvements are built and tested in a separate local environment. They are not yet installed on your live website. The steps below describe the finished candidate after an approved release. Your live social publishing connection is currently offline; that needs a separate controlled recovery before social publishing can work.

## What choosing a default AI model gives you

A default model tells Drupal which configured AI service to ask when you press an AI button. It is the engine choice. The new buttons turn that engine into useful work:

- In a conversation: suggest a reply or summarize recent messages.
- In a campaign: suggest a content plan using the saved campaign brief.
- On your command center: summarize the work needing your attention.

Choosing a default alone does not start any of these jobs. You also choose which tasks to enable in **AI assistance**, and set a maximum number of requests per staff member per hour. The current adapter supports a configured OpenAI chat model with a request timeout. Other providers need a verified adapter before these buttons can use them. Provider calls may cost money.

AI produces an editable suggestion. It does not send the reply, publish the campaign, approve a project, change prices or charge a customer. The screen shows the actual provider/model and records the result. A failed request leaves your saved work unchanged. If no model or task is enabled, the screen explains what needs setup; it does not present a canned answer as an AI response.

After release and authorized setup: open **AI assistance**, review the provider configuration, enable only the tasks you want, choose a low hourly limit, and save. A small separately authorized test call is still needed to prove the live provider works. Local tests used a fake provider, so they do not establish live response quality or cost.

## Start from your phone

Open **FAMtastic Operations**. The home screen points you to replies, reviews, delivery problems and upcoming work based on stored records. Use **Plan a campaign** or **Open communication desk** to begin. The bottom navigation keeps the main destinations accessible; **More** opens the remaining tools. Technical details can be expanded when you need them.

**Summarize my work** can help prioritize the visible workload once that AI task is enabled. It receives aggregate work information, and its suggestion does not complete or change the underlying tasks.

## Create or improve a campaign

1. Open the campaign workspace and select the campaign you want. The selection follows you through its planning, content, calendar, creative and results screens.
2. Create a campaign or choose **Edit plan**. Enter its goal, audience, offer/evidence, next action, channels and dates. Campaigns can have different lengths; you are no longer limited to the original 17 days.
3. Add content ideas and dated channel entries, then save the draft. If enabled, ask AI for suggestions using the saved brief, review its text and save the changes you want.
4. Duplicate a plan to start a separate draft. Archive plans you no longer need; restore brings them back as drafts. Archiving does not cancel posts already scheduled with a provider.

A saved plan is not a published campaign. Original campaign history stays available. Imported schedule files are a labeled source snapshot with a source version and sync time; they are not a promise that a social worker is running. Publishing and provider receipts remain separate. Missing assets or unavailable providers are shown as problems to resolve.

The current live Postiz endpoint is offline, not simply missing a setting. Recovery must inspect existing queued schedules before restarting its workers, because restarting could resume old work. Until authenticated health succeeds, do not treat social publishing as ready.

## Prepare and send a customer reply

1. Open **Messages**, then the customer conversation. Read its history and confirm that you are in the intended conversation.
2. Choose a purpose such as **Personal reply**, **Ask for missing information**, **Follow up**, or **Acknowledge a message**. Add the specific question or update, then use the purpose template to start the wording.
3. Edit the reply in your own voice and **Save draft**. It survives reload. Saving does not queue or send email.
4. If enabled, ask for an AI reply or summary. Check every fact; edit or discard the suggestion. AI generation does not send anything.
5. **Preview** the branded message. Check the exact recipient and the final text, then confirm the recipient and choose **Send reviewed reply** only when ready.
6. Read the delivery status in the conversation. **Queued** means waiting for the delivery worker; it does not mean delivered to the inbox. If the draft or conversation changes after review, preview it again.

These purpose templates work inside existing message threads. This release does not add an arbitrary-recipient email composer, bulk mailing tool or general template-management CMS. These purpose templates are for personal customer communication. They cannot bypass proof approval, account access, promotional eligibility or other business rules. System notices such as proof-ready emails remain attached to their original approved business transitions.

Use the reversible record group controls to separate explicit tests or duplicates from the ordinary customer backlog. Nothing is deleted. The Email Center defaults to customer communication; **Operational alerts** groups system messages, while **Failed deliveries** keeps delivery problems visible across categories.

## What has actually been proved

The separate local Drupal environment passed campaign lifecycle, durable drafts, branded preview, recipient review and synthetic queueing tests. The phone layout was checked at 390 pixels and desktop at 1440 pixels. The updated security dependencies passed their regression checks. No real customer email, paid AI call or social post was performed, and production has not been changed. The release handoff names the remaining deployment, AI setup and Postiz recovery steps.
