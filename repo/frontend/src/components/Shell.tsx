import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/auth';
import { api } from '../lib/api';
import type { Role } from '../lib/api';

const NAV: Record<Role, Array<{ to: string; label: string }>> = {
  trainee: [
    { to: '/trainee', label: 'Overview' },
    { to: '/trainee/bookings', label: 'Bookings' },
    { to: '/trainee/vouchers', label: 'Vouchers' },
    { to: '/trainee/certificates', label: 'Certificates' },
    { to: '/profile', label: 'Profile' },
  ],
  supervisor: [
    { to: '/supervisor', label: 'Overview' },
    { to: '/supervisor/sessions', label: 'Sessions' },
    { to: '/supervisor/leaves', label: 'Leaves' },
    { to: '/supervisor/assessments', label: 'Assessments' },
    { to: '/supervisor/certificates', label: 'Certificates' },
    { to: '/profile', label: 'Profile' },
  ],
  guardian: [
    { to: '/guardian', label: 'Children' },
    { to: '/profile', label: 'Profile' },
  ],
  employer: [
    { to: '/employer', label: 'Verify credentials' },
    { to: '/profile', label: 'Profile' },
  ],
  admin: [
    { to: '/admin', label: 'Overview' },
    { to: '/admin/bookings', label: 'Bookings' },
    { to: '/admin/moderation', label: 'Moderation' },
    { to: '/admin/vouchers', label: 'Vouchers' },
    { to: '/admin/resources', label: 'Resources' },
    { to: '/admin/certificates', label: 'Certificates' },
    { to: '/admin/ops', label: 'Ops' },
    { to: '/profile', label: 'Profile' },
  ],
};

export function Shell() {
  const { token, role, username, clearSession } = useAuth();
  const navigate = useNavigate();

  const items = role ? NAV[role] : [];

  const onLogout = async () => {
    if (token) {
      try { await api.logout(token); } catch { /* ignore */ }
    }
    clearSession();
    navigate('/login');
  };

  return (
    <div className="shell">
      <header className="topbar">
        <div className="brand">Workforce Hub</div>
        {role && <span className="role-badge">{role}</span>}
        <div className="spacer" />
        {username && <span>{username}</span>}
        <button className="btn secondary" onClick={onLogout}>Sign out</button>
      </header>
      <div className="layout">
        <aside className="sidebar">
          <nav>
            {items.map((i) => (
              <NavLink key={i.to} to={i.to} className={({ isActive }) => (isActive ? 'active' : '')} end>
                {i.label}
              </NavLink>
            ))}
          </nav>
        </aside>
        <main className="main">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
