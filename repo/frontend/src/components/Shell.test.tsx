import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { Shell } from './Shell';
import { AuthProvider } from '../lib/auth';
import { installFetchMock } from '../test/helpers';

function renderShell(initialPath: string, body: { userId: string; role: string }) {
  installFetchMock({
    'GET /api/auth/me': { status: 200, body },
    'POST /api/auth/logout': { status: 204 },
  });
  return render(
    <MemoryRouter initialEntries={[initialPath]}>
      <AuthProvider>
        <Routes>
          <Route element={<Shell />}>
            <Route path={initialPath} element={<div>content-{body.role}</div>} />
          </Route>
          <Route path="/login" element={<div>login-page</div>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  );
}

describe('Shell', () => {
  it('renders the role-specific nav for trainees', async () => {
    renderShell('/trainee', { userId: 'u1', role: 'trainee' });
    await waitFor(() => expect(screen.getByText('content-trainee')).toBeInTheDocument());
    expect(screen.getByRole('link', { name: 'Overview' })).toHaveAttribute('href', '/trainee');
    expect(screen.getByRole('link', { name: 'Bookings' })).toHaveAttribute('href', '/trainee/bookings');
    expect(screen.getByRole('link', { name: 'Vouchers' })).toHaveAttribute('href', '/trainee/vouchers');
    expect(screen.getByRole('link', { name: 'Certificates' })).toHaveAttribute('href', '/trainee/certificates');
    expect(screen.queryByRole('link', { name: 'Moderation' })).not.toBeInTheDocument();
  });

  it('renders the admin nav when the role is admin', async () => {
    renderShell('/admin', { userId: 'u1', role: 'admin' });
    await waitFor(() => expect(screen.getByText('content-admin')).toBeInTheDocument());
    for (const label of ['Overview', 'Bookings', 'Moderation', 'Vouchers', 'Resources', 'Certificates', 'Ops']) {
      expect(screen.getByRole('link', { name: label })).toBeInTheDocument();
    }
    expect(screen.getByText('admin')).toBeInTheDocument();
  });

  it('calls the backend logout and navigates back to /login', async () => {
    const user = userEvent.setup();
    renderShell('/trainee', { userId: 'u1', role: 'trainee' });
    await waitFor(() => expect(screen.getByText('content-trainee')).toBeInTheDocument());
    await act(async () => {
      await user.click(screen.getByRole('button', { name: /Sign out/i }));
    });
    await waitFor(() => expect(screen.getByText('login-page')).toBeInTheDocument());
    const mockFetch = fetch as unknown as import('vitest').Mock;
    const calls = mockFetch.mock.calls.map((args) => {
      const [u, init] = args as [string, RequestInit | undefined];
      return `${init?.method ?? 'GET'} ${u}`;
    });
    expect(calls).toContain('POST /api/auth/logout');
  });
});
