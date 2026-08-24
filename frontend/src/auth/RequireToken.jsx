import { Navigate } from 'react-router-dom';
import { useAuth } from './AuthContext';

export default function RequireToken({ children }) {
  const { token } = useAuth();

  if (!token) {
    return <Navigate to="/login" replace />;
  }

  return children;
}
