import { useEffect, useMemo, useState } from 'react';
import { createStaff, deleteStaff, downloadStaffCv, listStaff, updateStaff } from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import { triggerBlobDownload } from '../../utils/download';
import { formatDate } from '../../utils/format';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import ConfirmDialog from '../../components/ConfirmDialog';
import TableToolbar from '../../components/TableToolbar';
import { Field, Input, Select } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

const STAFF_ROLES = ['registrar', 'accountant', 'teacher', 'librarian'];

function centsToAmount(cents) {
  return (cents / 100).toFixed(2);
}

function initialsOf(fullName) {
  return fullName
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

function Avatar({ member, size = 'w-9 h-9' }) {
  if (member.photo_url) {
    return (
      <img src={member.photo_url} alt={member.full_name} className={`${size} rounded-full object-cover flex-shrink-0`} />
    );
  }
  return (
    <span
      className={`${size} rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 flex items-center justify-center text-xs font-semibold flex-shrink-0`}
    >
      {initialsOf(member.full_name)}
    </span>
  );
}

export default function StaffPage() {
  const [staff, setStaff] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');

  const [createOpen, setCreateOpen] = useState(false);
  const [fullName, setFullName] = useState('');
  const [dob, setDob] = useState('');
  const [gender, setGender] = useState('');
  const [email, setEmail] = useState('');
  const [contact, setContact] = useState('');
  const [address, setAddress] = useState('');
  const [staffRole, setStaffRole] = useState('teacher');
  const [salary, setSalary] = useState('');
  const [rfidUid, setRfidUid] = useState('');
  const [photoFile, setPhotoFile] = useState(null);
  const [cvFile, setCvFile] = useState(null);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [editing, setEditing] = useState(null);
  const [editContact, setEditContact] = useState('');
  const [editAddress, setEditAddress] = useState('');
  const [editRole, setEditRole] = useState('teacher');
  const [editStatus, setEditStatus] = useState('active');
  const [editSalary, setEditSalary] = useState('');
  const [editRfidUid, setEditRfidUid] = useState('');
  const [editError, setEditError] = useState('');
  const [editSubmitting, setEditSubmitting] = useState(false);

  const [cvError, setCvError] = useState('');

  const [viewing, setViewing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [deleteBusy, setDeleteBusy] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  const loadStaff = () => {
    setLoading(true);
    listStaff()
      .then(setStaff)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load staff.')))
      .finally(() => setLoading(false));
  };

  useEffect(loadStaff, []);

  const filteredStaff = useMemo(() => {
    return staff.filter((member) => {
      const matchesSearch =
        !search ||
        member.full_name.toLowerCase().includes(search.toLowerCase()) ||
        member.email?.toLowerCase().includes(search.toLowerCase()) ||
        member.staff_no?.toLowerCase().includes(search.toLowerCase());
      const matchesRole = !roleFilter || member.staff_role === roleFilter;
      return matchesSearch && matchesRole;
    });
  }, [staff, search, roleFilter]);

  const resetCreateForm = () => {
    setFullName('');
    setDob('');
    setGender('');
    setEmail('');
    setContact('');
    setAddress('');
    setStaffRole('teacher');
    setSalary('');
    setRfidUid('');
    setPhotoFile(null);
    setCvFile(null);
    setFormError('');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('full_name', fullName);
      formData.append('dob', dob);
      if (gender) formData.append('gender', gender);
      formData.append('email', email);
      formData.append('contact', contact);
      if (address) formData.append('address', address);
      formData.append('staff_role', staffRole);
      formData.append('salary_cents', Math.round(parseFloat(salary || '0') * 100));
      if (rfidUid) formData.append('rfid_uid', rfidUid);
      formData.append('photo', photoFile);
      formData.append('cv', cvFile);

      await createStaff(formData);
      resetCreateForm();
      setCreateOpen(false);
      loadStaff();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create staff member.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openEdit = (member) => {
    setEditing(member);
    setEditContact(member.contact ?? '');
    setEditAddress(member.address ?? '');
    setEditRole(member.staff_role);
    setEditStatus(member.status);
    setEditSalary(centsToAmount(member.salary_cents));
    setEditRfidUid(member.rfid_uid ?? '');
    setEditError('');
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    setEditError('');
    setEditSubmitting(true);
    try {
      const updated = await updateStaff(editing.id, {
        contact: editContact,
        address: editAddress || null,
        staff_role: editRole,
        status: editStatus,
        salary_cents: Math.round(parseFloat(editSalary || '0') * 100),
        rfid_uid: editRfidUid || null,
      });
      setStaff((prev) => prev.map((m) => (m.id === updated.id ? { ...m, ...updated } : m)));
      setEditing(null);
    } catch (err) {
      setEditError(extractErrorMessage(err, 'Could not save changes.'));
    } finally {
      setEditSubmitting(false);
    }
  };

  const handleDownloadCv = async (member) => {
    setCvError('');
    try {
      const blob = await downloadStaffCv(member.id);
      triggerBlobDownload(blob, `${member.staff_no}-cv.pdf`);
    } catch (err) {
      setCvError(extractErrorMessage(err, `Could not download ${member.full_name}'s CV.`));
    }
  };

  const confirmDelete = async () => {
    setDeleteError('');
    setDeleteBusy(true);
    try {
      await deleteStaff(deleting.id);
      setStaff((prev) => prev.filter((m) => m.id !== deleting.id));
      setDeleting(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err, `Could not delete ${deleting.full_name}.`));
    } finally {
      setDeleteBusy(false);
    }
  };

  return (
    <>
      <PageHeader
        title="Staff"
        description="Everyone employed at Brighter Day, and their login access."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <PlusIcon />
            Add Staff Member
          </Button>
        }
      />

      <TableToolbar
        searchValue={search}
        onSearchChange={setSearch}
        placeholder="Search by name, email, or staff no…"
        filter={
          <Select value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)} className="sm:w-48">
            <option value="">All roles</option>
            {STAFF_ROLES.map((role) => (
              <option key={role} value={role}>
                {role}
              </option>
            ))}
          </Select>
        }
      />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}
      {cvError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{cvError}</p>}

      {!loading && filteredStaff.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No staff match your search.</p>
      )}

      {filteredStaff.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['', 'Staff No.', 'Name', 'Role', 'Email', 'Salary', 'Status', '', '', '', ''].map((heading, index) => (
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
              {filteredStaff.map((member) => (
                <tr key={member.id}>
                  <td className="pl-4 py-3">
                    <Avatar member={member} />
                  </td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{member.staff_no}</td>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{member.full_name}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 capitalize">{member.staff_role}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{member.email}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{centsToAmount(member.salary_cents)}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300 capitalize">{member.status}</td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => handleDownloadCv(member)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      CV
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => setViewing(member)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      View
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(member)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Edit
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => {
                        setDeleting(member);
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
        title="Add Staff Member"
      >
        <form onSubmit={handleSubmit}>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Full name" htmlFor="full_name">
              <Input id="full_name" value={fullName} onChange={(e) => setFullName(e.target.value)} required />
            </Field>
            <Field label="Date of birth" htmlFor="dob">
              <Input id="dob" type="date" value={dob} onChange={(e) => setDob(e.target.value)} required />
            </Field>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Gender" htmlFor="gender">
              <Select id="gender" value={gender} onChange={(e) => setGender(e.target.value)}>
                <option value="">Prefer not to say</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </Select>
            </Field>
            <Field label="Email" htmlFor="email">
              <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            </Field>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Phone" htmlFor="contact">
              <Input id="contact" value={contact} onChange={(e) => setContact(e.target.value)} required />
            </Field>
            <Field label="Role" htmlFor="staff_role">
              <Select id="staff_role" value={staffRole} onChange={(e) => setStaffRole(e.target.value)}>
                {STAFF_ROLES.map((role) => (
                  <option key={role} value={role}>
                    {role}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Salary" htmlFor="salary">
              <Input
                id="salary"
                type="number"
                step="0.01"
                min="0"
                value={salary}
                onChange={(e) => setSalary(e.target.value)}
                required
              />
            </Field>
            <Field label="RFID card UID (optional)" htmlFor="rfid_uid">
              <Input
                id="rfid_uid"
                value={rfidUid}
                onChange={(e) => setRfidUid(e.target.value)}
                placeholder="Scan or leave blank"
              />
            </Field>
          </div>
          <Field label="Address" htmlFor="address">
            <textarea
              id="address"
              value={address}
              onChange={(e) => setAddress(e.target.value)}
              rows={2}
              className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Photo (JPG/PNG, max 2MB)" htmlFor="photo">
              <input
                id="photo"
                type="file"
                accept="image/jpeg,image/png"
                onChange={(e) => setPhotoFile(e.target.files[0] ?? null)}
                required
                className="w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 dark:file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-medium"
              />
            </Field>
            <Field label="CV (PDF, max 5MB)" htmlFor="cv">
              <input
                id="cv"
                type="file"
                accept="application/pdf"
                onChange={(e) => setCvFile(e.target.files[0] ?? null)}
                required
                className="w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 dark:file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-medium"
              />
            </Field>
          </div>

          {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Adding…' : 'Add Staff Member'}
            </Button>
          </div>
        </form>
      </Modal>

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={`Edit ${editing?.full_name ?? ''}`}>
        {editing && (
          <form onSubmit={saveEdit}>
            <div className="flex items-center gap-4 mb-5 pb-5 border-b border-slate-200 dark:border-slate-800">
              <Avatar member={editing} size="w-14 h-14" />
              <div className="text-sm">
                <p className="font-semibold text-slate-900 dark:text-slate-100">{editing.full_name}</p>
                <p className="text-slate-500 dark:text-slate-400">{editing.staff_no}</p>
                <p className="text-slate-500 dark:text-slate-400">
                  {formatDate(editing.dob)} · <span className="capitalize">{editing.gender ?? '—'}</span>
                </p>
                <p className="text-slate-500 dark:text-slate-400">{editing.email}</p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <Field label="Phone" htmlFor="edit_contact">
                <Input id="edit_contact" value={editContact} onChange={(e) => setEditContact(e.target.value)} required />
              </Field>
              <Field label="Role" htmlFor="edit_role">
                <Select id="edit_role" value={editRole} onChange={(e) => setEditRole(e.target.value)}>
                  {STAFF_ROLES.map((role) => (
                    <option key={role} value={role}>
                      {role}
                    </option>
                  ))}
                </Select>
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

            <div className="grid grid-cols-2 gap-4">
              <Field label="Salary" htmlFor="edit_salary">
                <Input
                  id="edit_salary"
                  type="number"
                  step="0.01"
                  min="0"
                  value={editSalary}
                  onChange={(e) => setEditSalary(e.target.value)}
                />
              </Field>
              <Field label="Status" htmlFor="edit_status">
                <Select id="edit_status" value={editStatus} onChange={(e) => setEditStatus(e.target.value)}>
                  <option value="active">active</option>
                  <option value="inactive">inactive</option>
                </Select>
              </Field>
            </div>

            <Field label="RFID card UID" htmlFor="edit_rfid_uid">
              <Input
                id="edit_rfid_uid"
                value={editRfidUid}
                onChange={(e) => setEditRfidUid(e.target.value)}
                placeholder="Scan or leave blank"
              />
            </Field>

            <p className="text-xs text-slate-500 dark:text-slate-400 -mt-2 mb-4">
              Setting status to inactive immediately signs this person out and blocks them from logging back in,
              until they&apos;re set to active again. Changing role also changes what they can access.
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
        )}
      </Modal>

      <Modal open={viewing !== null} onClose={() => setViewing(null)} title={viewing?.full_name ?? ''}>
        {viewing && (
          <div>
            <div className="flex items-center gap-4 mb-5">
              <Avatar member={viewing} size="w-16 h-16" />
              <div>
                <p className="font-semibold text-slate-900 dark:text-slate-100">{viewing.full_name}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400 capitalize">
                  {viewing.staff_role} · {viewing.staff_no}
                </p>
              </div>
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm mb-5">
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Date of birth</dt>
                <dd className="text-slate-900 dark:text-slate-100">{formatDate(viewing.dob)}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Gender</dt>
                <dd className="text-slate-900 dark:text-slate-100 capitalize">{viewing.gender ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Email</dt>
                <dd className="text-slate-900 dark:text-slate-100">{viewing.email}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Phone</dt>
                <dd className="text-slate-900 dark:text-slate-100">{viewing.contact ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Salary</dt>
                <dd className="text-slate-900 dark:text-slate-100">{centsToAmount(viewing.salary_cents)}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Status</dt>
                <dd className="text-slate-900 dark:text-slate-100 capitalize">{viewing.status}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">RFID card</dt>
                <dd className="text-slate-900 dark:text-slate-100">
                  {viewing.rfid_uid ?? <span className="text-slate-400 dark:text-slate-500">Not assigned</span>}
                </dd>
              </div>
              <div className="col-span-2">
                <dt className="text-slate-500 dark:text-slate-400">Address</dt>
                <dd className="text-slate-900 dark:text-slate-100">{viewing.address ?? '—'}</dd>
              </div>
            </dl>

            <div className="flex justify-end gap-3">
              <Button type="button" variant="secondary" onClick={() => handleDownloadCv(viewing)}>
                Download CV
              </Button>
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
        title="Delete staff member"
        message={
          deleting
            ? `This permanently removes ${deleting.full_name}'s staff record and signs them out. Their login access is also revoked. This can't be undone — if you just want to pause their access instead, use Edit → Status → inactive.`
            : ''
        }
      />
      {deleteError && <p className="text-sm text-rose-600 dark:text-rose-400 mt-4">{deleteError}</p>}
    </>
  );
}
