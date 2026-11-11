import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { EmptyRow } from '../ui/EmptyRow'
import { Alert } from '../ui/Feedback'
import { DeleteButton } from '../ui/RowActions'
import { AMOUNT, BODY, CELL, HEAD, MUTED, ROW, TABLE } from '../ui/tableStyle'
import { type GoldPrice, useDeleteGoldPrice } from './goldPriceApi'

type Props = {
  prices: GoldPrice[]
  // the descriptions of the assets by id; the prices have the id only
  assetNames: Map<number, string>
}

// The list of the web app: #, asset, date, price, with delete on every row.
export function GoldPricesTable({ prices, assetNames }: Props) {
  const { t } = useTranslation()
  const remove = useDeleteGoldPrice()

  return (
    <div className="space-y-2 overflow-x-auto">
      {remove.isError && <Alert tone="warning">{errorMessage(remove.error, t('app.delete_gold_price_failed'))}</Alert>}
      <table className={TABLE}>
        <thead className={HEAD}>
          <tr>
            <th className={`${CELL} text-right`}>#</th>
            <th className={CELL}>{t('gold_price.asset')}</th>
            <th className={CELL}>{t('form.date')}</th>
            <th className={`${CELL} text-right`}>{t('gold_price.price_pln')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className={BODY}>
          {prices.map((price) => (
            <tr key={price.id} className={ROW}>
              <td className={`${CELL} text-right tabular-nums ${MUTED}`}>{price.id}</td>
              <td className={CELL}>{assetNames.get(price.goldAssetId) ?? `#${price.goldAssetId}`}</td>
              <td className={`${CELL} whitespace-nowrap tabular-nums`}>{price.priceDate}</td>
              <td className={`${CELL} ${AMOUNT}`}>{formatAmount(price.pricePln)}</td>
              <td className={`${CELL} text-right`}>
                <DeleteButton onDelete={() => remove.mutate(price.id)} disabled={remove.isPending} />
              </td>
            </tr>
          ))}
          {prices.length === 0 && <EmptyRow columns={5} text={t('gold_price.none_found')} />}
        </tbody>
      </table>
    </div>
  )
}
