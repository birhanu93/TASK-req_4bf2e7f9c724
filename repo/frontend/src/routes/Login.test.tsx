import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { LoginPage } from './Login';
import { AuthProvider } from '../lib/auth';
import { installFetchMock } from '../test/helpers';

function renderLogin() {
  return render(
    <MemoryRouter initialEntries={['/login']}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/trainee" element={<div>trainee-portal</div>} />
          <Route path="/admin" element={<div>admin-portal</div>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  );
}

describe('LoginPage', () => {
  it('logs in and single-role users land directly on their portal', async () => {
    const user = userEvent.setup();
    installFetchMock({
      'GET /api/auth/me': { status: 401 },
      'POST /api/auth/login': {
        status: 200,
        body: { userId: 'u1', username: 'alice', availableRoles: ['trainee'] },
      },
      'POST /api/auth/select-role': {
        status: 200,
        body: { token: 'cookie', role: 'trainee', userId: 'u1' },
      },
    });
    renderLogin();
    await waitFor(() => expect(screen.getByRole('heading', { name: /Sign in/i })).toBeInTheDocument());

    await user.type(screen.getByLabelText(/Username/i), 'alice');
    await user.type(screen.getByLabelText(/Password/i), 'pw-12345');
    await user.click(screen.getByRole('button', { name: /Continue/i }));

    await screen.findByText(/Choose the role/i);
    await user.click(screen.getByRole('button', { name: /Enter/i }));
    await waitFor(() => expect(screen.getByText('trainee-portal')).toBeInTheDocument());
  });

  it('surfaces server-provided error messages when login fails', async () => {
    const user = userEvent.setup();
    installFetchMock({
      'GET /api/auth/me': { status: 401 },
      'POST /api/auth/login': { status: 401, body: { error: 'invalid credentials' } },
    });
    renderLogin();
    await waitFor(() => expect(screen.getByRole('heading', { name: /Sign in/i })).toBeInTheDocument());
    await user.type(screen.getByLabelText(/Username/i), 'alice');
    await user.type(screen.getByLabelText(/Password/i), 'bad');
    await user.click(screen.getByRole('button', { name: /Continue/i }));
    await screen.findByText(/invalid credentials/i);
  });

  it('lets the first-time setup flow create an admin and returns to login', async () => {
    const user = userEvent.setup();
    installFetchMock({
      'GET /api/auth/me': { status: 401 },
      'POST /api/auth/bootstrap': {
        status: 200,
        body: { id: 'u-admin', username: 'admin', roles: ['admin'] },
      },
    });
    renderLogin();
    await waitFor(() => expect(screen.getByRole('heading', { name: /Sign in/i })).toBeInTheDocument());
    await user.click(screen.getByRole('button', { name: /First-time setup/i }));
    await user.type(screen.getByLabelText(/Username/i), 'admin');
    await user.type(screen.getByLabelText(/Password/i), 'strong-pass-1');
    await user.click(screen.getByRole('button', { name: /Create admin/i }));
    await screen.findByText(/Initial admin created/i);
    expect(screen.getByRole('heading', { name: /Sign in/i })).toBeInTheDocument();
  });
});
