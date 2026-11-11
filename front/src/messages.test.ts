import { describe, expect, it } from 'vitest'
import { ApiError } from './api'
import { errorMessage, violationMessage } from './messages'

describe('errorMessage', () => {
  it('says what the error code of the API means, with its values', () => {
    const error = new ApiError(409, 'fixed_expense.plan_duplicate', 'Suspected duplicate…', {
      contractor: 'Netflix',
      amount: '-55.00',
      date: '2026-12-05',
    })

    expect(errorMessage(error, 'Nie udało się.')).toBe('Podejrzenie duplikatu: operacja dla "Netflix" na kwotę -55.00 '
      + 'w dniu 2026-12-05 już istnieje. Sprawdź konto przed ponownym planowaniem.')
  })

  it('finds the codes of the validators too', () => {
    const error = new ApiError(404, 'account_operation.other_side_not_found', 'Not found.')

    expect(errorMessage(error, 'Nie udało się.')).toBe('Nie znaleziono powiązanej operacji.')
  })

  it('falls back when the code is unknown or missing', () => {
    expect(errorMessage(new ApiError(500, undefined, 'Internal error'), 'Nie udało się.')).toBe('Nie udało się.')
    expect(errorMessage(new ApiError(400, 'no.such.code', 'Bad'), 'Nie udało się.')).toBe('Nie udało się.')
  })

  it('says the server does not answer when there was no answer at all', () => {
    const noAnswer = new TypeError('Failed to fetch')

    expect(errorMessage(noAnswer, 'Nie udało się.')).toBe('Serwer nie odpowiada. Spróbuj za chwilę.')
  })
})

describe('violationMessage', () => {
  it('gives the English text of the API for a code without a translation', () => {
    expect(violationMessage({ propertyPath: 'amount', message: 'Too big.', code: 'no.such.code' })).toBe('Too big.')
  })
})
