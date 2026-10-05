import { chmodSync, lstatSync, mkdirSync, readFileSync, renameSync, unlinkSync, writeFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { join } from 'node:path';

export function normalizeTestPhone(value) {
  const compact = String(value ?? '').trim().replace(/[\s().-]/g, '');
  const candidate = /^\d{10}$/.test(compact) ? `+1${compact}`
    : /^1\d{10}$/.test(compact) ? `+${compact}` : compact;
  return /^\+[1-9]\d{1,14}$/.test(candidate) ? candidate : null;
}

export function createPrivatePhoneStore(directory) {
  const file = join(directory, 'test-recipient');
  const owner = process.getuid?.();
  function privateDirectory() {
    mkdirSync(directory, { recursive: true, mode: 0o700 });
    const stat = lstatSync(directory);
    if (!stat.isDirectory() || (owner !== undefined && stat.uid !== owner)) {
      throw new Error('phone_store_directory_untrusted');
    }
    chmodSync(directory, 0o700);
  }
  return Object.freeze({
    save(rawPhone) {
      const phone = normalizeTestPhone(rawPhone);
      if (!phone) throw new Error('test_phone_invalid; use 10 US digits or +1 followed by 10 digits');
      privateDirectory();
      const temporary = join(directory, `.test-recipient-${process.pid}-${randomBytes(8).toString('hex')}`);
      writeFileSync(temporary, `${phone}\n`, { flag: 'wx', mode: 0o600 });
      try {
        renameSync(temporary, file);
        chmodSync(file, 0o600);
      } catch (error) {
        try { unlinkSync(temporary); } catch {}
        throw error;
      }
      return phone;
    },
    read() {
      try {
        const stat = lstatSync(file);
        if (!stat.isFile() || (owner !== undefined && stat.uid !== owner) || (stat.mode & 0o077) !== 0) {
          return null;
        }
        return normalizeTestPhone(readFileSync(file, 'utf8'));
      } catch {
        return null;
      }
    },
    remove() {
      try {
        const stat = lstatSync(file);
        if (!stat.isFile() || (owner !== undefined && stat.uid !== owner)) return false;
        unlinkSync(file);
        return true;
      } catch (error) {
        if (error?.code === 'ENOENT') return true;
        return false;
      }
    },
  });
}
