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
  const { id = '' } = useParams()
  const [params] = useSearchParams()
  const page = pageFromParam(params.get('page'))
  const highlight = Number(params.get('highlight')) || null
  const account = useAccount(id)
  const operations = useAccountOperations(id, page)

  if (account.isPending) {
    return <p className="text-slate-500">Wczytuję…</p>
  }
  if (account.isError) {
    return account.error instanceof ApiError && account.error.status === 404
      ? <NotFoundPage />
      : <p role="alert" className="text-red-600">{errorMessage(account.error, 'Nie udało się wczytać konta.')}</p>
  }

  return (
    <section className="space-y-4 rounded-xl bg-white p-6 shadow">
      <AccountHeader account={account.data} />
      {operations.isPending && <p className="text-slate-500">Wczytuję operacje…</p>}
      {operations.isError && (
        <p role="alert" className="text-red-600">
          {errorMessage(operations.error, 'Nie udało się wczytać operacji.')}
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
  return (
    <div className="space-y-2">
      <h1 className="text-xl font-semibold text-slate-900">Historia konta</h1>
      <p className="text-sm text-slate-500">
        <strong>Bank:</strong> {account.bankName} · <strong>Typ:</strong> {account.typeName} ·{' '}
        <strong>Waluta:</strong> {account.currencyCode} · <strong>Numer:</strong>{' '}
        <code className="rounded bg-slate-100 px-1.5 py-0.5">{account.accountNumber}</code>
      </p>
      <p className="text-slate-800">
        <strong>Saldo:</strong> {formatAmount(account.saldo.amount)}
        {/* Only when they differ: operations dated in the future are already on the account. */}
        {account.hasFutureOperations && (
          <> · <strong>Saldo na dziś:</strong> {formatAmount(account.saldoToday.amount)}</>
        )}
      </p>
      {/* A hidden account takes no new operations. */}
      {!account.hidden && (
        <Link
          to={`/operation/new?account=${account.id}`}
          className="inline-block rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white
            hover:bg-indigo-700"
        >
          Dodaj operację
        </Link>
      )}
    </div>
  )
}
