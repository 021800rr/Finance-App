import { useMutation, useQueryClient } from '@tanstack/react-query'
import { postJson } from '../api'

export type TransferInput = {
  fromAccountId: number
  toAccountId: number
  operationDate: string
  // positive; what leaves the first account
  amountFrom: string
  // positive; what reaches the second one, only between different currencies (the API refuses it otherwise)
  amountTo?: string
  description: string | null
}

// What POST /api/transfers returns: the two operations it created, linked by one transactionUuid.
export type Transfer = { outOperationId: number; inOperationId: number; fromAccountId: number }

export function useCreateTransfer() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: TransferInput) => postJson<Transfer>('/api/transfers', input),
    // Two balances change: the account pages and the summary are read again.
    onSuccess: () => Promise.all([
      queryClient.invalidateQueries({ queryKey: ['account'] }),
      queryClient.invalidateQueries({ queryKey: ['summary'] }),
    ]),
  })
}
