import type { Violation } from '../api'
import { violationMessage } from '../messages'
import { amountSeparators } from '../money'

// What the forms share: the look of their fields, the date range, the amount rules, the API's field errors.

const INPUT = 'block w-full rounded-md border px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none '
  + 'focus:ring-1 disabled:bg-slate-100 dark:disabled:bg-slate-800 disabled:text-slate-500 '
  + 'dark:disabled:text-slate-400 '
const INPUT_OK = `${INPUT}border-slate-300 dark:border-slate-600 focus:border-indigo-500 focus:ring-indigo-500`
const INPUT_ERROR = `${INPUT}border-red-500 focus:border-red-500 focus:ring-red-500`

// The classes of a field: red while it has an error.
export function inputClass(error: string | undefined): string {
  return error ? INPUT_ERROR : INPUT_OK
}

// The business date range of the backend (BusinessDateRange).
export const MIN_DATE = '2025-01-01'
export const MAX_DATE = '2070-12-31'

// A date of the API, 'YYYY-MM-DD', as a Date at local midnight (new Date('2026-11-06') would be midnight UTC, the day
// before west of Greenwich); undefined for text that is no such date, '2026-02-31' too.
export function dateFromValue(value: string): Date | undefined {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (match === null) {
    return undefined
  }
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))

  return valueFromDate(date) === value ? date : undefined
}

// A Date as the API takes it, 'YYYY-MM-DD', in the browser's time zone (toISOString() would give the UTC date).
export function valueFromDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${String(date.getDate()).padStart(2, '0')}`
}

export function today(): string {
  return valueFromDate(new Date())
}

// What is left of a typed or pasted text when only amount characters stay: digits, a comma or a dot, and a minus at
// the start when the amount may be negative. '-1 234,56 zł' → '-1234,56'.
export function amountCharacters(text: string, allowMinus: boolean): string {
  const kept = text.replace(/[^\d,.-]/g, '')
  const minus = allowMinus && kept.startsWith('-') ? '-' : ''

  return minus + kept.replace(/-/g, '')
}

// The amount of a form as the API takes it, a plain decimal number with a dot, whichever convention it was typed
// in: '1234,56' (Polish), '1,234.56' (English) and '1234.56' all give '1234.56'. Amounts have cents only, so the
// last comma or dot is the decimal one unless three digits follow it; every other one groups thousands ('3,200' and
// '3.200' are 3200). This does not depend on the language, which may change while a form is filled in.
export function toApiAmount(amount: string): string {
  const last = Math.max(amount.lastIndexOf(','), amount.lastIndexOf('.'))
  const fraction = last === -1 ? '' : amount.slice(last + 1)
  if (last === -1 || fraction.length === 3) {
    return amount.replace(/[,.]/g, '')
  }
  const whole = amount.slice(0, last).replace(/[,.]/g, '')

  return fraction === '' ? whole : `${whole}.${fraction}`
}

// A number below 1000 (a rate, ounces) as the API takes it. It has no thousands to group, so a comma or a dot is
// always the decimal one: '4,559' is 4.559 (as an amount it would be 4559).
export function toApiDecimal(number: string): string {
  return number.replace(/,/g, '.')
}

// The amount of the API as one types it in the language of the app: '-3200.00' → '-3200,00' in Polish.
export function formAmount(amount: string): string {
  return amount.replace('.', amountSeparators().decimal)
}

// The API's field errors on the fields of a form, in Polish; the first error of a field wins.
export function fieldErrors<F extends string>(
  violations: Violation[],
  fieldOfPath: Record<string, F>,
): Partial<Record<F, string>> {
  const errors: Partial<Record<F, string>> = {}
  for (const violation of violations) {
    const field = fieldOfPath[violation.propertyPath]
    if (field !== undefined) {
      errors[field] ??= violationMessage(violation)
    }
  }

  return errors
}
