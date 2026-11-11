import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '../api'
import type { GoldAsset } from '../goldAsset/goldAssetApi'
import { errorMessage } from '../messages'
import { AmountInput } from '../ui/AmountInput'
import { DateInput } from '../ui/DateInput'
import { fieldErrors, inputClass, MAX_DATE, MIN_DATE, toApiAmount, today } from '../ui/form'
import { FormRow } from '../ui/FormRow'
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
  const [errors, setErrors] = React.useState<Partial<Record<Field, string>>>({})
  const [failure, setFailure] = React.useState('')
  const [sending, setSending] = React.useState(false)

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setFailure('')
    setErrors({})
    setSending(true)
    try {
      // A price is an amount with cents, up to 999 999,99: '8 650,00' and '8,650.00' are both 8650.
      await save({ goldAssetId, priceDate, pricePln: toApiAmount(price) })
    } catch (error) {
      if (error instanceof ApiError && error.violations.length > 0) {
        setErrors(fieldErrors(error.violations, FIELD_OF_PATH))
        setFailure(t('form.fill_required_fields'))
      } else {
        setFailure(errorMessage(error, t('app.save_gold_price_failed')))
      }
    } finally {
      setSending(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
      {failure && <p role="alert" className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{failure}</p>}
      <FormRow label={t('gold_price.asset')} htmlFor="asset" error={errors.asset}>
        <select
          id="asset"
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
          value={price}
          onChange={setPrice}
          allowMinus={false}
          placeholder={t('app.price_placeholder')}
          className={inputClass(errors.price)}
        />
      </FormRow>
      <div className="pt-2">
        <button
          type="submit"
          disabled={sending}
          className="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          {sending ? t('app.saving') : t('common.add')}
        </button>
      </div>
    </form>
  )
}
