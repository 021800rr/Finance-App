import { TrashIcon } from '@heroicons/react/24/outline'
import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { type GoldPrice, useDeleteGoldPrice } from './goldPriceApi'

const CELL = 'px-3 py-1.5'

type Props = {
  prices: GoldPrice[]
  // the descriptions of the assets by id; the prices have the id only
  assetNames: Map<number, string>
}

// The list of the web app: #, asset, date, price, with delete on every row.
export function GoldPricesTable({ prices, assetNames }: Props) {
  const { t } = useTranslation()
  const remove = useDeleteGoldPrice()

  function deletePrice(price: GoldPrice) {
    // The browser's own question, as in the web app.
    if (!window.confirm(t('operation.confirm_delete'))) {
      return
    }
    remove.mutate(price.id)
  }

  return (
    <div className="overflow-x-auto">
      {remove.isError && (
        <p role="alert" className="mb-2 rounded bg-amber-100 dark:bg-amber-950 px-3 py-2 text-sm text-amber-900
          dark:text-amber-200">
          {errorMessage(remove.error, t('app.delete_gold_price_failed'))}
        </p>
      )}
      <table className="min-w-full text-sm">
        <thead className="bg-slate-800 text-left text-white">
          <tr>
            <th className={`${CELL} text-right`}>#</th>
            <th className={CELL}>{t('gold_price.asset')}</th>
            <th className={CELL}>{t('form.date')}</th>
            <th className={`${CELL} text-right`}>{t('gold_price.price_pln')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-800 dark:text-slate-200">
          {prices.map((price) => (
            <tr key={price.id} className="hover:bg-slate-50 dark:hover:bg-slate-800">
              <td className={`${CELL} text-right text-slate-500 dark:text-slate-400 tabular-nums`}>{price.id}</td>
              <td className={CELL}>{assetNames.get(price.goldAssetId) ?? `#${price.goldAssetId}`}</td>
              <td className={`${CELL} whitespace-nowrap tabular-nums`}>{price.priceDate}</td>
              <td className={`${CELL} whitespace-nowrap text-right font-semibold tabular-nums`}>
                {formatAmount(price.pricePln)}
              </td>
              <td className={`${CELL} text-right`}>
                <button
                  type="button"
                  onClick={() => deletePrice(price)}
                  disabled={remove.isPending}
                  title={t('operation.delete')}
                  aria-label={t('operation.delete')}
                  className="cursor-pointer align-middle disabled:cursor-wait"
                >
                  <TrashIcon className="size-5 text-red-600 dark:text-red-400" />
                </button>
              </td>
            </tr>
          ))}
          {prices.length === 0 && (
            <tr>
              <td colSpan={5} className={`${CELL} text-center text-slate-500 dark:text-slate-400`}>
                {t('gold_price.none_found')}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
