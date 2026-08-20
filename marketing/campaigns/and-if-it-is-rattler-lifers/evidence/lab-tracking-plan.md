# FAMtastic Lab tracking plan — AND IF IT IS?

## Identity

- Public route: `https://famtasticdesigns.com/lab/and-if-it-is/`
- Stable content ID: `famtastic-lab-and-if-it-is-v1`
- GA4 measurement ID: `G-T2ENFBZR4K`
- Campaign: `and_if_it_is_case_study`

Companion audience experience:

- Public route: `https://famtasticdesigns.com/and-if-it-is/`
- Stable content ID: `and-if-it-is-rattler-lifers-v1`
- The experience and Lab use separate page paths and content IDs so audience
  engagement is not confused with process/case-study engagement.

## Events

| Event | Trigger | Required parameters | Lifecycle use |
| --- | --- | --- | --- |
| `page_view` | Lab page loads | `content_id`, sanitized `page_path`, canonical `page_location`, title | Reach and case-study engagement |
| `cta_clicked` | Visitor opens the live experience | `content_id`, `cta_id=live-experience`, `cta_location`, `destination_type=owned_experience` | Proof interest |
| `cta_clicked` | Visitor starts intake | `content_id`, `cta_id=intake`, `cta_location`, `destination_type=owned_intake` | Qualified demand |
| `page_view` | Public experience loads | experience `content_id`, canonical path/location, title | Audience reach |
| `roll_call_generated` | Visitor generates a device-local roll call | experience `content_id`, `storage_scope=device_only` | Interaction completion without visitor content |
| `roll_call_shared` | Visitor copies or invokes sharing | experience `content_id`, `method` only | Voluntary sharing behavior without year or memory text |
| `cta_clicked` | Visitor opens the Lab DNA companion | experience `content_id`, `cta_id=lab-dna`, `cta_location=footer` | Process-interest handoff |

The intake links include `source=famtastic-lab`, `campaign=and-if-it-is`,
`content`, and standard UTM values. They contain no name, email, phone, token,
session, or other personal identifier.

## Current boundary

GA4 can attribute the session and CTA. The current Solution Finder request
payload does not yet persist all query parameters as a stable Drupal content ID,
so the cross-system GA4-to-lead join remains an open marketing-extraction gate.
This page does not write customer, project, pricing, approval, or Site Studio
state directly.

The experience never sends the class year or memory text to GA4. Those values
remain in browser local storage and leave the device only when the visitor
explicitly copies or shares them.
