import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

interface ResourceRow {
  id: string;
  name: string;
  kind: string;
  status: string;
  createdAt: string;
}

interface ReservationRow {
  id: string;
  resourceId: string;
  sessionId: string | null;
  startsAt: string;
  endsAt: string;
  reservedByUserId: string;
}

export function AdminResources() {
  const { token } = useAuth();
  const [resources, setResources] = useState<ResourceRow[]>([]);
  const [selected, setSelected] = useState<string | null>(null);
  const [reservations, setReservations] = useState<ReservationRow[]>([]);
  const [name, setName] = useState('');
  const [kind, setKind] = useState('room');
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const load = async () => {
    if (!token) return;
    try {
      const r = await api.listResources(token);
      setResources(r.resources);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const onCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setError(null);
    setInfo(null);
    try {
      await api.createResource({ name: name.trim(), kind: kind.trim() }, token);
      setInfo(`Resource ${name.trim()} created.`);
      setName('');
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onRetire = async (id: string) => {
    if (!token) return;
    if (!window.confirm('Retire this resource? Active reservations remain honoured; new reservations will be blocked.')) return;
    setError(null);
    try {
      await api.retireResource(id, token);
      setInfo('Resource retired.');
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onShowReservations = async (id: string) => {
    if (!token) return;
    setSelected(id);
    try {
      const r = await api.listResourceReservations(id, token);
      setReservations(r.reservations);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Resources</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Create resource</h2>
        <form onSubmit={onCreate}>
          <label><span>Name</span><input value={name} onChange={(e) => setName(e.target.value)} required /></label>
          <label>
            <span>Kind</span>
            <select value={kind} onChange={(e) => setKind(e.target.value)}>
              <option value="room">Room</option>
              <option value="equipment">Equipment</option>
              <option value="vehicle">Vehicle</option>
            </select>
          </label>
          <button className="btn" type="submit">Create</button>
        </form>
      </div>

      <div className="card">
        <h2>All resources</h2>
        <table>
          <thead><tr><th>Name</th><th>Kind</th><th>Status</th><th /></tr></thead>
          <tbody>
            {resources.map((r) => (
              <tr key={r.id}>
                <td>{r.name}</td>
                <td>{r.kind}</td>
                <td>{r.status}</td>
                <td>
                  <button className="btn secondary" onClick={() => onShowReservations(r.id)}>Reservations</button>{' '}
                  {r.status === 'active' && <button className="btn danger" onClick={() => onRetire(r.id)}>Retire</button>}
                </td>
              </tr>
            ))}
            {resources.length === 0 && <tr><td colSpan={4} style={{ color: 'var(--muted)' }}>No resources yet.</td></tr>}
          </tbody>
        </table>
      </div>

      {selected && (
        <div className="card">
          <h2>Reservations</h2>
          <table>
            <thead><tr><th>Starts</th><th>Ends</th><th>Session</th><th>Reserved by</th></tr></thead>
            <tbody>
              {reservations.map((r) => (
                <tr key={r.id}>
                  <td>{new Date(r.startsAt).toLocaleString()}</td>
                  <td>{new Date(r.endsAt).toLocaleString()}</td>
                  <td>{r.sessionId ?? '—'}</td>
                  <td>{r.reservedByUserId}</td>
                </tr>
              ))}
              {reservations.length === 0 && <tr><td colSpan={4} style={{ color: 'var(--muted)' }}>None.</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
