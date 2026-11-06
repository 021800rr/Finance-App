import { ApiError, type Violation } from './api'
import i18n from './i18n'

// The errorCodes whose text is under another key than the code itself.
const KEY_OF_ERROR_CODE: Record<string, string> = {
  'account_operation.other_side_not_found': 'operation.related_not_found',
  'auth.invalid_credentials': 'app.invalid_credentials',
  'auth.too_many_login_attempts': 'app.too_many_login_attempts',
}

// The violation codes whose text for someone filling in a form is under another key: the API's own text speaks of
// its fields (contractorId, contractorName).
const KEY_OF_VIOLATION_CODE: Record<string, string> = {
  'contractor.id_or_name_required': 'contractor.required',
}

// The text of a key in the first of the domains that has it, with its {{ values }} filled in.
function translated(key: string, domains: string[], params: Record<string, string> = {}): string | undefined {
  const ns = domains.find((domain) => i18n.exists(key, { ns: domain }))

  return ns === undefined ? undefined : i18n.t(key, { ns, ...params })
}

// The text of a field error in the language of the app (the API's codes are the keys of "validators"); the API's
// English one when the code has no translation.
export function violationMessage(violation: Violation): string {
  if (violation.code === undefined) {
    return violation.message
  }
  const key = KEY_OF_VIOLATION_CODE[violation.code] ?? violation.code

  return translated(key, ['validators', 'messages'], violation.params) ?? violation.message
}

// What to tell the user about a failed request: the text of its errorCode, the fallback for any other answer of the
// API, "the server does not answer" when there was no answer at all.
export function errorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) {
    return i18n.t('app.server_down')
  }
  if (error.errorCode === undefined) {
    return fallback
  }
  const key = KEY_OF_ERROR_CODE[error.errorCode] ?? error.errorCode

  return translated(key, ['messages', 'validators'], error.params) ?? fallback
}
