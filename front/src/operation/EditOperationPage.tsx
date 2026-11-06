import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { NotFoundPage } from '../pages/NotFoundPage'
import { formAmount } from '../ui/form'
import { OperationForm } from './OperationForm'
import { type AccountChoice, type OperationInput, useAccounts, useOperation, useUpdateOperation } from './operationApi'
import type { Operation } from '../account/accountApi'

// /operation/:id/edit: the same form; the account of an operation is fixed when it is created.
export function EditOperationPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const operation = useOperation(id)
  const accounts = useAccounts()

  if (operation.isPending || accounts.isPending) {
    return <p className="text-slate-500">{t('app.loading')}</p>
  }
  if (operation.isError) {
    return operation.error instanceof ApiError && operation.error.status === 404
      ? <NotFoundPage />
      : <p role="alert" className="text-red-600">{errorMessage(operation.error, t('app.operation_load_failed'))}</p>
  }
  if (accounts.isError) {
    return <p role="alert" className="text-red-600">{errorMessage(accounts.error, t('app.accounts_load_failed'))}</p>
  }

  return <EditForm operation={operation.data} accounts={accounts.data} />
}

function EditForm({ operation, accounts }: { operation: Operation; accounts: AccountChoice[] }) {
  const { t } = useTranslation()
  const update = useUpdateOperation(operation.id)
  // Only its own account, shown but fixed; it may have been hidden since, and still is the right one.
  const own = accounts.filter((account) => account.id === operation.accountId)

  return (
    <OperationForm
      title={t('operation.edit_title')}
      accounts={own}
      accountFixed
      initial={{
        accountId: operation.accountId,
        contractor: { id: operation.contractorId, name: operation.contractorName },
        operationDate: operation.operationDate,
        amount: formAmount(operation.amount),
        description: operation.description ?? '',
        confirmed: operation.confirmed,
      }}
      save={async (input) => {
        // The contractor goes along only when it changes (PATCH /api/account_operations/{id}).
        const changes: OperationInput = input.contractorId === operation.contractorId
          ? { ...input, contractorId: undefined }
          : input
        await update.mutateAsync(changes)

        return `/account/${operation.accountId}/operations?highlight=${operation.id}`
      }}
    />
  )
}
