import { useEffect, useState } from 'react';
import { getStaffAttendance, markStaffAttendance, openCheckInWindow } from '../../api/attendance';
import { extractErrorMessage } from '../../utils/errors';
import { formatTime } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import StatusBadge from '../../components/StatusBadge';

function initialsOf(fullName) {
  return fullName
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

function Avatar({ member }) {
  if (member.photo_url) {
    return <img src={member.photo_url} alt={member.full_name} className="w-9 h-9 rounded-full object-cover flex-shrink-0" />;
  }
  return (
    <span className="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 flex items-center justify-center text-xs font-semibold flex-shrink-0">
      {initialsOf(member.full_name)}
    </span>
  );
}

function windowMessage(window) {
  if (!window) {
    return "Check-in hasn't opened yet today.";
  }

  const now = new Date();
  const checkInCloses = new Date(window.check_in_closes_at);
  const checkOutOpens = new Date(window.check_out_opens_at);
  const checkOutCloses = new Date(window.check_out_closes_at);

  if (now < checkInCloses) {
    return `Check-in is open — closes at ${formatTime(window.check_in_closes_at)}.`;
  }
  if (now < checkOutOpens) {
    return `Check-in closed at ${formatTime(window.check_in_closes_at)}. Check-out opens at ${formatTime(window.check_out_opens_at)}.`;
  }
  if (now < checkOutCloses) {
    return `Check-out is open — closes at ${formatTime(window.check_out_closes_at)}.`;
  }
  return "Today's attendance windows are closed.";
}

export default function StaffAttendancePage() {
  const [window_, setWindow] = useState(null);
  const [staff, setStaff] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [openBusy, setOpenBusy] = useState(false);
  const [openError, setOpenError] = useState('');

  const [markBusyId, setMarkBusyId] = useState(null);
  const [markError, setMarkError] = useState('');

  const load = () => {
    setLoading(true);
    getStaffAttendance()
      .then((data) => {
        setWindow(data.window);
        setStaff(data.staff);
      })
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load attendance.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const handleOpenWindow = async () => {
    setOpenError('');
    setOpenBusy(true);
    try {
      await openCheckInWindow();
      load();
    } catch (err) {
      setOpenError(extractErrorMessage(err, 'Could not open the check-in window.'));
    } finally {
      setOpenBusy(false);
    }
  };

  const handleMark = async (staffId, status) => {
    setMarkError('');
    setMarkBusyId(staffId);
    try {
      await markStaffAttendance(staffId, status);
      setStaff((prev) => prev.map((m) => (m.id === staffId ? { ...m, status, method: 'manual' } : m)));
    } catch (err) {
      setMarkError(extractErrorMessage(err, 'Could not save that.'));
    } finally {
      setMarkBusyId(null);
    }
  };

  return (
    <>
      <PageHeader
        title="Staff Attendance"
        description="Today's check-in and check-out windows."
        actions={
          !window_ && (
            <Button onClick={handleOpenWindow} disabled={openBusy}>
              {openBusy ? 'Opening…' : 'Open Check-In Window'}
            </Button>
          )
        }
      />

      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 mb-6">
        <p className="text-sm font-medium text-slate-900 dark:text-slate-100">{windowMessage(window_)}</p>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Check-in opens automatically at 7:00 AM and stays open for 90 minutes. If it doesn&apos;t open on its own,
          you can open it any time up to 8:30 AM. Anyone still unmarked when it closes is automatically marked
          absent — you can still correct that afterward.
        </p>
        {openError && <p className="text-sm text-rose-600 dark:text-rose-400 mt-3">{openError}</p>}
      </div>

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}
      {markError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{markError}</p>}

      {!loading && staff.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No active staff on record yet.</p>
      )}

      {staff.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['', 'Name', 'Role', 'Status', '', '', ''].map((heading, index) => (
                  <th
                    key={`${heading}-${index}`}
                    className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 px-4 py-3"
                  >
                    {heading}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {staff.map((member) => (
                <tr key={member.id}>
                  <td className="pl-4 py-3">
                    <Avatar member={member} />
                  </td>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{member.full_name}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 capitalize">{member.staff_role}</td>
                  <td className="px-4 py-3">
                    {member.status ? (
                      <StatusBadge status={member.status} />
                    ) : (
                      <span className="text-slate-400 dark:text-slate-500">Not marked</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      disabled={markBusyId === member.id}
                      onClick={() => handleMark(member.id, 'present')}
                      className="text-emerald-600 dark:text-emerald-400 hover:underline font-medium disabled:opacity-50"
                    >
                      Present
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      disabled={markBusyId === member.id}
                      onClick={() => handleMark(member.id, 'late')}
                      className="text-amber-600 dark:text-amber-400 hover:underline font-medium disabled:opacity-50"
                    >
                      Late
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      disabled={markBusyId === member.id}
                      onClick={() => handleMark(member.id, 'absent')}
                      className="text-rose-600 dark:text-rose-400 hover:underline font-medium disabled:opacity-50"
                    >
                      Absent
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
