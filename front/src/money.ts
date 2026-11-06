// An amount as the API sends it: a decimal string and its currency, e.g. { amount: '-1234.50', currency: 'PLN' }.
export type Money = { amount: string; currency: string }

// '-1234567.80' → '-1 234 567,80', as the web app's |money filter shows it. The string is never turned into a
// number, so nothing gets rounded.
export function formatAmount(amount: string): string {
  const negative = amount.startsWith('-')
  const [whole, fraction] = (negative ? amount.slice(1) : amount).split('.')
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')

  return (negative ? '-' : '') + grouped + (fraction === undefined ? '' : ',' + fraction)
}
