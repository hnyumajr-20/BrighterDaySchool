import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { createAcademicYear, listAcademicYears, updateAcademicYear } from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import StatusBadge from '../../components/StatusBadge';
import TableToolbar from '../../components/TableToolbar';
import { Field, Input, Select } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

export default function AcademicYearsPage() {
  const [years, setYears] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [search, setSearch] = useState('');

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [editing, setEditing] = useState(null);
  const [editName, setEditName] = useState('');
  const [editStartDate, setEditStartDate] = useState('');
  const [editEndDate, setEditEndDate] = useState('');
  const [editStatus, setEditStatus] = useState('upcoming');
  const [editError, setEditError] = useState('');
  const [editSubmitting, setEditSubmitting] = useState(false);

  const load = () => {
    setLoading(true);
    listAcademicYears()
      .then(setYears)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load academic years.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const filteredYears = useMemo(
    () => years.filter((year) => !search || year.name.toLowerCase().includes(search.toLowerCase())),
    [years, search],
  );

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      await createAcademicYear({ name, start_date: startDate, end_date: endDate });
      setName('');
      setStartDate('');
      setEndDate('');
      setCreateOpen(false);
      load();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create academic year.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openEdit = (year) => {
    setEditing(year);
    setEditName(year.name);
    setEditStartDate(formatDate(year.start_date));
    setEditEndDate(formatDate(year.end_date));
    setEditStatus(year.status);
    setEditError('');
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    setEditError('');
    setEditSubmitting(true);
    try {
      const updated = await updateAcademicYear(editing.id, {
        name: editName,
        start_date: editStartDate,
        end_date: editEndDate,
        status: editStatus,
      });
      setYears((prev) => prev.map((y) => (y.id === updated.id ? updated : y)));
      setEditing(null);
    } catch (err) {
      setEditError(extractErrorMessage(err, 'Could not save changes.'));
    } finally {
      setEditSubmitting(false);
    }
  };

  return (
    <>
      <PageHeader
        title="Academic Years"
        description="Academic years, and the semesters and periods inside them."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <PlusIcon />
            Add Academic Year
          </Button>
        }
      />

      <TableToolbar searchValue={search} onSearchChange={setSearch} placeholder="Search by name…" />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}

      {!loading && filteredYears.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No academic years match your search.</p>
      )}

      {filteredYears.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Name', 'Start', 'End', 'Status', ''].map((heading, index) => (
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
              {filteredYears.map((year) => (
                <tr key={year.id}>
                  <td className="px-4 py-3">
                    <Link
                      to={`/dashboard/academic-years/${year.id}`}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      {year.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{formatDate(year.start_date)}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{formatDate(year.end_date)}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={year.status} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(year)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Edit
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="Add Academic Year">
        <form onSubmit={handleSubmit}>
          <Field label="Name" htmlFor="name">
            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="2026/2027" required />
          </Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Start date" htmlFor="start_date">
              <Input id="start_date" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} required />
            </Field>
            <Field label="End date" htmlFor="end_date">
              <Input id="end_date" type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} required />
            </Field>
          </div>

          {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Adding…' : 'Add Academic Year'}
            </Button>
          </div>
        </form>
      </Modal>

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={`Edit ${editing?.name ?? ''}`}>
        <form onSubmit={saveEdit}>
          <Field label="Name" htmlFor="edit_name">
            <Input id="edit_name" value={editName} onChange={(e) => setEditName(e.target.value)} required />
          </Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Start date" htmlFor="edit_start_date">
              <Input
                id="edit_start_date"
                type="date"
                value={editStartDate}
                onChange={(e) => setEditStartDate(e.target.value)}
                required
              />
            </Field>
            <Field label="End date" htmlFor="edit_end_date">
              <Input
                id="edit_end_date"
                type="date"
                value={editEndDate}
                onChange={(e) => setEditEndDate(e.target.value)}
                required
              />
            </Field>
          </div>
          <Field label="Status" htmlFor="edit_status">
            <Select id="edit_status" value={editStatus} onChange={(e) => setEditStatus(e.target.value)}>
              <option value="upcoming">Upcoming</option>
              <option value="active">Active (open)</option>
              <option value="closed">Closed</option>
            </Select>
          </Field>
          <p className="text-xs text-slate-500 dark:text-slate-400 -mt-2 mb-4">
            Years normally open and close automatically as their semesters do. Set this manually only to override
            that.
          </p>

          {editError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{editError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setEditing(null)}>
              Cancel
            </Button>
            <Button type="submit" disabled={editSubmitting}>
              {editSubmitting ? 'Saving…' : 'Save Changes'}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}
