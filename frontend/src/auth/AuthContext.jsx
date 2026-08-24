import { createContext, useContext, useMemo, useState } from 'react';
import client from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem('token'));
  const [user, setUser] = useState(() => {
    const stored = localStorage.getItem('user');
    return stored ? JSON.parse(stored) : null;
  });
  const [mustChangePassword, setMustChangePassword] = useState(
    () => localStorage.getItem('mustChangePassword') === 'true',
  );

  const login = async (usernameOrEmail, password) => {
    const response = await client.post('/auth/login', {
      username_or_email: usernameOrEmail,
      password,
    });

    const { token: newToken, user: newUser, must_change_password: mustChange } = response.data;

    localStorage.setItem('token', newToken);
    localStorage.setItem('user', JSON.stringify(newUser));
    localStorage.setItem('mustChangePassword', String(mustChange));

    setToken(newToken);
    setUser(newUser);
    setMustChangePassword(mustChange);

    return { mustChangePassword: mustChange };
  };

  const changePassword = async (oldPassword, newPassword) => {
    await client.post('/auth/change-password', {
      old_password: oldPassword,
      new_password: newPassword,
    });

    localStorage.setItem('mustChangePassword', 'false');
    setMustChangePassword(false);
  };

  const logout = async () => {
    try {
      await client.post('/auth/logout');
    } finally {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      localStorage.removeItem('mustChangePassword');
      setToken(null);
      setUser(null);
      setMustChangePassword(false);
    }
  };

  const value = useMemo(
    () => ({ token, user, mustChangePassword, login, changePassword, logout }),
    [token, user, mustChangePassword],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
