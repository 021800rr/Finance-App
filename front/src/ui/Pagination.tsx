import { useTranslation } from 'react-i18next'
import { Link, Navigate, useSearchParams } from 'react-router'

const LINK = 'rounded-md border border-slate-300 dark:border-slate-600 px-3 py-1 text-slate-700 dark:text-slate-300 '
  + 'hover:bg-slate-50 dark:hover:bg-slate-800'
const OFF = 'rounded-md border border-slate-200 dark:border-slate-700 px-3 py-1 text-slate-300 dark:text-slate-600'

// ‹ page / last › as in the web app; the page is ?page=N in the address, so it survives a reload and the back button
// returns to it. The other parameters of the address (the phrase of a search, the framed operation) stay. Nothing is
// shown when everything fits on one page.
export function Pagination({ page, lastPage }: { page: number; lastPage: number }) {
  const { t } = useTranslation()
  const [params] = useSearchParams()

  function pageAddress(target: number): string {
    const next = new URLSearchParams(params)
    next.set('page', String(target))

    return `?${next}`
  }

  // A page past the last one (its last rows were just deleted, or an old link): the last page instead of an empty one.
  const last = Math.max(lastPage, 1)
  if (page > last) {
    return <Navigate to={pageAddress(last)} replace />
  }
  if (last === 1) {
    return null
  }

  return (
    <div className="flex items-center justify-between text-sm">
      <nav aria-label={t('pagination.label')} className="flex items-center gap-2">
        {page > 1
          ? <Link to={pageAddress(page - 1)} aria-label={t('pagination.previous')} className={LINK}>‹</Link>
          : <span className={OFF}>‹</span>}
        <span className="px-2 text-slate-600 dark:text-slate-400">{page} / {lastPage}</span>
        {page < lastPage
          ? <Link to={pageAddress(page + 1)} aria-label={t('pagination.next')} className={LINK}>›</Link>
          : <span className={OFF}>›</span>}
      </nav>
      <span className="text-slate-500 dark:text-slate-400">
        {t('pagination.page_of', { page, totalPages: lastPage })}
      </span>
    </div>
  )
}
