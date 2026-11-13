import { useMediaQuery } from './useMediaQuery'

// Whether the system asks for dark colours (e.g. Ubuntu's "Dark" style); a change there shows at once.
export function usePrefersDark(): boolean {
  return useMediaQuery('(prefers-color-scheme: dark)')
}
