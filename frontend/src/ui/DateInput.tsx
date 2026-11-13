import type { AriaAttributes } from 'react'
import { CalendarDaysIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { DayPicker } from 'react-day-picker'
import { enUS, pl } from 'react-day-picker/locale'
import 'react-day-picker/style.css'
import { useTranslation } from 'react-i18next'
import { dateFromValue, valueFromDate } from './form'

type Props = Pick<AriaAttributes, 'aria-invalid' | 'aria-describedby'> & {
  id: string
  // 'YYYY-MM-DD', as the API takes it
  value: string
  onChange: (value: string) => void
  min: string
  max: string
  className: string
}

// A date typed as YYYY-MM-DD (as the tables and the API show it) or picked in a calendar. The calendar of
// <input type="date"> speaks the language of the browser; this one follows the language of the app, with its first
// day of the week, and offers no day outside min-max.
export function DateInput({ id, value, onChange, min, max, className, ...aria }: Props) {
  const { t, i18n } = useTranslation()
  const [open, setOpen] = React.useState(false)
  const box = React.useRef<HTMLDivElement>(null)
  const button = React.useRef<HTMLButtonElement>(null)
  const calendarId = React.useId()
  const selected = dateFromValue(value)
  const first = dateFromValue(min)
  const last = dateFromValue(max)

  // A click outside the calendar, or Escape, closes it.
  React.useEffect(() => {
    if (!open) {
      return
    }
    function onMouseDown(event: MouseEvent) {
      if (!box.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault()
        setOpen(false)
        button.current?.focus()
      }
    }
    document.addEventListener('mousedown', onMouseDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('mousedown', onMouseDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  return (
    <div ref={box} className="relative">
      <div className="flex gap-2">
        <input
          id={id}
          {...aria}
          type="text"
          inputMode="numeric"
          value={value}
          // Only what a date is made of; the API says when it is no date (or out of the range).
          onChange={(event) => onChange(event.target.value.replace(/[^\d-]/g, '').slice(0, 10))}
          placeholder={t('app.date_format')}
          className={`${className} tabular-nums`}
        />
        <button
          ref={button}
          type="button"
          onClick={() => setOpen(!open)}
          title={t('app.choose_date')}
          aria-label={t('app.choose_date')}
          aria-expanded={open}
          aria-haspopup="dialog"
          aria-controls={open ? calendarId : undefined}
          className="shrink-0 cursor-pointer rounded-md border border-slate-300 dark:border-slate-600 px-2
            text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800"
        >
          <CalendarDaysIcon className="size-5" />
        </button>
      </div>
      {open && (
        <div id={calendarId} role="dialog" aria-label={t('app.choose_date')}
          className="absolute z-20 mt-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white
          dark:bg-slate-900 p-3 shadow-lg">
          <DayPicker
            mode="single"
            selected={selected}
            defaultMonth={selected}
            onSelect={(date) => {
              if (date !== undefined) {
                onChange(valueFromDate(date))
                setOpen(false)
                button.current?.focus()
              }
            }}
            locale={i18n.resolvedLanguage === 'en' ? enUS : pl}
            startMonth={first}
            endMonth={last}
            disabled={[...(first ? [{ before: first }] : []), ...(last ? [{ after: last }] : [])]}
            captionLayout="dropdown"
            autoFocus
          />
        </div>
      )}
    </div>
  )
}
