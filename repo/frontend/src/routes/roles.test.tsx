import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../lib/auth';
import { TraineeHome } from './trainee/Home';
import { SupervisorHome } from './supervisor/Home';
import { AdminHome } from './admin/Home';
import { EmployerHome } from './employer/Home';
import { GuardianHome } from './guardian/Home';
import { installFetchMock } from '../test/helpers';

function renderWithAuth(ui: React.ReactElement) {
  return render(
    <MemoryRouter>
      <AuthProvider>{ui}</AuthProvider>
    </MemoryRouter>,
  );
}

describe('Role route screens', () => {
  it('TraineeHome fetches and renders progress', async () => {
    installFetchMock({
      'GET /api/auth/me': { status: 200, body: { userId: 'u-trainee', role: 'trainee' } },
      'GET /api/assessments/progress/u-trainee': {
        status: 200,
        body: { reps: 42, seconds: 130, assessments: 3, currentRank: 'Bronze', nextRank: 'Silver' },
      },
    });
    renderWithAuth(<TraineeHome />);
    await waitFor(() => expect(screen.getByText('42')).toBeInTheDocument());
    expect(screen.getByText('130')).toBeInTheDocument();
    expect(screen.getByText('Bronze')).toBeInTheDocument();
    expect(screen.getByText('Silver')).toBeInTheDocument();
  });

  it('TraineeHome surfaces errors from the API', async () => {
    installFetchMock({
      'GET /api/auth/me': { status: 200, body: { userId: 'u-trainee', role: 'trainee' } },
      'GET /api/assessments/progress/u-trainee': { status: 500, body: { error: 'boom' } },
    });
    renderWithAuth(<TraineeHome />);
    await screen.findByText(/boom/);
  });

  it('SupervisorHome renders static guidance', async () => {
    installFetchMock({ 'GET /api/auth/me': { status: 401 } });
    renderWithAuth(<SupervisorHome />);
    expect(screen.getByRole('heading', { name: /Supervisor overview/i })).toBeInTheDocument();
    expect(screen.getByText(/sessions, leaves, and assessments/i)).toBeInTheDocument();
  });

  it('AdminHome renders static guidance', async () => {
    installFetchMock({ 'GET /api/auth/me': { status: 401 } });
    renderWithAuth(<AdminHome />);
    expect(screen.getByRole('heading', { name: /Administration/i })).toBeInTheDocument();
    expect(screen.getByText(/moderation queues/i)).toBeInTheDocument();
  });

  it('EmployerHome verifies a credential and displays VALID when the backend says so', async () => {
    const user = userEvent.setup();
    installFetchMock({
      'GET /api/auth/me': { status: 200, body: { userId: 'u-emp', role: 'employer' } },
      'GET /api/certificates/verify/ABC123': {
        status: 200,
        body: { id: 'c1', valid: true, status: 'active', issuedAt: '2026-01-01T00:00:00+00:00' },
      },
    });
    renderWithAuth(<EmployerHome />);
    await user.type(screen.getByLabelText(/Verification code/i), 'ABC123');
    await user.click(screen.getByRole('button', { name: /Verify/i }));
    await screen.findByText(/VALID/);
    expect(screen.getByText('c1')).toBeInTheDocument();
    expect(screen.getByText('active')).toBeInTheDocument();
  });

  it('GuardianHome lists linked children and fetches progress on demand', async () => {
    const user = userEvent.setup();
    installFetchMock({
      'GET /api/auth/me': { status: 200, body: { userId: 'u-g', role: 'guardian' } },
      'GET /api/guardians/children': {
        status: 200,
        body: {
          children: [
            { id: 'link-1', childId: 'child-1', linkedAt: '2026-01-02T03:04:05+00:00' },
          ],
        },
      },
      'GET /api/guardians/children/child-1/progress': {
        status: 200,
        body: { reps: 7, seconds: 25, assessments: 1, currentRank: null, nextRank: 'Bronze' },
      },
      'GET /api/guardians/children/child-1/devices': {
        status: 200,
        body: { devices: [] },
      },
    });
    renderWithAuth(<GuardianHome />);
    await screen.findByText('child-1');
    await user.click(screen.getByRole('button', { name: /Manage/i }));
    await screen.findByText('7');
    expect(screen.getByText('25')).toBeInTheDocument();
    expect(screen.getByText(/No devices approved/i)).toBeInTheDocument();
  });
});
