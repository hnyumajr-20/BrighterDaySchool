import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  approveStudent,
  createParent,
  createStudent,
  deleteStudent,
  downloadAdmissionLetter,
  downloadTranscript,
  listAdmissions,
  lookupParentByPhone,
  rejectStudent,
  updateStudent,
  updateStudentClass,
} from '../../api/students';
import { listClasses } from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import { triggerBlobDownload } from '../../utils/download';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import ConfirmDialog from '../../components/ConfirmDialog';
import TableToolbar from '../../components/TableToolbar';
import StatusBadge from '../../components/StatusBadge';
import { Field, Input, Select } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

const STATUS_FILTERS = ['pending', 'approved'];

function initialsOf(fullName) {
  return fullName
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

function Avatar({ student, size = 'w-9 h-9' }) {
  if (student.photo_url) {
    return (
      <img
        src={student.photo_url}
        alt={student.full_name}
        className={`${size} rounded-full object-cover flex-shrink-0`}
      />
    );
  }
  return (
    <span
      className={`${size} rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 flex items-center justify-center text-xs font-semibold flex-shrink-0`}
    >
      {initialsOf(student.full_name)}
    </span>
  );
}

const emptyNewParent = { full_name: '', email: '', address: '' };

export default function AdmissionsPage() {
  const [students, setStudents] = useState([]);
  const [classes, setClasses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const [createOpen, setCreateOpen] = useState(false);

  const [phone, setPhone] = useState('');
  const [parentLookup, setParentLookup] = useState('idle'); // idle | searching | found | not_found
  const [parent, setParent] = useState(null);
  const [newParent, setNewParent] = useState(emptyNewParent);
  const [parentError, setParentError] = useState('');
  const [parentBusy, setParentBusy] = useState(false);

  const [fullName, setFullName] = useState('');
  const [dob, setDob] = useState('');
  const [gender, setGender] = useState('');
  const [email, setEmail] = useState('');
  const [contact, setContact] = useState('');
  const [address, setAddress] = useState('');
  const [classId, setClassId] = useState('');
  const [isTransfer, setIsTransfer] = useState(false);
  const [photoFile, setPhotoFile] = useState(null);
  const [transcriptFile, setTranscriptFile] = useState(null);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [viewing, setViewing] = useState(null);
  const [reassignClassId, setReassignClassId] = useState('');
  const [reassignBusy, setReassignBusy] = useState(false);
  const [reassignError, setReassignError] = useState('');

  const [approving, setApproving] = useState(null);
  const [approveBusy, setApproveBusy] = useState(false);
  const [approveError, setApproveError] = useState('');

  const [rejecting, setRejecting] = useState(null);
  const [rejectBusy, setRejectBusy] = useState(false);
  const [rejectError, setRejectError] = useState('');

  const [editing, setEditing] = useState(null);
  const [editFullName, setEditFullName] = useState('');
  const [editDob, setEditDob] = useState('');
  const [editGender, setEditGender] = useState('');
  const [editEmail, setEditEmail] = useState('');
  const [editContact, setEditContact] = useState('');
  const [editAddress, setEditAddress] = useState('');
  const [editClassId, setEditClassId] = useState('');
  const [editError, setEditError] = useState('');
  const [editSubmitting, setEditSubmitting] = useState(false);

  const [deleting, setDeleting] = useState(null);
  const [deleteBusy, setDeleteBusy] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  const [transcriptError, setTranscriptError] = useState('');

  const loadStudents = () => {
    setLoading(true);
    listAdmissions(statusFilter || undefined)
      .then(setStudents)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load admissions.')))
      .finally(() => setLoading(false));
  };

  useEffect(loadStudents, [statusFilter]);
  useEffect(() => {
    listClasses().then(setClasses).catch(() => {});
  }, []);

  const filteredStudents = useMemo(() => {
    return students.filter((student) => {
      const matchesSearch =
        !search ||
        student.full_name.toLowerCase().includes(search.toLowerCase()) ||
        student.admission_no?.toLowerCase().includes(search.toLowerCase()) ||
        student.email?.toLowerCase().includes(search.toLowerCase());
      return matchesSearch;
    });
  }, [students, search]);

  const resetCreateForm = () => {
    setPhone('');
    setParentLookup('idle');
    setParent(null);
    setNewParent(emptyNewParent);
    setParentError('');
    setFullName('');
    setDob('');
    setGender('');
    setEmail('');
    setContact('');
    setAddress('');
    setClassId('');
    setIsTransfer(false);
    setPhotoFile(null);
    setTranscriptFile(null);
    setFormError('');
  };

  const handleLookupParent = async (event) => {
    event.preventDefault();
    setParentError('');
    setParentBusy(true);
    try {
      const found = await lookupParentByPhone(phone);
      setParent(found);
      setParentLookup('found');
    } catch (err) {
      if (err.response?.status === 404) {
        setParentLookup('not_found');
        setNewParent({ ...emptyNewParent });
      } else {
        setParentError(extractErrorMessage(err, 'Could not look up that phone number.'));
      }
    } finally {
      setParentBusy(false);
    }
  };

  const handleCreateParent = async (event) => {
    event.preventDefault();
    setParentError('');
    setParentBusy(true);
    try {
      const created = await createParent({ ...newParent, phone });
      setParent(created);
      setParentLookup('found');
    } catch (err) {
      setParentError(extractErrorMessage(err, 'Could not save this parent/guardian.'));
    } finally {
      setParentBusy(false);
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('parent_id', parent.id);
      formData.append('full_name', fullName);
      formData.append('dob', dob);
      formData.append('gender', gender);
      formData.append('email', email);
      if (contact) formData.append('contact', contact);
      if (address) formData.append('address', address);
      if (classId) formData.append('class_id', classId);
      formData.append('is_transfer_student', isTransfer ? '1' : '0');
      if (photoFile) formData.append('photo', photoFile);
      if (transcriptFile) formData.append('transcript', transcriptFile);

      await createStudent(formData);
      resetCreateForm();
      setCreateOpen(false);
      loadStudents();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not submit this admission.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openView = (student) => {
    setViewing(student);
    setReassignClassId(student.class_id ?? '');
    setReassignError('');
  };

  const saveReassign = async () => {
    setReassignError('');
    setReassignBusy(true);
    try {
      const updated = await updateStudentClass(viewing.id, reassignClassId);
      setStudents((prev) => prev.map((s) => (s.id === updated.id ? { ...s, ...updated } : s)));
      setViewing((prev) => (prev ? { ...prev, ...updated } : prev));
    } catch (err) {
      setReassignError(extractErrorMessage(err, 'Could not reassign class.'));
    } finally {
      setReassignBusy(false);
    }
  };

  const confirmApprove = async () => {
    setApproveError('');
    setApproveBusy(true);
    try {
      await approveStudent(approving.id);
      setApproving(null);
      loadStudents();
    } catch (err) {
      setApproveError(extractErrorMessage(err, `Could not approve ${approving.full_name}.`));
    } finally {
      setApproveBusy(false);
    }
  };

  const confirmReject = async () => {
    setRejectError('');
    setRejectBusy(true);
    try {
      await rejectStudent(rejecting.id);
      setStudents((prev) => prev.filter((s) => s.id !== rejecting.id));
      setRejecting(null);
    } catch (err) {
      setRejectError(extractErrorMessage(err, `Could not reject ${rejecting.full_name}.`));
    } finally {
      setRejectBusy(false);
    }
  };

  const openEdit = (student) => {
    setEditing(student);
    setEditFullName(student.full_name);
    setEditDob(student.dob ? student.dob.slice(0, 10) : '');
    setEditGender(student.gender ?? '');
    setEditEmail(student.email ?? '');
    setEditContact(student.contact ?? '');
    setEditAddress(student.address ?? '');
    setEditClassId(student.class_id ?? '');
    setEditError('');
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    setEditError('');
    setEditSubmitting(true);
    try {
      const updated = await updateStudent(editing.id, {
        full_name: editFullName,
        dob: editDob,
        gender: editGender,
        email: editEmail,
        contact: editContact || null,
        address: editAddress || null,
        class_id: editClassId || null,
      });
      setStudents((prev) => prev.map((s) => (s.id === updated.id ? { ...s, ...updated } : s)));
      setEditing(null);
    } catch (err) {
      setEditError(extractErrorMessage(err, 'Could not save changes.'));
    } finally {
      setEditSubmitting(false);
    }
  };

  const confirmDelete = async () => {
    setDeleteError('');
    setDeleteBusy(true);
    try {
      await deleteStudent(deleting.id);
      setStudents((prev) => prev.filter((s) => s.id !== deleting.id));
      setDeleting(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err, `Could not delete ${deleting.full_name}'s admission.`));
    } finally {
      setDeleteBusy(false);
    }
  };

  const handleDownloadTranscript = async (student) => {
    setTranscriptError('');
    try {
      const blob = await downloadTranscript(student.id);
      triggerBlobDownload(blob, `${student.full_name}-transcript.pdf`);
    } catch (err) {
      setTranscriptError(extractErrorMessage(err, `Could not download ${student.full_name}'s transcript.`));
    }
  };

  const handleDownloadAdmissionLetter = async (student) => {
    setTranscriptError('');
    try {
      const blob = await downloadAdmissionLetter(student.id);
      triggerBlobDownload(blob, `${student.admission_no}-admission-letter.pdf`);
    } catch (err) {
      setTranscriptError(extractErrorMessage(err, `Could not download ${student.full_name}'s admission letter.`));
    }
  };

  const canSubmit = parentLookup === 'found' && parent;

  return (
    <>
      <PageHeader
        title="Admissions"
        description="Student applications, from intake through approval."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <PlusIcon />
            New Application
          </Button>
        }
      />

      <TableToolbar
        searchValue={search}
        onSearchChange={setSearch}
        placeholder="Search by name, admission no, or email…"
        filter={
          <Select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="sm:w-48">
            <option value="">All statuses</option>
            {STATUS_FILTERS.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </Select>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}
      {transcriptError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{transcriptError}</p>}

      {!loading && filteredStudents.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No applications match your search.</p>
      )}

      {filteredStudents.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['', 'Name', 'Admission No.', 'Class', 'Parent/Guardian', 'Status', '', '', '', '', ''].map(
                  (heading, index) => (
                    <th
                      key={`${heading}-${index}`}
                      className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 px-4 py-3"
                    >
                      {heading}
                    </th>
                  ),
                )}
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
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{student.guardian?.full_name ?? '—'}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={student.status} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openView(student)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      View
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {student.status === 'pending' && (
                      <button
                        type="button"
                        onClick={() => {
                          setApproving(student);
                          setApproveError('');
                        }}
                        className="text-emerald-600 dark:text-emerald-400 hover:underline font-medium"
                      >
                        Approve
                      </button>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {student.status === 'pending' && (
                      <button
                        type="button"
                        onClick={() => {
                          setRejecting(student);
                          setRejectError('');
                        }}
                        className="text-rose-600 dark:text-rose-400 hover:underline font-medium"
                      >
                        Reject
                      </button>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(student)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Edit
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => {
                        setDeleting(student);
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
        title="New Admission Application"
      >
        <div>
          <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Parent/Guardian</h3>

          {parentLookup !== 'found' && (
            <form onSubmit={handleLookupParent} className="mb-4">
              <div className="flex gap-2 items-end">
                <div className="flex-1">
                  <Field label="Phone number" htmlFor="parent_phone">
                    <Input
                      id="parent_phone"
                      value={phone}
                      onChange={(e) => {
                        setPhone(e.target.value);
                        setParentLookup('idle');
                      }}
                      required
                    />
                  </Field>
                </div>
                <Button type="submit" variant="secondary" disabled={parentBusy} className="mb-4">
                  {parentBusy ? 'Looking up…' : 'Look up'}
                </Button>
              </div>
            </form>
          )}

          {parentLookup === 'not_found' && (
            <form onSubmit={handleCreateParent} className="mb-5 pb-5 border-b border-slate-200 dark:border-slate-800">
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-3">
                No parent/guardian found with that phone number. Add them below.
              </p>
              <Field label="Full name" htmlFor="new_parent_name">
                <Input
                  id="new_parent_name"
                  value={newParent.full_name}
                  onChange={(e) => setNewParent((p) => ({ ...p, full_name: e.target.value }))}
                  required
                />
              </Field>
              <div className="grid grid-cols-2 gap-4">
                <Field label="Email (optional)" htmlFor="new_parent_email">
                  <Input
                    id="new_parent_email"
                    type="email"
                    value={newParent.email}
                    onChange={(e) => setNewParent((p) => ({ ...p, email: e.target.value }))}
                  />
                </Field>
                <Field label="Address (optional)" htmlFor="new_parent_address">
                  <Input
                    id="new_parent_address"
                    value={newParent.address}
                    onChange={(e) => setNewParent((p) => ({ ...p, address: e.target.value }))}
                  />
                </Field>
              </div>
              {parentError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{parentError}</p>}
              <Button type="submit" disabled={parentBusy}>
                {parentBusy ? 'Saving…' : 'Save Parent/Guardian'}
              </Button>
            </form>
          )}

          {parentLookup === 'idle' && parentError && (
            <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{parentError}</p>
          )}

          {parentLookup === 'found' && parent && (
            <div className="mb-5 pb-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div className="text-sm">
                <p className="font-medium text-slate-900 dark:text-slate-100">{parent.full_name}</p>
                <p className="text-slate-500 dark:text-slate-400">
                  {parent.phone} {parent.email ? `· ${parent.email}` : ''}
                </p>
              </div>
              <button
                type="button"
                onClick={() => {
                  setParentLookup('idle');
                  setParent(null);
                }}
                className="text-xs text-primary-600 dark:text-primary-400 hover:underline"
              >
                Change
              </button>
            </div>
          )}
        </div>

        {canSubmit && (
          <form onSubmit={handleSubmit}>
            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Bio-data</h3>
            <Field label="Full name" htmlFor="student_full_name">
              <Input id="student_full_name" value={fullName} onChange={(e) => setFullName(e.target.value)} required />
            </Field>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Date of birth" htmlFor="student_dob">
                <Input id="student_dob" type="date" value={dob} onChange={(e) => setDob(e.target.value)} required />
              </Field>
              <Field label="Gender" htmlFor="student_gender">
                <Select id="student_gender" value={gender} onChange={(e) => setGender(e.target.value)} required>
                  <option value="">Select…</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </Select>
              </Field>
            </div>

            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3 mt-5">Contact</h3>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Email" htmlFor="student_email">
                <Input id="student_email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </Field>
              <Field label="Phone (optional)" htmlFor="student_contact">
                <Input id="student_contact" value={contact} onChange={(e) => setContact(e.target.value)} />
              </Field>
            </div>
            <Field label="Address (optional)" htmlFor="student_address">
              <textarea
                id="student_address"
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                rows={2}
                className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </Field>

            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3 mt-5">Academic</h3>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Class (optional)" htmlFor="student_class">
                <Select id="student_class" value={classId} onChange={(e) => setClassId(e.target.value)}>
                  <option value="">Not yet assigned</option>
                  {classes.map((cls) => (
                    <option key={cls.id} value={cls.id}>
                      {cls.name} {cls.arm}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label="Photo (optional)" htmlFor="student_photo">
                <input
                  id="student_photo"
                  type="file"
                  accept="image/jpeg,image/png"
                  onChange={(e) => setPhotoFile(e.target.files[0] ?? null)}
                  className="w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 dark:file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-medium"
                />
              </Field>
            </div>

            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 mb-4">
              <input
                type="checkbox"
                checked={isTransfer}
                onChange={(e) => {
                  setIsTransfer(e.target.checked);
                  if (!e.target.checked) setTranscriptFile(null);
                }}
              />
              This is a transfer student
            </label>

            {isTransfer && (
              <Field label="Transcript (PDF, required for transfer students)" htmlFor="student_transcript">
                <input
                  id="student_transcript"
                  type="file"
                  accept="application/pdf"
                  onChange={(e) => setTranscriptFile(e.target.files[0] ?? null)}
                  required
                  className="w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 dark:file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-medium"
                />
              </Field>
            )}

            {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

            <div className="flex justify-end gap-3 mt-2">
              <Button type="button" variant="secondary" onClick={() => setCreateOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Submitting…' : 'Submit Application'}
              </Button>
            </div>
          </form>
        )}
      </Modal>

      <Modal open={viewing !== null} onClose={() => setViewing(null)} title={viewing?.full_name ?? ''}>
        {viewing && (
          <div>
            <div className="flex items-center gap-4 mb-5">
              <Avatar student={viewing} size="w-16 h-16" />
              <div>
                <p className="font-semibold text-slate-900 dark:text-slate-100">{viewing.full_name}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  {viewing.admission_no ?? 'No admission number yet'} · <StatusBadge status={viewing.status} />
                </p>
                {viewing.admission_no && (
                  <button
                    type="button"
                    onClick={() => handleDownloadAdmissionLetter(viewing)}
                    className="text-xs text-primary-600 dark:text-primary-400 hover:underline"
                  >
                    Download admission letter
                  </button>
                )}
                <Link
                  to={`/dashboard/students/${viewing.id}`}
                  className="block text-xs text-primary-600 dark:text-primary-400 hover:underline"
                >
                  Open full profile & invoices
                </Link>
              </div>
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm mb-5">
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Date of birth</dt>
                <dd className="text-slate-900 dark:text-slate-100">{formatDate(viewing.dob)}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Gender</dt>
                <dd className="text-slate-900 dark:text-slate-100 capitalize">{viewing.gender}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Email</dt>
                <dd className="text-slate-900 dark:text-slate-100">{viewing.email ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Phone</dt>
                <dd className="text-slate-900 dark:text-slate-100">{viewing.contact ?? '—'}</dd>
              </div>
              <div className="col-span-2">
                <dt className="text-slate-500 dark:text-slate-400">Address</dt>
                <dd className="text-slate-900 dark:text-slate-100">{viewing.address ?? '—'}</dd>
              </div>
              <div className="col-span-2">
                <dt className="text-slate-500 dark:text-slate-400">Parent/Guardian</dt>
                <dd className="text-slate-900 dark:text-slate-100">
                  {viewing.guardian?.full_name} · {viewing.guardian?.phone}
                </dd>
              </div>
              {viewing.is_transfer_student && (
                <div className="col-span-2">
                  <dt className="text-slate-500 dark:text-slate-400">Transfer student</dt>
                  <dd className="text-slate-900 dark:text-slate-100">
                    Yes —{' '}
                    <button
                      type="button"
                      onClick={() => handleDownloadTranscript(viewing)}
                      className="text-primary-600 dark:text-primary-400 hover:underline"
                    >
                      download transcript
                    </button>
                  </dd>
                </div>
              )}
            </dl>

            <div className="pt-4 border-t border-slate-200 dark:border-slate-800">
              <Field label="Class" htmlFor="reassign_class">
                <div className="flex gap-2">
                  <Select
                    id="reassign_class"
                    value={reassignClassId}
                    onChange={(e) => setReassignClassId(e.target.value)}
                    className="flex-1"
                  >
                    <option value="">Not assigned</option>
                    {classes.map((cls) => (
                      <option key={cls.id} value={cls.id}>
                        {cls.name} {cls.arm}
                      </option>
                    ))}
                  </Select>
                  <Button type="button" variant="secondary" onClick={saveReassign} disabled={reassignBusy}>
                    {reassignBusy ? 'Saving…' : 'Save'}
                  </Button>
                </div>
              </Field>
              {reassignError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{reassignError}</p>}
            </div>

            <div className="flex justify-end gap-3 mt-2">
              <Button type="button" onClick={() => setViewing(null)}>
                Close
              </Button>
            </div>
          </div>
        )}
      </Modal>

      <Modal open={approving !== null} onClose={() => setApproving(null)} title="Approve application">
        {approving && (
          <div>
            <p className="text-sm text-slate-600 dark:text-slate-300 mb-6">
              This creates a login for {approving.full_name}, generates their admission number, and emails their
              login details and a link to complete registration. This can&apos;t be undone.
            </p>
            {approveError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{approveError}</p>}
            <div className="flex justify-end gap-3">
              <Button type="button" variant="secondary" onClick={() => setApproving(null)}>
                Cancel
              </Button>
              <Button type="button" onClick={confirmApprove} disabled={approveBusy}>
                {approveBusy ? 'Approving…' : 'Approve & Send Login Details'}
              </Button>
            </div>
          </div>
        )}
      </Modal>

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={`Edit ${editing?.full_name ?? ''}`}>
        {editing && (
          <form onSubmit={saveEdit}>
            <Field label="Full name" htmlFor="edit_full_name">
              <Input id="edit_full_name" value={editFullName} onChange={(e) => setEditFullName(e.target.value)} required />
            </Field>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Date of birth" htmlFor="edit_dob">
                <Input id="edit_dob" type="date" value={editDob} onChange={(e) => setEditDob(e.target.value)} required />
              </Field>
              <Field label="Gender" htmlFor="edit_gender">
                <Select id="edit_gender" value={editGender} onChange={(e) => setEditGender(e.target.value)} required>
                  <option value="">Select…</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </Select>
              </Field>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Email" htmlFor="edit_email">
                <Input id="edit_email" type="email" value={editEmail} onChange={(e) => setEditEmail(e.target.value)} required />
              </Field>
              <Field label="Phone" htmlFor="edit_contact">
                <Input id="edit_contact" value={editContact} onChange={(e) => setEditContact(e.target.value)} />
              </Field>
            </div>
            <Field label="Address" htmlFor="edit_address">
              <textarea
                id="edit_address"
                value={editAddress}
                onChange={(e) => setEditAddress(e.target.value)}
                rows={2}
                className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </Field>
            <Field label="Class" htmlFor="edit_class">
              <Select id="edit_class" value={editClassId} onChange={(e) => setEditClassId(e.target.value)}>
                <option value="">Not assigned</option>
                {classes.map((cls) => (
                  <option key={cls.id} value={cls.id}>
                    {cls.name} {cls.arm}
                  </option>
                ))}
              </Select>
            </Field>

            {editing.status === 'approved' && (
              <p className="text-xs text-slate-500 dark:text-slate-400 -mt-2 mb-4">
                Changing the email here also updates their login.
              </p>
            )}

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
        )}
      </Modal>

      <ConfirmDialog
        open={rejecting !== null}
        onClose={() => setRejecting(null)}
        onConfirm={confirmReject}
        busy={rejectBusy}
        confirmLabel="Reject"
        title="Reject application"
        message={
          rejecting
            ? `This rejects and permanently deletes ${rejecting.full_name}'s application, including their photo and transcript. This can't be undone.`
            : ''
        }
      />
      {rejectError && <p className="text-sm text-rose-600 dark:text-rose-400 mt-4">{rejectError}</p>}

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        busy={deleteBusy}
        title="Delete admission"
        message={
          deleting
            ? `This permanently removes ${deleting.full_name}'s admission record${
                deleting.status === 'approved' ? ", and signs them out of their account" : ''
              }. This can't be undone.`
            : ''
        }
      />
      {deleteError && <p className="text-sm text-rose-600 dark:text-rose-400 mt-4">{deleteError}</p>}
    </>
  );
}
