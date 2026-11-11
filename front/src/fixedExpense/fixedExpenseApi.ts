import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { deleteResource, getJson, patchJson, postJson } from '../api'

// One row of GET /api/fixed_expenses: an expense of every month, always negative and in PLN. The API leaves out the
// fields that are null.
export type FixedExpense = {
  id: number
  contractorId: number
  contractorName: string
  // the day of the month, 1-28
  day: number
  // a decimal string, e.g. '-200.00'
  amount: string
  currencyCode: string
  description?: string | null
}

// What the form sends. The contractor goes as in an operation: contractorId (one from the list) or contractorName
// (a new one: the API creates it).
export type FixedExpenseInput = {
  contractorId?: number
  contractorName?: string
  day: number | null
  amount: string
  description: string | null
}

// What "Zaplanuj wydatki" did: the operations of the month (YYYY-MM) went to the main account.
export type Plan = { accountId: number; month: string; createdCount: number }

// Every fixed expense, by day, as in the web app; the list has no pages.
export function useFixedExpenses() {
  return useQuery({ queryKey: ['fixedExpenses'], queryFn: () => getJson<FixedExpense[]>('/api/fixed_expenses') })
}

export function useFixedExpense(id: string) {
  return useQuery({
    queryKey: ['fixedExpenses', id],
    queryFn: () => getJson<FixedExpense>(`/api/fixed_expenses/${encodeURIComponent(id)}`),
  })
}

// After a save the list is read again, and the contractors too (the save may have added one).
function useInvalidateAfterSave() {
  const queryClient = useQueryClient()

  return () => Promise.all(
    [['fixedExpenses'], ['contractors']].map((queryKey) => queryClient.invalidateQueries({ queryKey })),
  )
}

export function useCreateFixedExpense() {
  const invalidate = useInvalidateAfterSave()

  return useMutation({
    mutationFn: (input: FixedExpenseInput) => postJson<FixedExpense>('/api/fixed_expenses', input),
    onSuccess: invalidate,
  })
}

export function useUpdateFixedExpense(id: number) {
  const invalidate = useInvalidateAfterSave()

  return useMutation({
    mutationFn: (input: FixedExpenseInput) => patchJson(`/api/fixed_expenses/${id}`, input),
    onSuccess: invalidate,
  })
}

export function useDeleteFixedExpense() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => deleteResource(`/api/fixed_expenses/${id}`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['fixedExpenses'] }),
  })
}

// "Zaplanuj wydatki": one operation on the main account for every fixed expense, dated in the next month. The API
// refuses (409, nothing created) when there is no main account it can use, or when such an operation already exists.
export function usePlanFixedExpenses() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => postJson<Plan>('/api/fixed_expenses/plan', {}),
    // New operations: the balances, the account histories and the search results change.
    onSuccess: () => Promise.all(
      [['account'], ['summary'], ['search']].map((queryKey) => queryClient.invalidateQueries({ queryKey })),
    ),
  })
}
