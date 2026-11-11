import { PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { type GoldAsset, useDeleteGoldAsset } from './goldAssetApi'

const CELL = 'px-3 py-1.5'
const ICON = 'cursor-pointer text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 '
  + 'disabled:cursor-wait'

// The list of the web app: #, form, ounces, description, with edit and delete on every row.
export function GoldAssetsTable({ assets }: { assets: GoldAsset[] }) {
  const { t } = useTranslation()
  const remove = useDeleteGoldAsset()

  function deleteAsset(asset: GoldAsset) {
    // The browser's own question, as in the web app. The prices of the asset go with it.
    if (!window.confirm(t('operation.confirm_delete'))) {
      return
    }
    remove.mutate(asset.id)
  }

  return (
    <div className="overflow-x-auto">
      {remove.isError && (
        <p role="alert" className="mb-2 rounded bg-amber-100 dark:bg-amber-950 px-3 py-2 text-sm text-amber-900
          dark:text-amber-200">
          {errorMessage(remove.error, t('app.delete_gold_asset_failed'))}
        </p>
      )}
      <table className="min-w-full text-sm">
        <thead className="bg-slate-800 text-left text-white">
          <tr>
            <th className={`${CELL} text-right`}>#</th>
            <th className={CELL}>{t('gold_asset.shape')}</th>
            <th className={`${CELL} text-right`}>{t('gold_asset.ounces')}</th>
            <th className={CELL}>{t('form.description')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-800 dark:text-slate-200">
          {assets.map((asset) => (
            <tr key={asset.id} className="hover:bg-slate-50 dark:hover:bg-slate-800">
              <td className={`${CELL} text-right text-slate-500 dark:text-slate-400 tabular-nums`}>{asset.id}</td>
              <td className={CELL}>{asset.formName}</td>
              <td className={`${CELL} whitespace-nowrap text-right font-semibold tabular-nums`}>
                {formatAmount(asset.ounces)}
              </td>
              <td className={`${CELL} text-slate-500 dark:text-slate-400`}>{asset.description}</td>
              <td className={CELL}>
                <div className="flex items-center justify-end gap-2">
                  <Link
                    to={`/gold-assets/${asset.id}/edit`}
                    title={t('operation.edit')}
                    aria-label={t('operation.edit')}
                    className={ICON}
                  >
                    <PencilSquareIcon className="size-5" />
                  </Link>
                  <button
                    type="button"
                    onClick={() => deleteAsset(asset)}
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
          {assets.length === 0 && (
            <tr>
              <td colSpan={5} className={`${CELL} text-center text-slate-500 dark:text-slate-400`}>
                {t('gold_asset.none_found')}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
