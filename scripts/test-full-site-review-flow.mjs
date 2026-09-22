import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from '../frontend/node_modules/vite/dist/node/index.js';
import { createElement } from '../frontend/node_modules/react/index.js';
import { renderToStaticMarkup } from '../frontend/node_modules/react-dom/server.node.js';

test('a delivered full website takes precedence over an unfinished brief without claiming payment or acceptance', async () => {
  const server = await createServer({ root: new URL('../frontend', import.meta.url).pathname, publicDir: false, server: { middlewareMode: true }, appType: 'custom' });
  try {
    const { default: PortalProjectsView, customerNextStep, FullSiteReview } = await server.ssrLoadModule('/src/components/portal/PortalProjectsView.jsx');
    const request = {
      public_id: '11111111-2222-4333-8444-555555555555', status: 'draft', proof_review_status: 'not_started',
      full_site_review: {
        status: 'ready_for_review', title: 'Fixture website', page_count: 14,
        url: '/web/api/customer/website-requests/11111111-2222-4333-8444-555555555555/full-site/index.html',
        documents: [{ label: 'Design guide', url: '/web/api/customer/website-requests/11111111-2222-4333-8444-555555555555/full-site/review-documents/design.txt' }],
      },
    };
    assert.equal(customerNextStep(request).action, 'full-site');
    assert.equal(customerNextStep({ ...request, full_site_review: null }).action, 'brief');
    const html = renderToStaticMarkup(createElement(FullSiteReview, { request }));
    assert.match(html, /Full website/);
    assert.match(html, /Ready for your review/);
    assert.match(html, /14 pages/);
    assert.match(html, /Open your full website/);
    assert.match(html, /Design guide/);
    assert.match(html, /target="_blank" rel="noopener noreferrer"/);
    assert.doesNotMatch(html, /Paid|3 concepts|Choose one|Accept this|checkout|type="submit"/i);
    assert.equal(renderToStaticMarkup(createElement(FullSiteReview, { request: { status: 'draft' } })), '');
    const project = renderToStaticMarkup(createElement(PortalProjectsView, { workspace: { website_requests: [request], projects: [] }, activeRequestId: request.public_id }));
    assert.match(project, /Request changes in Messages/);
    assert.doesNotMatch(project, /Update my brief|Submit brief|Save draft|Choose one direction|Your 3 directions/);
    const staleEditor = renderToStaticMarkup(createElement(PortalProjectsView, { workspace: { website_requests: [request], projects: [] }, editingRequest: request }));
    assert.match(staleEditor, /Open your full website/);
    assert.doesNotMatch(staleEditor, /Submit brief|Save draft/);
  }
  finally { await server.close(); }
});
