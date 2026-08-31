# Owner-invited deep-dive follow-up template

Use this for a prospective website client when the first conversation is not
enough to responsibly recommend design, booking, payment-display, local-growth,
or content decisions. It is a planning invitation, not a quote, contract,
payment request, booking change, or launch notice.

## Custom fields

Use only the four supported merge fields in custom copy:

- `{{contact_name}}` — client name; defaults to `there`.
- `{{business_name}}` — the public-facing business name.
- `{{duration}}` — expected completion time; defaults to `10 minutes`.
- `{{interview_url}}` — private, bearer-secured planning link; inserted only
  at send time and never stored in the invitation template snapshot.

## Initial invitation — send only after owner review

**Subject:** `A few questions to shape {{business_name}}'s website`

```text
Hi {{contact_name}},

We want to understand {{business_name}} before we design anything. This
private interview asks one question at a time about your services, booking,
brand, location, photos, payments, and growth goals. It takes about
{{duration}}, saves your progress, and does not ask for payment details.

Start your private planning interview:
{{interview_url}}

After you finish and verify your free FAMtastic account with this same email
address, your answers will be saved in your private workspace. We will then
prepare a six-direction creative plan for owner review; no site, payment flow,
or booking change goes live without your approval.

— FAMtastic Designs
```

## Follow-up cadence — drafts, never automatic sends

| When | Condition | One job | Subject |
|---|---|---|---|
| 2 business days | Interview not started | Make resuming feel easy | `Still want to shape {{business_name}}'s website?` |
| 5 business days | Interview started but incomplete | Name the saved progress and invite one next answer | `Your website planning interview is saved` |
| 10 business days | Interview still incomplete | Offer a human planning call instead of pressure | `Want help finishing the website plan?` |

**Not started draft**

```text
Hi {{contact_name}},

Your private planning interview for {{business_name}} is ready whenever you
are. It is saved as you go, and you only answer one question at a time.

Continue here:
{{interview_url}}

If a short conversation would be easier, reply with the best time to talk.
Nothing is being purchased, published, or changed by opening the interview.

— FAMtastic Designs
```

**In-progress draft**

```text
Hi {{contact_name}},

Your answers for {{business_name}} are saved. A few more details about your
booking, brand, and growth goals will let us prepare a more useful creative
plan.

Resume your private interview:
{{interview_url}}

We will not change Booksy, take a payment, or publish anything from these
answers without your approval.

— FAMtastic Designs
```

**Human-help draft**

```text
Hi {{contact_name}},

If the planning interview is not the easiest way to explain what you want for
{{business_name}}, we can talk it through together. Your saved answers will
still be there whenever you return.

Resume the interview:
{{interview_url}}

Reply if you would rather schedule a planning conversation.

— FAMtastic Designs
```

## How to customize the initial email

The invitation command keeps sends exact-recipient only. Without `--send`, it
creates a draft, stores a safe template snapshot, and prints the reviewed
email. `--send` still requires `--confirm` to repeat the exact email address.

```bash
drush famtastic:deep-dive-invite \
  --email=client@example.com \
  --confirm=client@example.com \
  --business="Example Studio" \
  --contact="Alex" \
  --duration="8 minutes" \
  --subject="A few questions before we shape {{business_name}}" \
  --intro="Before we recommend anything for {{business_name}}, we want to understand what clients need most. This private interview takes about {{duration}}." \
  --cta="Open your private planning interview:" \
  --next-steps="Your answers are saved as you go. After free account verification with this email, we will prepare an owner-reviewed creative plan. No payment, booking, or publishing change happens without your approval." \
  --signature="— Fritz at FAMtastic Designs"
```

Do not put credentials, card/bank details, private client information, or a
manual copy of the bearer URL in custom copy. Review every rendered draft before
adding `--send`.
