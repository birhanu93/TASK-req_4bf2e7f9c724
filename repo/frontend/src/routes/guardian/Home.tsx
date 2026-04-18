import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { DeviceStatus, deviceIsRemoteLogoutEligible } from '../../lib/status';

interface ChildLink {
  id: string;
  childId: string;
  linkedAt: string;
}

interface DeviceRow {
  id: string;
  deviceName: string;
  fingerprint: string;
  status: string;
  approvedAt: string;
}

interface ProgressView {
  reps: number;
  seconds: number;
  assessments: number;
  currentRank: string | null;
  nextRank: string | null;
}

export function GuardianHome() {
  const { token } = useAuth();
  const [children, setChildren] = useState<ChildLink[]>([]);
  const [selected, setSelected] = useState<string | null>(null);
  const [progress, setProgress] = useState<ProgressView | null>(null);
  const [devices, setDevices] = useState<DeviceRow[]>([]);
  const [linkChildId, setLinkChildId] = useState('');
  const [deviceName, setDeviceName] = useState('');
  const [fingerprint, setFingerprint] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const refreshChildren = async () => {
    if (!token) return;
    try {
      const r = await api.children(token);
      setChildren(r.children);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  useEffect(() => { void refreshChildren(); }, [token]);

  const loadChild = async (childId: string) => {
    if (!token) return;
    setSelected(childId);
    setError(null);
    try {
      const [p, d] = await Promise.all([
        api.childProgress(childId, token),
        api.listChildDevices(childId, token),
      ]);
      setProgress(p);
      setDevices(d.devices);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onLink = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token || !linkChildId) return;
    setError(null);
    setInfo(null);
    try {
      await api.linkChild(linkChildId, token);
      setLinkChildId('');
      setInfo('Child linked.');
      await refreshChildren();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onApproveDevice = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token || !selected || !deviceName || !fingerprint) return;
    setError(null);
    setInfo(null);
    try {
      await api.approveChildDevice(selected, deviceName, fingerprint, token);
      setDeviceName('');
      setFingerprint('');
      setInfo('Device approved.');
      await loadChild(selected);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  const onRemoteLogout = async (deviceId: string) => {
    if (!token || !selected) return;
    if (!window.confirm('Revoke this device session?')) return;
    setError(null);
    try {
      await api.remoteLogoutDevice(deviceId, token);
      setInfo('Device session revoked.');
      await loadChild(selected);
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Guardian portal</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}

      <div className="card">
        <h2>Link a child</h2>
        <form onSubmit={onLink}>
          <label>
            <span>Child user id</span>
            <input value={linkChildId} onChange={(e) => setLinkChildId(e.target.value)} required />
          </label>
          <button className="btn" type="submit">Link</button>
        </form>
      </div>

      <div className="card">
        <h2>Your children</h2>
        <table>
          <thead><tr><th>Child ID</th><th>Linked</th><th /></tr></thead>
          <tbody>
            {children.map((c) => (
              <tr key={c.id}>
                <td>{c.childId}</td>
                <td>{new Date(c.linkedAt).toLocaleString()}</td>
                <td><button className="btn secondary" onClick={() => loadChild(c.childId)}>Manage</button></td>
              </tr>
            ))}
            {children.length === 0 && <tr><td colSpan={3} style={{ color: 'var(--muted)' }}>No children linked yet.</td></tr>}
          </tbody>
        </table>
      </div>

      {selected && progress && (
        <div className="card">
          <h2>Progress for {selected}</h2>
          <table>
            <tbody>
              <tr><th>Reps</th><td>{progress.reps}</td></tr>
              <tr><th>Seconds</th><td>{progress.seconds}</td></tr>
              <tr><th>Assessments</th><td>{progress.assessments}</td></tr>
              <tr><th>Current rank</th><td>{progress.currentRank ?? '—'}</td></tr>
              <tr><th>Next rank</th><td>{progress.nextRank ?? '—'}</td></tr>
            </tbody>
          </table>
        </div>
      )}

      {selected && (
        <div className="card">
          <h2>Devices for {selected}</h2>
          <form onSubmit={onApproveDevice}>
            <label><span>Device name</span><input value={deviceName} onChange={(e) => setDeviceName(e.target.value)} required /></label>
            <label><span>Fingerprint</span><input value={fingerprint} onChange={(e) => setFingerprint(e.target.value)} required /></label>
            <button className="btn" type="submit">Approve device</button>
          </form>
          <table>
            <thead><tr><th>Name</th><th>Fingerprint</th><th>Status</th><th>Approved</th><th /></tr></thead>
            <tbody>
              {devices.map((d) => (
                <tr key={d.id}>
                  <td>{d.deviceName}</td>
                  <td style={{ fontFamily: 'monospace' }}>{d.fingerprint.slice(0, 16)}…</td>
                  <td>{d.status}</td>
                  <td>{new Date(d.approvedAt).toLocaleString()}</td>
                  <td>
                    {deviceIsRemoteLogoutEligible(d.status) && (
                      <button className="btn danger" onClick={() => onRemoteLogout(d.id)}>Remote logout</button>
                    )}
                    {d.status === DeviceStatus.REVOKED && <span style={{ color: 'var(--muted)' }}>revoked</span>}
                  </td>
                </tr>
              ))}
              {devices.length === 0 && <tr><td colSpan={5} style={{ color: 'var(--muted)' }}>No devices approved.</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
