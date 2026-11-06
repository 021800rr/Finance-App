import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../messages'
import { OperationForm } from './OperationForm'
import { useAccounts, useCreateOperation } from './operationApi'

// Today in the browser's time zone, as YYYY-MM-DD (toISOString() would give the UTC date).
function today(): string {
  const now = new Date()

  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

// /operation/new?account=ID: a new operation; "Dodaj operację" of an account history chooses its account.
export function NewOperationPage() {
  const [params] = useSearchParams()
  const accounts = useAccounts()
  const create = useCreateOperation()

  if (accounts.isPending) {
    return <p className="text-slate-500">Wczytuję…</p>
  }
  if (accounts.isError) {
    return <p role="alert" className="text-red-600">{errorMessage(accounts.error, 'Nie udało się wczytać kont.')}</p>
  }

  const visible = accounts.data.filter((account) => !account.hidden)
  const wanted = params.get('account')
  const preselected = wanted === null ? undefined : accounts.data.find((account) => String(account.id) === wanted)
  // As in the web app: an account in the address that is missing or hidden is not offered instead of another one.
  if (wanted !== null && (preselected === undefined || preselected.hidden)) {
    return (
      <div role="alert" className="space-y-2">
        <p className="text-red-600">
          {preselected === undefined
            ? 'To konto nie istnieje.'
            : 'To konto jest ukryte. Wybierz konto, które nie jest ukryte.'}
        </p>
        <Link to="/" className="text-indigo-600 hover:underline">Wróć do podsumowania</Link>
      </div>
    )
  }

  return (
    <OperationForm
      title="Nowa operacja"
      accounts={visible}
      accountFixed={false}
      initial={{
        accountId: preselected?.id ?? visible[0]?.id ?? null,
        contractor: null,
        operationDate: today(),
        amount: '',
        description: '',
        confirmed: false,
      }}
      save={async (input, accountId) => {
        const operation = await create.mutateAsync({ ...input, accountId })

        return `/account/${operation.accountId}/operations?highlight=${operation.id}`
      }}
    />
  )
}
