import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { getJson, patchJson } from '../api'
import { queryKeys, useInvalidate } from '../queryKeys'
import type { Money } from '../money'

// One row of GET /api/summary "accounts".
export type SummaryAccount = {
  id: number
  bank: string
  type: string
  currency: string
  account_number: string
  hidden: boolean
  household: string
  is_own_funds: boolean
  is_retirement: boolean
  saldo: Money
  // the bank has no stored rate to PLN for this foreign currency account
  missing_rate: boolean
  is_main: boolean
  // the bank's latest rate to PLN; null for PLN accounts and when none is stored
  rate: Money | null
}

export type Summary = {
  accounts: SummaryAccount[]
  // per account type: the balance in each currency and their sum in PLN (own-funds accounts only)
  byType: Record<string, { raw: Record<string, Money>; pln: Money }>
  byHousehold: Record<string, Money>
  ownFundsHouseholdName: string
  gold: Money | null
  retirementBalance: Money | null
  retirementBalanceWithGold: Money | null
  accountsMissingRate: string[]
}

// The summary of the home page. While the other variant (with or without hidden accounts) loads, the table keeps
// showing the previous one instead of blinking empty.
export function useSummary(showHidden: boolean) {
  return useQuery({
    queryKey: queryKeys.summary(showHidden),
    queryFn: () => getJson<Summary>(`/api/summary?showHidden=${showHidden}`),
    placeholderData: keepPreviousData,
  })
}

// The ☆ of the web app: makes the account the main one; the backend takes the ★ from the previous one.
export function useSetMainAccount() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (accountId: number) => patchJson(`/api/accounts/${accountId}`, { isMain: true }),
    // Read again also after a failure: on a conflict another request has just moved the ★, and the table should show
    // where.
    onSettled: () => invalidate('accounts'),
  })
}

// The eye of the web app: hides the account from the summary (and from choosing it in forms), or shows it again.
export function useSetAccountHidden() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: ({ accountId, hidden }: { accountId: number; hidden: boolean }) =>
      patchJson(`/api/accounts/${accountId}`, { hidden }),
    onSettled: () => invalidate('accounts'),
  })
}
