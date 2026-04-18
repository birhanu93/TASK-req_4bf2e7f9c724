export type Role = 'trainee' | 'supervisor' | 'guardian' | 'employer' | 'admin';

export interface ApiError {
  status: number;
  message: string;
}

const BASE = '/api';

/**
 * Makes an API call. Session auth is carried by an HttpOnly cookie that the
 * browser attaches automatically — there is no way for JavaScript to read or
 * forge it. The legacy `token` parameter is kept in the signature so the
 * many existing `api.foo(..., token)` call sites keep compiling, but it is
 * intentionally ignored. `credentials: 'include'` sends the cookie on
 * cross-origin dev server setups too.
 */
export async function apiFetch<T>(
  method: string,
  path: string,
  body?: unknown,
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  _legacyToken?: string | null,
): Promise<T> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    credentials: 'include',
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (res.status === 204) {
    return undefined as T;
  }
  const payload = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err: ApiError = {
      status: res.status,
      message: (payload as { error?: string }).error ?? `request failed (${res.status})`,
    };
    throw err;
  }
  return payload as T;
}

export const api = {
  bootstrap: (username: string, password: string) =>
    apiFetch<{ id: string; username: string; roles: string[] }>(
      'POST',
      '/auth/bootstrap',
      { username, password },
    ),
  login: (username: string, password: string) =>
    apiFetch<{ userId: string; username: string; availableRoles: Role[] }>(
      'POST',
      '/auth/login',
      { username, password },
    ),
  selectRole: (username: string, password: string, role: Role) =>
    apiFetch<{ token: string; role: Role; userId: string }>(
      'POST',
      '/auth/select-role',
      { username, password, role },
    ),
  switchRole: (password: string, role: Role, token?: string | null) =>
    apiFetch<{ token: string; role: Role }>(
      'POST',
      '/auth/switch-role',
      { password, role },
      token,
    ),
  logout: (_token?: string) => apiFetch<void>('POST', '/auth/logout', undefined),
  me: (_token?: string) => apiFetch<{ userId: string; role: Role }>('GET', '/auth/me', undefined),
  changePassword: (oldPassword: string, newPassword: string, token?: string | null) =>
    apiFetch<void>('POST', '/auth/change-password', { oldPassword, newPassword }, token),

  listSessions: (token?: string | null) =>
    apiFetch<{ sessions: Array<{ id: string; title: string; startsAt: string; endsAt: string; capacity: number; availability: number; status: string }> }>(
      'GET',
      '/sessions',
      undefined,
      token,
    ),
  createSession: (
    payload: {
      title: string;
      startsAt: string;
      endsAt: string;
      capacity: number;
      bufferMinutes?: number;
      resourceIds?: string[];
    },
    token?: string | null,
  ) => apiFetch<unknown>('POST', '/sessions', payload, token),
  closeSession: (id: string, token?: string | null) => apiFetch<void>('POST', `/sessions/${id}/close`, undefined, token),

  addLeave: (
    payload: { startsAt: string; endsAt: string; rule?: string; reason?: string },
    token?: string | null,
  ) => apiFetch<unknown>('POST', '/sessions/leaves', payload, token),
  listLeaves: (token?: string | null) =>
    apiFetch<{ leaves: Array<{ id: string; startsAt: string; endsAt: string; rule: string; reason: string | null }> }>(
      'GET',
      '/sessions/leaves',
      undefined,
      token,
    ),

  listMyBookings: (token?: string | null) =>
    apiFetch<{ bookings: Array<{ id: string; sessionId: string; status: string; createdAt: string }> }>(
      'GET',
      '/bookings',
      undefined,
      token,
    ),
  book: (sessionId: string, idempotencyKey: string, token?: string | null) =>
    apiFetch<{ id: string; status: string }>('POST', '/bookings', { sessionId, idempotencyKey }, token),
  confirmBooking: (id: string, token?: string | null) =>
    apiFetch<unknown>('POST', `/bookings/${id}/confirm`, undefined, token),
  cancelBooking: (id: string, reason: string, override: boolean, token?: string | null) =>
    apiFetch<unknown>('POST', `/bookings/${id}/cancel`, { reason, override }, token),
  rescheduleBooking: (
    id: string,
    newSessionId: string,
    idempotencyKey: string,
    override: boolean,
    token?: string | null,
    reason?: string | null,
  ) =>
    apiFetch<unknown>(
      'POST',
      `/bookings/${id}/reschedule`,
      { newSessionId, idempotencyKey, override, reason: reason ?? undefined },
      token,
    ),

  progress: (traineeId: string, token?: string | null) =>
    apiFetch<{ reps: number; seconds: number; assessments: number; currentRank: string | null; nextRank: string | null }>(
      'GET',
      `/assessments/progress/${encodeURIComponent(traineeId)}`,
      undefined,
      token,
    ),
  recordAssessment: (
    payload: { templateId: string; traineeId: string; reps: number; seconds: number },
    token?: string | null,
  ) => apiFetch<unknown>('POST', '/assessments', payload, token),

  listPendingModeration: (token?: string | null) =>
    apiFetch<{ items: Array<{ id: string; authorId: string; kind: string; status: string; submittedAt: string }> }>(
      'GET',
      '/moderation/pending',
      undefined,
      token,
    ),
  submitModeration: (kind: string, content: string, token?: string | null) =>
    apiFetch<unknown>('POST', '/moderation', { kind, content }, token),
  approveModeration: (id: string, score: number, reason: string | undefined, token?: string | null) =>
    apiFetch<unknown>('POST', `/moderation/${id}/approve`, { score, reason }, token),
  rejectModeration: (id: string, reason: string, token?: string | null) =>
    apiFetch<unknown>('POST', `/moderation/${id}/reject`, { reason }, token),
  bulkApproveModeration: (ids: string[], score: number, token?: string | null) =>
    apiFetch<{ approved: unknown[]; failed: string[] }>(
      'POST',
      '/moderation/bulk-approve',
      { ids, score },
      token,
    ),
  bulkRejectModeration: (ids: string[], reason: string, token?: string | null) =>
    apiFetch<{ rejected: unknown[]; failed: string[] }>(
      'POST',
      '/moderation/bulk-reject',
      { ids, reason },
      token,
    ),

  children: (token?: string | null) =>
    apiFetch<{ children: Array<{ id: string; childId: string; linkedAt: string }> }>('GET', '/guardians/children', undefined, token),
  childProgress: (childId: string, token?: string | null) =>
    apiFetch<{ reps: number; seconds: number; assessments: number; currentRank: string | null; nextRank: string | null }>(
      'GET',
      `/guardians/children/${encodeURIComponent(childId)}/progress`,
      undefined,
      token,
    ),
  linkChild: (childId: string, token?: string | null) =>
    apiFetch<{ id: string; childId: string; linkedAt: string }>(
      'POST',
      '/guardians/links',
      { childId },
      token,
    ),
  listChildDevices: (childId: string, token?: string | null) =>
    apiFetch<{ devices: Array<{ id: string; deviceName: string; fingerprint: string; status: string; approvedAt: string }> }>(
      'GET',
      `/guardians/children/${encodeURIComponent(childId)}/devices`,
      undefined,
      token,
    ),
  approveChildDevice: (childId: string, deviceName: string, fingerprint: string, token?: string | null) =>
    apiFetch<{ id: string; deviceName: string; fingerprint: string; status: string; approvedAt: string }>(
      'POST',
      '/guardians/devices',
      { childId, deviceName, fingerprint },
      token,
    ),
  remoteLogoutDevice: (deviceId: string, token?: string | null) =>
    apiFetch<{ id: string; status: string }>(
      'POST',
      `/guardians/devices/${encodeURIComponent(deviceId)}/logout`,
      undefined,
      token,
    ),

  describeVoucher: (code: string, token?: string) =>
    apiFetch<{ id: string; code: string; discountCents: number; minSpendCents: number; remaining: number; status: string }>(
      'GET',
      `/vouchers/${encodeURIComponent(code)}`,
      undefined,
      token,
    ),
  listAllVouchers: (token?: string) =>
    apiFetch<{ vouchers: Array<{ id: string; code: string; discountCents: number; minSpendCents: number; claimLimit: number; claimed: number; remaining: number; expiresAt: string; status: string }> }>(
      'GET',
      '/vouchers',
      undefined,
      token,
    ),
  issueVoucher: (
    payload: { code: string; discountCents: number; minSpendCents: number; claimLimit: number; expiresAt: string },
    token?: string,
  ) => apiFetch<{ id: string; code: string; status: string }>('POST', '/vouchers', payload, token),
  voidVoucher: (id: string, token?: string) =>
    apiFetch<{ id: string; status: string }>('POST', `/vouchers/${id}/void`, undefined, token),
  claimVoucher: (code: string, idempotencyKey: string, token?: string) =>
    apiFetch<{ id: string; status: string }>('POST', '/vouchers/claims', { code, idempotencyKey }, token),
  redeemVoucher: (
    claimId: string,
    orderAmountCents: number,
    redemptionIdempotencyKey: string,
    token?: string,
  ) =>
    apiFetch<{ claim: unknown; discountCents: number; replayed: boolean }>(
      'POST',
      `/vouchers/claims/${claimId}/redeem`,
      { orderAmountCents, redemptionIdempotencyKey },
      token,
    ),

  verifyCertificate: (code: string, token?: string | null) =>
    apiFetch<{ id: string; status: string; verificationCode: string; valid: boolean; issuedAt: string }>(
      'GET',
      `/certificates/verify/${encodeURIComponent(code)}`,
      undefined,
      token,
    ),
  downloadCertificate: (id: string, token?: string | null) =>
    apiFetch<{ pdf: string }>('GET', `/certificates/${id}/download`, undefined, token),

  getProfile: (token?: string | null) =>
    apiFetch<{ profile: Record<string, unknown> }>('GET', '/profile', undefined, token),
  updateProfile: (profile: Record<string, unknown>, token?: string | null) =>
    apiFetch<void>('PUT', '/profile', { profile }, token),

  runSnapshot: (token?: string | null) =>
    apiFetch<{ path: string; manifest: unknown }>('POST', '/admin/snapshots', undefined, token),
  runTiering: (token?: string | null) =>
    apiFetch<{ movedCount: number; keptCount: number; snapshot: unknown }>(
      'POST',
      '/admin/storage/tier',
      undefined,
      token,
    ),
  rotateKey: (token?: string | null) =>
    apiFetch<{ newKeyVersion: number }>('POST', '/admin/keys/rotate', undefined, token),

  adminListBookings: (
    filters: { traineeId?: string; sessionId?: string; status?: string } = {},
    token?: string | null,
  ) => {
    const qs = Object.entries(filters)
      .filter(([, v]) => v !== undefined && v !== '')
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
      .join('&');
    return apiFetch<{
      bookings: Array<{
        id: string;
        sessionId: string;
        traineeId: string;
        status: string;
        createdAt: string;
        cancellationReason: string | null;
        overrideActorId: string | null;
      }>;
    }>(
      'GET',
      `/admin/bookings${qs ? `?${qs}` : ''}`,
      undefined,
      token,
    );
  },

  listResources: (token?: string | null) =>
    apiFetch<{ resources: Array<{ id: string; name: string; kind: string; status: string; createdAt: string }> }>(
      'GET',
      '/resources',
      undefined,
      token,
    ),
  createResource: (payload: { name: string; kind: string }, token?: string | null) =>
    apiFetch<{ id: string; name: string; kind: string; status: string }>('POST', '/resources', payload, token),
  retireResource: (id: string, token?: string | null) =>
    apiFetch<{ id: string; status: string }>('POST', `/resources/${id}/retire`, undefined, token),
  listResourceReservations: (id: string, token?: string | null) =>
    apiFetch<{ reservations: Array<{ id: string; resourceId: string; sessionId: string | null; startsAt: string; endsAt: string; reservedByUserId: string }> }>(
      'GET',
      `/resources/${id}/reservations`,
      undefined,
      token,
    ),

  listRanks: (token?: string | null) =>
    apiFetch<{ ranks: Array<{ id: string; name: string; minReps: number; minSeconds: number; order: number }> }>(
      'GET',
      '/assessments/ranks',
      undefined,
      token,
    ),

  listMyCertificates: (token?: string | null) =>
    apiFetch<{ certificates: Array<{ id: string; traineeId: string; rankId: string; verificationCode: string; status: string; issuedAt: string }> }>(
      'GET',
      '/certificates/mine',
      undefined,
      token,
    ),
  listAllCertificates: (token?: string | null) =>
    apiFetch<{ certificates: Array<{ id: string; traineeId: string; rankId: string; verificationCode: string; status: string; issuedAt: string }> }>(
      'GET',
      '/certificates',
      undefined,
      token,
    ),
  issueCertificate: (payload: { traineeId: string; rankId: string }, token?: string | null) =>
    apiFetch<{ id: string; verificationCode: string; status: string; issuedAt: string }>(
      'POST',
      '/certificates',
      payload,
      token,
    ),
  revokeCertificate: (id: string, token?: string | null) =>
    apiFetch<{ id: string; status: string }>('POST', `/certificates/${id}/revoke`, undefined, token),
};
