import { useTranslation } from 'react-i18next'
import { Link, useParams, useSearchParams } from 'react-router'
import { formatAmount } from '../money'
import { Card } from '../ui/Card'
import { Code } from '../ui/Code'
import { pageFromParam } from '../ui/pageParam'
import { Pagination } from '../ui/Pagination'
import { QueryResult } from '../ui/QueryResult'
import { type Account, useAccount, useAccountOperations } from './accountApi'
import { OperationsTable } from './OperationsTable'

// /account/:id/operations?page=N: one account with its balance and its operations, newest first. ?highlight=ID frames
// one operation, as the web app does after a 🔗.
export function AccountOperationsPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const [params] = useSearchParams()
  const page = pageFromParam(params.get('page'))
  const highlight = Number(params.get('highlight')) || null
  const account = useAccount(id)
  const operations = useAccountOperations(id, page)

  return (
    <QueryResult query={account} failure={t('app.account_load_failed')} notFound>
      {(found) => (
        <Card title={t('account.history')}>
          <AccountDetails account={found} />
          <QueryResult
            query={operations}
            failure={t('app.operations_load_failed')}
            loading={t('app.loading_operations')}
          >
            {(result) => (
              <>
                <OperationsTable operations={result.items} highlight={highlight} />
                <Pagination page={page} lastPage={result.lastPage} />
              </>
            )}
          </QueryResult>
        </Card>
      )}
    </QueryResult>
  )
}

function AccountDetails({ account }: { account: Account }) {
  const { t } = useTranslation()

  return (
    <div className="space-y-2">
      <p className="text-sm text-slate-500 dark:text-slate-400">
        <strong>{t('account.bank')}:</strong> {account.bankName} ·{' '}
        <strong>{t('account.type')}:</strong> {account.typeName} ·{' '}
        <strong>{t('account.currency')}:</strong> {account.currencyCode} ·{' '}
        <strong>{t('account.number')}:</strong> <Code>{account.accountNumber}</Code>
      </p>
      <p className="text-slate-800 dark:text-slate-200">
        <strong>{t('account.balance')}:</strong> {formatAmount(account.saldo.amount)}
        {/* Only when they differ: operations dated in the future are already on the account. */}
        {account.hasFutureOperations && (
          <> · <strong>{t('account.balance_today')}:</strong> {formatAmount(account.saldoToday.amount)}</>
        )}
      </p>
      {/* A hidden account takes no new operations. */}
      {!account.hidden && (
        <Link
          to={`/operation/new?account=${account.id}`}
          className="inline-block rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white
            hover:bg-indigo-700"
        >
          {t('account.add_operation')}
        </Link>
      )}
    </div>
  )
}
