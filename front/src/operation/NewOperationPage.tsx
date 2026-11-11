import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../messages'
import { today } from '../ui/form'
import { OperationForm } from './OperationForm'
import { useAccounts, useCreateOperation } from './operationApi'

// /operation/new?account=ID: a new operation; "Add operation" of an account history chooses its account.
export function NewOperationPage() {
  const { t } = useTranslation()
  const [params] = useSearchParams()
  const accounts = useAccounts()
  const create = useCreateOperation()

  if (accounts.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (accounts.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(accounts.error, t('app.accounts_load_failed'))}
      </p>
    )
  }

  const visible = accounts.data.filter((account) => !account.hidden)
  const wanted = params.get('account')
  const preselected = wanted === null ? undefined : accounts.data.find((account) => String(account.id) === wanted)
  // As in the web app: an account in the address that is missing or hidden is not offered instead of another one.
  if (wanted !== null && (preselected === undefined || preselected.hidden)) {
    return (
      <div role="alert" className="space-y-2">
        <p className="text-red-600 dark:text-red-400">
          {preselected === undefined
            ? t('account.not_found', { ns: 'validators' })
            : t('account.hidden_not_selectable', { ns: 'validators' })}
        </p>
        <Link to="/" className="text-indigo-600 dark:text-indigo-400 hover:underline">{t('error.back_home')}</Link>
      </div>
    )
  }

  return (
    <OperationForm
      title={t('operation.new_title')}
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
