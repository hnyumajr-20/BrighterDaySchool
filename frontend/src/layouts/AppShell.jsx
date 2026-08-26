import { useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { useTheme } from '../theme/ThemeContext';
import {
  GridIcon,
  BriefcaseIcon,
  BookIcon,
  FileTextIcon,
  CalendarIcon,
  GraduationCapIcon,
  ClockIcon,
  SunIcon,
  MoonIcon,
  LogoutIcon,
  KeyIcon,
  CreditCardIcon,
  TrendingUpIcon,
  ShieldIcon,
  MenuIcon,
  CloseIcon,
} from '../components/icons';

const NAV_ITEMS_BY_ROLE = {
  admin: [
    { label: 'Dashboard', to: '/dashboard', icon: GridIcon },
    { label: 'Staff', to: '/dashboard/staff', icon: BriefcaseIcon },
    { label: 'Classes', to: '/dashboard/classes', icon: BookIcon },
    { label: 'Subjects', to: '/dashboard/subjects', icon: FileTextIcon },
    { label: 'Academic Years', to: '/dashboard/academic-years', icon: CalendarIcon },
    { label: 'Admissions', to: '/dashboard/admissions', icon: GraduationCapIcon },
    { label: 'Attendance', to: '/dashboard/attendance', icon: ClockIcon },
    { label: 'Financial Report', to: '/dashboard/finance/report', icon: ShieldIcon },
  ],
  registrar: [
    { label: 'Dashboard', to: '/dashboard', icon: GridIcon },
    { label: 'Admissions', to: '/dashboard/admissions', icon: GraduationCapIcon },
    { label: 'Attendance', to: '/dashboard/attendance', icon: ClockIcon },
    { label: 'Schedules', icon: CalendarIcon, soon: true },
  ],
  accountant: [
    { label: 'Dashboard', to: '/dashboard', icon: GridIcon },
    { label: 'Invoices', to: '/dashboard/invoices', icon: FileTextIcon },
    { label: 'Fees', to: '/dashboard/fees', icon: CreditCardIcon },
    { label: 'Salary', to: '/dashboard/salary', icon: TrendingUpIcon },
  ],
  teacher: [{ label: 'Dashboard' }, { label: 'Results' }, { label: 'Attendance' }],
  librarian: [{ label: 'Dashboard' }, { label: 'Books' }, { label: 'Loans' }],
  student: [{ label: 'Dashboard' }, { label: 'Results' }, { label: 'Attendance' }, { label: 'Fees' }],
  parent: [{ label: 'Dashboard' }, { label: 'Children' }],
};

export default function AppShell() {
  const { user, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const [isNavOpen, setIsNavOpen] = useState(false);
  const navItems = NAV_ITEMS_BY_ROLE[user?.role] ?? [{ label: 'Dashboard', to: '/dashboard', icon: GridIcon }];
  const initials = (user?.username ?? '??').slice(0, 2).toUpperCase();
  const closeNav = () => setIsNavOpen(false);

  return (
    <div className="flex flex-col h-full bg-slate-50 dark:bg-slate-950">
      <header className="flex items-center bg-chrome text-white border-b border-slate-800 h-16 flex-shrink-0">
        <div className="hidden lg:block w-60 flex-shrink-0" />
        <div className="flex-1 flex items-center justify-between px-4 sm:px-6 min-w-0">
          <div className="flex items-center gap-3 min-w-0">
            <button
              type="button"
              onClick={() => setIsNavOpen(true)}
              aria-label="Open menu"
              className="lg:hidden -ml-1 p-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white flex-shrink-0"
            >
              <MenuIcon />
            </button>
            <img src="/logo.png" alt="" className="h-8 w-auto flex-shrink-0" />
            <div className="leading-tight min-w-0 hidden sm:block">
              <p className="font-semibold text-sm truncate">Brighter Day Secondary School</p>
              <p className="flex items-center gap-1.5 text-xs text-slate-400">
                <span className="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 flex-shrink-0" />
                Academic Portal
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={toggleTheme}
            aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2 text-sm hover:bg-slate-800 flex-shrink-0"
          >
            {theme === 'dark' ? <SunIcon /> : <MoonIcon />}
            <span className="hidden sm:inline">{theme === 'dark' ? 'Light Mode' : 'Dark Mode'}</span>
          </button>
        </div>
      </header>

      <div className="flex flex-1 min-h-0 relative">
        {isNavOpen && (
          <div
            onClick={closeNav}
            aria-hidden="true"
            className="fixed inset-0 bg-slate-900/60 z-30 lg:hidden"
          />
        )}

        <nav
          className={`fixed inset-y-0 left-0 z-40 w-60 flex-shrink-0 bg-chrome text-slate-300 flex flex-col p-3 transform transition-transform duration-200 ease-in-out lg:static lg:z-auto lg:translate-x-0 ${
            isNavOpen ? 'translate-x-0' : '-translate-x-full'
          }`}
        >
          <div className="flex items-center justify-between gap-3 px-2 pb-4 mb-3 border-b border-slate-700">
            <div className="flex items-center gap-3 min-w-0">
              <img src="/logo.png" alt="" className="h-10 w-auto flex-shrink-0" />
              <div className="leading-tight min-w-0">
                <p className="text-white font-bold text-sm truncate">Brighter Day</p>
                <p className="text-slate-400 text-[0.65rem] tracking-wide">SECONDARY SCHOOL</p>
              </div>
            </div>
            <button
              type="button"
              onClick={closeNav}
              aria-label="Close menu"
              className="lg:hidden p-1.5 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white flex-shrink-0"
            >
              <CloseIcon />
            </button>
          </div>

          <ul className="flex-1 flex flex-col gap-1 overflow-y-auto">
            {navItems.map((item) => {
              const Icon = item.icon;
              if (item.soon || !item.to) {
                return (
                  <li key={item.label}>
                    <span className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-500 opacity-60">
                      {Icon && <Icon />}
                      {item.label}
                      {item.soon && (
                        <span className="ml-auto text-[0.6rem] border border-slate-600 rounded-full px-1.5 py-0.5">
                          Soon
                        </span>
                      )}
                    </span>
                  </li>
                );
              }
              return (
                <li key={item.label}>
                  <NavLink
                    to={item.to}
                    end={item.to === '/dashboard'}
                    onClick={closeNav}
                    className={({ isActive }) =>
                      `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium ${
                        isActive ? 'bg-primary-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                      }`
                    }
                  >
                    {Icon && <Icon />}
                    {item.label}
                  </NavLink>
                </li>
              );
            })}
          </ul>

          <div className="border-t border-slate-700 pt-3 mt-3 flex-shrink-0">
            <div className="flex items-center gap-2.5 mb-2.5">
              {user?.photo_url ? (
                <img
                  src={user.photo_url}
                  alt={user.username}
                  className="w-8 h-8 rounded-full object-cover flex-shrink-0"
                />
              ) : (
                <span className="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center text-xs font-semibold flex-shrink-0">
                  {initials}
                </span>
              )}
              <div className="leading-tight min-w-0">
                <p className="text-white text-sm font-semibold truncate">{user?.username}</p>
                <p className="text-slate-400 text-xs capitalize">{user?.role}</p>
              </div>
            </div>
            <NavLink
              to="/dashboard/settings"
              onClick={closeNav}
              className={({ isActive }) =>
                `w-full flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium mb-1 ${
                  isActive ? 'bg-primary-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                }`
              }
            >
              <KeyIcon />
              Settings
            </NavLink>
            <button
              type="button"
              onClick={logout}
              className="w-full flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white"
            >
              <LogoutIcon />
              Logout
            </button>
          </div>
        </nav>

        <main className="flex-1 min-w-0 overflow-y-auto p-4 sm:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
