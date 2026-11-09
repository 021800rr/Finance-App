import * as React from 'react'

// The value, but only once it has stopped changing for the given time: typing a name asks the API once at the end,
// not after every letter.
export function useDebouncedValue<T>(value: T, milliseconds: number): T {
  const [debounced, setDebounced] = React.useState(value)

  React.useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), milliseconds)

    return () => clearTimeout(timer)
  }, [value, milliseconds])

  return debounced
}
// HELP: opóźniacz dla szukajki kontrahenta