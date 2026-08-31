#!/usr/bin/env node
/**
 * FAMtastic Client Portal Design DNA v1 Validator
 *
 * Verifies that the client portal implementation, routes, tokens, security boundaries,
 * and CSS invariants strictly conform to FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const REPO_ROOT = path.resolve(__dirname, '..');

let failures = 0;
let passes = 0;

function assert(condition, message) {
  if (condition) {
    console.log(`PASS: ${message}`);
    passes++;
  } else {
    console.error(`FAIL: ${message}`);
    failures++;
  }
}

console.log('== Validating FAMtastic Client Portal Design DNA v1 ==\n');

// 1. Contract & Spec Files
const specMdPath = path.join(REPO_ROOT, 'docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md');
const specJsonPath = path.join(REPO_ROOT, 'docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.json');

assert(fs.existsSync(specMdPath), 'docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md exists');
assert(fs.existsSync(specJsonPath), 'docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.json exists');

let contractJson = null;
try {
  contractJson = JSON.parse(fs.readFileSync(specJsonPath, 'utf8'));
  assert(contractJson.contract === 'famtastic.client-portal.design-dna.v1', 'Contract identifier matches famtastic.client-portal.design-dna.v1');
} catch (e) {
  assert(false, `Failed to parse contract JSON: ${e.message}`);
}

// 2. Frontend Routing Invariants
const appJsxPath = path.join(REPO_ROOT, 'frontend/src/App.jsx');
assert(fs.existsSync(appJsxPath), 'frontend/src/App.jsx exists');
if (fs.existsSync(appJsxPath)) {
  const appJsx = fs.readFileSync(appJsxPath, 'utf8');
  assert(appJsx.includes('path="/portal"'), 'App.jsx contains /portal route');
  assert(appJsx.includes('path="/portal/:token"'), 'App.jsx contains /portal/:token route');
  assert(appJsx.includes('path="/p/:token"'), 'App.jsx contains /p/:token route');
}

// 3. Customer Portal Dashboard Module & Link Integrity
const dashboardPath = path.join(REPO_ROOT, 'frontend/src/pages/CustomerPortalDashboard.jsx');
assert(fs.existsSync(dashboardPath), 'frontend/src/pages/CustomerPortalDashboard.jsx exists');
if (fs.existsSync(dashboardPath) && contractJson) {
  const dashboardCode = fs.readFileSync(dashboardPath, 'utf8');
  const projectsViewPath = path.join(REPO_ROOT, 'frontend/src/components/portal/PortalProjectsView.jsx');
  const projectsViewCode = fs.existsSync(projectsViewPath) ? fs.readFileSync(projectsViewPath, 'utf8') : '';
  const portalCode = dashboardCode + projectsViewCode;
  
  // Check all expected sections are handled
  const expectedSections = contractJson.routes.authenticated_dashboard.sections;
  for (const sec of expectedSections) {
    assert(
      dashboardCode.includes(`section === '${sec}'`) || dashboardCode.includes(`['${sec}'`),
      `Dashboard implements section '${sec}'`
    );
  }

  // Check no external contact leakage for recommended services
  const hasContactLeakage = /href=\{`\/contact\?service=/i.test(dashboardCode) || /to=\{`\/contact\?service=/i.test(dashboardCode);
  assert(!hasContactLeakage, 'Dashboard does not leak authenticated catalog/offer clicks to /contact form');

  // Check proof review iframe sandbox
  assert(
    portalCode.includes('sandbox="allow-scripts allow-same-origin"'),
    'Proof review iframes maintain secure sandbox="allow-scripts allow-same-origin"'
  );

  // Check file upload rights & AI consent confirmation
  assert(
    portalCode.includes('ownership_confirmed') && portalCode.includes('ai_use_consent'),
    'File upload includes explicit asset ownership and AI-use consent checks'
  );
}

// 4. Token-Scoped Client Portal Integrity
const tokenPagePath = path.join(REPO_ROOT, 'frontend/src/pages/ClientPortalPage.jsx');
assert(fs.existsSync(tokenPagePath), 'frontend/src/pages/ClientPortalPage.jsx exists');
if (fs.existsSync(tokenPagePath)) {
  const tokenPageCode = fs.readFileSync(tokenPagePath, 'utf8');
  assert(
    tokenPageCode.includes('useParams') && tokenPageCode.includes('getSession'),
    'Token portal uses parameterized session loading'
  );
}

// 5. CSS Brand Tokens & Strict Containment
const portalCssPath = path.join(REPO_ROOT, 'frontend/src/portal.css');
assert(fs.existsSync(portalCssPath), 'frontend/src/portal.css exists');
if (fs.existsSync(portalCssPath)) {
  const portalCss = fs.readFileSync(portalCssPath, 'utf8');
  assert(portalCss.includes('overflow-x:clip'), 'CSS contains strict overflow-x:clip containment');
  assert(portalCss.includes('#7cfc00') || portalCss.includes('var(--p-lime)'), 'CSS incorporates signature lime token (#7cfc00)');
  assert(portalCss.includes('min-height:44px') || portalCss.includes('min-height: 44px'), 'CSS enforces min 44px touch targets');
}

console.log(`\nValidation complete: ${passes} passed, ${failures} failed.`);
if (failures > 0) {
  process.exit(1);
} else {
  console.log('RESULT: PASS (All Client Portal Design DNA v1 rules satisfied)');
  process.exit(0);
}
