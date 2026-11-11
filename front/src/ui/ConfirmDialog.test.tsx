import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest'
import { ConfirmDialog } from './ConfirmDialog'
import { DeleteButton } from './RowActions'

// jsdom does not implement modal dialogs; the browser supplies focus trapping and the backdrop.
beforeAll(() => {
  Object.defineProperties(HTMLDialogElement.prototype, {
    showModal: {
      configurable: true,
      value(this: HTMLDialogElement) { this.setAttribute('open', '') },
    },
    close: {
      configurable: true,
      value(this: HTMLDialogElement) { this.removeAttribute('open') },
    },
  })
})

afterAll(() => {
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal')
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'close')
})

describe('confirmation before deleting', () => {
  it('waits for confirmation and cancels without deleting', async () => {
    const user = userEvent.setup()
    const onDelete = vi.fn()
    render(<DeleteButton onDelete={onDelete} />)

    await user.click(screen.getByRole('button', { name: 'Usuń' }))

    expect(screen.getByRole('dialog', { name: 'Usunąć?' })).toBeVisible()
    expect(screen.getByRole('button', { name: 'Anuluj' })).toHaveFocus()
    expect(onDelete).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Anuluj' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(onDelete).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Usuń' }))
    await user.click(screen.getByRole('button', { name: 'Potwierdź' }))

    expect(onDelete).toHaveBeenCalledTimes(1)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('treats the native Escape cancellation as cancelling, without deleting', async () => {
    const onDelete = vi.fn()
    render(<DeleteButton onDelete={onDelete} />)
    await userEvent.click(screen.getByRole('button', { name: 'Usuń' }))

    fireEvent(screen.getByRole('dialog'), new Event('cancel', { cancelable: true }))

    expect(onDelete).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})

describe('ConfirmDialog', () => {
  it('blocks confirmation while the action is disabled', async () => {
    const onConfirm = vi.fn()
    render(
      <ConfirmDialog open message="Zaplanować wydatki?" onConfirm={onConfirm} onClose={vi.fn()} disabled />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Potwierdź' }))

    expect(onConfirm).not.toHaveBeenCalled()
  })
})
