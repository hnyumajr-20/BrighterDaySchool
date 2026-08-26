import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { createPeriod, listPeriods, listSemesters, updatePeriod } from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import StatusBadge from '../../components/StatusBadge';
import { Field, Input, Select } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

export default function PeriodsPage() {
  const { yearId, semesterId } = useParams();
  const [semester, setSemester] = useState(null);
  const [periods, setPeriods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [sequence, setSequence] = useState('1');
  const [isExamPeriod, setIsExamPeriod] = useState(false);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [editing, setEditing] = useState(null);
  const [editName, setEditName] = useState('');
  const [editStartDate, setEditStartDate] = useState('');
  const [editEndDate, setEditEndDate] = useState('');
  const [editIsExamPeriod, setEditIsExamPeriod] = useState(false);
  const [editStatus, setEditStatus] = useState('upcoming');
  const [editError, setEditError] = useState('');
  const [editSubmitting, setEditSubmitting] = useState(false);

  const load = () => {
    setLoading(true);
    Promise.all([listSemesters(yearId), listPeriods(semesterId)])
      .then(([semesters, periodList]) => {
        setSemester(semesters.find((s) => String(s.id) === semesterId) ?? null);
        setPeriods(periodList);
      })
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load periods.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, [yearId, semesterId]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      await createPeriod({
        semester_id: Number(semesterId),
        name,
        sequence: Number(sequence),
        is_exam_period: isExamPeriod,
        start_date: startDate,
        end_date: endDate,
      });
      setName('');
      setStartDate('');
      setEndDate('');
      setIsExamPeriod(false);
      setCreateOpen(false);
      load();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create period.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openEdit = (period) => {
    setEditing(period);
    setEditName(period.name);
    setEditStartDate(formatDate(period.start_date));
    setEditEndDate(formatDate(period.end_date));
    setEditIsExamPeriod(period.is_exam_period);
    setEditStatus(period.status);
    setEditError('');
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    setEditError('');
    setEditSubmitting(true);
    try {
      const updated = await updatePeriod(editing.id, {
        name: editName,
        start_date: editStartDate,
        end_date: editEndDate,
        is_exam_period: editIsExamPeriod,
        status: editStatus,
      });
      setPeriods((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
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
        /{' '}
        <Link
          to={`/dashboard/academic-years/${yearId}`}
          className="text-primary-600 dark:text-primary-400 hover:underline"
        >
          Semesters
        </Link>{' '}
        / {semester?.name ?? `#${semesterId}`}
      </p>

      <PageHeader
        title="Periods"
        description="Periods within this semester."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <PlusIcon />
            Add Period
          </Button>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}

      {!loading && periods.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No periods yet for this semester.</p>
      )}

      {periods.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Name', 'Sequence', 'Exam?', 'Start', 'End', 'Status', ''].map((heading, index) => (
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
              {periods.map((period) => (
                <tr key={period.id}>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{period.name}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{period.sequence}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{period.is_exam_period ? 'Yes' : 'No'}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{formatDate(period.start_date)}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{formatDate(period.end_date)}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={period.status} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(period)}
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

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="Add Period">
        <form onSubmit={handleSubmit}>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Name" htmlFor="name">
              <Input id="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Period 1" required />
            </Field>
            <Field label="Sequence" htmlFor="sequence">
              <Select id="sequence" value={sequence} onChange={(e) => setSequence(e.target.value)}>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3 (exam)</option>
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
          <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 mb-4">
            <input
              type="checkbox"
              checked={isExamPeriod}
              onChange={(e) => setIsExamPeriod(e.target.checked)}
              className="rounded border-slate-300 dark:border-slate-600"
            />
            Exam period
          </label>

          {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Adding…' : 'Add Period'}
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
          <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 mb-4">
            <input
              type="checkbox"
              checked={editIsExamPeriod}
              onChange={(e) => setEditIsExamPeriod(e.target.checked)}
              className="rounded border-slate-300 dark:border-slate-600"
            />
            Exam period
          </label>
          <Field label="Status" htmlFor="edit_status">
            <Select id="edit_status" value={editStatus} onChange={(e) => setEditStatus(e.target.value)}>
              <option value="upcoming">Upcoming</option>
              <option value="active">Active (open)</option>
              <option value="closed">Closed</option>
            </Select>
          </Field>
          <p className="text-xs text-slate-500 dark:text-slate-400 -mt-2 mb-4">
            Periods normally open and close automatically by date. Closing this manually also closes the semester
            once all of its periods (through the exam period) are closed, and the year once both semesters are.
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
