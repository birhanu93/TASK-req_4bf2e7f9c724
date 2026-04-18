import { useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export function SupervisorAssessments() {
  const { token } = useAuth();
  const [templateId, setTemplateId] = useState('');
  const [traineeId, setTraineeId] = useState('');
  const [reps, setReps] = useState(0);
  const [seconds, setSeconds] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setError(null);
    setInfo(null);
    try {
      await api.recordAssessment({ templateId, traineeId, reps, seconds }, token);
      setInfo('Assessment recorded.');
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <section>
      <h1>Record assessment</h1>
      {error && <div className="banner error">{error}</div>}
      {info && <div className="banner ok">{info}</div>}
      <div className="card">
        <form onSubmit={onSubmit}>
          <label><span>Template ID</span><input value={templateId} onChange={(e) => setTemplateId(e.target.value)} required /></label>
          <label><span>Trainee ID</span><input value={traineeId} onChange={(e) => setTraineeId(e.target.value)} required /></label>
          <label><span>Reps</span><input type="number" value={reps} onChange={(e) => setReps(Number(e.target.value))} /></label>
          <label><span>Seconds</span><input type="number" value={seconds} onChange={(e) => setSeconds(Number(e.target.value))} /></label>
          <button className="btn" type="submit">Record</button>
        </form>
      </div>
    </section>
  );
}
