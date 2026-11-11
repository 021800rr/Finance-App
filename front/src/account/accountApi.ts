import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { deleteResource, getJson, getPage, patchJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'

// The types of the API, generated from its OpenAPI description (npm run api-types): an account as the API answers it.
// Only GET /api/accounts/{id} adds the balances (saldo with the operations dated in the future, saldoToday with those
// dated today or earlier); the list and the answer to PATCH leave them out…
type AccountAnswer = components['schemas']['Account']

// …so the account of a history page, read with that GET, has them for sure.
export type Account = AccountAnswer & Required<Pick<AccountAnswer, 'saldo' | 'saldoToday' | 'hasFutureOperations'>>

// An operation as the API answers it, a row of GET /api/accounts/{id}/operations among others: the description only
// when there is one, the transaction UUID only on the two operations of a transfer.
export type Operation = components['schemas']['AccountOperation']

export function useAccount(id: string) {
  return useQuery({
    queryKey: queryKeys.account(id),
    queryFn: () => getJson<Account>(`/api/accounts/${encodeURIComponent(id)}`),
  })
}

// One page of the account's operations, newest first. The previous page stays on the screen while the next loads.
export function useAccountOperations(id: string, page: number) {
  return useQuery({
    queryKey: queryKeys.accountOperations(id, page),
    queryFn: () => getPage<Operation>(`/api/accounts/${encodeURIComponent(id)}/operations?page=${page}`),
    placeholderData: keepPreviousData,
  })
}

// The ✓ / ? of the web app: the operation is confirmed (e.g. seen on the bank statement) or still to be checked.
export function useSetOperationConfirmed() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: ({ operationId, confirmed }: { operationId: number; confirmed: boolean }) =>
      patchJson(`/api/account_operations/${operationId}`, { confirmed }),
    // Also after a failure: the table shows what the API holds.
    onSettled: () => invalidate('operations'),
  })
}

export function useDeleteOperation() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (operationId: number) => deleteResource(`/api/account_operations/${operationId}`),
    onSettled: () => invalidate('operations'),
  })
}

// The other operation of a transfer, for the 🔗 of the web app.
export function findOtherSide(operationId: number): Promise<Operation> {
  return getJson<Operation>(`/api/account_operations/${operationId}/other-side`)
}
