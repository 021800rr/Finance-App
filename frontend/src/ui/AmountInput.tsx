import type { AriaAttributes } from 'react'
import { amountCharacters } from './form'

type Props = Pick<AriaAttributes, 'aria-invalid' | 'aria-describedby'> & {
  id: string
  value: string
  onChange: (value: string) => void
  // false for amounts that are always positive (a transfer): the minus does not even appear
  allowMinus: boolean
  className: string
  placeholder?: string
  disabled?: boolean
}

// A field that takes only an amount: digits, a comma or a dot, and a leading minus when allowed.
export function AmountInput({ id, value, onChange, allowMinus, className, placeholder, disabled, ...aria }: Props) {
  const character = allowMinus ? /^[\d,.-]$/ : /^[\d,.]$/

  return (
    <input
      id={id}
      {...aria}
      type="text"
      inputMode="decimal"
      value={value}
      // A typed key that is not an amount character does nothing, and the caret stays where it was. Longer text
      // (a paste) goes on to onChange, which keeps only its amount characters.
      onBeforeInput={(event) => {
        if (event.data.length === 1 && !character.test(event.data)) {
          event.preventDefault()
        }
      }}
      onChange={(event) => onChange(amountCharacters(event.target.value, allowMinus))}
      placeholder={placeholder}
      disabled={disabled}
      className={`${className} tabular-nums`}
    />
  )
}
