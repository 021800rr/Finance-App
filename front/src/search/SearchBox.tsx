import { MagnifyingGlassIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'

// The search of the header, as in the web app: Enter (or the magnifier) opens /search?q=... with the results. The
// field starts with the phrase of the results page shown, see Layout.
export function SearchBox({ initial }: { initial: string }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [phrase, setPhrase] = React.useState(initial)

  function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    const trimmed = phrase.trim()
    navigate(trimmed === '' ? '/search' : `/search?q=${encodeURIComponent(trimmed)}`)
  }

  return (
    <form role="search" onSubmit={handleSubmit} className="relative shrink-0">
      <input
        type="text"
        enterKeyHint="search"
        value={phrase}
        onChange={(event) => setPhrase(event.target.value)}
        placeholder={t('nav.search_placeholder')}
        aria-label={t('nav.search')}
        className="w-48 rounded-md border border-slate-300 py-1.5 pl-3 pr-8 text-sm text-slate-900
          focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
      />
      <button
        type="submit"
        title={t('nav.search')}
        aria-label={t('nav.search')}
        className="absolute inset-y-0 right-0 flex cursor-pointer items-center px-2 text-slate-500 hover:text-slate-800"
      >
        <MagnifyingGlassIcon className="size-4" />
      </button>
    </form>
  )
}
