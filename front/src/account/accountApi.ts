import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { deleteResource, getJson, getPage, patchJson } from '../api'
import type { Money } from '../money'

// GET /api/accounts/{id}
export type Account = {
  id: number
  accountNumber: string
  hidden: boolean
  isMain: boolean
  bankName: string
  currencyCode: string
  typeName: string
  householdName: string
  displayLabel: string
  // with the operations dated in the future
  saldo: Money
  // only the operations dated today or earlier
  saldoToday: Money
  hasFutureOperations: boolean
}

// One row of GET /api/accounts/{id}/operations. The API leaves out the fields that are null.
export type Operation = {
  id: number
  accountId: number
  contractorId: number
  contractorName: string
  // YYYY-MM-DD
  operationDate: string
  // a decimal string, negative for an expense
  amount: string
  currencyCode: string
  description?: string | null
  confirmed: boolean
  // set on both operations of a transfer
  transactionUuid?: string | null
}

export function useAccount(id: string) {
  return useQuery({
    queryKey: ['account', id],
    queryFn: () => getJson<Account>(`/api/accounts/${encodeURIComponent(id)}`),
  })
}

// One page of the account's operations, newest first. The previous page stays on the screen while the next loads.
export function useAccountOperations(id: string, page: number) {
  return useQuery({
    queryKey: ['account', id, 'operations', page],
    queryFn: () => getPage<Operation>(`/api/accounts/${encodeURIComponent(id)}/operations?page=${page}`),
    placeholderData: keepPreviousData,
  })
}

// The ✓ / ? of the web app: the operation is confirmed (e.g. seen on the bank statement) or still to be checked.
export function useSetOperationConfirmed() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ operationId, confirmed }: { operationId: number; confirmed: boolean }) =>
      patchJson(`/api/account_operations/${operationId}`, { confirmed }),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['account'] }),
  })
}

export function useDeleteOperation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (operationId: number) => deleteResource(`/api/account_operations/${operationId}`),
    // The balances change too: the account pages and the summary are read again.
    onSettled: () => Promise.all([
      queryClient.invalidateQueries({ queryKey: ['account'] }),
      queryClient.invalidateQueries({ queryKey: ['summary'] }),
    ]),
  })
}

// The other operation of a transfer, for the 🔗 of the web app.
export function findOtherSide(operationId: number): Promise<Operation> {
  return getJson<Operation>(`/api/account_operations/${operationId}/other-side`)
}
