export default function StatCard({ label, value, note }) {
  return (
    <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
      <p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">{label}</p>
      <h3 className="text-2xl font-bold text-slate-900 dark:text-slate-100">{value}</h3>
      {note && <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">{note}</p>}
    </div>
  );
}
