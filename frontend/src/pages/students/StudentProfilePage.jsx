import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { createInvoice, listInvoices } from '../../api/invoices';
import { getStudent } from '../../api/students';
import { listFeeTransactions } from '../../api/finance';
import { useAuth } from '../../auth/AuthContext';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import StatusBadge from '../../components/StatusBadge';
import { Field, Input, Select } from '../../components/Field';

const INVOICE_TYPES = [
  { value: 'registration', label: 'Registration Fee' },
  { value: 'tuition', label: 'Tuition' },
  { value: 'other', label: 'Other' },
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
    return <img src={student.photo_url} alt={student.full_name} className="w-16 h-16 rounded-full object-cover flex-shrink-0" />;
  }
  return (
    <span className="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 flex items-center justify-center text-lg font-semibold flex-shrink-0">
      {initialsOf(student.full_name)}
    </span>
  );
}

export default function StudentProfilePage() {
  const { studentId } = useParams();
  const { user } = useAuth();

  const [student, setStudent] = useState(null);
  const [invoices, setInvoices] = useState([]);
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [creating, setCreating] = useState(false);
  const [type, setType] = useState('tuition');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = () => {
    setLoading(true);
    setLoadError('');
    Promise.all([getStudent(studentId), listInvoices({ student_id: studentId }), listFeeTransactions(studentId)])
      .then(([studentData, invoiceList, transactionList]) => {
        setStudent(studentData);
        setInvoices(invoiceList);
        setTransactions(transactionList);
      })
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load this student.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, [studentId]);

  const openCreate = () => {
    setCreating(true);
    setType('tuition');
    setAmount('');
    setNote('');
    setFormError('');
  };

  const handleCreate = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      const amountCents = Math.round(parseFloat(amount || '0') * 100);
      await createInvoice({ student_id: student.id, type, amount_cents: amountCents, note: note || undefined });
      setCreating(false);
      load();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create that invoice.'));
    } finally {
      setSubmitting(false);
    }
  };

  const canManageInvoices = user?.role === 'accountant';

  if (loading) {
    return <p className="text-slate-500 dark:text-slate-400">Loading…</p>;
  }

  if (loadError || !student) {
    return <p className="text-sm text-rose-600 dark:text-rose-400">{loadError || 'Student not found.'}</p>;
  }

  return (
    <>
      <PageHeader
        title={student.full_name}
        description="Student profile, invoices, and fee history."
        actions={
          canManageInvoices && (
            <Button onClick={openCreate}>New Invoice</Button>
          )
        }
      />

      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 mb-6 flex items-center gap-4">
        <Avatar student={student} />
        <div>
          <p className="font-semibold text-slate-900 dark:text-slate-100">{student.full_name}</p>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            {student.admission_no ?? 'No admission number yet'} · <StatusBadge status={student.status} />
          </p>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            {student.school_class ? `${student.school_class.name} ${student.school_class.arm ?? ''}` : 'No class assigned yet'}
          </p>
        </div>
      </div>

      <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Invoices</h3>
      {invoices.length === 0 ? (
        <p className="text-sm text-slate-400 dark:text-slate-500 mb-6">No invoices yet.</p>
      ) : (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto mb-6">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Invoice No.', 'Type', 'Amount', 'Status', 'Date', ''].map((heading) => (
                  <th
                    key={heading}
                    className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 px-4 py-3"
                  >
                    {heading}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {invoices.map((invoice) => (
                <tr key={invoice.id}>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{invoice.invoice_no}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 capitalize">{invoice.type}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">${centsToAmount(invoice.amount_cents)}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={invoice.status} />
                  </td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{formatDate(invoice.created_at)}</td>
                  <td className="px-4 py-3 text-right">
                    <Link
                      to={`/dashboard/payments/${invoice.id}`}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      {invoice.status === 'unpaid' ? 'Pay' : 'View'}
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Fee history</h3>
      {transactions.length === 0 ? (
        <p className="text-sm text-slate-400 dark:text-slate-500">No transactions yet.</p>
      ) : (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-xs">
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {transactions.map((tx) => (
                <tr key={tx.id}>
                  <td className="px-3 py-2 text-slate-500 dark:text-slate-400 whitespace-nowrap">{formatDate(tx.created_at)}</td>
                  <td className="px-3 py-2 capitalize text-slate-700 dark:text-slate-300">{tx.type}</td>
                  <td
                    className={`px-3 py-2 text-right font-medium whitespace-nowrap ${
                      tx.amount_cents >= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'
                    }`}
                  >
                    {tx.amount_cents >= 0 ? '+' : '-'}${centsToAmount(Math.abs(tx.amount_cents))}
                  </td>
                  <td className="px-3 py-2 text-slate-500 dark:text-slate-400">{tx.recorded_by?.full_name ?? tx.note ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={creating} onClose={() => setCreating(false)} title="New Invoice">
        <form onSubmit={handleCreate}>
          <Field label="Type" htmlFor="invoice_type">
            <Select id="invoice_type" value={type} onChange={(e) => setType(e.target.value)}>
              {INVOICE_TYPES.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Amount (USD)" htmlFor="invoice_amount">
            <Input
              id="invoice_amount"
              type="number"
              step="0.01"
              min="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />
          </Field>
          <Field label="Note (optional)" htmlFor="invoice_note">
            <Input id="invoice_note" value={note} onChange={(e) => setNote(e.target.value)} />
          </Field>

          {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setCreating(false)}>
              Close
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Create Invoice'}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}
