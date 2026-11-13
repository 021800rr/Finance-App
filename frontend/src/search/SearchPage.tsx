import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { OperationsTable } from '../account/OperationsTable'
import { useAccounts } from '../operation/operationApi'
import { Card } from '../ui/Card'
import { pageFromParam } from '../ui/pageParam'
import { Pagination } from '../ui/Pagination'
import { QueryResult } from '../ui/QueryResult'
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
    <Card title={t('search.title')}>
      <p className="text-sm text-slate-500 dark:text-slate-400">
        {phrase === ''
          ? t('search.prompt')
          : (
            <>
              {t('search.query_label')}: <strong>{phrase}</strong>
              {results.data && <> · {t('search.results_label')}: {results.data.totalItems}</>}
            </>
          )}
      </p>
      {/* An empty phrase asks nothing: the query stays waiting, so there is nothing to show either. */}
      {phrase !== '' && (
        <QueryResult query={results} failure={t('app.operations_load_failed')}>
          {(found) => (
            <>
              <OperationsTable
                operations={found.items}
                accountNumbers={accountNumbers}
                otherSideLinks={false}
                emptyText={t('search.none_found_for', { query: phrase })}
              />
              <Pagination page={page} lastPage={found.lastPage} />
            </>
          )}
        </QueryResult>
      )}
    </Card>
  )
}
