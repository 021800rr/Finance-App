import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { Card } from '../ui/Card'
import { formAmount } from '../ui/form'
import { QueryResult } from '../ui/QueryResult'
import { GoldAssetForm } from './GoldAssetForm'
import { type GoldAsset, type GoldForm, useGoldAsset, useGoldForms, useUpdateGoldAsset } from './goldAssetApi'

// /gold-assets/:id/edit: the same form with the values of one asset; back to the list after a save.
export function EditGoldAssetPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const asset = useGoldAsset(id)
  const forms = useGoldForms()

  return (
    <QueryResult query={asset} failure={t('app.gold_asset_load_failed')} notFound fresh>
      {(found) => (
        <QueryResult query={forms} failure={t('app.gold_forms_load_failed')}>
          {(shapes) => <EditForm asset={found} forms={shapes} />}
        </QueryResult>
      )}
    </QueryResult>
  )
}

function EditForm({ asset, forms }: { asset: GoldAsset; forms: GoldForm[] }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const update = useUpdateGoldAsset(asset.id)

  return (
    <Card title={t('gold_asset.edit_title')}>
      <GoldAssetForm
        forms={forms}
        initial={{ formId: asset.formId, ounces: formAmount(asset.ounces), description: asset.description }}
        save={async (input) => {
          await update.mutateAsync(input)
          navigate('/gold-assets')
        }}
        // As in the web app: back to the list.
        onCancel={() => navigate('/gold-assets')}
      />
    </Card>
  )
}
