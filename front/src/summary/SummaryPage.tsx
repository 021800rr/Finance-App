import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { QueryResult } from '../ui/QueryResult'
import { AccountsTable } from './AccountsTable'
import { TotalsTable } from './TotalsTable'
import { useSummary } from './summaryApi'

// The home page: every account with its balance, and the totals below. ?show_hidden=1 in the address (as in the
// web app) also shows the hidden accounts and counts them in.
export function SummaryPage() {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  const showHidden = params.get('show_hidden') === '1'
  const summary = useSummary(showHidden)

  return (
    <section className="space-y-4">
      <button
        type="button"
        onClick={() => setParams(showHidden ? {} : { show_hidden: '1' })}
        className={showHidden
          ? 'cursor-pointer rounded-md bg-slate-600 px-3 py-1.5 text-sm text-white hover:bg-slate-700'
          : 'cursor-pointer rounded-md border border-slate-400 dark:border-slate-500 px-3 py-1.5 text-sm '
            + 'text-slate-700 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800'}
      >
        {showHidden ? t('home.hide_hidden') : t('home.show_hidden')}
      </button>
      <QueryResult query={summary} failure={t('app.summary_load_failed')}>
        {(data) => (
          <>
            <AccountsTable accounts={data.accounts} />
            <TotalsTable summary={data} />
          </>
        )}
      </QueryResult>
    </section>
  )
}
