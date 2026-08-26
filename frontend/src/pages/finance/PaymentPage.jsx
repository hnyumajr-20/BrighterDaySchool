import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getInvoice, payInvoice } from '../../api/invoices';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate, formatTime } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import StatusBadge from '../../components/StatusBadge';
import { Field, Input } from '../../components/Field';

const METHODS = [
  { value: 'cash', label: 'Cash' },
  { value: 'orange_money', label: 'Orange Money' },
  { value: 'lonestar_mtn', label: 'Lonestar MTN' },
];

function centsToAmount(cents) {
  return (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function PaymentPage() {
  const { invoiceId } = useParams();

  const [invoice, setInvoice] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [method, setMethod] = useState('');
  const [phone, setPhone] = useState('');
  const [payError, setPayError] = useState('');
  const [paying, setPaying] = useState(false);
  const [autoApprovalNote, setAutoApprovalNote] = useState(null);

  const load = () => {
    setLoading(true);
    getInvoice(invoiceId)
      .then(setInvoice)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load this invoice.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, [invoiceId]);

  const handlePay = async (event) => {
    event.preventDefault();
    setPayError('');
    setPaying(true);
    try {
      const data = method === 'cash' ? { payment_method: method } : { payment_method: method, payer_phone: phone };
      const response = await payInvoice(invoiceId, data);
      setInvoice(response);
      if (response.admission_auto_approved) {
        setAutoApprovalNote({ ok: true });
      } else if (response.admission_auto_approval_error) {
        setAutoApprovalNote({ ok: false, message: response.admission_auto_approval_error });
      }
    } catch (err) {
      setPayError(extractErrorMessage(err, 'Could not process that payment.'));
    } finally {
      setPaying(false);
    }
  };

  if (loading) {
    return <p className="text-slate-500 dark:text-slate-400">Loading…</p>;
  }

  if (loadError || !invoice) {
    return <p className="text-sm text-rose-600 dark:text-rose-400">{loadError || 'Invoice not found.'}</p>;
  }

  const providerLabel = method === 'orange_money' ? 'Orange Money Number' : 'Lonestar MTN Number';

  return (
    <>
      <PageHeader
        title={`Invoice ${invoice.invoice_no}`}
        description={
          <>
            {invoice.student?.full_name} · {invoice.student?.admission_no ?? 'pending'}
          </>
        }
      />

      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 max-w-lg">
        <div className="flex items-center justify-between mb-5 pb-5 border-b border-slate-200 dark:border-slate-800">
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide capitalize">{invoice.type} invoice</p>
            <p className="text-2xl font-serif font-bold text-slate-900 dark:text-slate-100">${centsToAmount(invoice.amount_cents)}</p>
          </div>
          <StatusBadge status={invoice.status} />
        </div>

        {invoice.status === 'paid' ? (
          <div>
            {autoApprovalNote?.ok && (
              <p className="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 text-sm px-3 py-2">
                Registration fee confirmed — the student has been approved and their admission letter has been emailed.
              </p>
            )}
            {autoApprovalNote?.ok === false && (
              <p className="mb-4 rounded-lg bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 text-sm px-3 py-2">
                Payment recorded, but the student could not be auto-approved: {autoApprovalNote.message}. Use the manual
                approve action on the Admissions page once this is resolved.
              </p>
            )}
            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Payment method</dt>
                <dd className="text-slate-900 dark:text-slate-100 capitalize">{invoice.payment_method?.replace('_', ' ')}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Paid at</dt>
                <dd className="text-slate-900 dark:text-slate-100">
                  {formatDate(invoice.paid_at)} {formatTime(invoice.paid_at)}
                </dd>
              </div>
              {invoice.payer_phone && (
                <div>
                  <dt className="text-slate-500 dark:text-slate-400">Payer phone</dt>
                  <dd className="text-slate-900 dark:text-slate-100">{invoice.payer_phone}</dd>
                </div>
              )}
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Gateway transaction ID</dt>
                <dd className="text-slate-900 dark:text-slate-100">{invoice.gateway_transaction_id ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Confirmed by</dt>
                <dd className="text-slate-900 dark:text-slate-100">{invoice.confirmed_by?.full_name ?? '—'}</dd>
              </div>
            </dl>
            <div className="mt-6">
              <Link
                to={`/dashboard/students/${invoice.student_id}`}
                className="text-primary-600 dark:text-primary-400 hover:underline text-sm font-medium"
              >
                Back to student profile
              </Link>
            </div>
          </div>
        ) : (
          <form onSubmit={handlePay}>
            <Field label="Payment method" htmlFor="method">
              <div className="grid grid-cols-3 gap-2">
                {METHODS.map((m) => (
                  <button
                    key={m.value}
                    type="button"
                    onClick={() => setMethod(m.value)}
                    className={`rounded-lg border px-3 py-2 text-sm font-medium ${
                      method === m.value
                        ? 'border-primary-600 bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300'
                        : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'
                    }`}
                  >
                    {m.label}
                  </button>
                ))}
              </div>
            </Field>

            {method && method !== 'cash' && (
              <div className="border border-slate-200 dark:border-slate-800 rounded-xl p-4 mb-4">
                <Field label="Amount (USD)" htmlFor="mm_amount">
                  <Input id="mm_amount" value={`$${centsToAmount(invoice.amount_cents)}`} disabled />
                </Field>
                <Field label={providerLabel} htmlFor="mm_phone">
                  <Input
                    id="mm_phone"
                    type="tel"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    placeholder="0770123456"
                    required
                  />
                </Field>
              </div>
            )}

            {payError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{payError}</p>}

            {method && (
              <div className="flex justify-end">
                <Button type="submit" disabled={paying}>
                  {paying ? 'Processing…' : method === 'cash' ? 'Confirm Cash Payment' : 'Pay Now'}
                </Button>
              </div>
            )}
          </form>
        )}
      </div>
    </>
  );
}
