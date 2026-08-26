import { useState } from 'react';
import { useAuth } from '../auth/AuthContext';
import { extractErrorMessage } from '../utils/errors';
import PageHeader from '../components/PageHeader';
import Button from '../components/Button';
import { Field, Input } from '../components/Field';

export default function SettingsPage() {
  const { changePassword } = useAuth();
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const reset = () => {
    setOldPassword('');
    setNewPassword('');
    setConfirmPassword('');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setSuccess(false);

    if (newPassword !== confirmPassword) {
      setError('New password and confirmation do not match.');
      return;
    }

    setSubmitting(true);
    try {
      await changePassword(oldPassword, newPassword);
      reset();
      setSuccess(true);
    } catch (err) {
      setError(extractErrorMessage(err, 'Could not change your password.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageHeader title="Settings" description="Manage your account." />

      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 max-w-md">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-1">Change password</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-5">
          Enter your current password, then choose a new one.
        </p>

        <form onSubmit={handleSubmit}>
          <Field label="Current password" htmlFor="old_password">
            <Input
              id="old_password"
              type="password"
              value={oldPassword}
              onChange={(e) => setOldPassword(e.target.value)}
              required
            />
          </Field>
          <Field label="New password" htmlFor="new_password">
            <Input
              id="new_password"
              type="password"
              minLength={8}
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              required
            />
          </Field>
          <Field label="Confirm new password" htmlFor="confirm_password">
            <Input
              id="confirm_password"
              type="password"
              minLength={8}
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              required
            />
          </Field>

          {error && <p className="text-sm text-rose-600 dark:text-rose-400 mb-3">{error}</p>}
          {success && <p className="text-sm text-emerald-600 dark:text-emerald-400 mb-3">Password changed.</p>}

          <Button type="submit" disabled={submitting}>
            {submitting ? 'Saving…' : 'Save Password'}
          </Button>
        </form>
      </div>
    </>
  );
}
