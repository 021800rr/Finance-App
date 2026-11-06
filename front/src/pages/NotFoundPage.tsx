import { Link } from 'react-router'

export function NotFoundPage() {
  return (
    <section>
      <h1 className="text-2xl font-semibold text-slate-900">Nie ma takiej strony</h1>
      <Link to="/" className="mt-2 inline-block text-indigo-600 hover:underline">Wróć do podsumowania</Link>
    </section>
  )
}
