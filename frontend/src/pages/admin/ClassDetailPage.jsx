import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
  createClassSubject,
  deleteClassSubject,
  listClasses,
  listClassSubjects,
  listStaff,
  listSubjects,
  updateClassSubject,
} from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import ConfirmDialog from '../../components/ConfirmDialog';
import { Field, Select } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

function centsToAmount(cents) {
  return (cents / 100).toFixed(2);
}

export default function ClassDetailPage() {
  const { classId } = useParams();

  const [schoolClass, setSchoolClass] = useState(null);
  const [academicYears, setAcademicYears] = useState([]);
  const [assignments, setAssignments] = useState([]);
  const [subjects, setSubjects] = useState([]);
  const [teachers, setTeachers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [assignOpen, setAssignOpen] = useState(false);
  const [assignSubjectId, setAssignSubjectId] = useState('');
  const [assignTeacherId, setAssignTeacherId] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [editingAssignment, setEditingAssignment] = useState(null);
  const [editTeacherId, setEditTeacherId] = useState('');
  const [editError, setEditError] = useState('');
  const [editSubmitting, setEditSubmitting] = useState(false);

  const [removing, setRemoving] = useState(null);
  const [removeBusy, setRemoveBusy] = useState(false);
  const [removeError, setRemoveError] = useState('');

  const load = () => {
    setLoading(true);
    Promise.all([listClasses(), listClassSubjects(classId), listSubjects(), listStaff()])
      .then(([classes, classSubjects, subjectList, staffList]) => {
        setSchoolClass(classes.find((c) => String(c.id) === classId) ?? null);
        setAssignments(classSubjects);
        setSubjects(subjectList);
        setTeachers(staffList.filter((member) => member.staff_role === 'teacher'));
      })
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load this class.')))
      .finally(() => setLoading(false));
  };

  useEffect(load, [classId]);

  const unassignedSubjects = useMemo(() => {
    const assignedIds = new Set(assignments.map((a) => a.subject_id));
    return subjects.filter((s) => !assignedIds.has(s.id));
  }, [assignments, subjects]);

  const openAssign = () => {
    setFormError('');
    setAssignSubjectId(unassignedSubjects[0] ? String(unassignedSubjects[0].id) : '');
    setAssignTeacherId('');
    setAssignOpen(true);
  };

  const handleAssign = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      await createClassSubject(classId, {
        subject_id: Number(assignSubjectId),
        teacher_id: assignTeacherId ? Number(assignTeacherId) : null,
      });
      setAssignOpen(false);
      load();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not assign that subject.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openEditTeacher = (assignment) => {
    setEditingAssignment(assignment);
    setEditTeacherId(assignment.teacher_id ? String(assignment.teacher_id) : '');
    setEditError('');
  };

  const saveTeacher = async (event) => {
    event.preventDefault();
    setEditError('');
    setEditSubmitting(true);
    try {
      const updated = await updateClassSubject(classId, editingAssignment.id, {
        teacher_id: editTeacherId ? Number(editTeacherId) : null,
      });
      setAssignments((prev) => prev.map((a) => (a.id === updated.id ? updated : a)));
      setEditingAssignment(null);
    } catch (err) {
      setEditError(extractErrorMessage(err, 'Could not change the teacher.'));
    } finally {
      setEditSubmitting(false);
    }
  };

  const confirmRemove = async () => {
    setRemoveError('');
    setRemoveBusy(true);
    try {
      await deleteClassSubject(classId, removing.id);
      setAssignments((prev) => prev.filter((a) => a.id !== removing.id));
      setRemoving(null);
    } catch (err) {
      setRemoveError(extractErrorMessage(err, 'Could not remove that subject.'));
    } finally {
      setRemoveBusy(false);
    }
  };

  return (
    <>
      <p className="text-sm text-slate-500 dark:text-slate-400 mb-2">
        <Link to="/dashboard/classes" className="text-primary-600 dark:text-primary-400 hover:underline">
          Classes
        </Link>{' '}
        / {schoolClass ? `${schoolClass.name} ${schoolClass.arm}` : `#${classId}`}
      </p>

      <PageHeader
        title={schoolClass ? `${schoolClass.name} ${schoolClass.arm}` : 'Class'}
        description={schoolClass ? `Fee: ${centsToAmount(schoolClass.fee_amount_cents)}` : ''}
        actions={
          <Button onClick={openAssign} disabled={unassignedSubjects.length === 0}>
            <PlusIcon />
            Assign Subject
          </Button>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}
      {removeError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{removeError}</p>}

      {!loading && !schoolClass && !loadError && (
        <p className="text-slate-500 dark:text-slate-400">This class couldn&apos;t be found.</p>
      )}

      {!loading && assignments.length === 0 && !loadError && schoolClass && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">
          No subjects assigned to this class yet.
        </p>
      )}

      {assignments.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Subject', 'Code', 'Teacher', '', ''].map((heading, index) => (
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
              {assignments.map((assignment) => (
                <tr key={assignment.id}>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">
                    {assignment.subject?.name}
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{assignment.subject?.code ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                    {assignment.teacher?.full_name ?? (
                      <span className="text-slate-400 dark:text-slate-500">Unassigned</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEditTeacher(assignment)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Change Teacher
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => {
                        setRemoving(assignment);
                        setRemoveError('');
                      }}
                      className="text-rose-600 dark:text-rose-400 hover:underline font-medium"
                    >
                      Remove
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={assignOpen} onClose={() => setAssignOpen(false)} title="Assign Subject">
        <form onSubmit={handleAssign}>
          <Field label="Subject" htmlFor="assign_subject">
            <Select id="assign_subject" value={assignSubjectId} onChange={(e) => setAssignSubjectId(e.target.value)}>
              {unassignedSubjects.map((subject) => (
                <option key={subject.id} value={subject.id}>
                  {subject.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Teacher" htmlFor="assign_teacher">
            <Select id="assign_teacher" value={assignTeacherId} onChange={(e) => setAssignTeacherId(e.target.value)}>
              <option value="">Unassigned for now</option>
              {teachers.map((teacher) => (
                <option key={teacher.id} value={teacher.id}>
                  {teacher.full_name}
                </option>
              ))}
            </Select>
          </Field>

          {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setAssignOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting || !assignSubjectId}>
              {submitting ? 'Assigning…' : 'Assign Subject'}
            </Button>
          </div>
        </form>
      </Modal>

      <Modal
        open={editingAssignment !== null}
        onClose={() => setEditingAssignment(null)}
        title={`Teacher for ${editingAssignment?.subject?.name ?? ''}`}
      >
        <form onSubmit={saveTeacher}>
          <Field label="Teacher" htmlFor="edit_teacher">
            <Select id="edit_teacher" value={editTeacherId} onChange={(e) => setEditTeacherId(e.target.value)}>
              <option value="">Unassigned</option>
              {teachers.map((teacher) => (
                <option key={teacher.id} value={teacher.id}>
                  {teacher.full_name}
                </option>
              ))}
            </Select>
          </Field>

          {editError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{editError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setEditingAssignment(null)}>
              Cancel
            </Button>
            <Button type="submit" disabled={editSubmitting}>
              {editSubmitting ? 'Saving…' : 'Save'}
            </Button>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={removing !== null}
        onClose={() => setRemoving(null)}
        onConfirm={confirmRemove}
        busy={removeBusy}
        title="Remove subject"
        message={
          removing ? `This removes ${removing.subject?.name} from ${schoolClass?.name} ${schoolClass?.arm}.` : ''
        }
      />
    </>
  );
}
