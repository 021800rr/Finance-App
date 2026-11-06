import { ApiError, type Violation } from './api'

export const SERVER_DOWN = 'Serwer nie odpowiada. Spróbuj za chwilę.'

// Polish texts of the errorCodes a user can run into, as the web app shows them (translations/messages.pl.yaml).
const ERROR_MESSAGES: Record<string, string> = {
  'account.main_changed_concurrently': 'Konto główne zostało właśnie zmienione przez inne żądanie. '
    + 'Sprawdź konto główne i spróbuj ponownie.',
  'account_operation.other_side_not_found': 'Nie znaleziono powiązanej operacji.',
}

// Polish texts of the field errors (violation codes) of the operation form, as in translations/validators.pl.yaml;
// {{ name }} is replaced with the value the API sends in "params".
const VIOLATION_MESSAGES: Record<string, string> = {
  'account.not_found': 'To konto nie istnieje.',
  'account.hidden_not_selectable': 'To konto jest ukryte. Wybierz konto, które nie jest ukryte.',
  'operation.account_required': 'Wybierz konto.',
  'common.amount_cannot_be_zero': 'Kwota nie może być równa zero.',
  'common.amount_not_blank': 'Podaj kwotę.',
  'common.amount_out_of_range': 'Kwota musi być z zakresu od -999 999,99 do 999 999,99.',
  'common.date_not_blank': 'Podaj datę.',
  'common.date_out_of_range': 'Data musi mieścić się w zakresie od {{ min }} do {{ max }} (włącznie).',
  'common.date_invalid': 'Podaj istniejącą datę w formacie RRRR-MM-DD.',
  'common.description_max_length': 'Opis może mieć najwyżej 255 znaków.',
  'common.numeric_invalid': 'Podaj poprawną liczbę.',
  'common.text_contains_null_character': 'Tekst zawiera niedozwolony znak.',
  'contractor.id_or_name_required': 'Wybierz kontrahenta z listy.',
  'contractor.not_found': 'Ten kontrahent nie istnieje.',
  'contractor.name_max_length': 'Nazwa kontrahenta może mieć najwyżej 255 znaków.',
  'contractor.name_not_blank': 'Podaj nazwę kontrahenta.',
}

// The Polish text of a field error; the API's English one when the code is not known here.
export function violationMessage(violation: Violation): string {
  const template = violation.code === undefined ? undefined : VIOLATION_MESSAGES[violation.code]

  return template === undefined
    ? violation.message
    : template.replace(/\{\{ (\w+) \}\}/g, (_, name: string) => violation.params?.[name] ?? '')
}

// What to tell the user about a failed request: the text of its errorCode, the fallback for any other answer of the
// API, SERVER_DOWN when there was no answer at all.
export function errorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) {
    return SERVER_DOWN
  }

  return (error.errorCode === undefined ? undefined : ERROR_MESSAGES[error.errorCode]) ?? fallback
}
