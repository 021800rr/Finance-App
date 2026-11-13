import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { DateInput } from './DateInput'

function show() {
  const onChange = vi.fn()
  render(<>
    <label htmlFor="date">Date</label>
    <DateInput id="date" value="2026-11-11" onChange={onChange}
      min="2025-01-01" max="2070-12-31" className="" />
    <button type="button">Outside</button>
  </>)

  return onChange
}

describe('DateInput', () => {
  it('associates the calendar with its button and restores focus after Escape', async () => {
    show()
    const trigger = screen.getByRole('button', { name: 'Wybierz datę z kalendarza' })
    await userEvent.click(trigger)
    const calendar = screen.getByRole('dialog')
    expect(trigger).toHaveAttribute('aria-controls', calendar.id)
    expect(trigger).toHaveAttribute('aria-expanded', 'true')

    await userEvent.keyboard('{Escape}')

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(trigger).toHaveFocus()
  })

  it('closes without stealing focus when another control is clicked', async () => {
    show()
    await userEvent.click(screen.getByRole('button', { name: 'Wybierz datę z kalendarza' }))

    await userEvent.click(screen.getByRole('button', { name: 'Outside' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Outside' })).toHaveFocus()
  })

  it('returns a calendar day without UTC conversion and restores focus after selection', async () => {
    const change = show()
    const trigger = screen.getByRole('button', { name: 'Wybierz datę z kalendarza' })
    await userEvent.click(trigger)

    await userEvent.click(screen.getByRole('button', { name: /10 listopada 2026/ }))

    expect(change).toHaveBeenCalledWith('2026-11-10')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(trigger).toHaveFocus()
  })

  it('accepts only date characters from typed or pasted text', () => {
    const change = show()

    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-12-25abc' } })

    expect(change).toHaveBeenCalledWith('2026-12-25')
  })
})
