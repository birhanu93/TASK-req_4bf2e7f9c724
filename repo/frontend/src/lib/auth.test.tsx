import { act, render, screen, waitFor } from '@testing-library/react';
import { AuthProvider, useAuth } from './auth';
import { installFetchMock } from '../test/helpers';

function Probe() {
  const { token, role, userId, bootstrapping, adoptSession, forgetSession } = useAuth();
  return (
    <div>
      <span data-testid="bootstrapping">{String(bootstrapping)}</span>
      <span data-testid="token">{token ?? 'null'}</span>
      <span data-testid="role">{role ?? 'null'}</span>
      <span data-testid="userId">{userId ?? 'null'}</span>
      <button onClick={() => adoptSession({ role: 'admin', userId: 'u-admin' })}>adopt</button>
      <button onClick={() => forgetSession()}>forget</button>
    </div>
  );
}

describe('AuthProvider', () => {
  it('resolves the existing session via /api/auth/me on mount', async () => {
    installFetchMock({
      'GET /api/auth/me': { status: 200, body: { userId: 'u1', role: 'trainee' } },
    });
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByTestId('bootstrapping')).toHaveTextContent('false'));
    expect(screen.getByTestId('token')).toHaveTextContent('cookie');
    expect(screen.getByTestId('role')).toHaveTextContent('trainee');
    expect(screen.getByTestId('userId')).toHaveTextContent('u1');
  });

  it('leaves the session empty when /api/auth/me fails', async () => {
    installFetchMock({
      'GET /api/auth/me': { status: 401, body: { error: 'no session' } },
    });
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByTestId('bootstrapping')).toHaveTextContent('false'));
    expect(screen.getByTestId('token')).toHaveTextContent('null');
    expect(screen.getByTestId('role')).toHaveTextContent('null');
  });

  it('adoptSession and forgetSession mutate the in-memory state', async () => {
    installFetchMock({ 'GET /api/auth/me': { status: 401 } });
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByTestId('bootstrapping')).toHaveTextContent('false'));

    act(() => { screen.getByText('adopt').click(); });
    expect(screen.getByTestId('role')).toHaveTextContent('admin');
    expect(screen.getByTestId('token')).toHaveTextContent('cookie');

    act(() => { screen.getByText('forget').click(); });
    expect(screen.getByTestId('role')).toHaveTextContent('null');
    expect(screen.getByTestId('token')).toHaveTextContent('null');
  });

  it('throws if useAuth is called outside the provider', () => {
    const { result } = (() => {
      try {
        render(<Probe />);
        return { result: null };
      } catch (err) {
        return { result: err as Error };
      }
    })();
    expect(result).toBeInstanceOf(Error);
    expect(result?.message).toMatch(/AuthProvider/);
  });
});
