// Deterministic failure/installed-mode checks. Real browser QA is recorded separately.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import vm from 'node:vm';

const root = new URL('../frontend/public/connect/', import.meta.url);
const read = name => readFileSync(new URL(name, root), 'utf8');

function fixture({ standalone = false, reduced = false, hash = '', ua = 'Android Chrome', script = 'app.js' } = {}) {
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
  const window = Object.assign(new Element(), { matchMedia: query => query.includes('display-mode') ? media : { matches: reduced }, scrollTo() {}, alert() {} });
  const location = { hash, pathname: '/connect/', search: '' };
  const history = { replaceState: () => { location.hash = ''; } };
  const timers = new Map(); let timerId = 0; let clock = 100;
  const context = { document, window, location, history, navigator: { userAgent: ua, clipboard: { writeText: async () => {} } },
    Date: { now: () => clock }, URL, setTimeout: (callback, delay) => { timers.set(++timerId, { callback, delay }); return timerId; },
    clearTimeout: id => timers.delete(id) };
  vm.runInNewContext(read(script), context, { filename: script });
  return { nodes, document, window, installs, cardLinks, location, timers,
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
  for (const name of ['logo.png', 'commercial.mp4', 'commercial-poster.jpg', 'fritz-famtastic.vcf', ...receipts.filter(row => row.path.startsWith('icons/')).map(row => row.path)]) {
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
    const f = fixture(options); assert.equal(f.nodes.get('intro').hidden, true); assert.equal(f.timers.size, 0);
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
