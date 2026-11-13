import { PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { ConfirmDialog } from './ConfirmDialog'

// The icons at the end of a table row: grey, darker under the mouse; a waiting cursor while the table is busy.
const ICON = 'cursor-pointer text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 '
  + 'disabled:cursor-wait'

type IconButtonProps = {
  // what the icon does, for the tooltip and for screen readers (the icon itself says nothing)
  label: string
  onClick: () => void
  disabled?: boolean
  // for an icon that shows a state it switches (confirmed / not)
  pressed?: boolean
  title?: string
  children: React.ReactNode
}

export function IconButton({ label, onClick, disabled, pressed, title, children }: IconButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      title={title ?? label}
      aria-label={label}
      aria-pressed={pressed}
      className={ICON}
    >
      {children}
    </button>
  )
}

export function IconLink({ to, label, children }: { to: string; label: string; children: React.ReactNode }) {
  return (
    <Link to={to} title={label} aria-label={label} className={ICON}>
      {children}
    </Link>
  )
}

// ✎ to the edit page of the row.
export function EditLink({ to }: { to: string }) {
  const { t } = useTranslation()

  return (
    <IconLink to={to} label={t('operation.edit')}>
      <PencilSquareIcon className="size-5" />
    </IconLink>
  )
}

// 🗑 asks for confirmation before deleting the row.
export function DeleteButton({ onDelete, disabled }: { onDelete: () => void; disabled?: boolean }) {
  const { t } = useTranslation()
  const [confirming, setConfirming] = React.useState(false)

  return (
    <>
      <IconButton label={t('operation.delete')} onClick={() => setConfirming(true)} disabled={disabled}>
        <TrashIcon className="size-5 text-red-600 dark:text-red-400" />
      </IconButton>
      {confirming && (
        <ConfirmDialog
          open
          message={t('operation.confirm_delete')}
          onConfirm={onDelete}
          onClose={() => setConfirming(false)}
          disabled={disabled}
        />
      )}
    </>
  )
}
