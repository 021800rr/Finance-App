import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { EmptyRow } from '../ui/EmptyRow'
import { Alert } from '../ui/Feedback'
import { DeleteButton, EditLink } from '../ui/RowActions'
import { AMOUNT, BODY, CELL, HEAD, MUTED, ROW, TABLE } from '../ui/tableStyle'
import { type GoldAsset, useDeleteGoldAsset } from './goldAssetApi'

// The list of the web app: #, form, ounces, description, with edit and delete on every row. Deleting an asset
// deletes its prices too.
export function GoldAssetsTable({ assets }: { assets: GoldAsset[] }) {
  const { t } = useTranslation()
  const remove = useDeleteGoldAsset()

  return (
    <div className="space-y-2 overflow-x-auto">
      {remove.isError && <Alert tone="warning">{errorMessage(remove.error, t('app.delete_gold_asset_failed'))}</Alert>}
      <table className={TABLE}>
        <thead className={HEAD}>
          <tr>
            <th className={`${CELL} text-right`}>#</th>
            <th className={CELL}>{t('gold_asset.shape')}</th>
            <th className={`${CELL} text-right`}>{t('gold_asset.ounces')}</th>
            <th className={CELL}>{t('form.description')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className={BODY}>
          {assets.map((asset) => (
            <tr key={asset.id} className={ROW}>
              <td className={`${CELL} text-right tabular-nums ${MUTED}`}>{asset.id}</td>
              <td className={CELL}>{asset.formName}</td>
              <td className={`${CELL} ${AMOUNT}`}>{formatAmount(asset.ounces)}</td>
              <td className={`${CELL} ${MUTED}`}>{asset.description}</td>
              <td className={CELL}>
                <div className="flex items-center justify-end gap-2">
                  <EditLink to={`/gold-assets/${asset.id}/edit`} />
                  <DeleteButton onDelete={() => remove.mutate(asset.id)} disabled={remove.isPending} />
                </div>
              </td>
            </tr>
          ))}
          {assets.length === 0 && <EmptyRow columns={5} text={t('gold_asset.none_found')} />}
        </tbody>
      </table>
    </div>
  )
}
