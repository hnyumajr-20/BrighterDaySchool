import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { getFinanceOverview, listStaff } from '../api/admin';
import { getAdmissionsDailySummary, listAdmissions } from '../api/students';
import { getStaffAttendance, getStaffAttendanceDailySummary } from '../api/attendance';
import { getAccountantSummary, getFeeDailyCollections, getSalaryDailySummary } from '../api/finance';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import Button from '../components/Button';
import CurrentPeriodBanner from '../components/CurrentPeriodBanner';
import DailyBarChart from '../components/DailyBarChart';

const ROLE_LABELS = {
  registrar: 'Registrar',
  accountant: 'Accountant',
  teacher: 'Teacher',
  librarian: 'Librarian',
};

function centsToAmount(cents) {
  return (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function AdminDashboard({ user }) {
  const navigate = useNavigate();
  const [staff, setStaff] = useState(null);
  const [students, setStudents] = useState(null);
  const [todayAttendance, setTodayAttendance] = useState(null);
  const [finance, setFinance] = useState(null);
  const [attendanceDaily, setAttendanceDaily] = useState(null);
  const [admissionsDaily, setAdmissionsDaily] = useState(null);
  const [loadError, setLoadError] = useState('');

  useEffect(() => {
    listStaff()
      .then(setStaff)
      .catch(() => setLoadError('Could not load staff data.'));
    listAdmissions()
      .then(setStudents)
      .catch(() => {});
    getStaffAttendance()
      .then((data) => setTodayAttendance(data.staff))
      .catch(() => {});
    getFinanceOverview()
      .then(setFinance)
      .catch(() => {});
    getStaffAttendanceDailySummary()
      .then(setAttendanceDaily)
      .catch(() => {});
    getAdmissionsDailySummary()
      .then(setAdmissionsDaily)
      .catch(() => {});
  }, []);

  const approvedStudentCount = students ? students.filter((s) => s.status === 'approved').length : null;
  const pendingAdmissionCount = students ? students.filter((s) => s.status === 'pending').length : null;
  const presentTodayCount = todayAttendance
    ? todayAttendance.filter((s) => s.status === 'present' || s.status === 'late').length
    : null;

  const staffByRole = (staff ?? []).reduce((counts, member) => {
    counts[member.staff_role] = (counts[member.staff_role] ?? 0) + 1;
    return counts;
  }, {});
  const maxRoleCount = Math.max(1, ...Object.values(staffByRole));

  return (
    <>
      <PageHeader
        title="School Overview"
        description={`Welcome back, ${user?.username}. Here's what's happening today.`}
        actions={<Button onClick={() => navigate('/dashboard/classes')}>Manage Classes &amp; Subjects</Button>}
      />

      <CurrentPeriodBanner />

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <StatCard label="Total Staff" value={staff ? staff.length : '…'} />
        <StatCard label="Approved Students" value={approvedStudentCount ?? '…'} />
        <StatCard
          label="Staff Present Today"
          value={presentTodayCount ?? '…'}
          note={staff && presentTodayCount !== null ? `of ${staff.length} total` : undefined}
        />
        <StatCard
          label="Fees Collected"
          value={finance ? `$${centsToAmount(finance.fees.total_collected_cents)}` : '…'}
        />
        <StatCard label="Pending Admissions" value={pendingAdmissionCount ?? '…'} />
        <StatCard
          label="Monthly Payroll"
          value={finance ? `$${centsToAmount(finance.payroll.monthly_total_cents)}` : '…'}
          note={finance ? `${finance.payroll.active_staff_count} active staff` : undefined}
        />
        <StatCard label="Salary Paid" value={finance ? `$${centsToAmount(finance.payroll.salary_paid_cents)}` : '…'} />
        <StatCard
          label="Available Balance"
          value={finance ? `$${centsToAmount(finance.payroll.available_cents)}` : '…'}
          note={finance && finance.payroll.available_cents < 0 ? 'Salary paid exceeds fees collected' : undefined}
        />
      </div>

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-6">{loadError}</p>}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-5">Staff by Role</h2>
          {staff && staff.length > 0 ? (
            <div className="flex items-end gap-4 h-40">
              {Object.entries(staffByRole).map(([role, count]) => (
                <div key={role} className="flex-1 flex flex-col items-center justify-end h-full gap-2">
                  <div
                    className="w-full max-w-10 rounded-t bg-primary-600"
                    style={{ height: `${Math.max(8, (count / maxRoleCount) * 100)}%` }}
                    title={`${count}`}
                  />
                  <span className="text-xs text-slate-500 dark:text-slate-400">{ROLE_LABELS[role] ?? role}</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-slate-400 dark:text-slate-500 py-10 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
              No staff on record yet.
            </p>
          )}
        </div>

        <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-5">Fee Collection (USD)</h2>
          {finance ? (
            <dl className="divide-y divide-slate-100 dark:divide-slate-800">
              {[
                ['Total billed', finance.fees.total_billed_cents],
                ['Total collected', finance.fees.total_collected_cents],
                ['Discounts given', finance.fees.total_discounts_cents],
                ['Outstanding balance', finance.fees.outstanding_cents],
              ].map(([label, cents]) => (
                <div key={label} className="flex items-center justify-between py-2.5 text-sm">
                  <dt className="text-slate-500 dark:text-slate-400">{label}</dt>
                  <dd className="font-semibold text-slate-900 dark:text-slate-100">${centsToAmount(cents)}</dd>
                </div>
              ))}
            </dl>
          ) : (
            <p className="text-sm text-slate-400 dark:text-slate-500 py-10 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
              Loading…
            </p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
        <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-5">
            Staff Attendance (Last 14 Days)
          </h2>
          {attendanceDaily ? (
            <DailyBarChart
              data={attendanceDaily}
              series={[
                { key: 'present', label: 'Present', colorClass: 'bg-emerald-500' },
                { key: 'absent', label: 'Absent', colorClass: 'bg-rose-500' },
              ]}
            />
          ) : (
            <p className="text-sm text-slate-400 dark:text-slate-500 py-10 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
              Loading…
            </p>
          )}
        </div>

        <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-5">
            New Admissions (Last 14 Days)
          </h2>
          {admissionsDaily ? (
            <DailyBarChart
              data={admissionsDaily}
              series={[{ key: 'count', label: 'Applications', colorClass: 'bg-primary-600' }]}
            />
          ) : (
            <p className="text-sm text-slate-400 dark:text-slate-500 py-10 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
              Loading…
            </p>
          )}
        </div>
      </div>
    </>
  );
}

function AccountantDashboard({ user }) {
  const navigate = useNavigate();
  const [summary, setSummary] = useState(null);
  const [feesDaily, setFeesDaily] = useState(null);
  const [salaryDaily, setSalaryDaily] = useState(null);
  const [loadError, setLoadError] = useState('');

  useEffect(() => {
    getAccountantSummary()
      .then(setSummary)
      .catch(() => setLoadError('Could not load the finance summary.'));
    getFeeDailyCollections()
      .then(setFeesDaily)
      .catch(() => {});
    getSalaryDailySummary()
      .then(setSalaryDaily)
      .catch(() => {});
  }, []);

  return (
    <>
      <PageHeader
        title="Finance Overview"
        description={`Welcome back, ${user?.username}. Here's where the school's money stands today.`}
        actions={
          <>
            <Button variant="secondary" onClick={() => navigate('/dashboard/salary')}>
              Pay Salary
            </Button>
            <Button onClick={() => navigate('/dashboard/fees')}>Record a Fee</Button>
          </>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-6">{loadError}</p>}

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <StatCard label="Fees Collected" value={summary ? `$${centsToAmount(summary.fees_collected_cents)}` : '…'} />
        <StatCard label="Outstanding Balance" value={summary ? `$${centsToAmount(summary.outstanding_cents)}` : '…'} />
        <StatCard label="Salary Paid" value={summary ? `$${centsToAmount(summary.salary_paid_cents)}` : '…'} />
        <StatCard
          label="Available Balance"
          value={summary ? `$${centsToAmount(summary.available_cents)}` : '…'}
          note={summary && summary.available_cents < 0 ? 'Salary paid exceeds fees collected' : undefined}
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-5">
            Fees Collected (Last 14 Days)
          </h2>
          {feesDaily ? (
            <DailyBarChart
              data={feesDaily}
              series={[{ key: 'collected_cents', label: 'Collected', colorClass: 'bg-emerald-500' }]}
              formatValue={(v) => `$${centsToAmount(v)}`}
            />
          ) : (
            <p className="text-sm text-slate-400 dark:text-slate-500 py-10 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
              Loading…
            </p>
          )}
        </div>

        <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-5">
            Salary Paid (Last 14 Days)
          </h2>
          {salaryDaily ? (
            <DailyBarChart
              data={salaryDaily}
              series={[{ key: 'paid_cents', label: 'Paid', colorClass: 'bg-primary-600' }]}
              formatValue={(v) => `$${centsToAmount(v)}`}
            />
          ) : (
            <p className="text-sm text-slate-400 dark:text-slate-500 py-10 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
              Loading…
            </p>
          )}
        </div>
      </div>
    </>
  );
}

export default function DashboardPage() {
  const { user } = useAuth();

  if (user?.role === 'accountant') {
    return <AccountantDashboard user={user} />;
  }

  return <AdminDashboard user={user} />;
}
