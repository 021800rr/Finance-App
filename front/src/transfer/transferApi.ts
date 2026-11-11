import { useMutation } from '@tanstack/react-query'
import { postJson } from '../api'
import type { components } from '../generated/api'
import { useInvalidate } from '../queryKeys'

// The types of the API, generated from its OpenAPI description (npm run api-types): what the form sends (amountFrom
// leaves the first account; amountTo reaches the second one, only between different currencies, the API refuses it
// otherwise)…
export type TransferInput = components['schemas']['Transfer.TransferInput']

// …and what POST /api/transfers answers: the two operations it created, linked by one transactionUuid.
export type Transfer = components['schemas']['Transfer']

export function useCreateTransfer() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: TransferInput) => postJson<Transfer>('/api/transfers', input),
    // Two new operations on two accounts.
    onSuccess: () => invalidate('operations'),
  })
}
