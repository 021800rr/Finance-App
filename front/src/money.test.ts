import { afterEach, describe, expect, it } from 'vitest'
import i18n from './i18n'
import { formatAmount } from './money'

describe('formatAmount', () => {
  afterEach(async () => {
    await i18n.changeLanguage('pl')
  })

  it('writes an amount as the web app does in Polish', () => {
    expect(formatAmount('-1234567.80')).toBe('-1 234 567,80')
    expect(formatAmount('0.50')).toBe('0,50')
    expect(formatAmount('1000')).toBe('1 000')
  })

  it('writes it as in the US in English', async () => {
    await i18n.changeLanguage('en')

    expect(formatAmount('-1234567.80')).toBe('-1,234,567.80')
  })

  it('never rounds: the text is not turned into a number', () => {
    expect(formatAmount('12345678901234.5678')).toBe('12 345 678 901 234,5678')
  })
})
