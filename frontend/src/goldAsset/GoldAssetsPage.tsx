import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Card } from '../ui/Card'
import { QueryResult } from '../ui/QueryResult'
import { GoldAssetForm } from './GoldAssetForm'
import { useCreateGoldAsset, useGoldAssets, useGoldForms } from './goldAssetApi'
import { GoldAssetsTable } from './GoldAssetsTable'

// /gold-assets, as in the web app: the form of a new gold asset and the list of all of them.
export function GoldAssetsPage() {
  const { t } = useTranslation()
  const assets = useGoldAssets()

  return (
    <div className="space-y-6">
      <Card title={t('gold_asset.add_title')}>
        <NewGoldAsset />
      </Card>
      <Card title={t('gold_asset.list_title')} heading="h2">
        <QueryResult query={assets} failure={t('app.gold_assets_load_failed')}>
          {(rows) => <GoldAssetsTable assets={rows} />}
        </QueryResult>
      </Card>
    </div>
  )
}

function NewGoldAsset() {
  const { t } = useTranslation()
  const forms = useGoldForms()
  const create = useCreateGoldAsset()
  // A new key gives a new, empty form after every asset added.
  const [formKey, setFormKey] = React.useState(0)

  return (
    <QueryResult query={forms} failure={t('app.gold_forms_load_failed')}>
      {(shapes) => (
        <GoldAssetForm
          key={formKey}
          forms={shapes}
          initial={{ formId: shapes[0]?.id ?? null, ounces: '', description: '' }}
          submitLabel={t('common.add')}
          save={async (input) => {
            await create.mutateAsync(input)
            setFormKey((key) => key + 1)
          }}
        />
      )}
    </QueryResult>
  )
}
