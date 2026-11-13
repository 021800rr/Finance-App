import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { AmountInput } from './AmountInput'

describe('AmountInput', () => {
  it.each([true, false])('filters pasted amounts, allowMinus=%s', (allowMinus) => {
    const change = vi.fn()
    render(<AmountInput id="amount" value="" onChange={change} allowMinus={allowMinus} className="" />)

    fireEvent.change(screen.getByRole('textbox'), { target: { value: '-1 234,56 zł' } })

    expect(change).toHaveBeenCalledWith(allowMinus ? '-1234,56' : '1234,56')
  })

  it('prevents an invalid character before it can change the value or caret', () => {
    render(<AmountInput id="amount" value="12" onChange={() => {}} allowMinus={false} className="" />)
    const input = screen.getByRole('textbox')

    expect(fireEvent.keyPress(input, { charCode: 120, which: 120 })).toBe(false)
    expect(fireEvent.keyPress(input, { charCode: 45, which: 45 })).toBe(false)
    expect(fireEvent.keyPress(input, { charCode: 51, which: 51 })).toBe(true)
    expect(input).toHaveValue('12')
  })
})
