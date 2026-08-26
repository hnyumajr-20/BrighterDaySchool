import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  createClass,
  deleteClass,
  listAcademicYears,
  listClasses,
  listClassSubjects,
  updateClass,
} from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import ConfirmDialog from '../../components/ConfirmDialog';
import TableToolbar from '../../components/TableToolbar';
import { Field, Input, Select } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

function centsToAmount(cents) {
  return (cents / 100).toFixed(2);
}

export default function ClassesPage() {
  const [classes, setClasses] = useState([]);
  const [academicYears, setAcademicYears] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [search, setSearch] = useState('');
  const [yearFilter, setYearFilter] = useState('');

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [arm, setArm] = useState('');
  const [fee, setFee] = useState('');
  const [academicYearId, setAcademicYearId] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [editing, setEditing] = useState(null);
  const [editFee, setEditFee] = useState('');
  const [editError, setEditError] = useState('');

  const [deleting, setDeleting] = useState(null);
  const [deleteBusy, setDeleteBusy] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  const [viewing, setViewing] = useState(null);
  const [viewAssignments, setViewAssignments] = useState([]);
  const [viewLoading, setViewLoading] = useState(false);
  const [viewError, setViewError] = useState('');

  const load = () => {
    setLoading(true);
    Promise.all([listClasses(), listAcademicYears()])
      .then(([classList, yearList]) => {
        setClasses(classList);
        setAcademicYears(yearList);
        setAcademicYearId((current) => current || (yearList[0] ? String(yearList[0].id) : ''));
      })
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load classes.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const yearName = (id) => academicYears.find((y) => y.id === id)?.name ?? '—';

  const filteredClasses = useMemo(() => {
    return classes.filter((schoolClass) => {
      const matchesSearch =
        !search ||
        schoolClass.name.toLowerCase().includes(search.toLowerCase()) ||
        schoolClass.arm.toLowerCase().includes(search.toLowerCase());
      const matchesYear = !yearFilter || String(schoolClass.academic_year_id) === yearFilter;
      return matchesSearch && matchesYear;
    });
  }, [classes, search, yearFilter]);

  const resetCreateForm = () => {
    setName('');
    setArm('');
    setFee('');
    setFormError('');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      await createClass({
        name,
        arm,
        fee_amount_cents: Math.round(parseFloat(fee || '0') * 100),
        academic_year_id: Number(academicYearId),
      });
      resetCreateForm();
      setCreateOpen(false);
      load();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create class.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openEdit = (schoolClass) => {
    setEditing(schoolClass);
    setEditFee(centsToAmount(schoolClass.fee_amount_cents));
    setEditError('');
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    setEditError('');
    try {
      const updated = await updateClass(editing.id, {
        fee_amount_cents: Math.round(parseFloat(editFee || '0') * 100),
      });
      setClasses((prev) => prev.map((c) => (c.id === updated.id ? updated : c)));
      setEditing(null);
    } catch (err) {
      setEditError(extractErrorMessage(err, 'Could not save changes.'));
    }
  };

  const openView = (schoolClass) => {
    setViewing(schoolClass);
    setViewError('');
    setViewAssignments([]);
    setViewLoading(true);
    listClassSubjects(schoolClass.id)
      .then(setViewAssignments)
      .catch((err) => setViewError(extractErrorMessage(err, 'Could not load subjects for this class.')))
      .finally(() => setViewLoading(false));
  };

  const confirmDelete = async () => {
    setDeleteError('');
    setDeleteBusy(true);
    try {
      await deleteClass(deleting.id);
      setClasses((prev) => prev.filter((c) => c.id !== deleting.id));
      setDeleting(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err, `Could not delete ${deleting.name} ${deleting.arm}.`));
    } finally {
      setDeleteBusy(false);
    }
  };

  if (!loading && academicYears.length === 0 && !loadError) {
    return (
      <>
        <PageHeader title="Classes" description="Class sections for the current academic setup." />
        <p className="text-slate-500 dark:text-slate-400">
          You need an academic year before you can create a class. Add one on the{' '}
          <Link to="/dashboard/academic-years" className="text-primary-600 dark:text-primary-400 hover:underline">
            Academic Years
          </Link>{' '}
          page first.
        </p>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Classes"
        description="Class sections for the current academic setup — click a class to manage its subjects and teachers."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <PlusIcon />
            Add Class
          </Button>
        }
      />

      <TableToolbar
        searchValue={search}
        onSearchChange={setSearch}
        placeholder="Search by name or arm…"
        filter={
          <Select value={yearFilter} onChange={(e) => setYearFilter(e.target.value)} className="sm:w-48">
            <option value="">All years</option>
            {academicYears.map((year) => (
              <option key={year.id} value={year.id}>
                {year.name}
              </option>
            ))}
          </Select>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}
      {deleteError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{deleteError}</p>}

      {!loading && filteredClasses.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No classes match your search.</p>
      )}

      {filteredClasses.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Name', 'Arm', 'Fee', 'Academic Year', '', '', '', ''].map((heading, index) => (
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
              {filteredClasses.map((schoolClass) => (
                <tr key={schoolClass.id}>
                  <td className="px-4 py-3">
                    <Link
                      to={`/dashboard/classes/${schoolClass.id}`}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      {schoolClass.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{schoolClass.arm}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                    {centsToAmount(schoolClass.fee_amount_cents)}
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                    {yearName(schoolClass.academic_year_id)}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openView(schoolClass)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      View
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Link
                      to={`/dashboard/classes/${schoolClass.id}`}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Subjects
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(schoolClass)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Edit
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => {
                        setDeleting(schoolClass);
                        setDeleteError('');
                      }}
                      className="text-rose-600 dark:text-rose-400 hover:underline font-medium"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal
        open={createOpen}
        onClose={() => {
          setCreateOpen(false);
          resetCreateForm();
        }}
        title="Add Class"
      >
        <form onSubmit={handleSubmit}>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Name" htmlFor="name">
              <Input id="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="JSS1" required />
            </Field>
            <Field label="Arm" htmlFor="arm">
              <Input id="arm" value={arm} onChange={(e) => setArm(e.target.value)} placeholder="A" required />
            </Field>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Fee" htmlFor="fee">
              <Input id="fee" type="number" step="0.01" min="0" value={fee} onChange={(e) => setFee(e.target.value)} required />
            </Field>
            <Field label="Academic Year" htmlFor="academic_year_id">
              <Select id="academic_year_id" value={academicYearId} onChange={(e) => setAcademicYearId(e.target.value)}>
                {academicYears.map((year) => (
                  <option key={year.id} value={year.id}>
                    {year.name}
                  </option>
                ))}
              </Select>
            </Field>
          </div>

          {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Adding…' : 'Add Class'}
            </Button>
          </div>
        </form>
      </Modal>

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={`Edit ${editing?.name ?? ''}`}>
        <form onSubmit={saveEdit}>
          <Field label="Fee" htmlFor="edit_fee">
            <Input
              id="edit_fee"
              type="number"
              step="0.01"
              min="0"
              value={editFee}
              onChange={(e) => setEditFee(e.target.value)}
            />
          </Field>

          {editError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{editError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setEditing(null)}>
              Cancel
            </Button>
            <Button type="submit">Save Changes</Button>
          </div>
        </form>
      </Modal>

      <Modal
        open={viewing !== null}
        onClose={() => setViewing(null)}
        title={viewing ? `${viewing.name} ${viewing.arm}` : ''}
      >
        {viewing && (
          <div>
            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm mb-5">
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Fee</dt>
                <dd className="text-slate-900 dark:text-slate-100">{centsToAmount(viewing.fee_amount_cents)}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Academic year</dt>
                <dd className="text-slate-900 dark:text-slate-100">{yearName(viewing.academic_year_id)}</dd>
              </div>
            </dl>

            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
              Subjects &amp; Teachers
            </p>

            {viewError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{viewError}</p>}

            {!viewLoading && !viewError && viewAssignments.length === 0 && (
              <p className="text-sm text-slate-500 dark:text-slate-400 py-4 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-lg mb-5">
                No subjects assigned to this class yet.
              </p>
            )}

            {viewAssignments.length > 0 && (
              <div className="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden mb-5">
                <table className="w-full text-sm">
                  <thead className="bg-slate-50 dark:bg-slate-800">
                    <tr>
                      <th className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 px-3 py-2">
                        Subject
                      </th>
                      <th className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 px-3 py-2">
                        Teacher
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {viewAssignments.map((assignment) => (
                      <tr key={assignment.id}>
                        <td className="px-3 py-2 text-slate-900 dark:text-slate-100 font-medium">
                          {assignment.subject?.name}
                          {assignment.subject?.code ? ` (${assignment.subject.code})` : ''}
                        </td>
                        <td className="px-3 py-2 text-slate-700 dark:text-slate-300">
                          {assignment.teacher?.full_name ?? (
                            <span className="text-slate-400 dark:text-slate-500">Unassigned</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="flex justify-end gap-3">
              <Link to={`/dashboard/classes/${viewing.id}`}>
                <Button type="button" variant="secondary">
                  Manage Subjects
                </Button>
              </Link>
              <Button type="button" onClick={() => setViewing(null)}>
                Close
              </Button>
            </div>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        busy={deleteBusy}
        title="Delete class"
        message={
          deleting
            ? `This removes ${deleting.name} ${deleting.arm} and its subject/teacher assignments. This can't be undone.`
            : ''
        }
      />
    </>
  );
}
