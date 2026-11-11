import { describe, expect, it } from 'vitest'
import { amountCharacters, dateFromValue, fieldErrors, toApiAmount, toApiDecimal, valueFromDate } from './form'

describe('toApiAmount', () => {
  it.each([
    ['1234,56', '1234.56'],
    ['1234.56', '1234.56'],
    ['1,234.56', '1234.56'],
    ['1.234,56', '1234.56'],
    ['-1.234,56', '-1234.56'],
    ['12,5', '12.5'],
    ['10,', '10'],
    ['-309', '-309'],
    // three digits after the last comma or dot: they group thousands, amounts have cents only
    ['3,200', '3200'],
    ['3.200', '3200'],
  ])('%s → %s', (typed, sent) => {
    expect(toApiAmount(typed)).toBe(sent)
  })
})

describe('toApiDecimal', () => {
  it('takes a comma or a dot as the decimal one, rates and ounces having no thousands', () => {
    expect(toApiDecimal('4,559')).toBe('4.559')
    expect(toApiDecimal('4.5590')).toBe('4.5590')
  })
})

describe('amountCharacters', () => {
  it('keeps digits, separators and a leading minus of a pasted text', () => {
    expect(amountCharacters('-1 234,56 zł', true)).toBe('-1234,56')
  })

  it('drops the minus of an amount that is always positive', () => {
    expect(amountCharacters('-12', false)).toBe('12')
  })

  it('keeps a minus only at the start', () => {
    expect(amountCharacters('1-2', true)).toBe('12')
  })
})

describe('dateFromValue', () => {
  it('gives local midnight of the day, not UTC midnight (the day before west of Greenwich)', () => {
    const date = dateFromValue('2026-11-06')

    expect([date?.getFullYear(), date?.getMonth(), date?.getDate(), date?.getHours()]).toEqual([2026, 10, 6, 0])
  })

  it.each(['2026-02-31', '2026-1-6', '', 'yesterday'])('gives nothing for %j, which is no date', (text) => {
    expect(dateFromValue(text)).toBeUndefined()
  })

  it('reads back what valueFromDate writes', () => {
    expect(valueFromDate(new Date(2026, 0, 5))).toBe('2026-01-05')
    expect(valueFromDate(dateFromValue('2026-12-31')!)).toBe('2026-12-31')
  })
})

describe('fieldErrors', () => {
  it('puts the first error of each field in Polish, the API text when the code has no translation', () => {
    const errors = fieldErrors([
      { propertyPath: 'amount', message: 'Enter an amount.', code: 'fixed_expense.amount_not_blank', params: {} },
      { propertyPath: 'amount', message: 'second error of the field', code: 'unknown', params: {} },
      { propertyPath: 'day', message: 'Text of the API.', code: 'no.such.code', params: {} },
      { propertyPath: 'notAField', message: 'ignored', code: 'unknown', params: {} },
    ], { amount: 'amount', day: 'day' })

    expect(errors).toEqual({ amount: 'Podaj kwotę.', day: 'Text of the API.' })
  })
})
