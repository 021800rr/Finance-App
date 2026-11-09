import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { OperationsTable } from '../account/OperationsTable'
import { errorMessage } from '../messages'
import { useAccounts } from '../operation/operationApi'
import { pageFromParam } from '../ui/pageParam'
import { Pagination } from '../ui/Pagination'
import { useOperationSearch } from './searchApi'

// /search?q=...&page=N: the operations of all accounts found by the search of the header, as on the web app's
// search page: with the account number, the confirmation, the edit and the delete; no 🔗.
export function SearchPage() {
  const { t } = useTranslation()
  const [params] = useSearchParams()
  const phrase = (params.get('q') ?? '').trim()
  const page = pageFromParam(params.get('page'))
  const results = useOperationSearch(phrase, page)
  const accounts = useAccounts()
  const accountNumbers = new Map((accounts.data ?? []).map((account) => [account.id, account.accountNumber]))

  return (
    <section className="space-y-4 rounded-xl bg-white p-6 shadow">
      <h1 className="text-xl font-semibold text-slate-900">{t('search.title')}</h1>
      <p className="text-sm text-slate-500">
        {phrase === ''
          ? t('search.prompt')
          : (
            <>
              {t('search.query_label')}: <strong>{phrase}</strong>
              {results.data && <> · {t('search.results_label')}: {results.data.totalItems}</>}
            </>
          )}
      </p>
      {phrase !== '' && results.isPending && <p className="text-slate-500">{t('app.loading')}</p>}
      {results.isError && (
        <p role="alert" className="text-red-600">{errorMessage(results.error, t('app.operations_load_failed'))}</p>
      )}
      {phrase !== '' && results.data && (
        <>
          <OperationsTable
            operations={results.data.items}
            accountNumbers={accountNumbers}
            otherSideLinks={false}
            emptyText={t('search.none_found_for', { query: phrase })}
          />
          <Pagination page={page} lastPage={results.data.lastPage} />
        </>
      )}
    </section>
  )
}
