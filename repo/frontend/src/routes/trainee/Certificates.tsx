import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { certificateIsDownloadable } from '../../lib/status';

interface CertRow {
  id: string;
  verificationCode: string;
  status: string;
  issuedAt: string;
}

export function TraineeCertificates() {
  const { token } = useAuth();
  const [certs, setCerts] = useState<CertRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const load = async () => {
    if (!token) return;
    try {
      const r = await api.listMyCertificates(token);
      setCerts(r.certificates);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const onDownload = async (id: string) => {
    if (!token) return;
    setError(null);
    setInfo(null);
    try {
      const res = await api.downloadCertificate(id, token);
      // Turn base64 into a Blob and trigger a browser download.
      const bin = atob(res.pdf);
      const bytes = new Uint8Array(bin.length);
      for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
      const blob = new Blob([bytes], { type: 'application/pdf' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `certificate-${id}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
      setInfo('Certificate downloaded.');
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>My certificates</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <table>
          <thead>
            <tr><th>Verification code</th><th>Status</th><th>Issued</th><th /></tr>
          </thead>
          <tbody>
            {certs.map((c) => (
              <tr key={c.id}>
                <td style={{ fontFamily: 'monospace' }}>{c.verificationCode}</td>
                <td>{c.status}</td>
                <td>{new Date(c.issuedAt).toLocaleString()}</td>
                <td>
                  {certificateIsDownloadable(c.status) && (
                    <button className="btn secondary" onClick={() => onDownload(c.id)}>Download PDF</button>
                  )}
                </td>
              </tr>
            ))}
            {certs.length === 0 && <tr><td colSpan={4} style={{ color: 'var(--muted)' }}>You have no certificates yet.</td></tr>}
          </tbody>
        </table>
      </div>
    </section>
  );
}
