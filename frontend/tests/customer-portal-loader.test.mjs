import assert from 'node:assert/strict';
import test from 'node:test';

import {
  getStaffCommandCenterLink,
  loadCustomerPortal,
} from '../src/pages/customerPortalLoader.js';

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });
  return { promise, resolve, reject };
}

test('does not request protected portal data when the session check fails', async () => {
  const unauthorized = Object.assign(new Error('Sign in required.'), { status: 401 });
  let workspaceRequests = 0;
  let catalogRequests = 0;

  await assert.rejects(
    loadCustomerPortal({
      getSession: async () => {
        throw unauthorized;
      },
      getWorkspace: async () => {
        workspaceRequests += 1;
      },
      getCatalog: async () => {
        catalogRequests += 1;
      },
    }),
    (error) => error === unauthorized,
  );

  assert.equal(workspaceRequests, 0);
  assert.equal(catalogRequests, 0);
});

test('waits for an authenticated session, then loads workspace and catalog in parallel', async () => {
  const sessionGate = deferred();
  const workspaceGate = deferred();
  const catalogGate = deferred();
  const calls = [];

  const loading = loadCustomerPortal({
    getSession: () => {
      calls.push('session');
      return sessionGate.promise;
    },
    getWorkspace: () => {
      calls.push('workspace');
      return workspaceGate.promise;
    },
    getCatalog: () => {
      calls.push('catalog');
      return catalogGate.promise;
    },
  });

  await Promise.resolve();
  assert.deepEqual(calls, ['session']);

  const session = { customer: { email: 'customer@example.test' } };
  const workspace = { organization: { public_id: 'org-test' } };
  const catalog = { products: [] };
  sessionGate.resolve(session);
  await Promise.resolve();
  assert.deepEqual(calls, ['session', 'workspace', 'catalog']);

  workspaceGate.resolve(workspace);
  catalogGate.resolve(catalog);

  assert.deepEqual(await loading, { session, workspace, catalog });
});

test('exposes the staff command center link only for the authorized capability', () => {
  assert.deepEqual(getStaffCommandCenterLink({
    staff: {
      can_access_command_center: true,
      command_center_url: '/web/admin/famtastic',
    },
  }), {
    href: '/web/admin/famtastic',
    label: 'Staff Command Center',
  });
});

test('ordinary customers and untrusted destinations receive no staff link', () => {
  assert.equal(getStaffCommandCenterLink({ customer: { verified: true } }), null);
  assert.equal(getStaffCommandCenterLink({
    staff: {
      can_access_command_center: false,
      command_center_url: '/web/admin/famtastic',
    },
  }), null);
  assert.equal(getStaffCommandCenterLink({
    staff: {
      can_access_command_center: true,
      command_center_url: 'https://example.test/not-the-command-center',
    },
  }), null);
});
