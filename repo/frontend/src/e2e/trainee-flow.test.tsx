import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { App } from '../App';
import { AuthProvider } from '../lib/auth';
import { installFetchMock } from '../test/helpers';

/**
 * Exercises the real FE↔BE wiring: the user visits /login, the AuthProvider
 * bootstraps, login returns available roles, select-role issues the session,
 * the shell mounts, and the trainee progress endpoint is called with the
 * userId the server returned. Every API call here is an assertion about the
 * actual contract between the SPA and the PHP backend — not just UI fluff.
 */
describe('trainee end-to-end flow', () => {
  it('logs in, lands on /trainee, and renders progress from the API', async () => {
    const user = userEvent.setup();
    const { calls } = installFetchMock({
      'GET /api/auth/me': { status: 401, body: { error: 'no session' } },
      'POST /api/auth/login': {
        status: 200,
        body: { userId: 'u-trainee', username: 'trainee1', availableRoles: ['trainee'] },
      },
      'POST /api/auth/select-role': {
        status: 200,
        body: { token: 'cookie', role: 'trainee', userId: 'u-trainee' },
      },
      'GET /api/assessments/progress/u-trainee': {
        status: 200,
        body: { reps: 17, seconds: 60, assessments: 2, currentRank: 'Bronze', nextRank: 'Silver' },
      },
    });

    render(
      <MemoryRouter initialEntries={['/login']}>
        <AuthProvider>
          <App />
        </AuthProvider>
      </MemoryRouter>,
    );

    // 1. Login screen renders once /auth/me returns 401.
    await waitFor(() => expect(screen.getByRole('heading', { name: /Sign in/i })).toBeInTheDocument());

    // 2. User submits credentials — backend returns available roles.
    await user.type(screen.getByLabelText(/Username/i), 'trainee1');
    await user.type(screen.getByLabelText(/Password/i), 'pw-12345');
    await user.click(screen.getByRole('button', { name: /Continue/i }));

    // 3. Role picker appears; single role auto-selected so Enter just confirms.
    await screen.findByText(/Choose the role/i);
    await user.click(screen.getByRole('button', { name: /Enter/i }));

    // 4. Shell + TraineeHome mount, which calls /assessments/progress.
    await waitFor(() => expect(screen.getByRole('heading', { name: /Your training/i })).toBeInTheDocument());
    await screen.findByText('17');
    expect(screen.getByText('Bronze')).toBeInTheDocument();
    expect(screen.getByText('Silver')).toBeInTheDocument();

    // 5. Assert the backend call sequence — this is the real contract.
    const sequence = calls.map((c) => `${c.method} ${c.url}`);
    expect(sequence).toEqual([
      'GET /api/auth/me',
      'POST /api/auth/login',
      'POST /api/auth/select-role',
      'GET /api/assessments/progress/u-trainee',
    ]);

    // 6. Body of select-role must carry the password the user just typed and
    //    the role that came back from /auth/login — a regression here would
    //    break every role portal.
    const selectRoleCall = calls.find((c) => c.url === '/api/auth/select-role');
    expect(selectRoleCall?.body).toEqual({
      username: 'trainee1',
      password: 'pw-12345',
      role: 'trainee',
    });
  });
});
