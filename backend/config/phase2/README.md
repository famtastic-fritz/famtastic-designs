# Phase 2 — Content Type Config

Drupal 11 config YAML for the three FAMtastic Designs Phase 2 content types,
following the same core-recipe config pattern used in Phase 1
(see `web/core/recipes/article_content_type/config/` for the reference layout).

## Contents

| Content type | Machine name | Fields |
|---|---|---|
| Client Project | `client_project` | `field_client_name` (string), `field_project_status` (list_string: discovery\|active\|review\|complete), `field_budget` (decimal), `field_due_date` (datetime, date-only), `field_notes` (text_long) |
| Service Package | `service_package` | `field_price` (decimal), `field_package_description` (text_long), `field_features` (string, unlimited), `field_tier` (list_string: starter\|professional\|premium) |
| Testimonial | `testimonial` | `field_testimonial_client` (string), `field_quote` (text_long), `field_project_ref` (entity_reference → node `client_project`), `field_rating` (list_integer 1–5) |

For each type there is a `node.type.*` config, `field.storage.node.*` +
`field.field.node.<type>.*` for every field, and
`core.entity_form_display` / `core.entity_view_display` (`default` + `teaser`).

## Import

From `backend/` (drush is never run by the scaffold; run this manually):

```bash
drush config:import --partial --source=/Users/famtasticfritz/famtastic/sites/site-famtastic-designs/backend/config/phase2
```

Same mechanism Phase 1's `setup.sh` uses for core-recipe config dirs: `--partial`
imports only what is in this directory, without touching existing active config.
`uuid` keys are intentionally omitted so Drupal generates them on import;
`_core` hashes are omitted as well (standard for recipe/partial imports).

## Notes

- Module dependencies are declared per file: `node`, `datetime`, `options`,
  `text`, plus `path`/`user` on the entity displays (all already enabled).
- `field_due_date` uses `datetime_type: date` (date-only storage).
- `field_project_ref` depends on `node.type.client_project`; import creates all
  configs in one pass so the reference target exists immediately.
- After import, JSON:API exposes the types at
  `/jsonapi/node/client_project`, `/jsonapi/node/service_package`,
  `/jsonapi/node/testimonial` (subject to Phase 2 permissions config).
