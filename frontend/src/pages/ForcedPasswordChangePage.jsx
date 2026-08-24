import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { extractErrorMessage } from '../utils/errors';

export default function ForcedPasswordChangePage() {
  const { changePassword, logout } = useAuth();
  const navigate = useNavigate();
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
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
    <div className="auth-page">
      <form className="auth-form" onSubmit={handleSubmit}>
        <h1>Change your password</h1>
        <p className="auth-subtitle">You must set a new password before continuing.</p>

        <label htmlFor="old_password">Current password</label>
        <input
          id="old_password"
          type="password"
          value={oldPassword}
          onChange={(event) => setOldPassword(event.target.value)}
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
          {submitting ? 'Saving…' : 'Save password'}
        </button>

        <button type="button" className="btn btn-tertiary" onClick={logout}>
          Cancel and sign out
        </button>
      </form>
    </div>
  );
}
