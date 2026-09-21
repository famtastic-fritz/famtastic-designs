// Deterministic failure/installed-mode checks. Real browser QA is recorded separately.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const root = new URL('../frontend/public/connect/', import.meta.url);
const read = name => readFileSync(new URL(name, root), 'utf8');

test('every supplied file is tracked and available to a clean server build', () => {
  const rows = JSON.parse(readFileSync(new URL('../docs/evidence/connect-production/source-downloads.json', import.meta.url)));
  execFileSync('git', ['ls-files', '--error-unmatch', '--', ...rows.map(row => `frontend/public/connect/${row.path}`), 'frontend/public/connect/icons/favicon-32.png'], { cwd: new URL('../', import.meta.url), stdio: 'pipe' });
});

function fixture({ standalone = false, reduced = false, hash = '', ua = 'Android Chrome', script = 'app.js', seen = false, storageBlocked = false, share, copyFails = false } = {}) {
  class Element {
    listeners = new Map();
    hidden = false;
    open = false;
    disabled = false;
    textContent = '';
    classes = new Set();
    classList = { add: value => this.classes.add(value), remove: value => this.classes.delete(value) };
    addEventListener(type, callback) { this.listeners.set(type, [...(this.listeners.get(type) || []), callback]); }
    emit(type, event = {}) { return Promise.all((this.listeners.get(type) || []).map(callback => callback({ target: this, preventDefault() {}, ...event }))); }
    setAttribute(name, value) { this[name] = value; }
    focus() { document.activeElement = this; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    replaceChildren(...children) { this.children = children; }
    querySelector(selector) { return (this.parts ??= {})[selector] ??= new Element(); }
    getBoundingClientRect() { return { left: 0, right: 400, top: 0, bottom: 800 }; }
    play() { this.paused = false; return Promise.resolve(); }
    pause() { this.paused = true; }
  }
  const nodes = new Map([...read('index.html').matchAll(/<[^>]+\bid="([^"]+)"[^>]*>/g)].map(match => {
    const node = new Element(); node.hidden = /\bhidden\b/.test(match[0]); return [match[1], node];
  }));
  // An intro must never disable the usable static document, even if JS exits early.
  Object.defineProperty(nodes.get('main'), 'inert', { set(value) { assert.equal(value, false); }, get() { return false; } });
  const installs = [new Element(), new Element()];
  const cardLinks = [new Element(), new Element()];
  const document = Object.assign(new Element(), {
    body: new Element(), hidden: false, activeElement: null,
    getElementById: id => nodes.get(id), createElement: () => new Element(),
    querySelectorAll: selector => selector === '[data-install]' ? installs : cardLinks,
  });
  const media = Object.assign(new Element(), { matches: standalone });
  const motion = Object.assign(new Element(), { matches: reduced });
  const stored = new Map(seen ? [['famtastic-connect-qr-hint-v1', 'seen']] : []);
  const storage = { getItem: key => { if (storageBlocked) throw Error('blocked'); return stored.get(key); }, setItem: (key, value) => { if (storageBlocked) throw Error('blocked'); stored.set(key, value); } };
  const window = Object.assign(new Element(), { matchMedia: query => query.includes('display-mode') ? media : motion, localStorage: storage, sessionStorage: storage, scrollTo() {}, alert() {} });
  const location = { hash, pathname: '/connect/', search: '' };
  const history = { replaceState: () => { location.hash = ''; } };
  const timers = new Map(); let timerId = 0; let clock = 100;
  const copied = [];
  const context = { document, window, location, history, navigator: { userAgent: ua, share, clipboard: { writeText: async value => { if (copyFails) throw Error('blocked'); copied.push(value); } } },
    Date: { now: () => clock }, URL, setTimeout: (callback, delay) => { timers.set(++timerId, { callback, delay }); return timerId; },
    clearTimeout: id => timers.delete(id) };
  vm.runInNewContext(read(script), context, { filename: script });
  return { nodes, document, window, installs, cardLinks, location, timers, stored, motion, copied,
    advance: ms => { clock += ms; },
    tick: delay => { for (const [id, timer] of [...timers]) if (timer.delay === delay) { timers.delete(id); timer.callback(); } },
  };
}

