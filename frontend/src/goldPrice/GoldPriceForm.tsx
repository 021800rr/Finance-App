import * as React from 'react'
import { useTranslation } from 'react-i18next'
import type { GoldAsset } from '../goldAsset/goldAssetApi'
import { AmountInput } from '../ui/AmountInput'
import { DateInput } from '../ui/DateInput'
import { Alert } from '../ui/Feedback'
import { fieldErrorProps, inputClass, MAX_DATE, MIN_DATE, toApiAmount, today } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import { useApiForm } from '../ui/useApiForm'
import type { GoldPriceInput } from './goldPriceApi'

type Field = 'asset' | 'date' | 'price'

// The fields of the API's field errors (propertyPath) on the fields of the form.
const FIELD_OF_PATH: Record<string, Field> = {
  goldAssetId: 'asset',
  priceDate: 'date',
  pricePln: 'price',
}

type Props = {
  assets: GoldAsset[]
  // sends the form; when it fails, the form shows why
  save: (input: GoldPriceInput) => Promise<void>
}

// The form of a new gold price, as in the web app: asset (by its description), date (today at first), price in PLN.
export function GoldPriceForm({ assets, save }: Props) {
  const { t } = useTranslation()
  const [goldAssetId, setGoldAssetId] = React.useState(assets[0]?.id ?? null)
  const [priceDate, setPriceDate] = React.useState(today)
  const [price, setPrice] = React.useState('')
  const form = useApiForm({ fieldOfPath: FIELD_OF_PATH, failure: t('app.save_gold_price_failed') })

  function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    // A price is an amount with cents, up to 999 999,99: '8 650,00' and '8,650.00' are both 8650.
    void form.submit(() => save({ goldAssetId, priceDate, pricePln: toApiAmount(price) }))
  }

  const { errors } = form

  return (
    <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
      {form.message && <Alert tone="error">{form.message}</Alert>}
      <FormRow label={t('gold_price.asset')} htmlFor="asset" error={errors.asset}>
        <select
          id="asset"
          {...fieldErrorProps('asset', errors.asset)}
          value={goldAssetId ?? ''}
          onChange={(event) => setGoldAssetId(Number(event.target.value))}
          className={inputClass(errors.asset)}
        >
          {assets.map((asset) => <option key={asset.id} value={asset.id}>{asset.description}</option>)}
        </select>
      </FormRow>
      <FormRow label={t('form.date')} htmlFor="date" error={errors.date}>
        <DateInput
          id="date"
          {...fieldErrorProps('date', errors.date)}
          value={priceDate}
          min={MIN_DATE}
          max={MAX_DATE}
          onChange={setPriceDate}
          className={inputClass(errors.date)}
        />
      </FormRow>
      <FormRow label={t('gold_price.price_pln')} htmlFor="price" error={errors.price}>
        <AmountInput
          id="price"
          {...fieldErrorProps('price', errors.price)}
          value={price}
          onChange={setPrice}
          allowMinus={false}
          placeholder={t('app.price_placeholder')}
          className={inputClass(errors.price)}
        />
      </FormRow>
      <FormButtons sending={form.sending} submitLabel={t('common.add')} />
    </form>
  )
}
