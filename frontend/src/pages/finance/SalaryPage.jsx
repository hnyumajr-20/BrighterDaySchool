import { useEffect, useMemo, useState } from 'react';
import { getAccountantSummary, getSalaryStaffOverview, listSalaryPayments, recordSalaryPayment } from '../../api/finance';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import TableToolbar from '../../components/TableToolbar';
import { Field, Input } from '../../components/Field';

function centsToAmount(cents) {
  return (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

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

export default function SalaryPage() {
  const [staff, setStaff] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [search, setSearch] = useState('');
  const [summary, setSummary] = useState(null);

  const [viewing, setViewing] = useState(null);
  const [payments, setPayments] = useState([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState('');

  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadStaff = () => {
    setLoading(true);
    getSalaryStaffOverview()
      .then(setStaff)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load staff.')))
      .finally(() => setLoading(false));
  };

  const loadSummary = () => {
    getAccountantSummary().then(setSummary).catch(() => {});
  };

  useEffect(loadStaff, []);
  useEffect(loadSummary, []);

  const filteredStaff = useMemo(() => {
    return staff.filter((member) => {
      const q = search.toLowerCase();
      return !q || member.full_name.toLowerCase().includes(q) || member.staff_role?.toLowerCase().includes(q);
    });
  }, [staff, search]);

  const loadHistory = (staffId) => {
    setHistoryLoading(true);
    setHistoryError('');
    listSalaryPayments(staffId)
      .then(setPayments)
      .catch((err) => setHistoryError(extractErrorMessage(err, 'Could not load payment history.')))
      .finally(() => setHistoryLoading(false));
  };

  const openView = (member) => {
    setViewing(member);
    setAmount('');
    setNote('');
    setFormError('');
    loadHistory(member.id);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      const amountCents = Math.round(parseFloat(amount || '0') * 100);
      await recordSalaryPayment({ staff_id: viewing.id, amount_cents: amountCents, note: note || undefined });
      setAmount('');
      setNote('');
      loadHistory(viewing.id);
      loadSummary();
      const overview = await getSalaryStaffOverview();
      setStaff(overview);
      const updated = overview.find((s) => s.id === viewing.id);
      if (updated) setViewing((prev) => (prev ? { ...prev, ...updated } : prev));
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not record that payment.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageHeader title="Salary" description="Pay staff and review payment history." />

      {summary && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 mb-6 flex flex-wrap gap-x-8 gap-y-2 text-sm">
          <p>
            <span className="text-slate-500 dark:text-slate-400">Fees collected: </span>
            <span className="font-semibold text-slate-900 dark:text-slate-100">${centsToAmount(summary.fees_collected_cents)}</span>
          </p>
          <p>
            <span className="text-slate-500 dark:text-slate-400">Salary paid: </span>
            <span className="font-semibold text-slate-900 dark:text-slate-100">${centsToAmount(summary.salary_paid_cents)}</span>
          </p>
          <p>
            <span className="text-slate-500 dark:text-slate-400">Available balance: </span>
            <span
              className={`font-semibold ${summary.available_cents < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}`}
            >
              ${centsToAmount(summary.available_cents)}
            </span>
          </p>
        </div>
      )}

      <TableToolbar searchValue={search} onSearchChange={setSearch} placeholder="Search by name or role…" />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}

      {!loading && filteredStaff.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No active staff match your search.</p>
      )}

      {filteredStaff.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['', 'Name', 'Role', 'Monthly Salary', 'This Month', ''].map((heading, index) => (
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
              {filteredStaff.map((member) => (
                <tr key={member.id}>
                  <td className="pl-4 py-3">
                    <Avatar member={member} />
                  </td>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{member.full_name}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 capitalize">{member.staff_role}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">${centsToAmount(member.salary_cents)}</td>
                  <td className="px-4 py-3">
                    {member.remaining_this_month_cents <= 0 ? (
                      <span className="text-emerald-600 dark:text-emerald-400 font-medium text-xs">
                        Fully paid this month
                      </span>
                    ) : (
                      <span className="text-slate-500 dark:text-slate-400 text-xs">
                        ${centsToAmount(member.paid_this_month_cents)} of ${centsToAmount(member.salary_cents)}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openView(member)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Pay Salary
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={viewing !== null} onClose={() => setViewing(null)} title={viewing?.full_name ?? ''}>
        {viewing && (
          <div>
            <div className="flex items-center gap-3 mb-5 pb-5 border-b border-slate-200 dark:border-slate-800">
              <Avatar member={viewing} />
              <div>
                <p className="font-semibold text-slate-900 dark:text-slate-100">{viewing.full_name}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 capitalize">
                  {viewing.staff_role} · ${centsToAmount(viewing.salary_cents)}/mo
                </p>
                <p className="text-xs mt-0.5">
                  {viewing.remaining_this_month_cents <= 0 ? (
                    <span className="text-emerald-600 dark:text-emerald-400 font-medium">Fully paid this month</span>
                  ) : (
                    <span className="text-slate-500 dark:text-slate-400">
                      ${centsToAmount(viewing.remaining_this_month_cents)} left this month
                    </span>
                  )}
                </p>
              </div>
            </div>

            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Payment history</h3>
            {historyError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{historyError}</p>}
            {!historyLoading && payments.length === 0 && !historyError && (
              <p className="text-sm text-slate-400 dark:text-slate-500 mb-4">No payments recorded yet.</p>
            )}
            {payments.length > 0 && (
              <div className="mb-5 max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-lg">
                <table className="w-full text-xs">
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {payments.map((p) => (
                      <tr key={p.id}>
                        <td className="px-3 py-2 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                          {formatDate(p.created_at)}
                        </td>
                        <td className="px-3 py-2 text-right font-medium text-slate-900 dark:text-slate-100 whitespace-nowrap">
                          ${centsToAmount(p.amount_cents)}
                        </td>
                        <td className="px-3 py-2 text-slate-500 dark:text-slate-400">
                          {p.recorded_by?.full_name ?? p.note ?? '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Pay salary</h3>
            {viewing.remaining_this_month_cents <= 0 ? (
              <div>
                <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                  This staff member has already received their full salary for this month.
                </p>
                <div className="flex justify-end">
                  <Button type="button" variant="secondary" onClick={() => setViewing(null)}>
                    Close
                  </Button>
                </div>
              </div>
            ) : (
              <form onSubmit={handleSubmit}>
                <Field label={`Amount (USD) — up to $${centsToAmount(viewing.remaining_this_month_cents)}`} htmlFor="amount">
                  <Input
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    max={(viewing.remaining_this_month_cents / 100).toFixed(2)}
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    required
                  />
                </Field>
                <Field label="Note (optional)" htmlFor="note">
                  <Input id="note" value={note} onChange={(e) => setNote(e.target.value)} placeholder="e.g. August salary" />
                </Field>

                {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

                <div className="flex justify-end gap-3 mt-2">
                  <Button type="button" variant="secondary" onClick={() => setViewing(null)}>
                    Close
                  </Button>
                  <Button type="submit" disabled={submitting}>
                    {submitting ? 'Saving…' : 'Pay Salary'}
                  </Button>
                </div>
              </form>
            )}
          </div>
        )}
      </Modal>
    </>
  );
}
