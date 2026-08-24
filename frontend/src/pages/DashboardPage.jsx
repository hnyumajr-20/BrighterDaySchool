import { useAuth } from '../auth/AuthContext';

const NAV_ITEMS_BY_ROLE = {
  admin: ['Dashboard', 'Staff', 'Classes', 'Subjects', 'Academic Years'],
  registrar: ['Dashboard', 'Students', 'Admissions', 'Schedules'],
  accountant: ['Dashboard', 'Fees'],
  teacher: ['Dashboard', 'Results', 'Attendance'],
  librarian: ['Dashboard', 'Books', 'Loans'],
  student: ['Dashboard', 'Results', 'Attendance', 'Fees'],
  parent: ['Dashboard', 'Children'],
};

export default function DashboardPage() {
  const { user, logout } = useAuth();
  const navItems = NAV_ITEMS_BY_ROLE[user?.role] ?? ['Dashboard'];

  return (
    <div className="app-shell">
      <header className="topbar">
        <span className="topbar-title">Brighter Day SMIS</span>
        <div className="topbar-actions">
          <span>{user?.username}</span>
          <button type="button" className="btn btn-tertiary" onClick={logout}>
            Logout
          </button>
        </div>
      </header>

      <div className="app-body">
        <nav className="sidebar">
          <ul>
            {navItems.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        </nav>

        <main className="content">
          <h1>Dashboard</h1>
          <p>
            Signed in as <strong>{user?.username}</strong> ({user?.role}). Phase 0 foundation is
            working — module pages arrive in their own build phases.
          </p>
        </main>
      </div>
    </div>
  );
}
