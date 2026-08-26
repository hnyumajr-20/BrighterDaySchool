import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { extractErrorMessage } from '../utils/errors';
import { LockIcon, EyeIcon, EyeOffIcon } from '../components/icons';
import AuthSplitLayout from '../components/AuthSplitLayout';
import Button from '../components/Button';

const fieldInputClasses =
  'w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 pl-10 pr-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500';

export default function ForcedPasswordChangePage() {
  const { changePassword, logout } = useAuth();
  const navigate = useNavigate();
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await changePassword(oldPassword, newPassword);
      navigate('/dashboard');
    } catch (err) {
      setError(extractErrorMessage(err, 'Could not change your password. Try again.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthSplitLayout>
      <form onSubmit={handleSubmit} className="w-full max-w-sm">
        <h2 className="font-serif text-2xl font-bold text-slate-900 dark:text-slate-100 mb-1">
          Change your password
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
          You must set a new password before continuing.
        </p>

        <label
          htmlFor="old_password"
          className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1"
        >
          Current password
        </label>
        <div className="relative mb-4">
          <LockIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            id="old_password"
            type="password"
            value={oldPassword}
            onChange={(event) => setOldPassword(event.target.value)}
            required
            className={fieldInputClasses}
          />
        </div>

        <label
          htmlFor="new_password"
          className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1"
        >
          New password
        </label>
        <div className="relative mb-2">
          <LockIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            id="new_password"
            type={showPassword ? 'text' : 'password'}
            minLength={8}
            value={newPassword}
            onChange={(event) => setNewPassword(event.target.value)}
            required
            className={fieldInputClasses}
          />
          <button
            type="button"
            onClick={() => setShowPassword((show) => !show)}
            aria-label={showPassword ? 'Hide password' : 'Show password'}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
          >
            {showPassword ? <EyeOffIcon /> : <EyeIcon />}
          </button>
        </div>

        {error && <p className="text-sm text-rose-600 dark:text-rose-400 mb-2">{error}</p>}

        <Button type="submit" disabled={submitting} className="w-full mt-4">
          {submitting ? 'Saving…' : 'Save password'}
        </Button>
        <Button type="button" variant="ghost" onClick={logout} className="w-full mt-2">
          Cancel and sign out
        </Button>
      </form>
    </AuthSplitLayout>
  );
}
