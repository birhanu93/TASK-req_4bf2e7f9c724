import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, type Role } from '../lib/api';
import { useAuth } from '../lib/auth';

export function LoginPage() {
  const navigate = useNavigate();
  const { setSession } = useAuth();
  const [mode, setMode] = useState<'login' | 'bootstrap'>('login');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [availableRoles, setAvailableRoles] = useState<Role[] | null>(null);
  const [selectedRole, setSelectedRole] = useState<Role | ''>('');
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const onLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setInfo(null);
    setBusy(true);
    try {
      const res = await api.login(username, password);
      setAvailableRoles(res.availableRoles);
      if (res.availableRoles.length === 1) {
        setSelectedRole(res.availableRoles[0]);
      }
    } catch (err) {
      setError((err as { message?: string }).message ?? 'login failed');
    } finally {
      setBusy(false);
    }
  };

  const onSelectRole = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedRole) {
      setError('select a role');
      return;
    }
    setError(null);
    setBusy(true);
    try {
      const res = await api.selectRole(username, password, selectedRole);
      // The server response also sets an HttpOnly session cookie; we only
      // stash the non-secret pieces in memory so the UI can render the
      // right role portal.
      setSession({ role: res.role, userId: res.userId, username });
      navigate(`/${res.role}`, { replace: true });
    } catch (err) {
      setError((err as { message?: string }).message ?? 'could not issue role token');
    } finally {
      setBusy(false);
    }
  };

  const onBootstrap = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setInfo(null);
    setBusy(true);
    try {
      await api.bootstrap(username, password);
      setInfo('Initial admin created. Sign in to continue.');
      setMode('login');
      setAvailableRoles(null);
    } catch (err) {
      setError((err as { message?: string }).message ?? 'bootstrap failed');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login-page">
      <div className="card">
        <h1>{mode === 'login' ? 'Sign in' : 'Create first admin'}</h1>
        {error && <div className="banner error">{error}</div>}
        {info && <div className="banner ok">{info}</div>}

        {mode === 'bootstrap' && (
          <form onSubmit={onBootstrap}>
            <label><span>Username</span><input value={username} onChange={(e) => setUsername(e.target.value)} required /></label>
            <label><span>Password</span><input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required /></label>
            <button className="btn" disabled={busy} type="submit">Create admin</button>{' '}
            <button className="btn secondary" type="button" onClick={() => setMode('login')}>Back</button>
          </form>
        )}

        {mode === 'login' && !availableRoles && (
          <form onSubmit={onLogin}>
            <label><span>Username</span><input value={username} onChange={(e) => setUsername(e.target.value)} required /></label>
            <label><span>Password</span><input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required /></label>
            <button className="btn" disabled={busy} type="submit">Continue</button>{' '}
            <button className="btn secondary" type="button" onClick={() => setMode('bootstrap')}>First-time setup</button>
          </form>
        )}

        {mode === 'login' && availableRoles && (
          <form onSubmit={onSelectRole}>
            <p>Choose the role you want to use for this session:</p>
            <label>
              <span>Role</span>
              <select value={selectedRole} onChange={(e) => setSelectedRole(e.target.value as Role)} required>
                <option value="">Select…</option>
                {availableRoles.map((r) => (
                  <option key={r} value={r}>{r}</option>
                ))}
              </select>
            </label>
            <button className="btn" disabled={busy || !selectedRole} type="submit">Enter</button>{' '}
            <button className="btn secondary" type="button" onClick={() => setAvailableRoles(null)}>Back</button>
          </form>
        )}
      </div>
    </div>
  );
}
