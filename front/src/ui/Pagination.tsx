import { Link } from 'react-router'

const LINK = 'rounded-md border border-slate-300 px-3 py-1 text-slate-700 hover:bg-slate-50'
const OFF = 'rounded-md border border-slate-200 px-3 py-1 text-slate-300'

// ‹ page / last › as in the web app; the page is ?page=N in the address, so it survives a reload and the back button
// returns to it. Nothing is shown when everything fits on one page.
export function Pagination({ page, lastPage }: { page: number; lastPage: number }) {
  if (lastPage <= 1) {
    return null
  }

  return (
    <div className="flex items-center justify-between text-sm">
      <nav aria-label="Paginacja" className="flex items-center gap-2">
        {page > 1
          ? <Link to={`?page=${page - 1}`} aria-label="Poprzednia" className={LINK}>‹</Link>
          : <span className={OFF}>‹</span>}
        <span className="px-2 text-slate-600">{page} / {lastPage}</span>
        {page < lastPage
          ? <Link to={`?page=${page + 1}`} aria-label="Następna" className={LINK}>›</Link>
          : <span className={OFF}>›</span>}
      </nav>
      <span className="text-slate-500">Strona {page} z {lastPage}</span>
    </div>
  )
}
