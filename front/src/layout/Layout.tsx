import { Link, NavLink, Outlet } from 'react-router'
import { useAuth } from '../auth/AuthContext'
import { LogoutButton } from '../auth/LogoutButton'

// The same menu as in the web app (templates/partials/_navbar.html.twig), with the same addresses.
const MENU = [
  { to: '/operation/new', label: 'Nowa operacja' },
  { to: '/transfer/new', label: 'Przelew / Wymiana' },
  { to: '/fixed-expense', label: 'Opłaty stałe' },
  { to: '/rate', label: 'Kursy' },
  { to: '/gold-assets', label: 'Złoto – aktywa' },
  { to: '/gold-prices', label: 'Złoto – ceny' },
]

// The frame of every page of the logged-in app: the header with the menu, the user and the logout; the page of the
// current address goes in place of <Outlet />.
export function Layout() {
  const { email } = useAuth()

  return (
    <div className="min-h-screen bg-slate-100">
      <header className="bg-white shadow-sm">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
          <Link to="/" className="text-lg font-semibold text-slate-900">Finance</Link>
          <nav className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
            {MENU.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) => isActive
                  ? 'font-medium text-indigo-600'
                  : 'text-slate-600 hover:text-slate-900'}
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-3 text-sm text-slate-600">
            <span>{email}</span>
            <LogoutButton />
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-6">
        <Outlet />
      </main>
    </div>
  )
}
