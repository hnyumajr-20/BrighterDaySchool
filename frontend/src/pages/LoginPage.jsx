import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { extractErrorMessage } from '../utils/errors';
import { MailIcon, LockIcon, EyeIcon, EyeOffIcon } from '../components/icons';
import AuthSplitLayout from '../components/AuthSplitLayout';
import Button from '../components/Button';

const fieldInputClasses =
  'w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 pl-10 pr-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500';

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [usernameOrEmail, setUsernameOrEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      const { mustChangePassword } = await login(usernameOrEmail, password);
      navigate(mustChangePassword ? '/change-password' : '/dashboard');
    } catch (err) {
      setError(extractErrorMessage(err, 'Could not log in. Check your details and try again.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthSplitLayout>
      <form onSubmit={handleSubmit} className="w-full max-w-sm">
        <h2 className="font-serif text-2xl font-bold text-slate-900 dark:text-slate-100 mb-1">
          Sign in to your account
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
          Enter your credentials to access your dashboard.
        </p>

        <label
          htmlFor="username_or_email"
          className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1"
        >
          Username or email
        </label>
        <div className="relative mb-4">
          <MailIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            id="username_or_email"
            type="text"
            value={usernameOrEmail}
            onChange={(event) => setUsernameOrEmail(event.target.value)}
            required
            className={fieldInputClasses}
          />
        </div>

        <div className="flex items-center justify-between mb-1">
          <label
            htmlFor="password"
            className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
          >
            Password
          </label>
          <Link to="/forgot-password" className="text-xs text-primary-600 dark:text-primary-400 hover:underline">
            Forgot password?
          </Link>
        </div>
        <div className="relative mb-2">
          <LockIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            id="password"
            type={showPassword ? 'text' : 'password'}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
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
          {submitting ? 'Signing in…' : 'Sign in →'}
        </Button>
      </form>
    </AuthSplitLayout>
  );
}
