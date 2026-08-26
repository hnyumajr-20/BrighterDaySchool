import { useEffect, useMemo, useState } from 'react';
import { listInvoices } from '../../api/invoices';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate, formatTime } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import TableToolbar from '../../components/TableToolbar';
import { Select } from '../../components/Field';

const METHOD_LABELS = {
  cash: 'Cash',
  orange_money: 'Orange Money',
  lonestar_mtn: 'Lonestar MTN',
};

function centsToAmount(cents) {
  return (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function FinancialReportPage() {
  const [invoices, setInvoices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [search, setSearch] = useState('');
  const [methodFilter, setMethodFilter] = useState('');

  useEffect(() => {
    setLoading(true);
    listInvoices({ status: 'paid' })
      .then(setInvoices)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load the financial report.')))
      .finally(() => setLoading(false));
  }, []);

  const filtered = useMemo(() => {
    return invoices.filter((invoice) => {
      const q = search.toLowerCase();
      const matchesSearch =
        !q ||
        invoice.invoice_no.toLowerCase().includes(q) ||
        invoice.student?.full_name?.toLowerCase().includes(q) ||
        invoice.student?.admission_no?.toLowerCase().includes(q);
      const matchesMethod = !methodFilter || invoice.payment_method === methodFilter;
      return matchesSearch && matchesMethod;
    });
  }, [invoices, search, methodFilter]);

  return (
    <>
      <PageHeader title="Financial Report" description="Read-only record of every confirmed payment across the school." />

      <TableToolbar
        searchValue={search}
        onSearchChange={setSearch}
        placeholder="Search by invoice no., name, or admission no…"
        filter={
          <Select value={methodFilter} onChange={(e) => setMethodFilter(e.target.value)}>
            <option value="">All payment methods</option>
            <option value="cash">Cash</option>
            <option value="orange_money">Orange Money</option>
            <option value="lonestar_mtn">Lonestar MTN</option>
          </Select>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}

      {!loading && filtered.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No payments match your search.</p>
      )}

      {filtered.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {[
                  'Transaction ID',
                  'Student',
                  'Created By',
                  'Confirmed By',
                  'Payment Method',
                  'Gateway Transaction ID',
                  'Date/Time',
                ].map((heading) => (
                  <th
                    key={heading}
                    className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 px-4 py-3 whitespace-nowrap"
                  >
                    {heading}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {filtered.map((invoice) => (
                <tr key={invoice.id}>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium whitespace-nowrap">
                    {invoice.invoice_no}
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                    {invoice.student?.full_name} · ${centsToAmount(invoice.amount_cents)}
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                    {invoice.created_by?.full_name ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                    {invoice.confirmed_by?.full_name ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                    {METHOD_LABELS[invoice.payment_method] ?? invoice.payment_method}
                  </td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    {invoice.gateway_transaction_id ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    {formatDate(invoice.paid_at)} {formatTime(invoice.paid_at)}
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
