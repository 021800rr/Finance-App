import { ApiError, type Violation } from './api'
import i18n from './i18n'

// The codes of the API are the keys of its translations: an errorCode in "messages" or "validators", a violation code
// in "validators". The text of a key comes from the first of the domains that has it, with its {{ values }} filled in.
function translated(key: string, domains: string[], params: Record<string, string> = {}): string | undefined {
  const ns = domains.find((domain) => i18n.exists(key, { ns: domain }))

  return ns === undefined ? undefined : i18n.t(key, { ns, ...params })
}

// The text of a field error in the language of the app; the API's English one when the code has no translation.
export function violationMessage(violation: Violation): string {
  return (violation.code === undefined ? undefined : translated(violation.code, ['validators'], violation.params))
    ?? violation.message
}

// What to tell the user about a failed request: the text of its errorCode, the fallback for any other answer of the
// API, "the server does not answer" when there was no answer at all.
export function errorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) {
    return i18n.t('app.server_down')
  }

  const text = error.errorCode === undefined
    ? undefined
    : translated(error.errorCode, ['messages', 'validators'], error.params)

  return text ?? fallback
}
