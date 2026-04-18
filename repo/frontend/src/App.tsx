import { Navigate, Route, Routes } from 'react-router-dom';
import { LoginPage } from './routes/Login';
import { Shell } from './components/Shell';
import { useAuth } from './lib/auth';
import type { Role } from './lib/api';
import { TraineeHome } from './routes/trainee/Home';
import { TraineeBookings } from './routes/trainee/Bookings';
import { TraineeVouchers } from './routes/trainee/Vouchers';
import { TraineeCertificates } from './routes/trainee/Certificates';
import { SupervisorHome } from './routes/supervisor/Home';
import { SupervisorSessions } from './routes/supervisor/Sessions';
import { SupervisorLeaves } from './routes/supervisor/Leaves';
import { SupervisorAssessments } from './routes/supervisor/Assessments';
import { SupervisorCertificates } from './routes/supervisor/Certificates';
import { GuardianHome } from './routes/guardian/Home';
import { EmployerHome } from './routes/employer/Home';
import { AdminHome } from './routes/admin/Home';
import { AdminModeration } from './routes/admin/Moderation';
import { AdminOps } from './routes/admin/Ops';
import { AdminVouchers } from './routes/admin/Vouchers';
import { AdminResources } from './routes/admin/Resources';
import { AdminCertificates } from './routes/admin/Certificates';
import { AdminBookings } from './routes/admin/Bookings';
import { ProfilePage } from './routes/Profile';

interface GuardProps {
  role: Role;
  children: React.ReactNode;
}

function RoleGuard({ role, children }: GuardProps) {
  const { token, role: active, bootstrapping } = useAuth();
  if (bootstrapping) {
    return null;
  }
  if (!token) {
    return <Navigate to="/login" replace />;
  }
  if (active !== role) {
    return <Navigate to={`/${active ?? 'login'}`} replace />;
  }
  return <>{children}</>;
}

export function App() {
  const { token, role, bootstrapping } = useAuth();
  if (bootstrapping) {
    return null;
  }
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route element={<Shell />}>
        <Route path="/profile" element={token ? <ProfilePage /> : <Navigate to="/login" replace />} />

        <Route path="/trainee" element={<RoleGuard role="trainee"><TraineeHome /></RoleGuard>} />
        <Route path="/trainee/bookings" element={<RoleGuard role="trainee"><TraineeBookings /></RoleGuard>} />
        <Route path="/trainee/vouchers" element={<RoleGuard role="trainee"><TraineeVouchers /></RoleGuard>} />
        <Route path="/trainee/certificates" element={<RoleGuard role="trainee"><TraineeCertificates /></RoleGuard>} />

        <Route path="/supervisor" element={<RoleGuard role="supervisor"><SupervisorHome /></RoleGuard>} />
        <Route path="/supervisor/sessions" element={<RoleGuard role="supervisor"><SupervisorSessions /></RoleGuard>} />
        <Route path="/supervisor/leaves" element={<RoleGuard role="supervisor"><SupervisorLeaves /></RoleGuard>} />
        <Route path="/supervisor/assessments" element={<RoleGuard role="supervisor"><SupervisorAssessments /></RoleGuard>} />
        <Route path="/supervisor/certificates" element={<RoleGuard role="supervisor"><SupervisorCertificates /></RoleGuard>} />

        <Route path="/guardian" element={<RoleGuard role="guardian"><GuardianHome /></RoleGuard>} />

        <Route path="/employer" element={<RoleGuard role="employer"><EmployerHome /></RoleGuard>} />

        <Route path="/admin" element={<RoleGuard role="admin"><AdminHome /></RoleGuard>} />
        <Route path="/admin/moderation" element={<RoleGuard role="admin"><AdminModeration /></RoleGuard>} />
        <Route path="/admin/vouchers" element={<RoleGuard role="admin"><AdminVouchers /></RoleGuard>} />
        <Route path="/admin/resources" element={<RoleGuard role="admin"><AdminResources /></RoleGuard>} />
        <Route path="/admin/certificates" element={<RoleGuard role="admin"><AdminCertificates /></RoleGuard>} />
        <Route path="/admin/bookings" element={<RoleGuard role="admin"><AdminBookings /></RoleGuard>} />
        <Route path="/admin/ops" element={<RoleGuard role="admin"><AdminOps /></RoleGuard>} />
      </Route>

      <Route path="/" element={<Navigate to={token && role ? `/${role}` : '/login'} replace />} />
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}
