import { useEffect, useMemo, useState } from 'react';
import { createSubject, deleteSubject, listSubjects, updateSubject } from '../../api/admin';
import { extractErrorMessage } from '../../utils/errors';
import PageHeader from '../../components/PageHeader';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import ConfirmDialog from '../../components/ConfirmDialog';
import TableToolbar from '../../components/TableToolbar';
import { Field, Input } from '../../components/Field';
import { PlusIcon } from '../../components/icons';

export default function SubjectsPage() {
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [search, setSearch] = useState('');

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [editing, setEditing] = useState(null);
  const [editName, setEditName] = useState('');
  const [editCode, setEditCode] = useState('');
  const [editError, setEditError] = useState('');
  const [editSubmitting, setEditSubmitting] = useState(false);

  const [deleting, setDeleting] = useState(null);
  const [deleteBusy, setDeleteBusy] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  const loadSubjects = () => {
    setLoading(true);
    listSubjects()
      .then(setSubjects)
      .catch((err) => setLoadError(extractErrorMessage(err, 'Could not load subjects.')))
      .finally(() => setLoading(false));
  };

  useEffect(loadSubjects, []);

  const filteredSubjects = useMemo(
    () =>
      subjects.filter(
        (subject) =>
          !search ||
          subject.name.toLowerCase().includes(search.toLowerCase()) ||
          subject.code?.toLowerCase().includes(search.toLowerCase()),
      ),
    [subjects, search],
  );

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      await createSubject({ name, code: code || null });
      setName('');
      setCode('');
      setCreateOpen(false);
      loadSubjects();
    } catch (err) {
      setFormError(extractErrorMessage(err, 'Could not create subject.'));
    } finally {
      setSubmitting(false);
    }
  };

  const openEdit = (subject) => {
    setEditing(subject);
    setEditName(subject.name);
    setEditCode(subject.code ?? '');
    setEditError('');
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    setEditError('');
    setEditSubmitting(true);
    try {
      const updated = await updateSubject(editing.id, { name: editName, code: editCode || null });
      setSubjects((prev) => prev.map((s) => (s.id === updated.id ? updated : s)));
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
      await deleteSubject(deleting.id);
      setSubjects((prev) => prev.filter((s) => s.id !== deleting.id));
      setDeleting(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err, `Could not delete ${deleting.name}.`));
    } finally {
      setDeleteBusy(false);
    }
  };

  return (
    <>
      <PageHeader
        title="Subjects"
        description="Subjects taught across the school."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <PlusIcon />
            Add Subject
          </Button>
        }
      />

      <TableToolbar searchValue={search} onSearchChange={setSearch} placeholder="Search by name or code…" />

      {loadError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{loadError}</p>}
      {deleteError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-4">{deleteError}</p>}

      {!loading && filteredSubjects.length === 0 && !loadError && (
        <p className="text-slate-500 dark:text-slate-400 py-8 text-center">No subjects match your search.</p>
      )}

      {filteredSubjects.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800">
              <tr>
                {['Name', 'Code', '', ''].map((heading, index) => (
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
              {filteredSubjects.map((subject) => (
                <tr key={subject.id}>
                  <td className="px-4 py-3 text-slate-900 dark:text-slate-100 font-medium">{subject.name}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{subject.code ?? '—'}</td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(subject)}
                      className="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                    >
                      Edit
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => {
                        setDeleting(subject);
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

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="Add Subject">
        <form onSubmit={handleSubmit}>
          <Field label="Name" htmlFor="name">
            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
          </Field>
          <Field label="Code" htmlFor="code">
            <Input id="code" value={code} onChange={(e) => setCode(e.target.value)} placeholder="MATH" />
          </Field>

          {formError && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{formError}</p>}

          <div className="flex justify-end gap-3 mt-2">
            <Button type="button" variant="secondary" onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Adding…' : 'Add Subject'}
            </Button>
          </div>
        </form>
      </Modal>

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={`Edit ${editing?.name ?? ''}`}>
        <form onSubmit={saveEdit}>
          <Field label="Name" htmlFor="edit_name">
            <Input id="edit_name" value={editName} onChange={(e) => setEditName(e.target.value)} required />
          </Field>
          <Field label="Code" htmlFor="edit_code">
            <Input id="edit_code" value={editCode} onChange={(e) => setEditCode(e.target.value)} placeholder="MATH" />
          </Field>

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

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        busy={deleteBusy}
        title="Delete subject"
        message={
          deleting
            ? `This removes "${deleting.name}" and unassigns it from any classes it's attached to. This can't be undone.`
            : ''
        }
      />
    </>
  );
}
