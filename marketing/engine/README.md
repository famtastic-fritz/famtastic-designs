# Incubating reusable marketing engine

This directory is the portable boundary for provider-neutral schemas and
workflow contracts. It must remain usable without Drupal or FAMtastic-specific
product data.

Allowed here:

- campaign/content schemas;
- approval-state and evidence contracts;
- provider interfaces and normalized results;
- validation and orchestration that accept injected configuration.

Not allowed here:

- customer data or recipient lists;
- OAuth tokens, API keys, cookies, or credentials;
- FAMtastic product prices or contractual wording;
- direct imports from `backend/`;
- public-publishing defaults;
- a provider-specific result treated as canonical campaign truth.

The first schema is `schemas/campaign-manifest.schema.json`. Repository-specific
runners validate FAMtastic manifests against it and add business-level checks.

