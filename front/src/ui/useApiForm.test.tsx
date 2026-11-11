import { act, renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ApiError } from '../api'
import { useApiForm } from './useApiForm'

type Field = 'amount' | 'to'

const FIELD_OF_PATH: Record<string, Field> = { amount: 'amount', toAccountId: 'to' }

function renderForm() {
  return renderHook(() => useApiForm({
    fieldOfPath: FIELD_OF_PATH,
    fieldOfErrorCode: { 'transfer.accounts_must_differ': 'to' },
    failure: 'Nie udało się zapisać.',
  })).result
}

const refuse = (error: unknown) => async () => {
  throw error
}

describe('useApiForm', () => {
  it('puts the field errors of the API under their fields', async () => {
    const form = renderForm()
    const violation = { propertyPath: 'amount', message: 'Enter an amount.', code: 'fixed_expense.amount_not_blank' }

    await act(() => form.current.submit(refuse(new ApiError(422, 'validation.failed', 'Invalid', {}, [violation]))))

    expect(form.current.errors).toEqual({ amount: 'Podaj kwotę.' })
    expect(form.current.message).toBe('Uzupełnij wymagane pola. Zaznaczone na czerwono.')
    expect(form.current.sending).toBe(false)
  })

  it('puts a refusal that has only an error code under its field', async () => {
    const form = renderForm()

    await act(() => form.current.submit(refuse(new ApiError(422, 'transfer.accounts_must_differ', 'Same account'))))

    expect(form.current.errors).toEqual({ to: 'Wybierz inne konto docelowe.' })
    expect(form.current.message).toBe('Wybierz inne konto docelowe.')
  })

  it('says why when the server cannot be reached', async () => {
    const form = renderForm()

    await act(() => form.current.submit(refuse(new TypeError('Failed to fetch'))))

    expect(form.current.errors).toEqual({})
    expect(form.current.message).toBe('Serwer nie odpowiada. Spróbuj za chwilę.')
  })

  it('clears the errors of the previous try when the next one is saved', async () => {
    const form = renderForm()
    await act(() => form.current.submit(refuse(new TypeError('Failed to fetch'))))

    await act(() => form.current.submit(async () => {}))

    expect(form.current.errors).toEqual({})
    expect(form.current.message).toBe('')
  })

  it('shows what the form finds itself, before the API', () => {
    const form = renderForm()

    act(() => form.current.fail({ amount: 'Za mało.' }))

    expect(form.current.errors).toEqual({ amount: 'Za mało.' })
    expect(form.current.message).toBe('Uzupełnij wymagane pola. Zaznaczone na czerwono.')
  })
})
