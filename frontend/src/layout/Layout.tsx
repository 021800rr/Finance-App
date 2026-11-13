import { Bars3Icon, XMarkIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet, useLocation, useSearchParams } from 'react-router'
import { LogoutButton } from '../auth/LogoutButton'
import { SearchBox } from '../search/SearchBox'
import { ErrorBoundary } from '../ui/ErrorBoundary'
import { Loading } from '../ui/Feedback'
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

// The header collapses below xl; the current page goes in place of <Outlet />.
export function Layout() {
  const { t } = useTranslation()
  const location = useLocation()
  const [params] = useSearchParams()
  const [openMenuLocation, setOpenMenuLocation] = React.useState<string | null>(null)
  const menuOpen = openMenuLocation === location.key
  const menuId = React.useId()
  const toggleRef = React.useRef<HTMLButtonElement>(null)

  function closeMenu() {
    setOpenMenuLocation(null)
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLElement>) {
    if (event.key === 'Escape' && menuOpen) {
      closeMenu()
      toggleRef.current?.focus()
    }
  }

  // The phrase of the results page shown; a new one (or leaving the page) gives the search field a fresh start.
  const phrase = location.pathname === '/search' ? params.get('q') ?? '' : ''

  return (
    <div className="min-h-screen bg-slate-100 dark:bg-slate-950">
      <header className="bg-white dark:bg-slate-900 shadow-sm" onKeyDown={handleKeyDown}>
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 px-4 py-3 xl:flex-nowrap">
          <Link
            to="/"
            onClick={closeMenu}
            className="shrink-0 text-lg font-semibold text-slate-900 dark:text-slate-100"
          >
            Finance
          </Link>
          <button
            ref={toggleRef}
            type="button"
            aria-controls={menuId}
            aria-expanded={menuOpen}
            aria-label={t(menuOpen ? 'nav.close_menu' : 'nav.open_menu')}
            onClick={() => setOpenMenuLocation(menuOpen ? null : location.key)}
            className="ml-auto flex size-11 cursor-pointer items-center justify-center rounded-md
              text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-500
              dark:text-slate-300 dark:hover:bg-slate-800 xl:hidden"
          >
            {menuOpen ? <XMarkIcon className="size-6" aria-hidden="true" />
              : <Bars3Icon className="size-6" aria-hidden="true" />}
          </button>
          <div
            id={menuId}
            className={`${menuOpen ? 'flex' : 'hidden'} w-full min-w-0 flex-col gap-4 pt-3
              xl:flex xl:w-auto xl:flex-1 xl:flex-row xl:items-center xl:gap-6 xl:pt-0`}
          >
            <nav
              aria-label={t('nav.navigation')}
              className="flex flex-col gap-1 text-sm xl:flex-row xl:gap-4 xl:whitespace-nowrap"
            >
              {MENU.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  onClick={closeMenu}
                  className={({ isActive }) => 'py-2 xl:py-0 ' + (isActive
                    ? 'font-medium text-indigo-600 dark:text-indigo-400'
                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100')}
                >
                  {t(item.label)}
                </NavLink>
              ))}
            </nav>
            <div className="flex flex-wrap items-center gap-3 text-sm text-slate-600
              dark:text-slate-400 xl:ml-auto xl:shrink-0 xl:flex-nowrap xl:whitespace-nowrap">
              <SearchBox key={phrase} initial={phrase} />
              <LanguageSwitcher />
              <LogoutButton />
            </div>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-6">
        {/* A page that fails keeps the header; another address starts afresh. A page loaded on demand says
            "Wczytuję…" meanwhile. */}
        <ErrorBoundary resetKey={location.pathname}>
          <React.Suspense fallback={<Loading />}>
            <Outlet />
          </React.Suspense>
        </ErrorBoundary>
      </main>
    </div>
  )
}
