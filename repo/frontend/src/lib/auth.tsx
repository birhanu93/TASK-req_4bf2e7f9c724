import { createContext, useContext, useEffect, useMemo, useState, type PropsWithChildren } from 'react';
import { api, type Role } from './api';

/**
 * Session is carried server-to-browser in an HttpOnly + SameSite=Strict
 * cookie — JavaScript can never read the raw token. This context keeps only
 * the non-secret parts of the session (role, userId, username) in memory so
 * UI can render role-specific chrome. On mount we ask the backend who we are
 * (`GET /api/auth/me`); if the cookie is missing or expired, we simply have
 * no session.
 */
export interface AuthState {
  role: Role | null;
  userId: string | null;
  username: string | null;
  /**
   * Truthy when an HttpOnly session cookie is believed to be in place.
   * JavaScript cannot read the cookie — this sentinel only signals
   * authenticated-ness so existing UI guards keep working.
   */
  token: string | null;
  /** True while we are still resolving the initial session from the server. */
  bootstrapping: boolean;
  /** Call after selectRole / switchRole responses. Role + userId are pulled from the response body. */
  adoptSession: (session: { role: Role; userId: string; username?: string }) => void;
  /** Alias for adoptSession, kept for backwards compatibility with existing screens. */
  setSession: (session: { role: Role; userId: string; username?: string }) => void;
  /** Clear the in-memory view; the cookie is cleared by the backend logout endpoint. */
  forgetSession: () => void;
  /** Alias for forgetSession. */
  clearSession: () => void;
}

const AuthContext = createContext<AuthState | null>(null);

interface InMemorySession {
  role: Role;
  userId: string;
  username?: string;
}

export function AuthProvider({ children }: PropsWithChildren) {
  const [state, setState] = useState<InMemorySession | null>(null);
  const [bootstrapping, setBootstrapping] = useState(true);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const me = await api.me();
        if (!active) return;
        setState({ role: me.role, userId: me.userId });
      } catch {
        if (!active) return;
        setState(null);
      } finally {
        if (active) setBootstrapping(false);
      }
    })();
    return () => {
      active = false;
    };
  }, []);

  const value = useMemo<AuthState>(() => {
    const adopt = (s: InMemorySession) => setState({ ...s });
    const forget = () => setState(null);
    return {
      role: state?.role ?? null,
      userId: state?.userId ?? null,
      username: state?.username ?? null,
      // Sentinel: presence of this string just means "we believe a session
      // cookie is set." The actual token is HttpOnly and unreachable from JS.
      token: state ? 'cookie' : null,
      bootstrapping,
      adoptSession: adopt,
      setSession: adopt,
      forgetSession: forget,
      clearSession: forget,
    };
  }, [state, bootstrapping]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used inside AuthProvider');
  }
  return ctx;
}
