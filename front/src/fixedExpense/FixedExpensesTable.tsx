import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { EmptyRow } from '../ui/EmptyRow'
import { Alert } from '../ui/Feedback'
import { DeleteButton, EditLink } from '../ui/RowActions'
import { AMOUNT, BODY, CELL, HEAD, MUTED, ROW, TABLE } from '../ui/tableStyle'
import { type FixedExpense, useDeleteFixedExpense } from './fixedExpenseApi'

// The list of the web app: #, day, contractor, description, amount, with edit and delete on every row.
export function FixedExpensesTable({ fixedExpenses }: { fixedExpenses: FixedExpense[] }) {
  const { t } = useTranslation()
  const remove = useDeleteFixedExpense()

  return (
    <div className="space-y-2 overflow-x-auto">
      {remove.isError && (
        <Alert tone="warning">{errorMessage(remove.error, t('app.delete_fixed_expense_failed'))}</Alert>
      )}
      <table className={TABLE}>
        <thead className={HEAD}>
          <tr>
            <th className={`${CELL} text-right`}>#</th>
            <th className={`${CELL} text-right`}>{t('fixed_expense.day')}</th>
            <th className={CELL}>{t('form.contractor')}</th>
            <th className={CELL}>{t('form.description')}</th>
            <th className={`${CELL} text-right`}>{t('form.amount')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className={BODY}>
          {fixedExpenses.map((fixedExpense) => (
            <tr key={fixedExpense.id} className={ROW}>
              <td className={`${CELL} text-right tabular-nums ${MUTED}`}>{fixedExpense.id}</td>
              <td className={`${CELL} text-right tabular-nums`}>{fixedExpense.day}</td>
              <td className={CELL}>{fixedExpense.contractorName}</td>
              <td className={`${CELL} ${MUTED}`}>{fixedExpense.description}</td>
              <td className={`${CELL} ${AMOUNT}`}>{formatAmount(fixedExpense.amount)}</td>
              <td className={CELL}>
                <div className="flex items-center justify-end gap-2">
                  <EditLink to={`/fixed-expense/${fixedExpense.id}/edit`} />
                  <DeleteButton onDelete={() => remove.mutate(fixedExpense.id)} disabled={remove.isPending} />
                </div>
              </td>
            </tr>
          ))}
          {fixedExpenses.length === 0 && <EmptyRow columns={6} text={t('fixed_expense.none_found')} />}
        </tbody>
      </table>
    </div>
  )
}