test('manifest launches the exact card, with all icons confined to /connect/', () => {
  const m = JSON.parse(read('manifest.webmanifest'));
  for (const field of ['id', 'start_url', 'scope']) assert.equal(m[field], '/connect/');
  for (const icon of m.icons) { assert.ok(icon.src.startsWith('/connect/icons/')); assert.ok(readFileSync(new URL(icon.src.slice('/connect/'.length), root)).length > 0); }
  assert.equal(m.display, 'standalone');
});

test('source media and contact are byte-preserved; injected hosting script is absent', () => {
  const receipts = JSON.parse(readFileSync(new URL('../docs/evidence/connect-production/source-downloads.json', import.meta.url)));
  for (const name of ['logo.png', 'commercial.mp4', 'commercial-poster.jpg', 'fritz-famtastic.vcf']) {
    assert.equal(createHash('sha256').update(readFileSync(new URL(name, root))).digest('hex'), receipts.find(row => row.path === name).sha256, name);
  }
  assert.doesNotMatch(read('index.html'), /cdn-cgi|__CF\$|nineoo/);
});

test('normal intro dismisses on deadline and Skip remains a working escape', async () => {
  const f = fixture(); assert.equal(f.nodes.get('intro').hidden, false);
  f.tick(2300); f.tick(400); assert.equal(f.nodes.get('intro').hidden, true);
  const skip = fixture(); await skip.nodes.get('skipIntro').emit('click'); skip.tick(400);
  assert.equal(skip.nodes.get('intro').hidden, true);
});

test('installed and reduced-motion launches immediately expose the card', () => {
  for (const options of [{ standalone: true }, { reduced: true }]) {
    const f = fixture(options); assert.equal(f.nodes.get('intro').hidden, true);
    assert.ok([...f.timers.values()].every(timer => timer.delay === 1250), 'only the nonblocking QR hint may wait');
  }
});

test('suspended timers recover after backgrounding or a back-forward restoration', async () => {
  const f = fixture(); f.advance(5000); await f.document.emit('visibilitychange'); f.tick(400);
  assert.equal(f.nodes.get('intro').hidden, true);
  const b = fixture(); await b.window.emit('pageshow', { persisted: true }); b.tick(400);
  assert.equal(b.nodes.get('intro').hidden, true);
});

test('QR deep link is usable without intro; tapping either link restores the card', async () => {
  for (const index of [0, 1]) {
    const f = fixture({ hash: '#qr' }); assert.equal(f.nodes.get('qrDialog').open, true);
    assert.equal(f.nodes.get('intro').hidden, true);
    await f.cardLinks[index].emit('click'); assert.equal(f.location.hash, ''); assert.equal(f.nodes.get('qrDialog').open, false);
    f.tick(2300); f.tick(400); assert.equal(f.nodes.get('intro').hidden, true);
  }
});

test('closing the commercial pauses playback', async () => {
  const f = fixture(); await f.nodes.get('watch').emit('click'); assert.equal(f.nodes.get('commercial').paused, false);
  await f.nodes.get('closeVideo').emit('click'); assert.equal(f.nodes.get('commercial').paused, true);
});

test('Android and iOS get explicit manual instructions without a native event', async () => {
  for (const [ua, expected] of [['Android Chrome', 'Chrome'], ['iPhone Safari', 'Safari']]) {
    const f = fixture({ script: 'install.js', ua }); await f.installs[0].emit('click');
    assert.equal(f.nodes.get('installDialog').open, true);
    assert.ok(f.nodes.get('installSteps').children[0].textContent.includes(expected));
    assert.equal(f.nodes.get('installNative').hidden, true);
  }
});

test('native installation needs a click and reports installed only after appinstalled', async () => {
  const f = fixture({ script: 'install.js' }); let prompts = 0;
  await f.window.emit('beforeinstallprompt', { prompt: async () => { prompts++; }, userChoice: Promise.resolve({ outcome: 'accepted' }) });
  assert.equal(prompts, 0); await f.installs[0].emit('click'); assert.equal(prompts, 1);
  assert.equal(f.installs[0].disabled, false);
  await f.window.emit('appinstalled'); assert.equal(f.installs[0].disabled, true);
});

test('cancelled or failed native prompts return to manual instructions', async () => {
  for (const fail of [false, true]) {
    const f = fixture({ script: 'install.js' });
    await f.window.emit('beforeinstallprompt', { prompt: async () => { if (fail) throw Error('unavailable'); }, userChoice: Promise.resolve({ outcome: 'dismissed' }) });
    await f.installs[0].emit('click'); assert.equal(f.nodes.get('installDialog').open, true); assert.equal(f.installs[0].disabled, false);
  }
});

