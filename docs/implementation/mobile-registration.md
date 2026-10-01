# Mobile command center integration

Source owner: UI lane. Profiles engineering/engineering-frontend-developer and testing/testing-workflow-optimizer, pinned 765be42358100bf89d2faa567668a94c602f9a26. Foundation v1.0.0 and existing FAMtastic Experience System retained.

Add to famtastic_pipeline.routing.yml:

```yaml
famtastic_pipeline.owner_work_summary:
  path: '/admin/famtastic/my-work-summary'
  defaults:
    _form: '\Drupal\famtastic_pipeline\Form\OwnerWorkSummaryForm'
    _title: 'Summarize my work'
  requirements:
    _permission: 'administer famtastic pipeline'
  options:
    _admin_route: TRUE
```

Dependencies: communication owner registers `famtastic_pipeline.staff_ai_tasks` and route `famtastic_pipeline.staff_ai_settings`. No new service, library or schema registration for the UI lane. Existing operations library/theme carry styles. Summary uses aggregate counts only and invokes AI only via explicit Form API submit; merely opening it runs readiness only. Form tokens and exact staff permission are required.

Operations hub is still the existing operations route. StaffCommandCenterBridge unchanged: its existing permission check and `/web/admin/famtastic` destination already map to this home. No customer portal changes.

Campaign workspace uses the existing marketing route; operations dashboard offers a campaign workspace entry and original campaign-record table. Campaign-specific projection is delegated to CampaignWorkspace, not duplicated in the home.

Urgent/errors/security notices remain fully visible. Mobile chrome spacing is compacted without suppressing messages. Navigation is three direct destinations plus native More disclosure; secondary links retain permission checks, keyboard semantics and safe-area clearance.


Validation performed by UI owner:
- PHP syntax: OperationsController, OwnerWorkSummaryForm, famtastic_admin.theme pass.
- `node scripts/validate-famtastic-admin-theme.mjs`: PASS.
- `php scripts/test-creator-credit-admin.php`: PASS; preserved exact existing footer hook.
- `node scripts/test-owner-mobile-ui.cjs <canonical frontend/node_modules/@playwright/test>`: PASS at 320, 390 and 1280 pixels for page overflow, 44px primary targets, keyboard More open/close and secondary focus order, final action clear of fixed navigation. Screenshot/result files in ignored `.artifacts/owner-mobile-ui/`.
- This is static CSS/navigation fixture evidence. Full authenticated Drupal render, persistence and task workflow proof remain integration/verifier responsibilities. No real customer records or providers were used.
- Reply queue uses ClientMessagingService active-label filtering. Support-review count explicitly covers all pending support suggestions including labeled tests; its existing metric preserves all historical review records. Job/delivery queues include operational items by design, with reasons visible.

Root integration should enroll the new summary route in any navigation/action inventory requiring explicit coverage. No changes to shared registration files were made by this lane.
