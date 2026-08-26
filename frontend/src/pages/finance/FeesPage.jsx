import { useEffect, useMemo, useState } from 'react';
import {
  getClassInstallments,
  listFeeTransactions,
  listStudentBalances,
  recordFeeTransaction,
  saveClassInstallments,
} from '../../api/finance';
import { listClasses } from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import TableToolbar from '../../components/TableToolbar';
import { Field, Input, Select } from '../../components/Field';

const TRANSACTION_TYPES = [
  { value: 'charge', label: 'Charge' },
  { value: 'payment', label: 'Payment' },
  { value: 'discount', label: 'Discount' },
];

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

function Avatar({ student }) {
  if (student.photo_url) {
    return <img src={student.photo_url} alt={student.full_name} className="w-9 h-9 rounded-full object-cover flex-shrink-0" />;
  }
  return (
    <span className="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 flex items-center justify-center text-xs font-semibold flex-shrink-0">
      {initialsOf(student.full_name)}
    </span>
  );
}

function BalanceLabel({ cents }) {
  if (cents > 0) {
    return <span className="font-semibold text-rose-600 dark:text-rose-400">${centsToAmount(cents)} owed</span>;
  }
  if (cents < 0) {
    return <span className="font-semibold text-primary-600 dark:text-primary-400">${centsToAmount(-cents)} credit</span>;
  }
  return <span className="font-semibold text-emerald-600 dark:text-emerald-400">$0.00</span>;
}

