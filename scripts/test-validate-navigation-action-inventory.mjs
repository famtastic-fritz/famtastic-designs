import {spawnSync} from 'node:child_process';
import {mkdtempSync, mkdirSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {fixtures} from '../backend/web/themes/custom/famtastic_admin/tests/fixtures/navigation-action-inventory-fixtures.mjs';

const root = resolve(import.meta.dirname, '..');
const validator = resolve(root, 'scripts/validate-navigation-action-inventory.mjs');
for (const [name, fixture] of Object.entries(fixtures)) {
  const fixtureRoot = mkdtempSync(join(tmpdir(), 'famtastic-nav-inventory-'));
  try {
    for (const [file, contents] of Object.entries(fixture.files)) {
      const target = join(fixtureRoot, file);
      mkdirSync(resolve(target, '..'), {recursive: true});
      writeFileSync(target, contents);
    }
    const result = spawnSync(process.execPath, [validator, '--root', fixtureRoot], {encoding: 'utf8'});
    const output = result.stdout + result.stderr;
    if (result.status !== fixture.status || !output.includes(fixture.output)) {
      throw new Error(`${name}: expected status ${fixture.status} and ${JSON.stringify(fixture.output)}; got ${result.status}\n${output}`);
    }
  }
  finally {
    rmSync(fixtureRoot, {recursive: true, force: true});
  }
}
console.log('Navigation/action inventory fixtures: PASS');
