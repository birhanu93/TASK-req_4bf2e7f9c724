import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export function TraineeHome() {
  const { token, userId } = useAuth();
  const [progress, setProgress] = useState<{ reps: number; seconds: number; assessments: number; currentRank: string | null; nextRank: string | null } | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token || !userId) return;
    api.progress(userId, token)
      .then(setProgress)
      .catch((err) => setError(err.message ?? 'failed'));
  }, [token, userId]);

  return (
    <section>
      <h1>Your training</h1>
      {error && <div className="banner error">{error}</div>}
      <div className="card">
        <h2>Progress</h2>
        {progress ? (
          <table>
            <tbody>
              <tr><th>Total reps</th><td>{progress.reps}</td></tr>
              <tr><th>Total seconds</th><td>{progress.seconds}</td></tr>
              <tr><th>Assessments</th><td>{progress.assessments}</td></tr>
              <tr><th>Current rank</th><td>{progress.currentRank ?? '—'}</td></tr>
              <tr><th>Next rank</th><td>{progress.nextRank ?? '—'}</td></tr>
            </tbody>
          </table>
        ) : (
          <p>Loading…</p>
        )}
      </div>
    </section>
  );
}
