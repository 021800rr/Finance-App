import { CheckCircleIcon, PencilSquareIcon, QuestionMarkCircleIcon, TrashIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { findOtherSide, type Operation, useDeleteOperation, useSetOperationConfirmed } from './accountApi'

const CELL = 'px-3 py-1.5'
const ICON = 'cursor-pointer text-slate-500 hover:text-slate-900 disabled:cursor-wait'
// The blue frame the web app draws around the operation a 🔗 led to (tr.highlight in its app.css).
const HIGHLIGHT = '[&>td]:border-y-2 [&>td]:border-blue-500 [&>td:first-child]:border-l-2 '
  + '[&>td:last-child]:border-r-2'

type Props = {
  operations: Operation[]
  // the operation framed after a 🔗 (?highlight=ID)
  highlight?: number | null
  // the search results show the account of every operation: account numbers by account id
  accountNumbers?: Map<number, string>
  // the 🔗 to the other side of a transfer; the search results have none, as in the web app
  otherSideLinks?: boolean
  // what the table says when there are no operations
  emptyText?: string
}

// The operations of an account history or of a search, with the actions of the web app on each of them.
export function OperationsTable({
  operations,
  highlight = null,
  accountNumbers,
  otherSideLinks = true,
  emptyText,
}: Props) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const setConfirmed = useSetOperationConfirmed()
  const remove = useDeleteOperation()
  const [otherSideMessage, setOtherSideMessage] = React.useState('')
  // One change at a time: the buttons wait until the table shows the result of the previous one.
  const busy = setConfirmed.isPending || remove.isPending
  const failure = remove.isError
    ? errorMessage(remove.error, t('app.delete_failed'))
    : setConfirmed.isError
      ? errorMessage(setConfirmed.error, t('app.confirm_failed'))
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
      setOtherSideMessage(errorMessage(error, t('app.other_side_failed')))
      setParams({ ...Object.fromEntries(params), highlight: String(operation.id) })
    }
  }

  function toggleConfirmed(operation: Operation) {
    clearMessages()
    setConfirmed.mutate({ operationId: operation.id, confirmed: !operation.confirmed })
  }

  function deleteOperation(operation: Operation) {
    // The browser's own question, as in the web app.
    if (!window.confirm(t('operation.confirm_delete'))) {
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
            {accountNumbers && <th className={CELL}>{t('search.account_column')}</th>}
            <th className={CELL}>{t('operation.date')}</th>
            <th className={CELL}>{t('operation.contractor')}</th>
            <th className={CELL}>{t('operation.description')}</th>
            <th className={`${CELL} text-right`}>{t('operation.amount')}</th>
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 text-slate-800">
          {operations.map((operation) => (
            <tr key={operation.id} className={operation.id === highlight ? HIGHLIGHT : 'hover:bg-slate-50'}>
              <td className={`${CELL} text-right text-slate-500 tabular-nums`}>{operation.id}</td>
              {accountNumbers && (
                <td className={CELL}>
                  <code className="rounded bg-slate-100 px-1.5 py-0.5">
                    {accountNumbers.get(operation.accountId) ?? operation.accountId}
                  </code>
                </td>
              )}
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
                  {otherSideLinks && (
                    <span className="w-5 text-center">
                      {operation.transactionUuid && (
                        <button
                          type="button"
                          onClick={() => goToOtherSide(operation)}
                          title={t('operation.show_other_side')}
                          aria-label={t('operation.show_other_side')}
                          className="cursor-pointer"
                        >
                          🔗
                        </button>
                      )}
                    </span>
                  )}
                  <button
                    type="button"
                    onClick={() => toggleConfirmed(operation)}
                    disabled={busy}
                    title={operation.confirmed ? t('app.confirmed') : t('app.unconfirmed')}
                    aria-label={t('operation.change_confirmation_status')}
                    aria-pressed={operation.confirmed}
                    className={ICON}
                  >
                    {operation.confirmed
                      ? <CheckCircleIcon className="size-5 text-green-600" />
                      : <QuestionMarkCircleIcon className="size-5 text-amber-500" />}
                  </button>
                  <Link
                    to={`/operation/${operation.id}/edit`}
                    title={t('operation.edit')}
                    aria-label={t('operation.edit')}
                    className={ICON}
                  >
                    <PencilSquareIcon className="size-5" />
                  </Link>
                  <button
                    type="button"
                    onClick={() => deleteOperation(operation)}
                    disabled={busy}
                    title={t('operation.delete')}
                    aria-label={t('operation.delete')}
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
              <td colSpan={accountNumbers ? 7 : 6} className={`${CELL} text-center text-slate-500`}>
                {emptyText ?? t('operation.none_found')}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
