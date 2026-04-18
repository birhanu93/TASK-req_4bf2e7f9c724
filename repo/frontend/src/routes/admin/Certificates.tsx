import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { certificateIsRevokable } from '../../lib/status';

interface CertRow {
  id: string;
  traineeId: string;
  rankId: string;
  verificationCode: string;
  status: string;
  issuedAt: string;
}

export function AdminCertificates() {
  const { token } = useAuth();
  const [certs, setCerts] = useState<CertRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const load = async () => {
    if (!token) return;
    try {
      const r = await api.listAllCertificates(token);
      setCerts(r.certificates);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const onRevoke = async (id: string) => {
    if (!token) return;
    if (!window.confirm('Revoke this certificate? Verification will mark it invalid going forward.')) return;
    setError(null);
    setInfo(null);
    try {
      await api.revokeCertificate(id, token);
      setInfo('Certificate revoked.');
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Certificates</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}
      <div className="card">
        <table>
          <thead>
            <tr>
              <th>Verification code</th>
              <th>Trainee</th>
              <th>Rank</th>
              <th>Status</th>
              <th>Issued</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {certs.map((c) => (
              <tr key={c.id}>
                <td style={{ fontFamily: 'monospace' }}>{c.verificationCode}</td>
                <td>{c.traineeId}</td>
                <td>{c.rankId}</td>
                <td>{c.status}</td>
                <td>{new Date(c.issuedAt).toLocaleString()}</td>
                <td>
                  {certificateIsRevokable(c.status) && (
                    <button className="btn danger" onClick={() => onRevoke(c.id)}>Revoke</button>
                  )}
                </td>
              </tr>
            ))}
            {certs.length === 0 && <tr><td colSpan={6} style={{ color: 'var(--muted)' }}>No certificates.</td></tr>}
          </tbody>
        </table>
      </div>
    </section>
  );
}