test('QR cue waits until the card is visible, then settles and remembers the visit', () => {
  const f = fixture(); const hint = f.nodes.get('qrPrompt');
  f.tick(1250); assert.equal(hint.classes.has('is-visible'), false);
  f.tick(2300); f.tick(400);
  assert.equal(hint.classes.has('is-visible'), false);
  f.tick(1250); assert.equal(hint.classes.has('is-nudging'), true);
  assert.equal(f.stored.get('famtastic-connect-qr-hint-v1'), 'seen');
  f.tick(2400); assert.equal(hint.classes.has('is-nudging'), false);
  assert.equal(hint.classes.has('is-visible'), true);
});

test('repeat and reduced-motion visits have a static cue; a preference change stops the cue', async () => {
  for (const options of [{ seen: true }, { reduced: true }]) {
    const f = fixture(options); f.tick(2300); f.tick(400); f.tick(1250);
    assert.equal(f.nodes.get('qrPrompt').classes.has('is-nudging'), false);
    assert.equal(f.nodes.get('qrPrompt').classes.has('is-visible'), true);
  }
  const f = fixture({ standalone: true }); f.tick(1250);
  f.motion.matches = true; await f.motion.emit('change');
  assert.equal(f.nodes.get('qrPrompt').classes.has('is-nudging'), false);
});

test('both QR controls work before the cue, restore focus and remove the hash when closed', async () => {
  for (const id of ['showQrPrimary', 'showQr']) {
    const f = fixture({ storageBlocked: true });
    await f.nodes.get(id).emit('click');
    assert.equal(f.nodes.get('qrDialog').open, true);
    assert.equal(f.document.activeElement, f.nodes.get('closeQr'));
    assert.equal(f.location.hash, 'qr');
    f.location.hash = '#qr';
    await f.nodes.get('closeQr').emit('click');
    assert.equal(f.nodes.get('qrDialog').open, false);
    assert.equal(f.document.activeElement, f.nodes.get(id));
    assert.equal(f.location.hash, '');
    f.tick(400); f.tick(1250);
    assert.equal(f.nodes.get('qrPrompt').classes.has('is-nudging'), false);
  }
});

test('a backgrounded first visit defers its cue until visible', async () => {
  const f = fixture({ standalone: true }); f.document.hidden = true; f.tick(1250);
  assert.equal(f.stored.size, 0);
  f.document.hidden = false; await f.document.emit('visibilitychange'); f.tick(1250);
  assert.equal(f.nodes.get('qrPrompt').classes.has('is-nudging'), true);
});

test('QR hash navigation and backdrop dismissal close without stranding focus', async () => {
  const f = fixture({ hash: '#qr' });
  f.location.hash = ''; await f.window.emit('hashchange');
  assert.equal(f.nodes.get('qrDialog').open, false);
  f.location.hash = '#qr'; await f.window.emit('hashchange');
  await f.nodes.get('qrDialog').emit('click', { clientX: -1, clientY: -1 });
  assert.equal(f.nodes.get('qrDialog').open, false);
  assert.equal(f.document.activeElement, f.nodes.get('showQrPrimary'));
});

test('Share Link uses the canonical URL and never reports a cancelled share as success', async () => {
  const calls = []; const f = fixture({ share: async data => calls.push(data) });
  await f.nodes.get('shareQrLink').emit('click');
  assert.equal(calls[0].url, 'https://famtasticdesigns.com/connect');
  assert.equal(f.nodes.get('qrShareStatus').hidden, true);
  const cancelled = fixture({ share: async () => { throw { name: 'AbortError' }; } });
  await cancelled.nodes.get('shareQrLink').emit('click');
  assert.equal(cancelled.copied.length, 0);
  assert.equal(cancelled.nodes.get('qrShareStatus').hidden, true);
});

test('clipboard fallback reports success only after copying and exposes the URL on failure', async () => {
  const f = fixture({ share: async () => { throw Error('unavailable'); } });
  await f.nodes.get('shareQrLink').emit('click');
  assert.equal(f.copied[0], 'https://famtasticdesigns.com/connect');
  assert.match(f.nodes.get('qrShareStatus').textContent, /^Link copied/);
  const blocked = fixture({ copyFails: true });
  await blocked.nodes.get('shareQrLink').emit('click');
  assert.equal(blocked.nodes.get('qrShareStatus').textContent, 'Copy this link: https://famtasticdesigns.com/connect');
});
