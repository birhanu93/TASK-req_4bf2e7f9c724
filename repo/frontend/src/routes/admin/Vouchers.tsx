import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

interface VoucherRow {
  id: string;
  code: string;
  discountCents: number;
  minSpendCents: number;
  claimLimit: number;
  claimed: number;
  remaining: number;
  expiresAt: string;
  status: string;
}

const formatCents = (c: number) => `$${(c / 100).toFixed(2)}`;

const toDatetimeLocal = (iso: string) => iso.slice(0, 16);

export function AdminVouchers() {
  const { token } = useAuth();
  const [vouchers, setVouchers] = useState<VoucherRow[]>([]);
  const [code, setCode] = useState('');
  const [discount, setDiscount] = useState('25.00');
  const [minSpend, setMinSpend] = useState('150.00');
  const [limit, setLimit] = useState('100');
  // Default expiry: 30 days from now in local datetime format for the input.
  const [expiresAt, setExpiresAt] = useState(() =>
    toDatetimeLocal(new Date(Date.now() + 30 * 86400_000).toISOString()),
  );
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = async () => {
    if (!token) return;
    try {
      const r = await api.listAllVouchers(token);
      setVouchers(r.vouchers);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const onIssue = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setError(null);
    setInfo(null);
    setBusy(true);
    try {
      const discountCents = Math.round(Number(discount) * 100);
      const minSpendCents = Math.round(Number(minSpend) * 100);
      const claimLimit = Number(limit);
      if (!Number.isFinite(discountCents) || discountCents <= 0) throw new Error('Discount must be positive.');
      if (!Number.isFinite(minSpendCents) || minSpendCents < 0) throw new Error('Min spend must be zero or more.');
      if (!Number.isFinite(claimLimit) || claimLimit <= 0) throw new Error('Claim limit must be positive.');
      await api.issueVoucher(
        {
          code: code.trim(),
          discountCents,
          minSpendCents,
          claimLimit,
          expiresAt: new Date(expiresAt).toISOString(),
        },
        token,
      );
      setInfo(`Voucher ${code.trim()} issued.`);
      setCode('');
      await load();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const onVoid = async (id: string, c: string) => {
    if (!token) return;
    if (!window.confirm(`Void voucher ${c}? Outstanding claims remain honoured but no new claims will be possible.`)) return;
    setError(null);
    try {
      await api.voidVoucher(id, token);
      setInfo(`Voucher ${c} voided.`);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Voucher management</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Issue a voucher</h2>
        <form onSubmit={onIssue}>
          <label><span>Code</span><input value={code} onChange={(e) => setCode(e.target.value)} maxLength={32} required /></label>
          <label><span>Discount ($)</span><input type="number" step="0.01" min="0.01" value={discount} onChange={(e) => setDiscount(e.target.value)} required /></label>
          <label><span>Minimum spend ($)</span><input type="number" step="0.01" min="0" value={minSpend} onChange={(e) => setMinSpend(e.target.value)} required /></label>
          <label><span>Claim limit</span><input type="number" min="1" value={limit} onChange={(e) => setLimit(e.target.value)} required /></label>
          <label><span>Expires at</span><input type="datetime-local" value={expiresAt} onChange={(e) => setExpiresAt(e.target.value)} required /></label>
          <button className="btn" disabled={busy} type="submit">Issue</button>
        </form>
      </div>

      <div className="card">
        <h2>All vouchers</h2>
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Discount</th>
              <th>Min spend</th>
              <th>Claimed / limit</th>
              <th>Remaining</th>
              <th>Expires</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {vouchers.map((v) => (
              <tr key={v.id}>
                <td style={{ fontFamily: 'monospace' }}>{v.code}</td>
                <td>{formatCents(v.discountCents)}</td>
                <td>{formatCents(v.minSpendCents)}</td>
                <td>{v.claimed} / {v.claimLimit}</td>
                <td>{v.remaining}</td>
                <td>{new Date(v.expiresAt).toLocaleString()}</td>
                <td>{v.status}</td>
                <td>
                  {v.status === 'active' && (
                    <button className="btn danger" onClick={() => onVoid(v.id, v.code)}>Void</button>
                  )}
                </td>
              </tr>
            ))}
            {vouchers.length === 0 && <tr><td colSpan={8} style={{ color: 'var(--muted)' }}>No vouchers issued yet.</td></tr>}
          </tbody>
        </table>
      </div>
    </section>
  );
}
