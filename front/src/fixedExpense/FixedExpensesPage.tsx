import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { errorMessage } from '../messages'
import { Card } from '../ui/Card'
import { ConfirmDialog } from '../ui/ConfirmDialog'
import { Alert } from '../ui/Feedback'
import { QueryResult } from '../ui/QueryResult'
import { FixedExpenseForm, type FixedExpenseFormValues } from './FixedExpenseForm'
import { useCreateFixedExpense, useFixedExpenses, usePlanFixedExpenses } from './fixedExpenseApi'
import { FixedExpensesTable } from './FixedExpensesTable'

const EMPTY: FixedExpenseFormValues = { day: '', contractor: null, amount: '', description: '' }

// /fixed-expense, as in the web app: the form of a new fixed expense with "Zaplanuj wydatki" beside its title, and
// the list of all fixed expenses under it.
export function FixedExpensesPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const fixedExpenses = useFixedExpenses()
  const create = useCreateFixedExpense()
  const plan = usePlanFixedExpenses()
  // A new key gives a new, empty form after every fixed expense added.
  const [formKey, setFormKey] = React.useState(0)
  const [confirmingPlan, setConfirmingPlan] = React.useState(false)

  function planNextMonth() {
    // Afterwards the history of the main account, with the new operations, as in the web app.
    plan.mutate(undefined, { onSuccess: (result) => navigate(`/account/${result.accountId}/operations`) })
  }

  return (
    <div className="space-y-6">
      <Card
        title={t('fixed_expense.add_title')}
        aside={<PlanButton planning={plan.isPending} onClick={() => setConfirmingPlan(true)} />}
      >
        {/* Why the API refused to plan, e.g. the operations of that month are already there. */}
        {plan.isError && <Alert tone="warning">{errorMessage(plan.error, t('app.plan_failed'))}</Alert>}
        <FixedExpenseForm
          key={formKey}
          initial={EMPTY}
          submitLabel={t('fixed_expense.add')}
          save={async (input) => {
            await create.mutateAsync(input)
            setFormKey((key) => key + 1)
          }}
        />
      </Card>
      <Card title={t('fixed_expense.list_title')} heading="h2">
        <QueryResult query={fixedExpenses} failure={t('app.fixed_expenses_load_failed')}>
          {(rows) => <FixedExpensesTable fixedExpenses={rows} />}
        </QueryResult>
      </Card>
      <ConfirmDialog
        open={confirmingPlan}
        message={t('fixed_expense.confirm_plan')}
        onConfirm={planNextMonth}
        onCancel={() => setConfirmingPlan(false)}
        disabled={plan.isPending}
      />
    </div>
  )
}

function PlanButton({ planning, onClick }: { planning: boolean; onClick: () => void }) {
  const { t } = useTranslation()

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={planning}
      className="cursor-pointer rounded-md border border-indigo-600 dark:border-indigo-400 px-3 py-1.5 text-sm
        font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950
        disabled:cursor-wait disabled:opacity-50"
    >
      {planning ? t('app.planning') : t('fixed_expense.plan_button')}
    </button>
  )
}
