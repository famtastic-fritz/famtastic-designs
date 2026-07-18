import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useUser } from '../auth/UserContext.jsx';

/**
 * Site header with FAMtastic branding. The nav is passed in as children by
 * the Layout so menu data stays decoupled from presentation. The right-hand
 * auth area shows a Login link for guests, or the signed-in email plus
 * Admin and Logout actions for authenticated users.
 */
export default function Header({ children }) {
  const { user, isAuthenticated, logout } = useUser();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate('/', { replace: true });
  }

  return (
    <header className="site-header">
      <div className="site-header__inner">
        <Link to="/" className="brand" aria-label="FAMtastic Designs — home">
          <span className="brand__mark">FAM</span>
          <span>tastic</span>
          <span className="brand__tag">Designs</span>
        </Link>
        {children}
        <div className="auth-area">
          {isAuthenticated ? (
            <>
              <NavLink to="/admin" className="site-nav__link">
                Admin
              </NavLink>
              <span className="auth-area__user" title={user?.email ?? ''}>
                {user?.email}
              </span>
              <button type="button" className="btn btn--ghost btn--sm" onClick={handleLogout}>
                Logout
              </button>
            </>
          ) : (
            <NavLink to="/login" className="site-nav__link">
              Login
            </NavLink>
          )}
        </div>
      </div>
    </header>
  );
}
