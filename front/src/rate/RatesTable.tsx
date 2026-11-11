import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { EmptyRow } from '../ui/EmptyRow'
import { Alert } from '../ui/Feedback'
import { DeleteButton } from '../ui/RowActions'
import { AMOUNT, BODY, CELL, HEAD, ROW, TABLE } from '../ui/tableStyle'
import { type Rate, useDeleteRate } from './rateApi'

type Props = {
  rates: Rate[]
  // the names of the banks by id; the rates have the id only
  bankNames: Map<number, string>
}

// The list of the web app: bank, currency, date, rate, with delete on every row.
export function RatesTable({ rates, bankNames }: Props) {
  const { t } = useTranslation()
  const remove = useDeleteRate()

  return (
    <div className="space-y-2 overflow-x-auto">
      {remove.isError && <Alert tone="warning">{errorMessage(remove.error, t('app.delete_rate_failed'))}</Alert>}
      <table className={TABLE}>
        <thead className={HEAD}>
          <tr>
            <th className={CELL}>{t('account.bank')}</th>
            <th className={CELL}>{t('account.currency')}</th>
            <th className={CELL}>{t('form.date')}</th>
            <th className={`${CELL} text-right`}>{t('rate.value')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className={BODY}>
          {rates.map((rate) => (
            <tr key={rate.id} className={ROW}>
              <td className={CELL}>{bankNames.get(rate.bankId) ?? `#${rate.bankId}`}</td>
              <td className={CELL}>{rate.currencyCode}</td>
              <td className={`${CELL} whitespace-nowrap tabular-nums`}>{rate.rateDate}</td>
              <td className={`${CELL} ${AMOUNT}`}>{formatAmount(rate.rateToPln)}</td>
              <td className={`${CELL} text-right`}>
                <DeleteButton onDelete={() => remove.mutate(rate.id)} disabled={remove.isPending} />
              </td>
            </tr>
          ))}
          {rates.length === 0 && <EmptyRow columns={5} text={t('rate.none_found')} />}
        </tbody>
      </table>
    </div>
  )
}
