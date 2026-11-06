import { CheckCircleIcon, PencilSquareIcon, QuestionMarkCircleIcon, TrashIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { findOtherSide, type Operation, useDeleteOperation, useSetOperationConfirmed } from './accountApi'

const CELL = 'px-3 py-1.5'
const ICON = 'cursor-pointer text-slate-500 hover:text-slate-900 disabled:cursor-wait'
// The blue frame the web app draws around the operation a 🔗 led to (tr.highlight in its app.css).
const HIGHLIGHT = '[&>td]:border-y-2 [&>td]:border-blue-500 [&>td:first-child]:border-l-2 '
  + '[&>td:last-child]:border-r-2'

export function OperationsTable({ operations, highlight }: { operations: Operation[]; highlight: number | null }) {
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const setConfirmed = useSetOperationConfirmed()
  const remove = useDeleteOperation()
  const [otherSideMessage, setOtherSideMessage] = React.useState('')
  // One change at a time: the buttons wait until the table shows the result of the previous one.
  const busy = setConfirmed.isPending || remove.isPending
  const failure = remove.isError
    ? errorMessage(remove.error, 'Nie udało się usunąć operacji.')
    : setConfirmed.isError
      ? errorMessage(setConfirmed.error, 'Nie udało się zmienić statusu potwierdzenia.')
      : otherSideMessage

  // Every action clears the message of the previous one, so only the last failure is on the screen.
  function clearMessages() {
    setOtherSideMessage('')
    setConfirmed.reset()
    remove.reset()
  }

  // As in the web app: the history of the other account, with the other operation framed. When there is no other
  // side (it was deleted), the message says so and the operation itself is framed.
  async function goToOtherSide(operation: Operation) {
    clearMessages()
    try {
      const otherSide = await findOtherSide(operation.id)
      navigate(`/account/${otherSide.accountId}/operations?highlight=${otherSide.id}`)
    } catch (error) {
      setOtherSideMessage(errorMessage(error, 'Nie udało się znaleźć drugiej strony transakcji.'))
      setParams({ ...Object.fromEntries(params), highlight: String(operation.id) })
    }
  }

  function toggleConfirmed(operation: Operation) {
    clearMessages()
    setConfirmed.mutate({ operationId: operation.id, confirmed: !operation.confirmed })
  }

  function deleteOperation(operation: Operation) {
    // The browser's own question, as in the web app.
    if (!window.confirm('Usunąć?')) {
      return
    }
    clearMessages()
    remove.mutate(operation.id)
  }

  return (
    <div className="overflow-x-auto">
      {failure && <p role="alert" className="mb-2 rounded bg-amber-100 px-3 py-2 text-sm text-amber-900">{failure}</p>}
      <table className="min-w-full text-sm">
        <thead className="bg-slate-800 text-left text-white">
          <tr>
            <th className={`${CELL} text-right`}>#</th>
            <th className={CELL}>Data</th>
            <th className={CELL}>Kontrahent</th>
            <th className={CELL}>Opis</th>
            <th className={`${CELL} text-right`}>Kwota</th>
            <th className={CELL}><span className="sr-only">Akcje</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 text-slate-800">
          {operations.map((operation) => (
            <tr key={operation.id} className={operation.id === highlight ? HIGHLIGHT : 'hover:bg-slate-50'}>
              <td className={`${CELL} text-right text-slate-500 tabular-nums`}>{operation.id}</td>
              <td className={`${CELL} whitespace-nowrap tabular-nums`}>{operation.operationDate}</td>
              <td className={CELL}>{operation.contractorName}</td>
              <td className={`${CELL} text-slate-500`}>{operation.description}</td>
              {/* Income in green, as in the web app; an expense stays in the normal colour. */}
              <td
                className={`${CELL} whitespace-nowrap text-right font-semibold tabular-nums `
                  + (Number(operation.amount) > 0 ? 'text-green-700' : '')}
              >
                {formatAmount(operation.amount)}
              </td>
              <td className={CELL}>
                <div className="flex items-center justify-end gap-2">
                  {/* Every icon has its own place, so the icons of all rows stay in line. */}
                  <span className="w-5 text-center">
                    {operation.transactionUuid && (
                      <button
                        type="button"
                        onClick={() => goToOtherSide(operation)}
                        title="Pokaż drugą stronę transakcji"
                        aria-label="Pokaż drugą stronę transakcji"
                        className="cursor-pointer"
                      >
                        🔗
                      </button>
                    )}
                  </span>
                  <button
                    type="button"
                    onClick={() => toggleConfirmed(operation)}
                    disabled={busy}
                    title={operation.confirmed ? 'Potwierdzona' : 'Do potwierdzenia'}
                    aria-label="Zmień status potwierdzenia"
                    aria-pressed={operation.confirmed}
                    className={ICON}
                  >
                    {operation.confirmed
                      ? <CheckCircleIcon className="size-5 text-green-600" />
                      : <QuestionMarkCircleIcon className="size-5 text-amber-500" />}
                  </button>
                  <Link to={`/operation/${operation.id}/edit`} title="Edytuj" aria-label="Edytuj" className={ICON}>
                    <PencilSquareIcon className="size-5" />
                  </Link>
                  <button
                    type="button"
                    onClick={() => deleteOperation(operation)}
                    disabled={busy}
                    title="Usuń"
                    aria-label="Usuń"
                    className={ICON}
                  >
                    <TrashIcon className="size-5 text-red-600" />
                  </button>
                </div>
              </td>
            </tr>
          ))}
          {operations.length === 0 && (
            <tr>
              <td colSpan={6} className={`${CELL} text-center text-slate-500`}>Brak operacji</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
