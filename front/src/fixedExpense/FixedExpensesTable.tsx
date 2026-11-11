import { PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { type FixedExpense, useDeleteFixedExpense } from './fixedExpenseApi'

const CELL = 'px-3 py-1.5'
const ICON = 'cursor-pointer text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 '
  + 'disabled:cursor-wait'

// The list of the web app: #, day, contractor, description, amount, with edit and delete on every row.
export function FixedExpensesTable({ fixedExpenses }: { fixedExpenses: FixedExpense[] }) {
  const { t } = useTranslation()
  const remove = useDeleteFixedExpense()

  function deleteFixedExpense(fixedExpense: FixedExpense) {
    // The browser's own question, as in the web app.
    if (!window.confirm(t('operation.confirm_delete'))) {
      return
    }
    remove.mutate(fixedExpense.id)
  }

  return (
    <div className="overflow-x-auto">
      {remove.isError && (
        <p role="alert" className="mb-2 rounded bg-amber-100 dark:bg-amber-950 px-3 py-2 text-sm text-amber-900
          dark:text-amber-200">
          {errorMessage(remove.error, t('app.delete_fixed_expense_failed'))}
        </p>
      )}
      <table className="min-w-full text-sm">
        <thead className="bg-slate-800 text-left text-white">
          <tr>
            <th className={`${CELL} text-right`}>#</th>
            <th className={`${CELL} text-right`}>{t('fixed_expense.day')}</th>
            <th className={CELL}>{t('form.contractor')}</th>
            <th className={CELL}>{t('form.description')}</th>
            <th className={`${CELL} text-right`}>{t('form.amount')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-800 dark:text-slate-200">
          {fixedExpenses.map((fixedExpense) => (
            <tr key={fixedExpense.id} className="hover:bg-slate-50 dark:hover:bg-slate-800">
              <td className={`${CELL} text-right text-slate-500 dark:text-slate-400 tabular-nums`}>
                {fixedExpense.id}
              </td>
              <td className={`${CELL} text-right tabular-nums`}>{fixedExpense.day}</td>
              <td className={CELL}>{fixedExpense.contractorName}</td>
              <td className={`${CELL} text-slate-500 dark:text-slate-400`}>{fixedExpense.description}</td>
              <td className={`${CELL} whitespace-nowrap text-right font-semibold tabular-nums`}>
                {formatAmount(fixedExpense.amount)}
              </td>
              <td className={CELL}>
                <div className="flex items-center justify-end gap-2">
                  <Link
                    to={`/fixed-expense/${fixedExpense.id}/edit`}
                    title={t('operation.edit')}
                    aria-label={t('operation.edit')}
                    className={ICON}
                  >
                    <PencilSquareIcon className="size-5" />
                  </Link>
                  <button
                    type="button"
                    onClick={() => deleteFixedExpense(fixedExpense)}
                    disabled={remove.isPending}
                    title={t('operation.delete')}
                    aria-label={t('operation.delete')}
                    className={ICON}
                  >
                    <TrashIcon className="size-5 text-red-600 dark:text-red-400" />
                  </button>
                </div>
              </td>
            </tr>
          ))}
          {fixedExpenses.length === 0 && (
            <tr>
              <td colSpan={6} className={`${CELL} text-center text-slate-500 dark:text-slate-400`}>
                {t('fixed_expense.none_found')}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
