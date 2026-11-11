import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import type { Operation } from '../account/accountApi'
import { formAmount } from '../ui/form'
import { QueryResult } from '../ui/QueryResult'
import { OperationForm } from './OperationForm'
import { type AccountChoice, type OperationInput, useAccounts, useOperation, useUpdateOperation } from './operationApi'

// /operation/:id/edit: the same form; the account of an operation is fixed when it is created.
export function EditOperationPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const operation = useOperation(id)
  const accounts = useAccounts()

  return (
    <QueryResult query={operation} failure={t('app.operation_load_failed')} notFound fresh>
      {(found) => (
        <QueryResult query={accounts} failure={t('app.accounts_load_failed')}>
          {(all) => <EditForm operation={found} accounts={all} />}
        </QueryResult>
      )}
    </QueryResult>
  )
}

function EditForm({ operation, accounts }: { operation: Operation; accounts: AccountChoice[] }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
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
        navigate(`/account/${operation.accountId}/operations?highlight=${operation.id}`)
      }}
      onCancel={() => navigate(-1)}
    />
  )
}
