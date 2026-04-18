import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export function SupervisorLeaves() {
  const { token } = useAuth();
  const [leaves, setLeaves] = useState<Array<{ id: string; startsAt: string; endsAt: string; rule: string; reason: string | null }>>([]);
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [rule, setRule] = useState('one_off');
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    if (!token) return;
    try {
      const res = await api.listLeaves(token);
      setLeaves(res.leaves);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const onCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setError(null);
    try {
      await api.addLeave({ startsAt, endsAt, rule, reason }, token);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Leaves</h1>
      {error && <div className="banner error">{error}</div>}
      <div className="card">
        <h2>Schedule leave</h2>
        <form onSubmit={onCreate}>
          <label><span>Starts</span><input type="datetime-local" value={startsAt} onChange={(e) => setStartsAt(e.target.value)} required /></label>
          <label><span>Ends</span><input type="datetime-local" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} required /></label>
          <label>
            <span>Recurrence</span>
            <select value={rule} onChange={(e) => setRule(e.target.value)}>
              <option value="one_off">One-off</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </label>
          <label><span>Reason (optional)</span><input value={reason} onChange={(e) => setReason(e.target.value)} /></label>
          <button className="btn" type="submit">Schedule</button>
        </form>
      </div>
      <div className="card">
        <h2>Scheduled leaves</h2>
        <table>
          <thead><tr><th>Starts</th><th>Ends</th><th>Rule</th><th>Reason</th></tr></thead>
          <tbody>
            {leaves.map((l) => (
              <tr key={l.id}>
                <td>{new Date(l.startsAt).toLocaleString()}</td>
                <td>{new Date(l.endsAt).toLocaleString()}</td>
                <td>{l.rule}</td>
                <td>{l.reason ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
