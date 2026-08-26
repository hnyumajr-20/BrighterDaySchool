const TONES = {
  upcoming: 'text-slate-500 dark:text-slate-400',
  active: 'text-emerald-600 dark:text-emerald-400',
  closed: 'text-rose-600 dark:text-rose-400',
  pending: 'text-amber-600 dark:text-amber-400',
  approved: 'text-emerald-600 dark:text-emerald-400',
  rejected: 'text-rose-600 dark:text-rose-400',
  present: 'text-emerald-600 dark:text-emerald-400',
  absent: 'text-rose-600 dark:text-rose-400',
  late: 'text-amber-600 dark:text-amber-400',
  unpaid: 'text-amber-600 dark:text-amber-400',
  paid: 'text-emerald-600 dark:text-emerald-400',
  cancelled: 'text-rose-600 dark:text-rose-400',
};

export default function StatusBadge({ status }) {
  return <span className={`capitalize font-medium ${TONES[status] ?? ''}`}>{status}</span>;
}
