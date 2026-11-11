import { TrashIcon } from '@heroicons/react/24/outline'
import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { type Rate, useDeleteRate } from './rateApi'

const CELL = 'px-3 py-1.5'

type Props = {
  rates: Rate[]
  // the names of the banks by id; the rates have the id only
  bankNames: Map<number, string>
}

// The list of the web app: bank, currency, date, rate, with delete on every row.
export function RatesTable({ rates, bankNames }: Props) {
  const { t } = useTranslation()
  const remove = useDeleteRate()

  function deleteRate(rate: Rate) {
    // The browser's own question, as in the web app.
    if (!window.confirm(t('operation.confirm_delete'))) {
      return
    }
    remove.mutate(rate.id)
  }

  return (
    <div className="overflow-x-auto">
      {remove.isError && (
        <p role="alert" className="mb-2 rounded bg-amber-100 px-3 py-2 text-sm text-amber-900">
          {errorMessage(remove.error, t('app.delete_rate_failed'))}
        </p>
      )}
      <table className="min-w-full text-sm">
        <thead className="bg-slate-800 text-left text-white">
          <tr>
            <th className={CELL}>{t('account.bank')}</th>
            <th className={CELL}>{t('account.currency')}</th>
            <th className={CELL}>{t('form.date')}</th>
            <th className={`${CELL} text-right`}>{t('rate.value')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 text-slate-800">
          {rates.map((rate) => (
            <tr key={rate.id} className="hover:bg-slate-50">
              <td className={CELL}>{bankNames.get(rate.bankId) ?? rate.bankId}</td>
              <td className={CELL}>{rate.currencyCode}</td>
              <td className={`${CELL} whitespace-nowrap tabular-nums`}>{rate.rateDate}</td>
              <td className={`${CELL} text-right font-semibold tabular-nums`}>{formatAmount(rate.rateToPln)}</td>
              <td className={`${CELL} text-right`}>
                <button
                  type="button"
                  onClick={() => deleteRate(rate)}
                  disabled={remove.isPending}
                  title={t('operation.delete')}
                  aria-label={t('operation.delete')}
                  className="cursor-pointer align-middle disabled:cursor-wait"
                >
                  <TrashIcon className="size-5 text-red-600" />
                </button>
              </td>
            </tr>
          ))}
          {rates.length === 0 && (
            <tr>
              <td colSpan={5} className={`${CELL} text-center text-slate-500`}>{t('rate.none_found')}</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
