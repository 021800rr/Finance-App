import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { getJson, patchJson, postJson } from '../api'
import type { Operation } from '../account/accountApi'

// One row of GET /api/accounts.
export type AccountChoice = {
  id: number
  hidden: boolean
  isMain: boolean
  displayLabel: string
  bankName: string
  currencyCode: string
  accountNumber: string
}

export type Contractor = { id: number; name: string }

// What the form sends. The contractor goes as contractorId (one from the list) or contractorName (a new one: the API
// creates it).
export type OperationInput = {
  contractorId?: number
  contractorName?: string
  operationDate: string
  amount: string
  description: string | null
  confirmed: boolean
}

// Every account, ordered by bank, type, currency and number; a new operation can go only to one that is not hidden.
export function useAccounts() {
  return useQuery({ queryKey: ['accounts'], queryFn: () => getJson<AccountChoice[]>('/api/accounts') })
}

export function useOperation(id: string) {
  return useQuery({
    queryKey: ['operation', id],
    queryFn: () => getJson<Operation>(`/api/account_operations/${encodeURIComponent(id)}`),
  })
}

// Contractors whose name contains the text (case-insensitive); nothing is asked for an empty text.
export function useContractorSearch(text: string) {
  return useQuery({
    queryKey: ['contractors', text],
    queryFn: () => getJson<Contractor[]>(`/api/contractors?name=${encodeURIComponent(text)}`),
    enabled: text !== '',
  })
}

// After a save the balances, the history, the search results and the contractors (maybe a new one) are read again.
function useInvalidateAfterSave() {
  const queryClient = useQueryClient()

  return () => Promise.all(
    [['account'], ['summary'], ['operation'], ['search'], ['contractors']]
      .map((queryKey) => queryClient.invalidateQueries({ queryKey })),
  )
}

export function useCreateOperation() {
  const invalidate = useInvalidateAfterSave()

  return useMutation({
    mutationFn: (input: OperationInput & { accountId: number }) =>
      postJson<Operation>('/api/account_operations', input),
    onSuccess: invalidate,
  })
}

export function useUpdateOperation(id: number) {
  const invalidate = useInvalidateAfterSave()

  return useMutation({
    mutationFn: (input: OperationInput) => patchJson(`/api/account_operations/${id}`, input),
    onSuccess: invalidate,
  })
}
