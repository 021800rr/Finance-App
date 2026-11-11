import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { Card } from '../ui/Card'
import { formAmount } from '../ui/form'
import { QueryResult } from '../ui/QueryResult'
import { FixedExpenseForm } from './FixedExpenseForm'
import { type FixedExpense, type FixedExpenseInput, useFixedExpense, useUpdateFixedExpense } from './fixedExpenseApi'

// /fixed-expense/:id/edit: the same form with the values of one fixed expense; back to the list after a save.
export function EditFixedExpensePage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const fixedExpense = useFixedExpense(id)

  return (
    <QueryResult query={fixedExpense} failure={t('app.fixed_expense_load_failed')} notFound>
      {(found) => <EditForm fixedExpense={found} />}
    </QueryResult>
  )
}

function EditForm({ fixedExpense }: { fixedExpense: FixedExpense }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const update = useUpdateFixedExpense(fixedExpense.id)

  return (
    <Card title={t('fixed_expense.edit_title')}>
      <FixedExpenseForm
        initial={{
          day: String(fixedExpense.day),
          contractor: { id: fixedExpense.contractorId, name: fixedExpense.contractorName },
          amount: formAmount(fixedExpense.amount),
          description: fixedExpense.description ?? '',
        }}
        save={async (input) => {
          // The contractor goes along only when it changes (PATCH /api/fixed_expenses/{id}).
          const changes: FixedExpenseInput = input.contractorId === fixedExpense.contractorId
            ? { ...input, contractorId: undefined }
            : input
          await update.mutateAsync(changes)
          navigate('/fixed-expense')
        }}
        // As in the web app: back to the list.
        onCancel={() => navigate('/fixed-expense')}
      />
    </Card>
  )
}
