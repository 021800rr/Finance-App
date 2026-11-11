import * as React from 'react'

const DARK = '(prefers-color-scheme: dark)'

// Whether the system asks for dark colours (e.g. Ubuntu's "Dark" style); a change there shows at once.
export function usePrefersDark(): boolean {
  return React.useSyncExternalStore(
    (onChange) => {
      const media = window.matchMedia(DARK)
      media.addEventListener('change', onChange)

      return () => media.removeEventListener('change', onChange)
    },
    () => window.matchMedia(DARK).matches,
  )
}
