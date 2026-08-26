import { Navigate } from 'react-router-dom';
import { useAuth } from './AuthContext';

export default function RoleGate({ roles, children }) {
  const { user } = useAuth();

  if (user && !roles.includes(user.role)) {
    return <Navigate to="/dashboard" replace />;
  }

  return children;
}
