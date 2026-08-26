import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { createSemester, listAcademicYears, listSemesters, updateSemester } from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import StatusBadge from '../../components/StatusBadge';
import { Field, Input, Select } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

export default function SemestersPage() {
  const { yearId } = useParams();
  const [year, setYear] = useState(null);
  const [semesters, setSemesters] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [sequence, setSequence] = useState('1');
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
    Promise.all([listAcademicYears(), listSemesters(yearId)])
      .then(([years, semesterList]) => {
        setYear(years.find((y) => String(y.id) === yearId) ?? null);
        setSemesters(semesterList);
      })
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load semesters.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, [yearId]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      await createSemester({
        academic_year_id: Number(yearId),
        name,
        sequence: Number(sequence),
        start_date: startDate,
        end_date: endDate,
      });
      setName('');
      setStartDate('');
      setEndDate('');
      setCreateOpen(false);
      load();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create semester.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openEdit = (semester) => {
    setEditing(semester);
    setEditName(semester.name);
    setEditStartDate(formatDate(semester.start_date));
    setEditEndDate(formatDate(semester.end_date));
    setEditStatus(semester.status);
    setEditError('');
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    setEditError('');
    setEditSubmitting(true);
    try {
      const updated = await updateSemester(editing.id, {
        name: editName,
        start_date: editStartDate,
        end_date: editEndDate,
        status: editStatus,
      });
      setSemesters((prev) => prev.map((s) => (s.id === updated.id ? updated : s)));
      setEditing(null);
    } catch (err) {
      setEditError(extractErrorMessage(err, 'Could not save changes.'));
    } finally {
      setEditSubmitting(false);
    }
  };

  return (
    <>
      <p className="text-sm text-slate-500 dark:text-slate-400 mb-2">
        <Link to="/dashboard/academic-years" className="text-primary-600 dark:text-primary-400 hover:underline">
          Academic Years
        </Link>{' '}
        / {year?.name ?? `#${yearId}`}
      </p>

      <PageHeader
        title="Semesters"
        description="Semesters within this academic year."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <PlusIcon />
            Add Semester
          </Button>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}

      {!loading && semesters.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No semesters yet for this academic year.</p>
      )}

      {semesters.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Name', 'Sequence', 'Start', 'End', 'Status', ''].map((heading, index) => (
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
              {semesters.map((semester) => (
                <tr key={semester.id}>
                  <td className="px-4 py-3">
                    <Link
                      to={`/dashboard/academic-years/${yearId}/semesters/${semester.id}`}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      {semester.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{semester.sequence}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{formatDate(semester.start_date)}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{formatDate(semester.end_date)}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={semester.status} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(semester)}
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

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="Add Semester">
        <form onSubmit={handleSubmit}>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Name" htmlFor="name">
              <Input id="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="1st Semester" required />
            </Field>
            <Field label="Sequence" htmlFor="sequence">
              <Select id="sequence" value={sequence} onChange={(e) => setSequence(e.target.value)}>
                <option value="1">1</option>
                <option value="2">2</option>
              </Select>
            </Field>
          </div>
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
              {submitting ? 'Adding…' : 'Add Semester'}
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
            Semesters normally open and close automatically based on their periods. Set this manually only to
            override that.
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
