# Lead Outreach Smoke Runbook

This is the bounded operating path for the first three ten-recipient learning
batches. It does not authorize bulk outreach. Each exact campaign remains
behind the existing explicit approval and suppression gates.

## Batch contract

1. Select one lawful public or licensed source and record its source URL,
   publication date, geography, and record count.
2. Research no more than 50 candidates across no more than 15 categories.
3. Exclude regulated or sensitive categories for the first pilot. Exclude any
   business with a healthy owned website unless a specific upgrade defect is
   recorded.
4. Put raw contact data in a mode-`0600` file outside Git. The repository may
   retain aggregate counts and findings, but not the prospect email list.
5. Run `famtastic:leads-import --dry-run` first. Review qualification,
   suppression, deduplication, offer routing, and every personalization fact.
6. Import the approved ten. Importing creates prospects and `proof.generate`
   jobs; it never sends email.
7. Generate and visually review exactly three proof directions per prospect.
   Placeholder fixtures are not customer-ready and must fail closed.
8. Verify the sending identity with an internal inbox test. A Drupal/SMTP
   `accepted` result is not delivery proof.
9. Review the exact template, valid postal address, recipients, proof links,
   suppression state, and campaign key. Approve only that exact campaign.
10. For the first batch, send one message and verify it before releasing the
    remaining nine at a maximum of one message every six minutes.

## Scheduling ownership

Production work belongs on the server, not a local laptop. A local cron stops
when the machine sleeps or loses connectivity. Drupal cron is active on the
current GoDaddy host, but the custom automation worker is not registered with
`hook_cron()`. Until that integration is deliberately added and tested, use a
bounded cPanel cron entry beside the production database:

```cron
*/6 * * * * cd /home/ACCOUNT/public_html && ./vendor/bin/drush --root=web famtastic:jobs-run --type=outreach.send --campaign=EXACT_CAMPAIGN_KEY --limit=1
```

Replace `ACCOUNT` with the environment-owned account path. Do not install this
entry until the internal delivery test, proof review, postal-address gate, and
exact campaign approval all pass. Never omit the exact campaign scope from a
production cron. Proof generation should initially be run one prospect at a
time with `--prospect=ID`, because it is materially more expensive than queue
processing and each result needs visual acceptance.

The interval between learning batches is 72 hours. Batch 2 is not approved
automatically; review Batch 1 metrics and revise source, qualification, proof,
or copy first. Apply the same rule between Batches 2 and 3.

## Measurement contract

Record these counts for every batch:

- source records and candidates researched;
- qualified, unqualified, duplicate, suppressed, and invalid;
- proofs dispatched, proof callbacks received, and proofs visually accepted;
- messages staged, approved, queued, SMTP-accepted, provider-delivered,
  bounced, complained, opened, clicked, unsubscribed, replied, and purchased;
- elapsed time and operator intervention at each stage.

GoDaddy SMTP does not currently post delivery/bounce/complaint webhooks into
the pipeline. Until a webhook-capable provider is connected, `sent` means only
SMTP accepted. Inbox evidence and the sending mailbox's bounce/reply handling
must be reviewed manually; do not report these messages as delivered. The
current outreach is plain text and does not embed the open endpoint as a
tracking pixel, so opens are also `unknown`; prioritize clicks, replies,
unsubscribes, and purchases rather than reporting synthetic open data.

Pause a batch on any complaint, any template/address defect, two bounces in a
ten-recipient batch, a broken unsubscribe route, an unreviewed proof, or a
failure of the internal delivery test.
