import { useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../api/client';
import { extractErrorMessage } from '../utils/errors';
import { MailIcon } from '../components/icons';
import AuthSplitLayout from '../components/AuthSplitLayout';
import Button from '../components/Button';

const fieldInputClasses =
  'w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 pl-10 pr-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setMessage('');
    setSubmitting(true);
    try {
      const response = await client.post('/auth/forgot-password', { email });
      setMessage(response.data.message);
    } catch (err) {
      setError(extractErrorMessage(err, 'Something went wrong. Try again.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthSplitLayout>
      <form onSubmit={handleSubmit} className="w-full max-w-sm">
        <h2 className="font-serif text-2xl font-bold text-slate-900 dark:text-slate-100 mb-1">Forgot password</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
          Enter your email and we&apos;ll send you a reset token.
        </p>

        <label
          htmlFor="email"
          className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1"
        >
          Email
        </label>
        <div className="relative mb-2">
          <MailIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            id="email"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
            className={fieldInputClasses}
          />
        </div>

        {error && <p className="text-sm text-rose-600 dark:text-rose-400 mb-2">{error}</p>}
        {message && <p className="text-sm text-emerald-600 dark:text-emerald-400 mb-2">{message}</p>}

        <Button type="submit" disabled={submitting} className="w-full mt-4">
          {submitting ? 'Sending…' : 'Send reset token'}
        </Button>

        <div className="flex justify-between mt-4 text-sm">
          <Link to="/reset-password" className="text-primary-600 dark:text-primary-400 hover:underline">
            Already have a token?
          </Link>
          <Link to="/login" className="text-primary-600 dark:text-primary-400 hover:underline">
            Back to sign in
          </Link>
        </div>
      </form>
    </AuthSplitLayout>
  );
}
