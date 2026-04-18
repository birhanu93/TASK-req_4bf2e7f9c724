import { useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export function AdminOps() {
  const { token } = useAuth();
  const [log, setLog] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  const append = (line: string) => setLog((l) => [`${new Date().toLocaleTimeString()} ${line}`, ...l]);

  const onSnapshot = async () => {
    if (!token) return;
    try {
      const r = await api.runSnapshot(token);
      append(`Snapshot written to ${r.path}`);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onTier = async () => {
    if (!token) return;
    try {
      const r = await api.runTiering(token);
      append(`Tiering: moved=${r.movedCount}, kept=${r.keptCount}`);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onRotate = async () => {
    if (!token) return;
    try {
      const r = await api.rotateKey(token);
      append(`Rotated key → version ${r.newKeyVersion}`);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Operations</h1>
      {error && <div className="banner error">{error}</div>}
      <div className="card">
        <button className="btn" onClick={onSnapshot}>Run snapshot export</button>{' '}
        <button className="btn secondary" onClick={onTier}>Run storage tiering</button>{' '}
        <button className="btn secondary" onClick={onRotate}>Rotate encryption key</button>
      </div>
      <div className="card">
        <h2>Activity</h2>
        {log.length === 0 ? <p style={{ color: 'var(--muted)' }}>No operations yet.</p> : (
          <ul>{log.map((l, i) => <li key={i}><code>{l}</code></li>)}</ul>
        )}
      </div>
    </section>
  );
}
