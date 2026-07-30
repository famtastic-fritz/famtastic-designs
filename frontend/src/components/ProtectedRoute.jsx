import { Navigate, useLocation } from 'react-router-dom';
import { useUser } from '../auth/UserContext.jsx';

/**
 * Route guard for authenticated areas (e.g. /admin).
 *
 * - While the persisted session is being restored, renders the shared
 *   loading spinner so the guard never flashes a false redirect.
 * - When unauthenticated, redirects to /login?redirect=<current path> so
 *   the user lands back here after a successful login.
 * - Otherwise renders its children.
 */
export default function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useUser();
  const location = useLocation();

  if (loading) {
    return (
      <div className="loading" role="status" aria-live="polite">
        Restoring your session…
      </div>
    );
  }

  if (!isAuthenticated) {
    const target = `${location.pathname}${location.search}`;
    return <Navigate to={`/login?redirect=${encodeURIComponent(target)}`} replace />;
  }

  return children;
}
