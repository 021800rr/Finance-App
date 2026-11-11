import { type QueryKey, useQueryClient } from '@tanstack/react-query'

// The keys of everything read from the API, in one place. A key is a list: its first word names the kind of data,
// the rest picks one piece of it. TanStack Query keeps one copy per key.
export const queryKeys = {
  summary: (showHidden: boolean) => ['summary', showHidden],
  accounts: () => ['accounts'],
  account: (id: string) => ['account', id],
  accountOperations: (id: string, page: number) => ['account', id, 'operations', page],
  operation: (id: string) => ['operation', id],
  contractors: (text: string) => ['contractors', text],
  search: (phrase: string, page: number) => ['search', phrase, page],
  fixedExpenses: () => ['fixedExpenses'],
  fixedExpense: (id: string) => ['fixedExpenses', id],
  banks: () => ['banks'],
  currencies: () => ['currencies'],
  recentRates: () => ['rates', 'recent'],
  chartRates: () => ['rates', 'chart'],
  goldForms: () => ['goldForms'],
  goldAssets: () => ['goldAssets'],
  goldAsset: (id: string) => ['goldAssets', id],
  recentGoldPrices: () => ['goldPrices', 'recent'],
  chartGoldPrices: () => ['goldPrices', 'chart'],
} satisfies Record<string, (...args: never[]) => QueryKey>

// What a change on the API makes stale, as the beginnings of the keys to read again: ['account'] reaches every key
// that starts with it, ['account', '4', 'operations', 2] too.
const STALE_AFTER = {
  // an operation added, changed or deleted, also by a transfer or by planning the fixed expenses: the balances, the
  // account histories, the search results, the operation itself, and the contractors (a save may add one)
  operations: [['summary'], ['account'], ['operation'], ['search'], ['contractors']],
  // an account made the main one, hidden or shown: the summary, and the accounts the forms offer
  accounts: [['summary'], ['accounts'], ['account']],
  // a fixed expense saved or deleted; a save may add a contractor
  fixedExpenses: [['fixedExpenses'], ['contractors']],
  // a rate counts in the summary: the rate column and the PLN totals of the foreign accounts
  rates: [['rates'], ['summary']],
  // a gold asset or price counts in the summary (gold); deleting an asset deletes its prices
  gold: [['goldAssets'], ['goldPrices'], ['summary']],
} satisfies Record<string, QueryKey[]>

export type Change = keyof typeof STALE_AFTER

// After a change the queries it concerns are read again: those on the screen at once, the others when they are
// shown next.
//
//   const invalidate = useInvalidate()
//   useMutation({ mutationFn: …, onSuccess: () => invalidate('rates') })
export function useInvalidate(): (change: Change) => Promise<void> {
  const queryClient = useQueryClient()

  return async (change) => {
    await Promise.all(STALE_AFTER[change].map((queryKey) => queryClient.invalidateQueries({ queryKey })))
  }
}
