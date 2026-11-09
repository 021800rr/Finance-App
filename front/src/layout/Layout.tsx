import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet, useLocation, useSearchParams } from 'react-router'
import { LogoutButton } from '../auth/LogoutButton'
import { SearchBox } from '../search/SearchBox'
import { LanguageSwitcher } from './LanguageSwitcher'

// The same menu as in the web app (templates/partials/_navbar.html.twig), with the same addresses and texts.
const MENU = [
  { to: '/operation/new', label: 'nav.new_operation' },
  { to: '/transfer/new', label: 'nav.transfer' },
  { to: '/fixed-expense', label: 'nav.fixed_expenses' },
  { to: '/rate', label: 'nav.rates' },
  { to: '/gold-assets', label: 'nav.gold_assets' },
  { to: '/gold-prices', label: 'nav.gold_prices' },
]

// The frame of every page of the logged-in app: the header with the menu, the language and the logout, all in one
// row (on a narrow screen the menu scrolls sideways instead of wrapping); the page of the current address goes in
// place of <Outlet />.
export function Layout() {
  const { t } = useTranslation()
  const location = useLocation()
  const [params] = useSearchParams()
  // The phrase of the results page shown; a new one (or leaving the page) gives the search field a fresh start.
  const phrase = location.pathname === '/search' ? params.get('q') ?? '' : ''

  return (
    <div className="min-h-screen bg-slate-100">
      <header className="bg-white shadow-sm">
        <div className="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3">
          <Link to="/" className="shrink-0 text-lg font-semibold text-slate-900">Finance</Link>
          <nav className="flex min-w-0 gap-4 overflow-x-auto whitespace-nowrap text-sm">
            {MENU.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) => isActive
                  ? 'font-medium text-indigo-600'
                  : 'text-slate-600 hover:text-slate-900'}
              >
                {t(item.label)}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto flex shrink-0 items-center gap-3 whitespace-nowrap text-sm text-slate-600">
            <SearchBox key={phrase} initial={phrase} />
            <LanguageSwitcher />
            <LogoutButton />
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-6">
        <Outlet />
      </main>
    </div>
  )
}
