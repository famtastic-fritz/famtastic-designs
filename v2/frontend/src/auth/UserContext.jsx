import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import {
  login as apiLogin,
  refreshToken as apiRefreshToken,
  readStoredToken,
  storeToken,
  clearStoredToken,
  isTokenExpired,
  AUTH_EVENT,
} from '../api/drupal.js';

/**
 * Authentication context for the FAMtastic SPA (Phase 2).
 *
 * Provides:
 *   user            { email } of the logged-in Drupal user, or null
 *   token           stored OAuth bundle { access_token, refresh_token,
 *                   expires_at, user_email }, or null
 *   login(email, password)  password grant via drupal/simple_oauth
 *   logout()        clears the session locally
 *   isAuthenticated boolean derived from token presence
 *   loading         true while a stored session is restored on mount
 *
 * Persistence: the token bundle is stored in localStorage under
 * 'famtastic_oauth' (access_token, refresh_token, expires_at). localStorage
 * is readable by any JS on the origin (XSS risk) — for production prefer a
 * small BFF/proxy that keeps tokens in secure httpOnly SameSite cookies and
 * have this client call that proxy instead of /oauth/token directly.
 *
 * Cross-layer sync: apiFetchAuth may transparently refresh (or clear) the
 * stored token outside React. It dispatches the AUTH_EVENT window event,
 * which this provider listens to so context state never goes stale — no
 * circular import between the API layer and React.
 */

const UserContext = createContext(null);

export function UserProvider({ children }) {
  const [token, setToken] = useState(null);
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  /** Apply a token bundle (or null) to React state. */
  const applyToken = useCallback((bundle) => {
    setToken(bundle);
    setUser(bundle?.user_email ? { email: bundle.user_email } : null);
  }, []);

  /* Restore the persisted session on mount. If the access token is expired
   * but a refresh token exists, silently renew it before giving up. */
  useEffect(() => {
    let cancelled = false;

    async function restore() {
      const stored = readStoredToken();
      if (!stored) {
        if (!cancelled) setLoading(false);
        return;
      }

      if (!isTokenExpired(stored)) {
        if (!cancelled) {
          applyToken(stored);
          setLoading(false);
        }
        return;
      }

      if (stored.refresh_token) {
        try {
          const refreshed = await apiRefreshToken(stored.refresh_token);
          const next = { ...stored, ...refreshed };
          storeToken(next); // persists + dispatches AUTH_EVENT
          if (!cancelled) {
            applyToken(next);
            setLoading(false);
          }
          return;
        } catch (err) {
          console.warn('[auth] session refresh failed:', err.message);
        }
      }

      clearStoredToken();
      if (!cancelled) {
        applyToken(null);
        setLoading(false);
      }
    }

    restore();
    return () => {
      cancelled = true;
    };
  }, [applyToken]);

  /* Mirror token changes made outside React (auto-refresh in apiFetchAuth). */
  useEffect(() => {
    function onAuthEvent(event) {
      applyToken(event.detail?.token ?? null);
    }
    window.addEventListener(AUTH_EVENT, onAuthEvent);
    return () => window.removeEventListener(AUTH_EVENT, onAuthEvent);
  }, [applyToken]);

  /** Log in with Drupal credentials; throws with a friendly message on failure. */
  const login = useCallback(
    async (email, password) => {
      const bundle = await apiLogin(email, password);
      storeToken(bundle);
      applyToken(bundle);
      return { email: bundle.user_email };
    },
    [applyToken],
  );

  /** Clear the local session. (simple_oauth has no core revoke endpoint.) */
  const logout = useCallback(() => {
    clearStoredToken();
    applyToken(null);
  }, [applyToken]);

  const value = useMemo(
    () => ({
      user,
      token,
      login,
      logout,
      isAuthenticated: Boolean(token?.access_token),
      loading,
    }),
    [user, token, login, logout, loading],
  );

  return <UserContext.Provider value={value}>{children}</UserContext.Provider>;
}

/** Hook: access the auth context. Must be used inside <UserProvider>. */
export function useUser() {
  const ctx = useContext(UserContext);
  if (!ctx) {
    throw new Error('useUser() must be used within a <UserProvider>.');
  }
  return ctx;
}
