import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export function SupervisorSessions() {
  const { token } = useAuth();
  const [sessions, setSessions] = useState<Array<{ id: string; title: string; startsAt: string; endsAt: string; status: string }>>([]);
  const [resources, setResources] = useState<Array<{ id: string; name: string; kind: string; status: string }>>([]);
  const [title, setTitle] = useState('Morning session');
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [capacity, setCapacity] = useState(10);
  const [selectedResources, setSelectedResources] = useState<Record<string, boolean>>({});
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const load = async () => {
    if (!token) return;
    try {
      const [s, r] = await Promise.all([api.listSessions(token), api.listResources(token)]);
      setSessions(s.sessions);
      setResources(r.resources.filter((x) => x.status === 'active'));
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const toggleResource = (id: string) =>
    setSelectedResources((s) => ({ ...s, [id]: !s[id] }));

  const onCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setError(null);
    setInfo(null);
    try {
      const resourceIds = Object.entries(selectedResources)
        .filter(([, v]) => v)
        .map(([k]) => k);
      await api.createSession(
        { title, startsAt, endsAt, capacity, resourceIds },
        token,
      );
      setInfo(
        resourceIds.length > 0
          ? `Session created and ${resourceIds.length} resource(s) reserved.`
          : 'Session created.',
      );
      setSelectedResources({});
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onClose = async (id: string) => {
    if (!token) return;
    try {
      await api.closeSession(id, token);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Sessions</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Create session</h2>
        <form onSubmit={onCreate}>
          <label><span>Title</span><input value={title} onChange={(e) => setTitle(e.target.value)} required /></label>
          <label><span>Starts at</span><input type="datetime-local" value={startsAt} onChange={(e) => setStartsAt(e.target.value)} required /></label>
          <label><span>Ends at</span><input type="datetime-local" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} required /></label>
          <label><span>Capacity</span><input type="number" min={1} value={capacity} onChange={(e) => setCapacity(Number(e.target.value))} required /></label>
          {resources.length > 0 && (
            <div>
              <label><span>Resources to reserve</span></label>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                {resources.map((r) => (
                  <label key={r.id} style={{ display: 'flex', alignItems: 'center', gap: 8, margin: 0 }}>
                    <input
                      type="checkbox"
                      checked={!!selectedResources[r.id]}
                      onChange={() => toggleResource(r.id)}
                    />
                    <span style={{ margin: 0 }}>{r.name} <em style={{ color: 'var(--muted)' }}>({r.kind})</em></span>
                  </label>
                ))}
              </div>
            </div>
          )}
          <button className="btn" type="submit">Create</button>
        </form>
      </div>

      <div className="card">
        <h2>All sessions</h2>
        <table>
          <thead><tr><th>Title</th><th>Starts</th><th>Ends</th><th>Status</th><th /></tr></thead>
          <tbody>
            {sessions.map((s) => (
              <tr key={s.id}>
                <td>{s.title}</td>
                <td>{new Date(s.startsAt).toLocaleString()}</td>
                <td>{new Date(s.endsAt).toLocaleString()}</td>
                <td>{s.status}</td>
                <td>{s.status === 'open' && <button className="btn danger" onClick={() => onClose(s.id)}>Close</button>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
