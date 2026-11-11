import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { errorMessage } from '../messages'
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

  function planNextMonth() {
    // The browser's own question, as in the web app.
    if (!window.confirm(t('fixed_expense.confirm_plan'))) {
      return
    }
    // Afterwards the history of the main account, with the new operations, as in the web app.
    plan.mutate(undefined, { onSuccess: (result) => navigate(`/account/${result.accountId}/operations`) })
  }

  return (
    <div className="space-y-6">
      <section className="rounded-xl bg-white dark:bg-slate-900 p-6 shadow">
        <div className="mb-4 flex items-center justify-between gap-4">
          <h1 className="text-xl font-semibold text-slate-900 dark:text-slate-100">{t('fixed_expense.add_title')}</h1>
          <button
            type="button"
            onClick={planNextMonth}
            disabled={plan.isPending}
            className="cursor-pointer rounded-md border border-indigo-600 dark:border-indigo-400 px-3 py-1.5 text-sm
              font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950
              disabled:cursor-wait disabled:opacity-50"
          >
            {plan.isPending ? t('app.planning') : t('fixed_expense.plan_button')}
          </button>
        </div>
        {/* Why the API refused to plan, e.g. the operations of that month are already there. */}
        {plan.isError && (
          <p role="alert" className="mb-4 rounded bg-amber-100 dark:bg-amber-950 px-3 py-2 text-sm text-amber-900
            dark:text-amber-200">
            {errorMessage(plan.error, t('app.plan_failed'))}
          </p>
        )}
        <FixedExpenseForm
          key={formKey}
          initial={EMPTY}
          adding
          save={async (input) => {
            await create.mutateAsync(input)
            setFormKey((key) => key + 1)
          }}
        />
      </section>
      <section className="rounded-xl bg-white dark:bg-slate-900 p-6 shadow">
        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
          {t('fixed_expense.list_title')}
        </h2>
        {fixedExpenses.isPending && <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>}
        {fixedExpenses.isError && (
          <p role="alert" className="text-red-600 dark:text-red-400">
            {errorMessage(fixedExpenses.error, t('app.fixed_expenses_load_failed'))}
          </p>
        )}
        {fixedExpenses.data && <FixedExpensesTable fixedExpenses={fixedExpenses.data} />}
      </section>
    </div>
  )
}
