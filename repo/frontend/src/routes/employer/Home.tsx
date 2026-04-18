import { useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export function EmployerHome() {
  const { token } = useAuth();
  const [code, setCode] = useState('');
  const [result, setResult] = useState<{ id: string; valid: boolean; status: string; issuedAt: string } | null>(null);
  const [error, setError] = useState<string | null>(null);

  const onVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setError(null);
    try {
      const r = await api.verifyCertificate(code, token);
      setResult(r);
    } catch (err) {
      setResult(null);
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Verify credential</h1>
      {error && <div className="banner error">{error}</div>}
      <div className="card">
        <form onSubmit={onVerify}>
          <label><span>Verification code</span><input value={code} onChange={(e) => setCode(e.target.value)} required /></label>
          <button className="btn" type="submit">Verify</button>
        </form>
      </div>
      {result && (
        <div className="card">
          <h2>Result</h2>
          <p>Certificate ID: <code>{result.id}</code></p>
          <p>Status: <strong>{result.status}</strong> — {result.valid ? 'VALID' : 'INVALID'}</p>
          <p>Issued: {new Date(result.issuedAt).toLocaleString()}</p>
        </div>
      )}
    </section>
  );
}
