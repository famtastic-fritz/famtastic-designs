# Communication lane integration contract — 2026-09-30

Specialist: `engineering/engineering-cms-developer`, pinned `765be42358100bf89d2faa567668a94c602f9a26`.

## Services

```yaml
famtastic_pipeline.communication_drafts:
  class: Drupal\famtastic_pipeline\Service\CommunicationDraftService
  arguments: ['@database', '@famtastic_pipeline.client_messages']
famtastic_pipeline.staff_ai_tasks:
  class: Drupal\famtastic_pipeline\Service\StaffAiTaskService
  arguments: ['@config.factory', '@database', '@flood', '@lock', '@?ai.provider']
```

Existing reply form create() uses those services and existing `famtastic_pipeline.mailer`. No new conversation route needed.

```yaml
famtastic_pipeline.staff_ai_settings:
  path: '/admin/famtastic/ai-assistance'
  defaults:
    _form: '\Drupal\famtastic_pipeline\Form\StaffAiSettingsForm'
    _title: 'Staff AI assistance'
  requirements:
    _permission: 'administer famtastic pipeline'
```

Add discoverable menu link under existing pipeline admin. Form API owns mutation CSRF. No public AI endpoint.

## Fresh-install schema plus idempotent update hook

Use exact field definitions below; existing rows label `active`, never infer tests from names.

```php
$schema['famtastic_portal_thread']['fields']['staff_label'] = ['type' => 'varchar', 'length' => 16, 'not null' => TRUE, 'default' => 'active'];
$schema['famtastic_message_draft'] = [
  'fields' => [
    'thread' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
    'uid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
    'revision' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
    'body' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
    'purpose' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
    'source_digest' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
    'status' => ['type' => 'varchar', 'length' => 16, 'not null' => TRUE],
    'changed' => ['type' => 'int', 'not null' => TRUE],
  ],
  'primary key' => ['thread', 'uid'],
];
$schema['famtastic_message_draft_revision'] = $schema['famtastic_message_draft'];
$schema['famtastic_message_draft_revision']['primary key'] = ['thread', 'uid', 'revision'];
$schema['famtastic_ai_receipt'] = [
  'fields' => [
    'id' => ['type' => 'serial', 'not null' => TRUE],
    'uid' => ['type' => 'int', 'not null' => TRUE],
    'task' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
    'provider' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
    'model' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
    'source_json' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
    'created' => ['type' => 'int', 'not null' => TRUE],
    'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE],
    'elapsed_ms' => ['type' => 'int', 'not null' => TRUE],
    'output_digest' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
    'prompt_digest' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
    'prompt_version' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
    'token_count' => ['type' => 'int', 'not null' => FALSE],
    'cost' => ['type' => 'numeric', 'precision' => 12, 'scale' => 6, 'not null' => FALSE],
  ],
  'primary key' => ['id'],
  'indexes' => ['actor_time' => ['uid', 'created']],
];
```

Receipts insert `started`, terminal transition guarded by `status=started`; terminal records are never modified. Provider exceptions retained as previous exception only, never exposed or persisted (avoid credential leakage). Output and sources digest only; actual provider/model and nullable tokens/cost. Source builder uses current authorized detail and no markRead. The generic adapter callers must supply account-authorized projections, with `id`, sha256(json_encode(data,JSON_THROW_ON_ERROR)) `digest`, and `data`.

Config install `famtastic_pipeline.staff_ai.yml`: all four `enabled` tasks (`reply`, `summarize`, `campaign`, `needs_me`) false; `hourly_limit: 5`. Schema config_object mapping enabled mapping four booleans + hourly_limit integer. Existing installs missing config safely act disabled.

## Provider proof boundary

API verified against installed `ai/docs/developers/call_chat.md`, `ChatInput.php` and `ChatOutput.php`. ChatInput owns system/stream setters. `AiProviderClientBase::create` (lines 211–218) accepts `http_client_options`; `OpenAiBasedProviderClientBase` line 234 passes bounded HTTP client to SDK. Adapter currently permits configured `openai` default only, enforcing 45s HTTP /10s connect timeout, no model fallback. Other provider adapters must verify equivalent timeout before enablement. No real model call performed. Configuration is not connection proof.

## Remaining verification

Integrator must run disposable Drupal persistence/Form API fixture after registration, including draft reload, stale source/revision, unauthorized access, preview/outbox zero, exact review send and duplicate submission. Lane has no vendor/core in isolated checkout; no live provider or real mail invocation authorized.

Persistent workflow fixture is `tests/fixtures/assert-communication-drafts.php`. Invoke through disposable drush after registration + fixture seed. It sends only into fixture memory outbox. No production invocation. Existing `php scripts/email-preview/test.php`: PASS 86 presentation assertions. PHP syntax passed for all lane classes.

Prior draft bodies preserved in insert-only `famtastic_message_draft_revision`, independently of current draft pointer. AI conversation context is last eight messages, at most 3000 chars each, excludes recipient email/name fields; historical source IDs/digests bound in receipt. Inbound text may itself contain PII; this is explicit staff-triggered provider processing, not private/local inference.
