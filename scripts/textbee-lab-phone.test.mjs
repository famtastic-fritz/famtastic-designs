import test from 'node:test';
import assert from 'node:assert/strict';
import { chmodSync, lstatSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createPrivatePhoneStore, normalizeTestPhone } from './textbee-lab-phone.mjs';

test('phone prompt accepts ordinary US input and rejects ambiguous extensions', () => {
  assert.equal(normalizeTestPhone('(555) 555-0123'), '+15555550123');
  assert.equal(normalizeTestPhone('+1 555 555 0123'), '+15555550123');
  assert.equal(normalizeTestPhone('555-555-0123 ext 7'), null);
});

test('only a private validated test phone is readable, and it can be removed', () => {
  const root = mkdtempSync(join(tmpdir(), 'textbee-phone-test-'));
  const directory = join(root, 'lab');
  try {
    const store = createPrivatePhoneStore(directory);
    assert.throws(() => store.save('not-a-phone'), /test_phone_invalid/);
    assert.equal(store.read(), null);
    assert.equal(store.save('5555550123'), '+15555550123');
    assert.equal(store.read(), '+15555550123');
    assert.equal(lstatSync(directory).mode & 0o777, 0o700);
    assert.equal(lstatSync(join(directory, 'test-recipient')).mode & 0o777, 0o600);
    chmodSync(join(directory, 'test-recipient'), 0o644);
    assert.equal(store.read(), null);
    assert.equal(store.remove(), true);
    assert.equal(store.read(), null);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
