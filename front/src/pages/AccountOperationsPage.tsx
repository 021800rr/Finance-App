import { useParams } from 'react-router'

// /account/:id/operations: the history of one account. The :id part of the address arrives in useParams().
export function AccountOperationsPage() {
  const { id } = useParams()

  return (
    <section>
      <h1 className="text-2xl font-semibold text-slate-900">Historia konta #{id}</h1>
      <p className="mt-2 text-slate-500">Wkrótce.</p>
    </section>
  )
}
