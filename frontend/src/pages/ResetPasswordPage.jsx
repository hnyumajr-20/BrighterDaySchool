import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import client from '../api/client';
import { extractErrorMessage } from '../utils/errors';

export default function ResetPasswordPage() {
  const navigate = useNavigate();
  const [token, setToken] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await client.post('/auth/reset-password', {
        token,
        new_password: newPassword,
      });
      navigate('/login');
    } catch (err) {
      setError(extractErrorMessage(err, 'That reset token is invalid or has expired.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="auth-page">
      <form className="auth-form" onSubmit={handleSubmit}>
        <h1>Reset password</h1>
        <p className="auth-subtitle">Paste the reset token you were sent, and choose a new password.</p>

        <label htmlFor="token">Reset token</label>
        <input
          id="token"
          type="text"
          value={token}
          onChange={(event) => setToken(event.target.value)}
          required
        />

        <label htmlFor="new_password">New password</label>
        <input
          id="new_password"
          type="password"
          minLength={8}
          value={newPassword}
          onChange={(event) => setNewPassword(event.target.value)}
          required
        />

        {error && <p className="form-error">{error}</p>}

        <button type="submit" className="btn btn-primary" disabled={submitting}>
          {submitting ? 'Resetting…' : 'Reset password'}
        </button>

        <Link className="auth-link" to="/login">
          Back to sign in
        </Link>
      </form>
    </div>
  );
}
