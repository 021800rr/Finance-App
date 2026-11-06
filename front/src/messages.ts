import { ApiError } from './api'

export const SERVER_DOWN = 'Serwer nie odpowiada. Spróbuj za chwilę.'

// Polish texts of the errorCodes a user can run into, as the web app shows them (translations/messages.pl.yaml).
const ERROR_MESSAGES: Record<string, string> = {
  'account.main_changed_concurrently': 'Konto główne zostało właśnie zmienione przez inne żądanie. '
    + 'Sprawdź konto główne i spróbuj ponownie.',
}

// What to tell the user about a failed request: the text of its errorCode, the fallback for any other answer of the
// API, SERVER_DOWN when there was no answer at all.
export function errorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) {
    return SERVER_DOWN
  }

  return (error.errorCode === undefined ? undefined : ERROR_MESSAGES[error.errorCode]) ?? fallback
}
