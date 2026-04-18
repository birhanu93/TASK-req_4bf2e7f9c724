import { useEffect, useMemo, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

interface SessionRow {
  id: string;
  title: string;
  startsAt: string;
  endsAt: string;
  capacity: number;
  availability: number;
  status: string;
}

interface BookingRow {
  id: string;
  sessionId: string;
  status: string;
  createdAt: string;
}

interface RescheduleState {
  booking: BookingRow;
  currentSession: SessionRow | null;
  newSessionId: string;
  requestOverride: boolean;
}

const RESCHEDULE_BLOCK_HOURS = 12;

const hoursBetween = (future: string) => (new Date(future).getTime() - Date.now()) / 3_600_000;

export function TraineeBookings() {
  const { token, role } = useAuth();
  const [sessions, setSessions] = useState<SessionRow[]>([]);
  const [bookings, setBookings] = useState<BookingRow[]>([]);
  const [reschedule, setReschedule] = useState<RescheduleState | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const sessionsById = useMemo(() => {
    const map = new Map<string, SessionRow>();
    for (const s of sessions) map.set(s.id, s);
    return map;
  }, [sessions]);

  const load = async () => {
    if (!token) return;
    try {
      const [s, b] = await Promise.all([api.listSessions(token), api.listMyBookings(token)]);
      setSessions(s.sessions);
      setBookings(b.bookings);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const onBook = async (sessionId: string) => {
    if (!token) return;
    setError(null);
    try {
      const key = `${sessionId}:${Date.now()}:${Math.random().toString(36).slice(2, 8)}`;
      await api.book(sessionId, key, token);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onCancel = async (id: string) => {
    if (!token) return;
    const reason = window.prompt('Reason for cancellation?') ?? '';
    if (!reason) return;
    setError(null);
    try {
      await api.cancelBooking(id, reason, false, token);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onConfirm = async (id: string) => {
    if (!token) return;
    setError(null);
    try {
      await api.confirmBooking(id, token);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const openReschedule = (booking: BookingRow) => {
    setError(null);
    setInfo(null);
    setReschedule({
      booking,
      currentSession: sessionsById.get(booking.sessionId) ?? null,
      newSessionId: '',
      requestOverride: false,
    });
  };

  const closeReschedule = () => setReschedule(null);

  const onSubmitReschedule = async () => {
    if (!token || !reschedule || !reschedule.newSessionId) return;
    const idem = `rs:${reschedule.booking.id}:${reschedule.newSessionId}:${Date.now()}`;
    setError(null);
    try {
      await api.rescheduleBooking(
        reschedule.booking.id,
        reschedule.newSessionId,
        idem,
        reschedule.requestOverride,
        token,
      );
      setInfo('Booking rescheduled.');
      setReschedule(null);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const hoursUntil = reschedule?.currentSession ? hoursBetween(reschedule.currentSession.startsAt) : null;
  const insideWindow = hoursUntil !== null && hoursUntil < RESCHEDULE_BLOCK_HOURS;
  const isAdmin = role === 'admin';

  const otherSessions = reschedule
    ? sessions.filter((s) => s.id !== reschedule.booking.sessionId && s.availability > 0 && s.status === 'open')
    : [];

  return (
    <section>
      <h1>Bookings</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Upcoming sessions</h2>
        <table>
          <thead><tr><th>Title</th><th>Starts</th><th>Capacity</th><th>Free</th><th /></tr></thead>
          <tbody>
            {sessions.map((s) => (
              <tr key={s.id}>
                <td>{s.title}</td>
                <td>{new Date(s.startsAt).toLocaleString()}</td>
                <td>{s.capacity}</td>
                <td>{s.availability}</td>
                <td>{s.availability > 0 && <button className="btn" onClick={() => onBook(s.id)}>Reserve</button>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card">
        <h2>Your bookings</h2>
        <table>
          <thead><tr><th>ID</th><th>Session</th><th>Starts</th><th>Status</th><th /></tr></thead>
          <tbody>
            {bookings.map((b) => {
              const s = sessionsById.get(b.sessionId);
              return (
                <tr key={b.id}>
                  <td>{b.id}</td>
                  <td>{s?.title ?? b.sessionId}</td>
                  <td>{s ? new Date(s.startsAt).toLocaleString() : '—'}</td>
                  <td>{b.status}</td>
                  <td>
                    {b.status === 'reserved' && <button className="btn secondary" onClick={() => onConfirm(b.id)}>Confirm</button>}{' '}
                    {(b.status === 'reserved' || b.status === 'confirmed') && (
                      <>
                        <button className="btn secondary" onClick={() => openReschedule(b)}>Reschedule</button>{' '}
                        <button className="btn danger" onClick={() => onCancel(b.id)}>Cancel</button>
                      </>
                    )}
                  </td>
                </tr>
              );
            })}
            {bookings.length === 0 && <tr><td colSpan={5} style={{ color: 'var(--muted)' }}>No bookings yet.</td></tr>}
          </tbody>
        </table>
      </div>

      {reschedule && (
        <div className="bulk-modal" role="dialog" aria-modal="true">
          <h3>Reschedule booking</h3>
          {reschedule.currentSession ? (
            <p>
              Current session: <strong>{reschedule.currentSession.title}</strong>{' '}
              — starts {new Date(reschedule.currentSession.startsAt).toLocaleString()}{' '}
              ({hoursUntil !== null ? `${hoursUntil.toFixed(1)}h from now` : 'time unknown'}).
            </p>
          ) : (
            <p>Session details could not be loaded for this booking.</p>
          )}

          {insideWindow && (
            <div className="banner error" style={{ marginBottom: 12 }}>
              This session starts in under {RESCHEDULE_BLOCK_HOURS} hours. Reschedules inside this
              window are blocked by policy. An administrator can override this rule on your
              behalf; as a trainee your reschedule will be rejected with a 409 until they do.
            </div>
          )}

          <label>
            <span>Pick a new session</span>
            <select
              value={reschedule.newSessionId}
              onChange={(e) => setReschedule({ ...reschedule, newSessionId: e.target.value })}
              required
            >
              <option value="">Select…</option>
              {otherSessions.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.title} — {new Date(s.startsAt).toLocaleString()} ({s.availability} free)
                </option>
              ))}
            </select>
          </label>

          {insideWindow && isAdmin && (
            <label style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <input
                type="checkbox"
                checked={reschedule.requestOverride}
                onChange={(e) => setReschedule({ ...reschedule, requestOverride: e.target.checked })}
              />
              <span>Apply admin override for the 12-hour window (audited)</span>
            </label>
          )}

          <div className="actions-bar">
            <button className="btn secondary" onClick={closeReschedule}>Cancel</button>
            <button
              className="btn"
              disabled={!reschedule.newSessionId || (insideWindow && !isAdmin)}
              onClick={() => void onSubmitReschedule()}
            >
              Reschedule
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
