import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { App } from './App';
import { AuthProvider } from './lib/auth';
import { installFetchMock } from './test/helpers';

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <App />
      </AuthProvider>
    </MemoryRouter>,
  );
}

describe('App routing', () => {
  it('redirects unauthenticated users to /login for root', async () => {
    installFetchMock({ 'GET /api/auth/me': { status: 401 } });
    renderAt('/');
    await waitFor(() => expect(screen.getByRole('heading', { name: /Sign in/i })).toBeInTheDocument());
    expect(screen.getByRole('button', { name: /First-time setup/i })).toBeInTheDocument();
  });

  it('renders the admin portal under /admin when the session is admin', async () => {
    installFetchMock({ 'GET /api/auth/me': { status: 200, body: { userId: 'u1', role: 'admin' } } });
    renderAt('/admin');
    await waitFor(() => expect(screen.getByRole('heading', { name: /Administration/i })).toBeInTheDocument());
    // The shell's role badge is rendered for authenticated roles.
    expect(screen.getByText('admin')).toBeInTheDocument();
  });

  it('redirects a trainee who tries to access /admin back to /trainee', async () => {
    installFetchMock({
      'GET /api/auth/me': { status: 200, body: { userId: 'u1', role: 'trainee' } },
      'GET /api/assessments/progress/u1': {
        status: 200,
        body: { reps: 0, seconds: 0, assessments: 0, currentRank: null, nextRank: null },
      },
    });
    renderAt('/admin');
    await waitFor(() => expect(screen.getByRole('heading', { name: /Your training/i })).toBeInTheDocument());
  });

  it('an unknown path falls through to /login', async () => {
    installFetchMock({ 'GET /api/auth/me': { status: 401 } });
    renderAt('/nope');
    await waitFor(() => expect(screen.getByRole('heading', { name: /Sign in/i })).toBeInTheDocument());
  });
});
