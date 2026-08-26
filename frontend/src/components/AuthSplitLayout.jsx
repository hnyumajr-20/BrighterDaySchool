import { ShieldIcon, EnvelopeStackIcon, SignalIcon } from './icons';

export default function AuthSplitLayout({ children }) {
  return (
    <div className="flex flex-col md:flex-row min-h-full">
      <div className="flex-1 bg-chrome text-white p-8 sm:p-12 lg:p-16 flex flex-col justify-center">
        <div className="flex items-center gap-3 mb-10">
          <img src="/logo.png" alt="" className="h-12 w-auto" />
          <div>
            <p className="font-bold">Brighter Day</p>
            <p className="text-xs text-slate-400 tracking-wide">PREPARATORY ELEM, JR &amp; SR HIGH SCHOOL</p>
          </div>
        </div>

        <h1 className="font-serif text-3xl sm:text-4xl font-bold mb-4 max-w-sm">
          One system, every corner of the school.
        </h1>
        <p className="text-slate-400 max-w-md mb-10">
          Admissions, results, attendance, fees, and staff records — one login per role, built for
          Brighter Day.
        </p>

        <ul className="space-y-5 mb-10">
          <li className="flex gap-3 items-start">
            <span className="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center text-primary-400 flex-shrink-0">
              <ShieldIcon />
            </span>
            <div>
              <p className="font-semibold text-sm">Role-based access</p>
              <p className="text-sm text-slate-400">Enforced server-side, not just hidden in the UI.</p>
            </div>
          </li>
          <li className="flex gap-3 items-start">
            <span className="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center text-brand-yellow-400 flex-shrink-0">
              <EnvelopeStackIcon />
            </span>
            <div>
              <p className="font-semibold text-sm">Tracked credential emails</p>
              <p className="text-sm text-slate-400">
                Every login and reset email is queued and logged, not fired and forgotten.
              </p>
            </div>
          </li>
          <li className="flex gap-3 items-start">
            <span className="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center text-primary-400 flex-shrink-0">
              <SignalIcon />
            </span>
            <div>
              <p className="font-semibold text-sm">Built for low connectivity</p>
              <p className="text-sm text-slate-400">Lightweight pages, no bloat — made for Liberia&apos;s networks.</p>
            </div>
          </li>
        </ul>

        <p className="text-xs text-slate-500">© 2026 Brighter Day SMIS — Final Year Project</p>
      </div>

      <div className="flex-1 bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-8">{children}</div>
    </div>
  );
}
