/**
 * Load the authenticated portal in two phases so anonymous visitors do not
 * fan out requests to protected workspace and catalog endpoints.
 */
export async function loadCustomerPortal({ getSession, getWorkspace, getCatalog }) {
  const session = await getSession();
  const [workspace, catalog] = await Promise.all([getWorkspace(), getCatalog()]);

  return { session, workspace, catalog };
}

/**
 * Convert the server-authorized staff capability into a trusted portal link.
 */
export function getStaffCommandCenterLink(session) {
  if (session?.staff?.can_access_command_center !== true) return null;
  if (session.staff.command_center_url !== '/web/admin/famtastic') return null;

  return {
    href: session.staff.command_center_url,
    label: 'Staff Command Center',
  };
}
