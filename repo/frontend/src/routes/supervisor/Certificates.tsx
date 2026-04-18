import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

interface Rank {
  id: string;
  name: string;
}

export function SupervisorCertificates() {
  const { token } = useAuth();
  const [ranks, setRanks] = useState<Rank[]>([]);
  const [traineeId, setTraineeId] = useState('');
  const [rankId, setRankId] = useState('');
  const [lastCode, setLastCode] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;
    api.listRanks(token)
      .then((r) => setRanks(r.ranks))
      .catch((err) => setError((err as Error).message));
  }, [token]);

  const onIssue = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setError(null);
    setInfo(null);
    try {
      const res = await api.issueCertificate({ traineeId: traineeId.trim(), rankId }, token);
      setLastCode(res.verificationCode);
      setInfo(`Certificate issued (verification code: ${res.verificationCode}).`);
      setTraineeId('');
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Issue certificates</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Issue a certificate</h2>
        <p style={{ color: 'var(--muted)' }}>
          You can only issue certificates to trainees who have a prior booking in a session you own.
        </p>
        <form onSubmit={onIssue}>
          <label><span>Trainee user id</span>
            <input value={traineeId} onChange={(e) => setTraineeId(e.target.value)} required />
          </label>
          <label><span>Rank</span>
            <select value={rankId} onChange={(e) => setRankId(e.target.value)} required>
              <option value="">Select…</option>
              {ranks.map((r) => (<option key={r.id} value={r.id}>{r.name}</option>))}
            </select>
          </label>
          <button className="btn" type="submit" disabled={!traineeId || !rankId}>Issue</button>
        </form>
        {lastCode && (
          <p>Latest verification code: <strong style={{ fontFamily: 'monospace' }}>{lastCode}</strong></p>
        )}
      </div>
    </section>
  );
}
