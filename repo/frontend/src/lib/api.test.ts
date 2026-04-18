import { api, apiFetch } from './api';
import { installFetchMock } from '../test/helpers';

describe('apiFetch', () => {
  it('sends JSON bodies with credentials and the cookie-style auth envelope', async () => {
    const { mock } = installFetchMock({
      'POST /api/auth/login': {
        status: 200,
        body: { userId: 'u1', username: 'alice', availableRoles: ['trainee'] },
      },
    });
    const result = await api.login('alice', 'pw-12345');
    expect(result).toEqual({ userId: 'u1', username: 'alice', availableRoles: ['trainee'] });
    expect(mock).toHaveBeenCalledTimes(1);
    const [input, init] = mock.mock.calls[0];
    expect(input).toBe('/api/auth/login');
    expect(init?.method).toBe('POST');
    expect(init?.credentials).toBe('include');
    expect((init?.headers as Record<string, string>)['Content-Type']).toBe('application/json');
    expect(JSON.parse(init!.body as string)).toEqual({ username: 'alice', password: 'pw-12345' });
  });

  it('returns undefined on 204', async () => {
    installFetchMock({ 'POST /api/auth/logout': { status: 204 } });
    await expect(api.logout()).resolves.toBeUndefined();
  });

  it('throws an ApiError carrying the backend error message', async () => {
    installFetchMock({
      'POST /api/auth/login': { status: 401, body: { error: 'invalid credentials' } },
    });
    await expect(api.login('alice', 'bad')).rejects.toMatchObject({
      status: 401,
      message: 'invalid credentials',
    });
  });

  it('falls back to a synthetic message when no body is returned', async () => {
    installFetchMock({ 'GET /api/sessions': { status: 500 } });
    await expect(apiFetch('GET', '/sessions')).rejects.toMatchObject({
      status: 500,
      message: 'request failed (500)',
    });
  });

  it('omits the body on GET', async () => {
    const { mock } = installFetchMock({
      'GET /api/auth/me': { status: 200, body: { userId: 'u1', role: 'admin' } },
    });
    await api.me();
    expect(mock.mock.calls[0][1]?.body).toBeUndefined();
  });

  it('builds admin booking queries with URL-encoded filters', async () => {
    const { mock } = installFetchMock({
      'GET /api/admin/bookings?traineeId=t%201&status=confirmed': {
        status: 200,
        body: { bookings: [] },
      },
    });
    await api.adminListBookings({ traineeId: 't 1', status: 'confirmed' });
    expect(mock).toHaveBeenCalledTimes(1);
  });
});
