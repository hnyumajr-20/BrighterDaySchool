function formatShortDate(dateString) {
  const date = new Date(`${dateString}T00:00:00`);
  return date.toLocaleDateString(undefined, { month: 'numeric', day: 'numeric' });
}

/**
 * A plain, hand-rolled stacked bar chart — one bar per day, each segment's
 * height is a share of the shared max across the whole series, so multiple
 * series stack accurately without extra math per bar.
 *
 * @param data   Array of `{ date: 'YYYY-MM-DD', ...seriesValues }`.
 * @param series Array of `{ key, label, colorClass }`, bottom of the stack first.
 * @param formatValue Optional (value) => string for the hover title.
 */
export default function DailyBarChart({ data, series, formatValue = (v) => `${v}` }) {
  const max = Math.max(1, ...data.map((day) => series.reduce((sum, s) => sum + (Number(day[s.key]) || 0), 0)));

  return (
    <div>
      <div className="flex items-end gap-1 h-40">
        {data.map((day) => (
          <div key={day.date} className="flex-1 flex flex-col items-center justify-end h-full gap-1 min-w-0">
            <div className="w-full flex-1 flex flex-col-reverse justify-start">
              {series.map((s) => {
                const value = Number(day[s.key]) || 0;
                if (value <= 0) return null;
                return (
                  <div
                    key={s.key}
                    className={`w-full ${s.colorClass} last:rounded-t`}
                    style={{ height: `${(value / max) * 100}%` }}
                    title={`${s.label}: ${formatValue(value)}`}
                  />
                );
              })}
            </div>
            <span className="text-[0.6rem] text-slate-400 dark:text-slate-500 whitespace-nowrap">
              {formatShortDate(day.date)}
            </span>
          </div>
        ))}
      </div>
      {series.length > 1 && (
        <div className="flex items-center gap-4 mt-3">
          {series.map((s) => (
            <span key={s.key} className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
              <span className={`inline-block w-2 h-2 rounded-full ${s.colorClass}`} />
              {s.label}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}
