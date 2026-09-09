const route = 'frontend/src/App.jsx';
const portal = 'frontend/src/components/portal/Nav.jsx';
const routes = 'backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.routing.yml';
const controller = 'backend/web/modules/custom/famtastic_pipeline/src/Controller/ExampleController.php';

export const fixtures = {
  valid: {
    status: 0,
    output: 'PASS',
    files: {
      [route]: 'export default () => <Route path="/portal" />;',
      [portal]: 'export default () => <><Link to="/portal">Home</Link><button type="button" onClick={() => {}}>Open</button></>;',
      [routes]: "famtastic_pipeline.operations:\n  path: '/admin/famtastic'\n",
      [controller]: "<?php\nUrl::fromRoute('famtastic_pipeline.operations');\n",
    },
  },
  placeholder: {
    status: 1,
    output: 'placeholder navigation target',
    files: {[route]: 'export default () => <Route path="/portal" />;', [portal]: 'export default () => <Link to="#">Placeholder</Link>;'},
  },
  emptyHrefAction: {
    status: 1,
    output: 'empty or placeholder href/action',
    files: {
      [route]: 'export default () => <Route path="/portal" />;',
      'backend/web/themes/custom/famtastic_admin/templates/example.html': '<a href="">Empty</a><form action=""></form>',
    },
  },
  route: {
    status: 1,
    output: 'internal React target is not registered',
    files: {[route]: 'export default () => <Route path="/portal" />;', [portal]: 'export default () => <Link to="/missing">Missing</Link>;'},
  },
  button: {
    status: 1,
    output: 'button has no handler',
    files: {[route]: 'export default () => <Route path="/portal" />;', [portal]: 'export default () => <button type="button">Does nothing</button>;'},
  },
  claim: {
    status: 1,
    output: 'unsupported operational claim',
    files: {[route]: 'export default () => <Route path="/portal" />;', [portal]: 'export default () => <button type="button" onClick={() => {}}>Deploy website</button>;'},
  },
  drupalRoute: {
    status: 1,
    output: 'unregistered Drupal route',
    files: {
      [route]: 'export default () => <Route path="/portal" />;',
      [routes]: "famtastic_pipeline.operations:\n  path: '/admin/famtastic'\n",
      [controller]: "<?php\nUrl::fromRoute('famtastic_pipeline.not_registered');\n",
    },
  },
};
