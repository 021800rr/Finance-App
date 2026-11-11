import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useDebouncedValue } from '../ui/useDebouncedValue'
import { useContractorSearch } from './operationApi'

// The chosen contractor: one from the list (id), or a new name the API adds when the form is saved (id null).
export type ContractorChoice = { id: number | null; name: string }

// One line of the list: a contractor found, or "➕ Add" for a new one.
type Option = { key: string; label: string; choice: ContractorChoice }

type Props = {
  id: string
  value: ContractorChoice | null
  onChange: (value: ContractorChoice | null) => void
  error?: string
  inputClassName: string
}

// As in the web app: type a part of the name, then pick a contractor from the list, or "➕ Add" for a new one.
// Typed text alone is not a choice, so a typo does not quietly add a new contractor.
// A combobox (the ARIA pattern of a field with a list of suggestions): the focus stays in the field, ↑ ↓ move through
// the list, Enter picks, Escape closes; a screen reader hears which line is marked.
export function ContractorField({ id, value, onChange, error, inputClassName }: Props) {
  const { t } = useTranslation()
  const listId = React.useId()
  const [text, setText] = React.useState(value?.name ?? '')
  const [open, setOpen] = React.useState(false)
  // the marked line of the list; -1: none
  const [active, setActive] = React.useState(-1)
  const search = useDebouncedValue(text.trim(), 250)
  const contractors = useContractorSearch(search)
  const name = text.trim()
  // The results go on showing while the next ones load, but only those that still contain the text (the API matches
  // any letter case the same way): typing on narrows the list, a different text hides it until the API answers.
  const found = (contractors.data ?? [])
    .filter((contractor) => contractor.name.toLowerCase().includes(name.toLowerCase()))

  const options: Option[] = found.slice(0, 10).map((contractor) => (
    { key: String(contractor.id), label: contractor.name, choice: contractor }
  ))
  // "➕ Add" only once the API has answered for this very text: while it is still asking, the name may yet be found.
  const answered = search === name && contractors.isSuccess && !contractors.isPlaceholderData
  if (answered && name !== '' && !found.some((contractor) => contractor.name === name)) {
    options.push({ key: 'new', label: t('contractor_autocomplete.add', { name }), choice: { id: null, name } })
  }
  const listShown = open && search !== '' && options.length > 0

  function choose(choice: ContractorChoice) {
    onChange(choice)
    setText(choice.name)
    setOpen(false)
    setActive(-1)
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault()
      setOpen(true)
      if (options.length > 0) {
        const step = event.key === 'ArrowDown' ? 1 : -1
        setActive((current) => (current + step + options.length) % options.length)
      }
    } else if (event.key === 'Enter' && listShown && options[active] !== undefined) {
      // Picks the marked line instead of sending the form.
      event.preventDefault()
      choose(options[active].choice)
    } else if (event.key === 'Escape' && listShown) {
      event.preventDefault()
      setOpen(false)
      setActive(-1)
    }
  }

  return (
    <div className="relative">
      <input
        id={id}
        type="text"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={listShown}
        aria-controls={listId}
        aria-activedescendant={listShown && active >= 0 ? `${listId}-${active}` : undefined}
        aria-invalid={error !== undefined}
        value={text}
        onChange={(event) => {
          setText(event.target.value)
          setOpen(true)
          setActive(-1)
          onChange(null)
        }}
        onKeyDown={handleKeyDown}
        onFocus={() => setOpen(true)}
        onBlur={() => setOpen(false)}
        placeholder={t('form.contractor_placeholder')}
        autoComplete="off"
        className={inputClassName}
      />
      {listShown && (
        // mousedown would blur the field (and close the list) before the click; preventing it keeps the list.
        <ul
          id={listId}
          role="listbox"
          onMouseDown={(event) => event.preventDefault()}
          className="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200
            dark:border-slate-700 bg-white dark:bg-slate-900 text-sm shadow-lg"
        >
          {options.map((option, index) => (
            <li
              key={option.key}
              id={`${listId}-${index}`}
              role="option"
              aria-selected={index === active}
              onClick={() => choose(option.choice)}
              onMouseEnter={() => setActive(index)}
              className={'cursor-pointer px-3 py-1.5 '
                + (index === active ? 'bg-slate-100 dark:bg-slate-800 ' : '')
                + (option.choice.id === null ? 'text-indigo-700 dark:text-indigo-300' : '')}
            >
              {option.label}
            </li>
          ))}
        </ul>
      )}
      {error && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{error}</p>}
      {!error && value?.id === null && (
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {t('app.new_contractor', { name: value.name })}
        </p>
      )}
    </div>
  )
}
