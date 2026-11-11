import { useMutation } from '@tanstack/react-query'
import { postJson } from '../api'
import { useInvalidate } from '../queryKeys'

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
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: TransferInput) => postJson<Transfer>('/api/transfers', input),
    // Two new operations on two accounts.
    onSuccess: () => invalidate('operations'),
  })
}
