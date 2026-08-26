import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getCurrentAcademicContext } from '../api/academic';

function Segment({ label, value, to }) {
  return (
    <div className="flex items-center gap-2">
      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 flex-shrink-0" />
      <div className="leading-tight">
        <p className="text-[0.65rem] uppercase tracking-wide text-slate-500 dark:text-slate-400">{label}</p>
        {to ? (
          <Link to={to} className="text-sm font-semibold text-slate-900 dark:text-slate-100 hover:underline">
            {value}
          </Link>
        ) : (
          <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{value}</p>
        )}
      </div>
    </div>
  );
}

export default function CurrentPeriodBanner() {
  const [context, setContext] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getCurrentAcademicContext()
      .then(setContext)
      .finally(() => setLoading(false));
  }, []);

  if (loading) return null;

  const { academic_year: year, semester, period } = context ?? {};

  if (!year) {
    return (
      <div className="flex items-center justify-between bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl px-5 py-3 mb-6 text-sm">
        <span className="text-slate-500 dark:text-slate-400">
          No academic year is currently open — nothing is operating under a set period right now.
        </span>
        <Link to="/dashboard/academic-years" className="text-primary-600 dark:text-primary-400 hover:underline font-medium">
          Open one
        </Link>
      </div>
    );
  }

  return (
    <div className="flex flex-wrap items-center gap-x-8 gap-y-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-3 mb-6">
      <Segment label="Academic Year" value={year.name} to="/dashboard/academic-years" />

      {semester ? (
        <Segment label="Semester" value={semester.name} to={`/dashboard/academic-years/${year.id}`} />
      ) : (
        <div>
          <p className="text-[0.65rem] uppercase tracking-wide text-slate-500 dark:text-slate-400">Semester</p>
          <p className="text-sm text-slate-400 dark:text-slate-500">None open</p>
        </div>
      )}

      {semester &&
        (period ? (
          <Segment
            label="Period"
            value={period.name}
            to={`/dashboard/academic-years/${year.id}/semesters/${semester.id}`}
          />
        ) : (
          <div>
            <p className="text-[0.65rem] uppercase tracking-wide text-slate-500 dark:text-slate-400">Period</p>
            <p className="text-sm text-slate-400 dark:text-slate-500">Between periods — locked</p>
          </div>
        ))}
    </div>
  );
}
