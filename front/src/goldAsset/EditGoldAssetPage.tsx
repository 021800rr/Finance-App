import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { NotFoundPage } from '../pages/NotFoundPage'
import { formAmount } from '../ui/form'
import { GoldAssetForm } from './GoldAssetForm'
import { type GoldAsset, type GoldForm, useGoldAsset, useGoldForms, useUpdateGoldAsset } from './goldAssetApi'

// /gold-assets/:id/edit: the same form with the values of one asset; back to the list after a save.
export function EditGoldAssetPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const asset = useGoldAsset(id)
  const forms = useGoldForms()

  if (asset.isPending || forms.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (asset.isError) {
    return asset.error instanceof ApiError && asset.error.status === 404
      ? <NotFoundPage />
      : (
        <p role="alert" className="text-red-600 dark:text-red-400">
          {errorMessage(asset.error, t('app.gold_asset_load_failed'))}
        </p>
      )
  }
  if (forms.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(forms.error, t('app.gold_forms_load_failed'))}
      </p>
    )
  }

  return <EditForm asset={asset.data} forms={forms.data} />
}

function EditForm({ asset, forms }: { asset: GoldAsset; forms: GoldForm[] }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const update = useUpdateGoldAsset(asset.id)

  return (
    <section className="rounded-xl bg-white dark:bg-slate-900 p-6 shadow">
      <h1 className="mb-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{t('gold_asset.edit_title')}</h1>
      <GoldAssetForm
        forms={forms}
        initial={{ formId: asset.formId, ounces: formAmount(asset.ounces), description: asset.description }}
        adding={false}
        save={async (input) => {
          await update.mutateAsync(input)
          navigate('/gold-assets')
        }}
      />
    </section>
  )
}
