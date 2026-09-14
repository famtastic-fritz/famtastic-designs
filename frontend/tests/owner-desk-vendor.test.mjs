import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { resolve } from 'node:path';
const directory = new URL('../src/components/owner-desk/', import.meta.url);
const provenance = JSON.parse(readFileSync(new URL('provenance.json', directory)));
test('vendored reusable component exactly matches recorded canonical hashes', () => {
  for (const file of provenance.files) {
    const source = readFileSync(new URL(file.path, directory));
    assert.equal(createHash('sha256').update(source).digest('hex'), file.sha256, file.path);
    if (process.env.COMPONENT_STUDIO_DIR) {
      assert.deepEqual(source, readFileSync(resolve(process.env.COMPONENT_STUDIO_DIR, 'components/owner-desk', file.path)), `canonical copy: ${file.path}`);
    }
  }
});
