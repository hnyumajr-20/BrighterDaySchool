import { useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../api/client';
import { extractErrorMessage } from '../utils/errors';

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
    <div className="auth-page">
      <form className="auth-form" onSubmit={handleSubmit}>
        <h1>Forgot password</h1>
        <p className="auth-subtitle">Enter your email and we&apos;ll send you a reset token.</p>

        <label htmlFor="email">Email</label>
        <input
          id="email"
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
        />

        {error && <p className="form-error">{error}</p>}
        {message && <p className="form-success">{message}</p>}

        <button type="submit" className="btn btn-primary" disabled={submitting}>
          {submitting ? 'Sending…' : 'Send reset token'}
        </button>

        <Link className="auth-link" to="/reset-password">
          Already have a token?
        </Link>
        <Link className="auth-link" to="/login">
          Back to sign in
        </Link>
      </form>
    </div>
  );
}