export default function FeesPage() {
  const [students, setStudents] = useState([]);
  const [classes, setClasses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [search, setSearch] = useState('');

  const [viewing, setViewing] = useState(null);
  const [transactions, setTransactions] = useState([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState('');
  const [studentInstallments, setStudentInstallments] = useState([]);
  const [installmentChargeBusyId, setInstallmentChargeBusyId] = useState(null);
  const [installmentChargeError, setInstallmentChargeError] = useState('');

  const [amount, setAmount] = useState('');
  const [type, setType] = useState('payment');
  const [note, setNote] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [plansOpen, setPlansOpen] = useState(false);
  const [planClassId, setPlanClassId] = useState('');
  const [planAmounts, setPlanAmounts] = useState(['', '', '']);
  const [planDueDates, setPlanDueDates] = useState(['', '', '']);
  const [planLoading, setPlanLoading] = useState(false);
  const [planError, setPlanError] = useState('');
  const [planSubmitting, setPlanSubmitting] = useState(false);

  const loadStudents = () => {
    setLoading(true);
    listStudentBalances()
      .then(setStudents)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load student fee accounts.')))
      .finally(() => setLoading(false));
  };

  useEffect(loadStudents, []);
  useEffect(() => {
    listClasses().then(setClasses).catch(() => {});
  }, []);

  const filteredStudents = useMemo(() => {
    return students.filter((student) => {
      const q = search.toLowerCase();
      return (
        !q ||
        student.full_name.toLowerCase().includes(q) ||
        student.admission_no?.toLowerCase().includes(q)
      );
    });
  }, [students, search]);

  const loadHistory = (student) => {
    setHistoryLoading(true);
    setHistoryError('');
    setStudentInstallments([]);
    listFeeTransactions(student.id)
      .then(setTransactions)
      .catch((err) => setHistoryError(extractErrorMessage(err, 'Could not load transaction history.')))
      .finally(() => setHistoryLoading(false));

    if (student.school_class) {
      getClassInstallments(student.school_class.id)
        .then(setStudentInstallments)
        .catch(() => {});
    }
  };

  const openView = (student) => {
    setViewing(student);
    setAmount('');
    setType('payment');
    setNote('');
    setFormError('');
    setInstallmentChargeError('');
    loadHistory(student);
  };

  const refreshAfterChange = async (student) => {
    loadHistory(student);
    const overview = await listStudentBalances();
    setStudents(overview);
    const updated = overview.find((s) => s.id === student.id);
    if (updated) setViewing((prev) => (prev ? { ...prev, ...updated } : prev));
    return updated;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      const amountCents = Math.round(parseFloat(amount || '0') * 100);
      await recordFeeTransaction({ student_id: viewing.id, amount_cents: amountCents, type, note: note || undefined });
      setAmount('');
      setNote('');
      await refreshAfterChange(viewing);
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not record that transaction.'));
    } finally {
      setSubmitting(false);
    }
  };

  const handleChargeInstallment = async (installment) => {
    setInstallmentChargeError('');
    setInstallmentChargeBusyId(installment.id);
    try {
      await recordFeeTransaction({
        student_id: viewing.id,
        amount_cents: installment.amount_cents,
        type: 'charge',
        class_fee_installment_id: installment.id,
        note: `Installment ${installment.sequence} of 3`,
      });
      await refreshAfterChange(viewing);
    } catch (err) {
      setInstallmentChargeError(extractErrorMessage(err, 'Could not charge that installment.'));
    } finally {
      setInstallmentChargeBusyId(null);
    }
  };

  const openPlans = () => {
    setPlansOpen(true);
    setPlanClassId('');
    setPlanAmounts(['', '', '']);
    setPlanDueDates(['', '', '']);
    setPlanError('');
  };

  const loadPlanForClass = (classId) => {
    setPlanClassId(classId);
    setPlanError('');
    if (!classId) return;
    setPlanLoading(true);
    getClassInstallments(classId)
      .then((rows) => {
        const cls = classes.find((c) => String(c.id) === String(classId));
        if (rows.length === 3) {
          setPlanAmounts(rows.map((r) => (r.amount_cents / 100).toFixed(2)));
          setPlanDueDates(rows.map((r) => (r.due_date ? r.due_date.slice(0, 10) : '')));
        } else {
          setPlanDueDates(['', '', '']);
          if (cls) {
            const third = cls.fee_amount_cents / 3 / 100;
            setPlanAmounts([third.toFixed(2), third.toFixed(2), third.toFixed(2)]);
          }
        }
      })
      .catch((err) => setPlanError(extractErrorMessage(err, 'Could not load this class’s plan.')))
      .finally(() => setPlanLoading(false));
  };

  const handleSavePlan = async (event) => {
    event.preventDefault();
    setPlanError('');
    setPlanSubmitting(true);
    try {
      const amounts = planAmounts.map((a) => Math.round(parseFloat(a || '0') * 100));
      await saveClassInstallments(planClassId, amounts, planDueDates);
      setPlansOpen(false);
    } catch (err) {
      setPlanError(extractErrorMessage(err, 'Could not save this installment plan.'));
    } finally {
      setPlanSubmitting(false);
    }
  };

  const chargedInstallmentIds = new Set(
    transactions.filter((tx) => tx.class_fee_installment_id).map((tx) => tx.class_fee_installment_id),
  );

  return (
    <>
      <PageHeader
        title="Fees"
        description="Student fee balances and transaction history."
        actions={
          <Button variant="secondary" onClick={openPlans}>
            Class Fee Plans
          </Button>
        }
      />

      <TableToolbar
        searchValue={search}
        onSearchChange={setSearch}
        placeholder="Search by name or admission no…"
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}

      {!loading && filteredStudents.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No approved students match your search.</p>
      )}

      {filteredStudents.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['', 'Name', 'Admission No.', 'Class', 'Balance', ''].map((heading, index) => (
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
              {filteredStudents.map((student) => (
                <tr key={student.id}>
                  <td className="pl-4 py-3">
                    <Avatar student={student} />
                  </td>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{student.full_name}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{student.admission_no ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                    {student.school_class ? `${student.school_class.name} ${student.school_class.arm ?? ''}` : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <BalanceLabel cents={student.balance_cents} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openView(student)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Record Transaction
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
            <div className="flex items-center justify-between mb-5 pb-5 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-3">
                <Avatar student={viewing} />
                <div>
                  <p className="font-semibold text-slate-900 dark:text-slate-100">{viewing.full_name}</p>
                  <p className="text-xs text-slate-500 dark:text-slate-400">{viewing.admission_no}</p>
                </div>
              </div>
              <BalanceLabel cents={viewing.balance_cents} />
            </div>

            {studentInstallments.length === 3 && (
              <div className="mb-5 pb-5 border-b border-slate-200 dark:border-slate-800">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Installments</h3>
                <div className="space-y-2">
                  {studentInstallments.map((installment) => {
                    const charged = chargedInstallmentIds.has(installment.id);
                    return (
                      <div
                        key={installment.id}
                        className="flex items-center justify-between text-sm bg-slate-50 dark:bg-slate-800 rounded-lg px-3 py-2"
                      >
                        <span className="text-slate-700 dark:text-slate-300">
                          Installment {installment.sequence} — ${centsToAmount(installment.amount_cents)}
                          {installment.due_date && (
                            <span className="text-slate-400 dark:text-slate-500">
                              {' '}
                              (due {formatDate(installment.due_date)})
                            </span>
                          )}
                        </span>
                        {charged ? (
                          <span className="text-emerald-600 dark:text-emerald-400 text-xs font-medium">Charged</span>
                        ) : (
                          <button
                            type="button"
                            disabled={installmentChargeBusyId === installment.id}
                            onClick={() => handleChargeInstallment(installment)}
                            className="text-primary-600 dark:text-primary-400 hover:underline text-xs font-medium disabled:opacity-50"
                          >
                            {installmentChargeBusyId === installment.id ? 'Charging…' : 'Charge'}
                          </button>
                        )}
                      </div>
                    );
                  })}
                </div>
                {installmentChargeError && (
                  <p className="text-sm text-rose-600 dark:text-rose-400 mt-3">{installmentChargeError}</p>
                )}
              </div>
            )}

            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">History</h3>
            {historyError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{historyError}</p>}
            {!historyLoading && transactions.length === 0 && !historyError && (
              <p className="text-sm text-slate-400 dark:text-slate-500 mb-4">No transactions yet.</p>
            )}
            {transactions.length > 0 && (
              <div className="mb-5 max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-lg">
                <table className="w-full text-xs">
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {transactions.map((tx) => (
                      <tr key={tx.id}>
                        <td className="px-3 py-2 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                          {formatDate(tx.created_at)}
                        </td>
                        <td className="px-3 py-2 capitalize text-slate-700 dark:text-slate-300">{tx.type}</td>
                        <td
                          className={`px-3 py-2 text-right font-medium whitespace-nowrap ${
                            tx.amount_cents >= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'
                          }`}
                        >
                          {tx.amount_cents >= 0 ? '+' : '-'}${centsToAmount(Math.abs(tx.amount_cents))}
                        </td>
                        <td className="px-3 py-2 text-slate-500 dark:text-slate-400">
                          {tx.recorded_by?.full_name ?? tx.note ?? '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Record a transaction</h3>
            <form onSubmit={handleSubmit}>
              <div className="grid grid-cols-2 gap-4">
                <Field label="Amount (USD)" htmlFor="amount">
                  <Input
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    required
                  />
                </Field>
                <Field label="Type" htmlFor="type">
                  <Select id="type" value={type} onChange={(e) => setType(e.target.value)}>
                    {TRANSACTION_TYPES.map((t) => (
                      <option key={t.value} value={t.value}>
                        {t.label}
                      </option>
                    ))}
                  </Select>
                </Field>
              </div>
              <Field label="Note (optional)" htmlFor="note">
                <Input id="note" value={note} onChange={(e) => setNote(e.target.value)} />
              </Field>

              {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

              <div className="flex justify-end gap-3 mt-2">
                <Button type="button" variant="secondary" onClick={() => setViewing(null)}>
                  Close
                </Button>
                <Button type="submit" disabled={submitting}>
                  {submitting ? 'Saving…' : 'Record Transaction'}
                </Button>
              </div>
            </form>
          </div>
        )}
      </Modal>

      <Modal open={plansOpen} onClose={() => setPlansOpen(false)} title="Class Fee Plans">
        <form onSubmit={handleSavePlan}>
          <Field label="Class" htmlFor="plan_class">
            <Select id="plan_class" value={planClassId} onChange={(e) => loadPlanForClass(e.target.value)} required>
              <option value="">Select a class…</option>
              {classes.map((cls) => (
                <option key={cls.id} value={cls.id}>
                  {cls.name} {cls.arm} — ${centsToAmount(cls.fee_amount_cents)} fee
                </option>
              ))}
            </Select>
          </Field>

          {planClassId && !planLoading && (
            <div className="grid grid-cols-3 gap-4">
              {[0, 1, 2].map((index) => (
                <div key={index}>
                  <Field label={`Installment ${index + 1} (USD)`} htmlFor={`plan_amount_${index}`}>
                    <Input
                      id={`plan_amount_${index}`}
                      type="number"
                      step="0.01"
                      min="0.01"
                      value={planAmounts[index]}
                      onChange={(e) =>
                        setPlanAmounts((prev) => prev.map((v, i) => (i === index ? e.target.value : v)))
                      }
                      required
                    />
                  </Field>
                  <Field label="Due date" htmlFor={`plan_due_${index}`}>
                    <Input
                      id={`plan_due_${index}`}
                      type="date"
                      value={planDueDates[index]}
                      onChange={(e) =>
                        setPlanDueDates((prev) => prev.map((v, i) => (i === index ? e.target.value : v)))
                      }
                      required
                    />
                  </Field>
                </div>
              ))}
            </div>
          )}

          {planError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{planError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setPlansOpen(false)}>
              Close
            </Button>
            <Button type="submit" disabled={!planClassId || planSubmitting}>
              {planSubmitting ? 'Saving…' : 'Save Plan'}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}
