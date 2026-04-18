import { useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

interface VoucherDescription {
  id: string;
  code: string;
  discountCents: number;
  minSpendCents: number;
  remaining: number;
  status: string;
}

interface ClaimView {
  id: string;
  status: string;
  code: string;
  voucher: VoucherDescription;
}

const formatCents = (c: number) => `$${(c / 100).toFixed(2)}`;

export function TraineeVouchers() {
  const { token } = useAuth();
  const [code, setCode] = useState('');
  const [preview, setPreview] = useState<VoucherDescription | null>(null);
  const [claim, setClaim] = useState<ClaimView | null>(null);
  const [orderInput, setOrderInput] = useState('50.00');
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const onLookup = async () => {
    if (!token || !code) return;
    setError(null);
    setInfo(null);
    try {
      const v = await api.describeVoucher(code, token);
      setPreview(v);
    } catch (err) {
      setPreview(null);
      setError((err as Error).message);
    }
  };

  const onClaim = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token || !preview) return;
    setError(null);
    setInfo(null);
    try {
      const idem = `${preview.code}:${Date.now()}:${Math.random().toString(36).slice(2, 8)}`;
      const c = await api.claimVoucher(preview.code, idem, token);
      setClaim({ id: c.id, status: c.status, code: preview.code, voucher: preview });
      setInfo('Claim locked — review applicability rules and redeem at checkout.');
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onRedeem = async () => {
    if (!token || !claim) return;
    const cents = Math.round(Number(orderInput) * 100);
    if (!Number.isFinite(cents) || cents <= 0) {
      setError('Enter a valid order amount.');
      return;
    }
    if (cents < claim.voucher.minSpendCents) {
      setError(`Order below the minimum spend of ${formatCents(claim.voucher.minSpendCents)}.`);
      return;
    }
    setError(null);
    try {
      // Redemption idempotency key: stable across retries of this particular
      // redeem attempt, unique across separate attempts. The backend replays
      // the original outcome if the same key is submitted twice.
      const redemptionKey = `rk:${claim.id}:${cents}:${Date.now()}`;
      const r = await api.redeemVoucher(claim.id, cents, redemptionKey, token);
      const suffix = r.replayed ? ' (idempotent replay)' : '';
      setInfo(`Redeemed. Discount applied: ${formatCents(r.discountCents)}${suffix}`);
      setClaim({ ...claim, status: 'redeemed' });
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Vouchers</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Look up a voucher</h2>
        <form onSubmit={(e) => { e.preventDefault(); void onLookup(); }}>
          <label><span>Voucher code</span><input value={code} onChange={(e) => setCode(e.target.value)} required /></label>
          <button className="btn secondary" type="submit">Preview</button>
        </form>
      </div>

      {preview && (
        <div className="card">
          <h2>Voucher rules: {preview.code}</h2>
          <table>
            <tbody>
              <tr><th>Discount</th><td>{formatCents(preview.discountCents)}</td></tr>
              <tr><th>Minimum spend</th><td>{formatCents(preview.minSpendCents)}</td></tr>
              <tr><th>Status</th><td>{preview.status}</td></tr>
              <tr><th>Remaining claims</th><td>{preview.remaining}</td></tr>
            </tbody>
          </table>
          <p style={{ color: 'var(--muted)' }}>
            Applicability: this voucher applies to orders of {formatCents(preview.minSpendCents)} or more.
            The discount will be deducted from the order total at redemption.
          </p>
          <form onSubmit={onClaim}>
            <button className="btn" type="submit" disabled={preview.status !== 'active' || preview.remaining <= 0}>Claim</button>
          </form>
        </div>
      )}

      {claim && (
        <div className="card">
          <h2>Your claim</h2>
          <p>Code: <strong>{claim.code}</strong></p>
          <p>Status: <strong>{claim.status}</strong></p>
          <p style={{ color: 'var(--muted)' }}>
            Redemption requires an order total of at least {formatCents(claim.voucher.minSpendCents)}.
            Discount of {formatCents(claim.voucher.discountCents)} applied at checkout.
          </p>
          {claim.status === 'locked' && (
            <form onSubmit={(e) => { e.preventDefault(); void onRedeem(); }}>
              <label><span>Order total ($)</span>
                <input type="number" step="0.01" min="0" value={orderInput} onChange={(e) => setOrderInput(e.target.value)} required />
              </label>
              <button className="btn" type="submit">Redeem</button>
            </form>
          )}
        </div>
      )}
    </section>
  );
}
