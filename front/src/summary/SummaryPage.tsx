import { useSearchParams } from 'react-router'
import { AccountsTable } from './AccountsTable'
import { TotalsTable } from './TotalsTable'
import { useSummary } from './summaryApi'

// The home page: every account with its balance, and the totals below. ?show_hidden=1 in the address (as in the
// web app) also shows the hidden accounts and counts them in.
export function SummaryPage() {
  const [params, setParams] = useSearchParams()
  const showHidden = params.get('show_hidden') === '1'
  const summary = useSummary(showHidden)

  return (
    <section className="space-y-4">
      <button
        type="button"
        onClick={() => setParams(showHidden ? {} : { show_hidden: '1' })}
        className={showHidden
          ? 'rounded-md bg-slate-600 px-3 py-1.5 text-sm text-white hover:bg-slate-700'
          : 'rounded-md border border-slate-400 px-3 py-1.5 text-sm text-slate-700 hover:bg-white'}
      >
        {showHidden ? 'Ukryj ukryte' : 'Pokaż ukryte'}
      </button>
      {summary.isPending && <p className="text-slate-500">Wczytuję…</p>}
      {summary.isError && <p role="alert" className="text-red-600">Nie udało się wczytać podsumowania.</p>}
      {summary.data && (
        <>
          <AccountsTable accounts={summary.data.accounts} />
          <TotalsTable summary={summary.data} />
        </>
      )}
    </section>
  )
}
