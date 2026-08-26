import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { createInvoice, listInvoices } from '../../api/invoices';
import { listStudents } from '../../api/students';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import TableToolbar from '../../components/TableToolbar';
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

export default function InvoicesPage() {
  const [invoices, setInvoices] = useState([]);
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [search, setSearch] = useState('');

  const [creating, setCreating] = useState(false);
  const [studentSearch, setStudentSearch] = useState('');
  const [selectedStudent, setSelectedStudent] = useState(null);
  const [type, setType] = useState('tuition');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = () => {
    setLoading(true);
    Promise.all([listInvoices(), listStudents()])
      .then(([invoiceList, studentList]) => {
        setInvoices(invoiceList);
        setStudents(studentList);
      })
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load invoices.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const filteredInvoices = useMemo(() => {
    return invoices.filter((invoice) => {
      const q = search.toLowerCase();
      return (
        !q ||
        invoice.invoice_no.toLowerCase().includes(q) ||
        invoice.student?.full_name?.toLowerCase().includes(q) ||
        invoice.student?.admission_no?.toLowerCase().includes(q)
      );
    });
  }, [invoices, search]);

  const studentMatches = useMemo(() => {
    if (!studentSearch) return [];
    const q = studentSearch.toLowerCase();
    return students
      .filter((s) => s.full_name.toLowerCase().includes(q) || s.admission_no?.toLowerCase().includes(q))
      .slice(0, 8);
  }, [students, studentSearch]);

  const openCreate = () => {
    setCreating(true);
    setStudentSearch('');
    setSelectedStudent(null);
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
      await createInvoice({ student_id: selectedStudent.id, type, amount_cents: amountCents, note: note || undefined });
      setCreating(false);
      load();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create that invoice.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageHeader
        title="Invoices"
        description="Search a student and raise an invoice for registration fees, tuition, or any other charge."
        actions={<Button onClick={openCreate}>New Invoice</Button>}
      />

      <TableToolbar searchValue={search} onSearchChange={setSearch} placeholder="Search by invoice no., name, or admission no…" />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}

      {!loading && filteredInvoices.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No invoices match your search.</p>
      )}

      {filteredInvoices.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Invoice No.', 'Student', 'Type', 'Amount', 'Status', 'Date', ''].map((heading) => (
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
              {filteredInvoices.map((invoice) => (
                <tr key={invoice.id}>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{invoice.invoice_no}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                    <Link to={`/dashboard/students/${invoice.student_id}`} className="hover:underline">
                      {invoice.student?.full_name}
                    </Link>
                  </td>
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

      <Modal open={creating} onClose={() => setCreating(false)} title="New Invoice">
        {!selectedStudent ? (
          <div>
            <Field label="Search for a student" htmlFor="student_search">
              <Input
                id="student_search"
                value={studentSearch}
                onChange={(e) => setStudentSearch(e.target.value)}
                placeholder="Name or admission no…"
                autoFocus
              />
            </Field>
            {studentMatches.length > 0 && (
              <div className="mb-4 border border-slate-200 dark:border-slate-800 rounded-lg divide-y divide-slate-100 dark:divide-slate-800 max-h-56 overflow-y-auto">
                {studentMatches.map((s) => (
                  <button
                    key={s.id}
                    type="button"
                    onClick={() => setSelectedStudent(s)}
                    className="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                  >
                    <span className="font-medium text-slate-900 dark:text-slate-100">{s.full_name}</span>{' '}
                    <span className="text-slate-500 dark:text-slate-400">
                      {s.admission_no ?? 'pending'} · {s.status}
                    </span>
                  </button>
                ))}
              </div>
            )}
            {studentSearch && studentMatches.length === 0 && (
              <p className="text-sm text-slate-400 dark:text-slate-500 mb-4">No matching students.</p>
            )}
            <div className="flex justify-end">
              <Button type="button" variant="secondary" onClick={() => setCreating(false)}>
                Close
              </Button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleCreate}>
            <div className="mb-4 flex items-center justify-between bg-slate-50 dark:bg-slate-800 rounded-lg px-3 py-2">
              <div>
                <p className="text-sm font-medium text-slate-900 dark:text-slate-100">{selectedStudent.full_name}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  {selectedStudent.admission_no ?? 'pending'} · {selectedStudent.status}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setSelectedStudent(null)}
                className="text-xs text-primary-600 dark:text-primary-400 hover:underline"
              >
                Change
              </button>
            </div>

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
        )}
      </Modal>
    </>
  );
}
