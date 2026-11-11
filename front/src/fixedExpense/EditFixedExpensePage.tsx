import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { NotFoundPage } from '../pages/NotFoundPage'
import { formAmount } from '../ui/form'
import { FixedExpenseForm } from './FixedExpenseForm'
import { type FixedExpense, type FixedExpenseInput, useFixedExpense, useUpdateFixedExpense } from './fixedExpenseApi'

// /fixed-expense/:id/edit: the same form with the values of one fixed expense; back to the list after a save.
export function EditFixedExpensePage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const fixedExpense = useFixedExpense(id)

  if (fixedExpense.isPending) {
    return <p className="text-slate-500">{t('app.loading')}</p>
  }
  if (fixedExpense.isError) {
    return fixedExpense.error instanceof ApiError && fixedExpense.error.status === 404
      ? <NotFoundPage />
      : (
        <p role="alert" className="text-red-600">
          {errorMessage(fixedExpense.error, t('app.fixed_expense_load_failed'))}
        </p>
      )
  }

  return <EditForm fixedExpense={fixedExpense.data} />
}

function EditForm({ fixedExpense }: { fixedExpense: FixedExpense }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const update = useUpdateFixedExpense(fixedExpense.id)

  return (
    <section className="rounded-xl bg-white p-6 shadow">
      <h1 className="mb-4 text-xl font-semibold text-slate-900">{t('fixed_expense.edit_title')}</h1>
      <FixedExpenseForm
        adding={false}
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
      />
    </section>
  )
}
