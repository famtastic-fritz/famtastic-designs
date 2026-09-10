/**
 * Load the authenticated portal in two phases so anonymous visitors do not
 * fan out requests to protected workspace and catalog endpoints.
 */
export async function loadCustomerPortal({ getSession, getWorkspace, getCatalog }) {
  const session = await getSession();
  const [workspace, catalog] = await Promise.all([getWorkspace(), getCatalog()]);

  return { session, workspace, catalog };
}
