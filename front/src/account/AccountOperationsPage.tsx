import { useTranslation } from 'react-i18next'
import { Link, useParams, useSearchParams } from 'react-router'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { NotFoundPage } from '../pages/NotFoundPage'
import { pageFromParam } from '../ui/pageParam'
import { Pagination } from '../ui/Pagination'
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

  if (account.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (account.isError) {
    return account.error instanceof ApiError && account.error.status === 404
      ? <NotFoundPage />
      : (
        <p role="alert" className="text-red-600 dark:text-red-400">
          {errorMessage(account.error, t('app.account_load_failed'))}
        </p>
      )
  }

  return (
    <section className="space-y-4 rounded-xl bg-white dark:bg-slate-900 p-6 shadow">
      <AccountHeader account={account.data} />
      {operations.isPending && <p className="text-slate-500 dark:text-slate-400">{t('app.loading_operations')}</p>}
      {operations.isError && (
        <p role="alert" className="text-red-600 dark:text-red-400">
          {errorMessage(operations.error, t('app.operations_load_failed'))}
        </p>
      )}
      {operations.data && (
        <>
          <OperationsTable operations={operations.data.items} highlight={highlight} />
          <Pagination page={page} lastPage={operations.data.lastPage} />
        </>
      )}
    </section>
  )
}

function AccountHeader({ account }: { account: Account }) {
  const { t } = useTranslation()

  return (
    <div className="space-y-2">
      <h1 className="text-xl font-semibold text-slate-900 dark:text-slate-100">{t('account.history')}</h1>
      <p className="text-sm text-slate-500 dark:text-slate-400">
        <strong>{t('account.bank')}:</strong> {account.bankName} ·{' '}
        <strong>{t('account.type')}:</strong> {account.typeName} ·{' '}
        <strong>{t('account.currency')}:</strong> {account.currencyCode} ·{' '}
        <strong>{t('account.number')}:</strong>{' '}
        <code className="rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5">{account.accountNumber}</code>
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
