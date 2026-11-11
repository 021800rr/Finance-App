import { useTranslation } from 'react-i18next'
import { Link, Navigate, useSearchParams } from 'react-router'

const LINK = 'rounded-md border border-slate-300 dark:border-slate-600 px-3 py-1 text-slate-700 dark:text-slate-300 '
  + 'hover:bg-slate-50 dark:hover:bg-slate-800'
const OFF = 'rounded-md border border-slate-200 dark:border-slate-700 px-3 py-1 text-slate-300 dark:text-slate-600'

// Numbered pages with ellipses; ?page=N survives a reload and the back button
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

  // Ten-page groups, with direct links to the first/last page and jumps between groups.
  const groupSize = 10
  const start = Math.floor((page - 1) / groupSize) * groupSize + 1
  const end = Math.min(start + groupSize - 1, last)
  const pages: { kind: 'page' | 'gap'; page: number }[] = [
    ...(start > 1 ? [{ kind: 'page' as const, page: 1 }] : []),
    ...(start > 2 ? [{ kind: 'gap' as const, page: Math.max(1, start - groupSize) }] : []),
    ...Array.from({ length: end - start + 1 }, (_, index) => ({ kind: 'page' as const, page: start + index })),
    ...(end < last - 1 ? [{ kind: 'gap' as const, page: end + 1 }] : []),
    ...(end < last ? [{ kind: 'page' as const, page: last }] : []),
  ]

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
      <nav aria-label={t('pagination.label')} className="flex flex-wrap items-center gap-1">
        {page > 1
          ? <Link to={pageAddress(page - 1)} aria-label={t('pagination.previous')} className={LINK}>‹</Link>
          : <span aria-disabled="true" aria-label={t('pagination.previous')} className={OFF}>‹</span>}
        {pages.map(({ kind, page: target }) => kind === 'gap'
          ? (
            <Link
              key={`${kind}-${target}`}
              to={pageAddress(target)}
              aria-label={t('pagination.page', {
                page: target,
              })}
              title={t('pagination.page', {
                page: target,
              })}
              className={LINK}
            >
              …
            </Link>
          )
          : target === page
            ? (
              <span
                key={`${kind}-${target}`}
                aria-current="page"
                aria-label={t('pagination.page', { page: target })}
                className="rounded-md border border-indigo-600 bg-indigo-600 px-3 py-1 text-white"
              >
                {target}
              </span>
            )
            : (
              <Link
                key={`${kind}-${target}`}
                to={pageAddress(target)}
                aria-label={t('pagination.page', { page: target })}
                className={LINK}
              >
                {target}
              </Link>
            ))}
        {page < last
          ? <Link to={pageAddress(page + 1)} aria-label={t('pagination.next')} className={LINK}>›</Link>
          : <span aria-disabled="true" aria-label={t('pagination.next')} className={OFF}>›</span>}
      </nav>
      <span className="text-slate-500 dark:text-slate-400">
        {t('pagination.page_of', { page, totalPages: last })}
      </span>
    </div>
  )
}
