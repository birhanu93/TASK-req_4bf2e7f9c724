import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

interface ModerationRow {
  id: string;
  authorId: string;
  kind: string;
  status: string;
  submittedAt: string;
}

type BulkMode = 'approve' | 'reject';

export function AdminModeration() {
  const { token } = useAuth();
  const [items, setItems] = useState<ModerationRow[]>([]);
  const [selected, setSelected] = useState<Record<string, boolean>>({});
  const [bulkMode, setBulkMode] = useState<BulkMode | null>(null);
  const [bulkReason, setBulkReason] = useState('');
  const [bulkScore, setBulkScore] = useState(80);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const load = async () => {
    if (!token) return;
    try {
      const r = await api.listPendingModeration(token);
      setItems(r.items);
      setSelected({});
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void load(); }, [token]);

  const toggle = (id: string) => setSelected((s) => ({ ...s, [id]: !s[id] }));
  const toggleAll = () => {
    if (items.every((i) => selected[i.id])) {
      setSelected({});
    } else {
      const next: Record<string, boolean> = {};
      for (const i of items) next[i.id] = true;
      setSelected(next);
    }
  };
  const selectedIds = () => items.filter((i) => selected[i.id]).map((i) => i.id);

  const onBulk = (mode: BulkMode) => {
    if (selectedIds().length === 0) {
      setError('Select at least one item.');
      return;
    }
    setError(null);
    setBulkReason('');
    setBulkMode(mode);
  };

  const confirmBulk = async () => {
    if (!token || !bulkMode) return;
    const ids = selectedIds();
    if (bulkMode === 'reject' && bulkReason.trim() === '') {
      setError('Rejection reason is required for bulk reject.');
      return;
    }
    setError(null);
    setInfo(null);
    try {
      if (bulkMode === 'approve') {
        const r = await api.bulkApproveModeration(ids, bulkScore, token);
        setInfo(`Approved ${r.approved.length}, failed ${r.failed.length}.`);
      } else {
        const r = await api.bulkRejectModeration(ids, bulkReason.trim(), token);
        setInfo(`Rejected ${r.rejected.length}, failed ${r.failed.length}.`);
      }
      setBulkMode(null);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onApproveOne = async (id: string) => {
    if (!token) return;
    const raw = window.prompt('Quality score (0-100)', '80');
    if (raw === null) return;
    const score = Number(raw);
    if (Number.isNaN(score)) return setError('Invalid score');
    try {
      await api.approveModeration(id, score, undefined, token);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onRejectOne = async (id: string) => {
    if (!token) return;
    const reason = window.prompt('Reason for rejection?') ?? '';
    if (!reason) return;
    try {
      await api.rejectModeration(id, reason, token);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const allSelected = items.length > 0 && items.every((i) => selected[i.id]);
  const count = selectedIds().length;

  return (
    <section>
      <h1>Moderation queue</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <div className="actions-bar">
          <span>{count} of {items.length} selected</span>
          <button className="btn" disabled={count === 0} onClick={() => onBulk('approve')}>Bulk approve</button>
          <button className="btn danger" disabled={count === 0} onClick={() => onBulk('reject')}>Bulk reject</button>
        </div>

        {bulkMode && (
          <div className="bulk-modal" role="dialog" aria-modal="true">
            <h3>Confirm bulk {bulkMode}</h3>
            <p>Affecting {count} item{count === 1 ? '' : 's'}.</p>
            {bulkMode === 'approve' ? (
              <label>
                <span>Quality score (0-100)</span>
                <input type="number" min={0} max={100} value={bulkScore} onChange={(e) => setBulkScore(Number(e.target.value))} />
              </label>
            ) : (
              <label>
                <span>Reason (required)</span>
                <textarea value={bulkReason} onChange={(e) => setBulkReason(e.target.value)} rows={3} required />
              </label>
            )}
            <div className="actions-bar">
              <button className="btn secondary" onClick={() => setBulkMode(null)}>Cancel</button>
              <button className="btn" onClick={confirmBulk}>Confirm</button>
            </div>
          </div>
        )}

        <table>
          <thead>
            <tr>
              <th><input type="checkbox" checked={allSelected} onChange={toggleAll} /></th>
              <th>Kind</th>
              <th>Author</th>
              <th>Submitted</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {items.map((i) => (
              <tr key={i.id}>
                <td><input type="checkbox" checked={!!selected[i.id]} onChange={() => toggle(i.id)} /></td>
                <td>{i.kind}</td>
                <td>{i.authorId}</td>
                <td>{new Date(i.submittedAt).toLocaleString()}</td>
                <td>
                  <button className="btn" onClick={() => onApproveOne(i.id)}>Approve</button>{' '}
                  <button className="btn danger" onClick={() => onRejectOne(i.id)}>Reject</button>
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={5} style={{ color: 'var(--muted)' }}>Queue empty.</td></tr>}
          </tbody>
        </table>
      </div>
    </section>
  );
}
