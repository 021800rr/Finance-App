import * as React from 'react'
import { useDebouncedValue } from '../ui/useDebouncedValue'
import { useContractorSearch } from './operationApi'

// The chosen contractor: one from the list (id), or a new name the API adds when the form is saved (id null).
export type ContractorChoice = { id: number | null; name: string }

type Props = {
  value: ContractorChoice | null
  onChange: (value: ContractorChoice | null) => void
  error?: string
  inputClassName: string
}

// As in the web app: type a part of the name, then pick a contractor from the list, or "➕ Dodaj" for a new one.
// Typed text alone is not a choice, so a typo does not quietly add a new contractor.
export function ContractorField({ value, onChange, error, inputClassName }: Props) {
  const [text, setText] = React.useState(value?.name ?? '')
  const [open, setOpen] = React.useState(false)
  const search = useDebouncedValue(text.trim(), 250)
  const contractors = useContractorSearch(search)
  const found = contractors.data ?? []
  const exactMatch = found.some((contractor) => contractor.name === text.trim())

  function choose(choice: ContractorChoice) {
    onChange(choice)
    setText(choice.name)
    setOpen(false)
  }

  return (
    <div className="relative">
      <input
        id="contractor"
        type="text"
        value={text}
        onChange={(event) => {
          setText(event.target.value)
          setOpen(true)
          onChange(null)
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => setOpen(false)}
        placeholder="Wpisz nazwę kontrahenta..."
        autoComplete="off"
        aria-invalid={error !== undefined}
        className={inputClassName}
      />
      {open && search !== '' && (
        // mousedown would blur the input (and close the list) before the click; preventing it keeps the list.
        <ul
          onMouseDown={(event) => event.preventDefault()}
          className="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200 bg-white
            text-sm shadow-lg"
        >
          {found.slice(0, 10).map((contractor) => (
            <li key={contractor.id}>
              <button
                type="button"
                onClick={() => choose(contractor)}
                className="block w-full cursor-pointer px-3 py-1.5 text-left hover:bg-slate-100"
              >
                {contractor.name}
              </button>
            </li>
          ))}
          {!exactMatch && text.trim() !== '' && (
            <li>
              <button
                type="button"
                onClick={() => choose({ id: null, name: text.trim() })}
                className="block w-full cursor-pointer px-3 py-1.5 text-left text-indigo-700 hover:bg-slate-100"
              >
                ➕ Dodaj „{text.trim()}”
              </button>
            </li>
          )}
        </ul>
      )}
      {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
      {!error && value?.id === null && (
        <p className="mt-1 text-sm text-slate-500">Nowy kontrahent „{value.name}” zostanie dodany przy zapisie.</p>
      )}
    </div>
  )
}
