import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { GoldAssetForm } from './GoldAssetForm'
import { useCreateGoldAsset, useGoldAssets, useGoldForms } from './goldAssetApi'
import { GoldAssetsTable } from './GoldAssetsTable'

const CARD = 'rounded-xl bg-white dark:bg-slate-900 p-6 shadow'

// /gold-assets, as in the web app: the form of a new gold asset and the list of all of them.
export function GoldAssetsPage() {
  const { t } = useTranslation()
  const assets = useGoldAssets()

  return (
    <div className="space-y-6">
      <section className={CARD}>
        <h1 className="mb-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{t('gold_asset.add_title')}</h1>
        <NewGoldAsset />
      </section>
      <section className={CARD}>
        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{t('gold_asset.list_title')}</h2>
        {assets.isPending && <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>}
        {assets.isError && (
          <p role="alert" className="text-red-600 dark:text-red-400">
            {errorMessage(assets.error, t('app.gold_assets_load_failed'))}
          </p>
        )}
        {assets.data && <GoldAssetsTable assets={assets.data} />}
      </section>
    </div>
  )
}

function NewGoldAsset() {
  const { t } = useTranslation()
  const forms = useGoldForms()
  const create = useCreateGoldAsset()
  // A new key gives a new, empty form after every asset added.
  const [formKey, setFormKey] = React.useState(0)

  if (forms.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (forms.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(forms.error, t('app.gold_forms_load_failed'))}
      </p>
    )
  }

  return (
    <GoldAssetForm
      key={formKey}
      forms={forms.data}
      initial={{ formId: forms.data[0]?.id ?? null, ounces: '', description: '' }}
      adding
      save={async (input) => {
        await create.mutateAsync(input)
        setFormKey((key) => key + 1)
      }}
    />
  )
}
