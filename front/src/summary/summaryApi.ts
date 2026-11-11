import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { getJson, patchJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'

// The types of the API, generated from its OpenAPI description (npm run api-types): the summary of the home page, its
// totals by account type covering the own-funds accounts only…
export type Summary = components['schemas']['Summary']

// …and one of its accounts, the fields named as in /api/accounts. The rate to PLN is there only for an account in
// another currency whose bank has one stored; missingRate says when it has none.
export type SummaryAccount = Summary['accounts'][number]

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
