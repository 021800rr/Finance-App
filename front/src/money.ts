import i18n from './i18n'

// An amount as the API sends it: a decimal string and its currency, e.g. { amount: '-1234.50', currency: 'PLN' }.
export type Money = { amount: string; currency: string }

// The separators of an amount: in Polish as the web app shows it (1 234,56), in English as in the US (1,234.56).
const SEPARATORS = {
  pl: { group: ' ', decimal: ',' },
  en: { group: ',', decimal: '.' },
}

export function amountSeparators(): { group: string; decimal: string } {
  return i18n.resolvedLanguage === 'en' ? SEPARATORS.en : SEPARATORS.pl
}

// '-1234567.80' → '-1 234 567,80' in Polish, '-1,234,567.80' in English. The string is never turned into a number,
// so nothing gets rounded.
export function formatAmount(amount: string): string {
  const { group, decimal } = amountSeparators()
  const negative = amount.startsWith('-')
  const [whole = '', fraction] = (negative ? amount.slice(1) : amount).split('.')
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, group)

  return (negative ? '-' : '') + grouped + (fraction === undefined ? '' : decimal + fraction)
}
