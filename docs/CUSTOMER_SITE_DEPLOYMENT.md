# Customer Site Release and Deployment

## Release contract

Customer approval creates a source checksum and release SHA, then enqueues
`deployment.prepare`. Preparation renders and records an immutable release:

```text
<release-root>/<customer-key>/<release-sha>/
  index.html
  release.json
```

`release.json` binds the project, customer key, release SHA, source checksum,
artifact checksum, creation time, and per-file checksums. Reusing the same
release path with different content is rejected.

## Deployment contract

The deployment transport defaults to `disabled`. A customer release can only be
promoted when:

```text
FAMTASTIC_CUSTOMER_RELEASE_ROOT=/private/release/path
FAMTASTIC_CUSTOMER_DEPLOY_ROOT=/isolated/customer/sites
FAMTASTIC_DEPLOY_TRANSPORT=local|real
```

`local` is exclusively for deterministic acceptance tests. `real` additionally
requires:

```text
FAMTASTIC_ALLOW_CUSTOMER_DEPLOYMENTS=true
```

That approval is separate from a FAMtastic Designs application deployment.

Each customer receives one sanitized key such as
`customer-123-acme-plumbing`. Promotion:

1. verifies the immutable release manifest and checksum;
2. copies to a managed staging directory and rejects symlinks;
3. moves any current customer target into `.backups`;
4. atomically renames staging to the customer target;
5. re-hashes the deployed `index.html`;
6. records target, backup, public URL, checksum, transport, and timestamp;
7. appends a deployment event and queues domain verification.

The service refuses filesystem operations outside the configured deployment
root.

## Rollback

Rollback preserves the failed release under `.failed`, restores the exact
pre-deployment directory, and persists timestamped rollback evidence. It fails
closed when no backup exists.

For an approved operational rollback, use the recorded deployment ID through
the deployment service or its future operator UI. Database rows and event
history are retained; rollback never deletes the release manifest.

## Verification

```bash
./scripts/e2e-customer-deployment.sh
```

The synthetic test creates an approved project, prepares an immutable release,
installs a previous target, applies the new release to an isolated temporary
root, verifies it through an HTTP browser request, restores the previous target,
and checks prepared/deployed/rolled-back events.
