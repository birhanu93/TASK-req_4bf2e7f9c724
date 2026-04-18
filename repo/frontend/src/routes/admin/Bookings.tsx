import { useEffect, useMemo, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

interface BookingRow {
  id: string;
  sessionId: string;
  traineeId: string;
  status: string;
  createdAt: string;
  cancellationReason: string | null;
  overrideActorId: string | null;
}

interface SessionRow {
  id: string;
  title: string;
  startsAt: string;
  endsAt: string;
  status: string;
  availability: number;
}

type OverrideMode = 'cancel' | 'reschedule';

export function AdminBookings() {
  const { token } = useAuth();
  const [bookings, setBookings] = useState<BookingRow[]>([]);
  const [sessions, setSessions] = useState<SessionRow[]>([]);
  const [traineeFilter, setTraineeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [overrideMode, setOverrideMode] = useState<OverrideMode | null>(null);
  const [overrideTarget, setOverrideTarget] = useState<BookingRow | null>(null);
  const [overrideReason, setOverrideReason] = useState('');
  const [rescheduleTarget, setRescheduleTarget] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const sessionsById = useMemo(() => {
    const m = new Map<string, SessionRow>();
    for (const s of sessions) m.set(s.id, s);
    return m;
  }, [sessions]);

  const load = async () => {
    if (!token) return;
    try {
      const [b, s] = await Promise.all([
        api.adminListBookings(
          { traineeId: traineeFilter || undefined, status: statusFilter || undefined },
          token,
        ),
        api.listSessions(token),
      ]);
      setBookings(b.bookings);
      setSessions(s.sessions);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const openOverride = (mode: OverrideMode, booking: BookingRow) => {
    setError(null);
    setInfo(null);
    setOverrideMode(mode);
    setOverrideTarget(booking);
    setOverrideReason('');
    setRescheduleTarget('');
  };

  const closeOverride = () => {
    setOverrideMode(null);
    setOverrideTarget(null);
    setOverrideReason('');
    setRescheduleTarget('');
  };

  const submitOverride = async () => {
    if (!token || !overrideMode || !overrideTarget) return;
    if (overrideReason.trim() === '') {
      setError('An override reason is required.');
      return;
    }
    try {
      if (overrideMode === 'cancel') {
        await api.cancelBooking(overrideTarget.id, overrideReason.trim(), true, token);
        setInfo(`Booking ${overrideTarget.id} cancelled under admin override.`);
      } else {
        if (!rescheduleTarget) {
          setError('Select a destination session.');
          return;
        }
        const idem = `admin-rs:${overrideTarget.id}:${rescheduleTarget}:${Date.now()}`;
        await api.rescheduleBooking(
          overrideTarget.id,
          rescheduleTarget,
          idem,
          true,
          token,
          overrideReason.trim(),
        );
        setInfo(`Booking ${overrideTarget.id} rescheduled under admin override.`);
      }
      closeOverride();
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Booking administration</h1>
      <p style={{ color: 'var(--muted)' }}>
        Overrides bypass the 12-hour policy and are written to the audit log with the reason you supply.
      </p>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Filter</h2>
        <form onSubmit={(e) => { e.preventDefault(); void load(); }}>
          <label><span>Trainee id</span><input value={traineeFilter} onChange={(e) => setTraineeFilter(e.target.value)} /></label>
          <label>
            <span>Status</span>
            <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
              <option value="">Any</option>
              <option value="reserved">Reserved</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
              <option value="expired">Expired</option>
            </select>
          </label>
          <button className="btn" type="submit">Search</button>
        </form>
      </div>

      <div className="card">
        <h2>Bookings</h2>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Session</th>
              <th>Trainee</th>
              <th>Status</th>
              <th>Created</th>
              <th>Reason / override</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {bookings.map((b) => {
              const s = sessionsById.get(b.sessionId);
              return (
                <tr key={b.id}>
                  <td style={{ fontFamily: 'monospace' }}>{b.id}</td>
                  <td>{s ? `${s.title} — ${new Date(s.startsAt).toLocaleString()}` : b.sessionId}</td>
                  <td>{b.traineeId}</td>
                  <td>{b.status}</td>
                  <td>{new Date(b.createdAt).toLocaleString()}</td>
                  <td>
                    {b.cancellationReason ?? '—'}
                    {b.overrideActorId && <em style={{ color: 'var(--muted)' }}> (override by {b.overrideActorId})</em>}
                  </td>
                  <td>
                    {(b.status === 'reserved' || b.status === 'confirmed') && (
                      <>
                        <button className="btn danger" onClick={() => openOverride('cancel', b)}>Admin cancel</button>{' '}
                        <button className="btn secondary" onClick={() => openOverride('reschedule', b)}>Admin reschedule</button>
                      </>
                    )}
                  </td>
                </tr>
              );
            })}
            {bookings.length === 0 && <tr><td colSpan={7} style={{ color: 'var(--muted)' }}>No bookings match the filter.</td></tr>}
          </tbody>
        </table>
      </div>

      {overrideMode && overrideTarget && (
        <div className="bulk-modal" role="dialog" aria-modal="true">
          <h3>Admin {overrideMode}</h3>
          <p>
            Target booking <strong style={{ fontFamily: 'monospace' }}>{overrideTarget.id}</strong> for trainee
            {' '}<strong>{overrideTarget.traineeId}</strong>. This action bypasses the 12-hour policy and is audited.
          </p>
          <label>
            <span>Reason (required)</span>
            <textarea
              rows={3}
              value={overrideReason}
              onChange={(e) => setOverrideReason(e.target.value)}
              placeholder="Why is policy being overridden?"
              required
            />
          </label>
          {overrideMode === 'reschedule' && (
            <label>
              <span>Move to session</span>
              <select
                value={rescheduleTarget}
                onChange={(e) => setRescheduleTarget(e.target.value)}
                required
              >
                <option value="">Select…</option>
                {sessions
                  .filter((s) => s.id !== overrideTarget.sessionId && s.status === 'open' && s.availability > 0)
                  .map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.title} — {new Date(s.startsAt).toLocaleString()} ({s.availability} free)
                    </option>
                  ))}
              </select>
            </label>
          )}
          <div className="actions-bar">
            <button className="btn secondary" onClick={closeOverride}>Cancel</button>
            <button
              className="btn"
              disabled={
                overrideReason.trim() === '' ||
                (overrideMode === 'reschedule' && !rescheduleTarget)
              }
              onClick={() => void submitOverride()}
            >
              Confirm
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
