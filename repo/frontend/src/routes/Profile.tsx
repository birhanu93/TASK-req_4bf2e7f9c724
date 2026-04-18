import { useEffect, useState } from 'react';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

export function ProfilePage() {
  const { token } = useAuth();
  const [profile, setProfile] = useState<Record<string, unknown>>({});
  const [raw, setRaw] = useState('{}');
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;
    api.getProfile(token)
      .then((r) => {
        setProfile(r.profile ?? {});
        setRaw(JSON.stringify(r.profile ?? {}, null, 2));
      })
      .catch((err) => setError(err.message ?? 'load failed'));
  }, [token]);

  const onSave = async () => {
    if (!token) return;
    setError(null);
    try {
      const parsed = JSON.parse(raw);
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        throw new Error('Profile must be a JSON object');
      }
      await api.updateProfile(parsed, token);
      setProfile(parsed);
      setStatus('Saved');
    } catch (e) {
      setError((e as Error).message);
    }
  };

  return (
    <section>
      <h1>Profile</h1>
      {error && <div className="banner error">{error}</div>}
      {status && <div className="banner ok">{status}</div>}
      <div className="card">
        <h2>Encrypted profile data</h2>
        <p style={{ color: 'var(--muted)', fontSize: 13 }}>
          This is stored encrypted at rest on the server. Edit as JSON.
        </p>
        <textarea rows={12} value={raw} onChange={(e) => setRaw(e.target.value)} />
        <div style={{ marginTop: 12 }}>
          <button className="btn" onClick={onSave}>Save</button>
        </div>
      </div>
      <div className="card">
        <h2>Preview</h2>
        <pre>{JSON.stringify(profile, null, 2)}</pre>
      </div>
    </section>
  );
}
