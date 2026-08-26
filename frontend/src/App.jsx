import { Navigate, Route, Routes } from 'react-router-dom';
import LoginPage from './pages/LoginPage';
import ForcedPasswordChangePage from './pages/ForcedPasswordChangePage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import DashboardPage from './pages/DashboardPage';
import StaffPage from './pages/admin/StaffPage';
import ClassesPage from './pages/admin/ClassesPage';
import ClassDetailPage from './pages/admin/ClassDetailPage';
import SubjectsPage from './pages/admin/SubjectsPage';
import AcademicYearsPage from './pages/admin/AcademicYearsPage';
import SemestersPage from './pages/admin/SemestersPage';
import PeriodsPage from './pages/admin/PeriodsPage';
import AdmissionsPage from './pages/students/AdmissionsPage';
import StudentProfilePage from './pages/students/StudentProfilePage';
import StaffAttendancePage from './pages/attendance/StaffAttendancePage';
import FeesPage from './pages/finance/FeesPage';
import SalaryPage from './pages/finance/SalaryPage';
import InvoicesPage from './pages/finance/InvoicesPage';
import PaymentPage from './pages/finance/PaymentPage';
import FinancialReportPage from './pages/finance/FinancialReportPage';
import SettingsPage from './pages/SettingsPage';
import AppShell from './layouts/AppShell';
import ProtectedRoute from './auth/ProtectedRoute';
import RequireToken from './auth/RequireToken';
import RoleGate from './auth/RoleGate';

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route
        path="/change-password"
        element={
          <RequireToken>
            <ForcedPasswordChangePage />
          </RequireToken>
        }
      />
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <AppShell />
          </ProtectedRoute>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="staff" element={<StaffPage />} />
        <Route path="classes" element={<ClassesPage />} />
        <Route path="classes/:classId" element={<ClassDetailPage />} />
        <Route path="subjects" element={<SubjectsPage />} />
        <Route path="academic-years" element={<AcademicYearsPage />} />
        <Route path="academic-years/:yearId" element={<SemestersPage />} />
        <Route path="academic-years/:yearId/semesters/:semesterId" element={<PeriodsPage />} />
        <Route path="admissions" element={<AdmissionsPage />} />
        <Route
          path="students/:studentId"
          element={
            <RoleGate roles={['admin', 'registrar', 'accountant']}>
              <StudentProfilePage />
            </RoleGate>
          }
        />
        <Route path="attendance" element={<StaffAttendancePage />} />
        <Route
          path="fees"
          element={
            <RoleGate roles={['accountant']}>
              <FeesPage />
            </RoleGate>
          }
        />
        <Route
          path="salary"
          element={
            <RoleGate roles={['accountant']}>
              <SalaryPage />
            </RoleGate>
          }
        />
        <Route
          path="invoices"
          element={
            <RoleGate roles={['accountant']}>
              <InvoicesPage />
            </RoleGate>
          }
        />
        <Route
          path="payments/:invoiceId"
          element={
            <RoleGate roles={['accountant']}>
              <PaymentPage />
            </RoleGate>
          }
        />
        <Route
          path="finance/report"
          element={
            <RoleGate roles={['admin', 'registrar', 'accountant']}>
              <FinancialReportPage />
            </RoleGate>
          }
        />
        <Route path="settings" element={<SettingsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}
