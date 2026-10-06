# Campaign implementation handoff — 2026-09-30

Profile engineering/engineering-cms-developer, upstream pin `765be42358100bf89d2faa567668a94c602f9a26`. Foundation v1.0.0 and repository contracts reviewed. No live changes, network provider calls, schedule writes, messages, publication or commit performed.

## Serialized integration registrations

services.yml:
```yaml
  famtastic_pipeline.campaign_workspace:
    class: Drupal\famtastic_pipeline\Service\CampaignWorkspace
    arguments: ['@database', '@datetime.time']
```

routing.yml (existing campaign_add route is retained):
```yaml
famtastic_pipeline.campaign_manage:
  path: '/admin/famtastic/campaign/{campaign_key}/{action}'
  defaults:
    _form: 'Drupal\famtastic_pipeline\Form\CampaignAddForm'
    _title: 'Campaign draft plan'
  requirements:
    _permission: 'administer famtastic pipeline'
    campaign_key: '[a-z0-9]+(?:-[a-z0-9]+)*'
    action: 'edit|duplicate|archive|restore'
```

libraries.yml:
```yaml
campaign_workspace:
  css:
    theme:
      css/campaign-workspace.css: {}
```

Add fields to existing `famtastic_campaign` schema (new installs and guarded update hook):
```php
'plan_json' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
'revision' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
```
Do not seed, reclassify or replace existing rows. Existing revision 0 rows advance on first edit. Form API CSRF is retained; route permission matches existing campaign_add. Archived rows reject edits. Restore sets draft explicitly without touching external schedules. Source-only manifests acquire a new Drupal brief through Edit plan; no CLI file is changed. Duplicate copies the brief, never provider IDs or approvals.

AI integration dependency: `famtastic_pipeline.staff_ai_tasks` and `famtastic_pipeline.staff_ai_settings` from AI owner. Campaign button uses only the last saved authorized campaign projection, records source ID/digest, populates editable content-plan text on success, and leaves persistent state unchanged until Save. No actual provider execution was performed.

## Projection/API and behavior

`CampaignWorkspace::all()` returns keyed campaign rows, including source-only discovered schedules and legacy manifest. `get(key)` returns row/null. `items(key)` normalizes Drupal draft rows, read-only CLI drops, and the legacy imported table ONLY for `55-cents-17-day`. Actual campaign status/name/plan/revision are preserved. Content planning includes dated per-channel draft rows plus freeform idea notes. Date validation rejects invalid/out-of-range rows. Campaign duration is unrestricted.

Existing Marketing Command routes retain selected `?campaign=key` across tabs. Invalid explicit selection yields 404 rather than silently showing another campaign. Shared queue/calendar/creative/dispatch projection retains source, actual copy, planned date, channel and review controls. Legacy per-item content/media/publish gate routes and asset-view links remain available. Legacy hard-coded media/video/CTA recipes were removed from shared dispatch. Provider records retain Drops actions and scorecard inspection. Results/Build DNA filter selected campaign. Shared Email center remains an explicitly global communication view for integration owner to replace/link appropriately.

Archive does NOT cancel an already scheduled provider post; form confirmation explains this. Historic campaign assets retain the old protected asset route. Newer media paths are shown as source provenance, not fabricated working asset URLs: secure generalized media serving/preview remains a concrete integration enhancement if required by flow verification.

## Postiz and deploy investigation

The Python scheduling client (`scripts/queue-campaign-drops.py:66-69`) and deployment instructions (`scripts/deploy-postiz-server.sh:179`) accept the FULL `/api/public/v1` URL. Drupal previously appended `/api/public/v1/integrations` unconditionally; a full-base setting produced a duplicate path. Service now normalizes host-only or full-base URLs and also honors `FAMTASTIC_POSTIZ_BASE_URL`. Redirects are disabled to avoid forwarding credentials. HTTP 404, transport/JSON failure and empty channel list remain distinct. An enabled integration no longer claims its OAuth token has been tested. This fixes a concrete source inconsistency; the production 404 root cause remains unverified until its actual setting/proxy is inspected and owner-authorized connection proof runs.

Locator now unions all approved roots and deduplicates slugs. Missing/corrupt deployed schedule produces an explicit unavailable message; no inference that Postiz has no campaigns.

Canonical backend deploy currently stages module/admin/customer themes and config, not campaign JSON. Safe proposed deployment allowlist (integration/release owner only, not applied): exact Git-tracked `marketing/campaigns/<validated-slug>/posting-schedule.json`, `manifest.json`, `scorecard.json`; parse JSON, preserve relative directory, retain SHA256 + source commit in deployment receipt, stage outside web document root in one locator-approved root, include per-file backup/rollback. Do not deploy `.env`, provider configs/keys, customer lists, research dumps, or arbitrary globbed media. Media requires separate exact path/size/MIME/hash manifest and same review. GUI never becomes a second schedule writer. CLI mutation concurrency guards remain its existing writer's responsibility.

## Local checks

Using existing canonical Composer runtime read-only (not copied/changed):

```
/Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs/backend/vendor/bin/phpunit --bootstrap /Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs/backend/web/core/tests/bootstrap.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/CampaignWorkspaceTest.php
```

PASS: 5 tests, 24 assertions. Durable SQLite CRUD/revision conflict/isolation, key/date/channel validation, URL normalization, multi-root discovery/corrupt JSON/path traversal rejection, mocked HTTP404 false connection tested. PHP syntax passes controller/form/workspace/Postiz/locator. No live Drupal/browser or real provider proof claimed. Integration still must prove render arrays (literal Array regression), form rebuild/AI draft retention, mobile touch/overflow, schema migration and permission checks in actual isolated Drupal runtime.

## Owned files

- src/Controller/MarketingCommandController.php
- src/Form/CampaignAddForm.php
- src/Service/CampaignWorkspace.php (new)
- src/Service/PostizChannelsService.php
- src/Utility/CampaignFileLocator.php
- css/campaign-workspace.css (new)
- tests/src/Unit/CampaignWorkspaceTest.php (new)
- this registration handoff

No shared service/routing/library/schema registries were modified. Final documentation mirrors and independent verification belong to serialized integration.

Generalized media implementation added before handoff: `campaignMedia()` serves only an exact drop's recorded image/video path from approved roots, validates extension/path, contains realpath under marketing, denies traversal, sends private/no-store + nosniff. Media buttons now expose primary/supporting/surface variants for newer campaigns; missing deployment returns honest 404. Add protected route:
```yaml
famtastic_pipeline.campaign_media:
  path: '/admin/famtastic/campaign-media/{campaign_key}/{content_id}/{variant}'
  defaults:
    _controller: 'Drupal\famtastic_pipeline\Controller\MarketingCommandController::campaignMedia'
  requirements:
    _permission: 'administer famtastic pipeline'
    campaign_key: '[a-z0-9]+(?:-[a-z0-9]+)*'
    content_id: '[a-zA-Z0-9._-]+'
    variant: '[a-zA-Z0-9_-]+'
```
This supersedes the earlier generalized-media enhancement note. File presence/404 and real Drupal access proof remain integration checks.
