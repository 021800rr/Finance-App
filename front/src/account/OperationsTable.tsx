import { CheckCircleIcon, QuestionMarkCircleIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { Code } from '../ui/Code'
import { EmptyRow } from '../ui/EmptyRow'
import { Alert } from '../ui/Feedback'
import { DeleteButton, EditLink, IconButton } from '../ui/RowActions'
import { AMOUNT, BODY, CELL, HEAD, MUTED, ROW, TABLE } from '../ui/tableStyle'
import { findOtherSide, type Operation, useDeleteOperation, useSetOperationConfirmed } from './accountApi'

// Income in green, as in the web app; an expense stays in the normal colour.
const INCOME = 'text-green-700 dark:text-green-400'
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
  // true while the other side of a transfer is looked for: one click, one request
  const [following, setFollowing] = React.useState(false)
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
    setFollowing(true)
    try {
      const otherSide = await findOtherSide(operation.id)
      navigate(`/account/${otherSide.accountId}/operations?highlight=${otherSide.id}`)
    } catch (error) {
      setOtherSideMessage(errorMessage(error, t('app.other_side_failed')))
      setParams({ ...Object.fromEntries(params), highlight: String(operation.id) })
    } finally {
      setFollowing(false)
    }
  }

  return (
    <div className="space-y-2 overflow-x-auto">
      {failure && <Alert tone="warning">{failure}</Alert>}
      <table className={TABLE}>
        <thead className={HEAD}>
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
        <tbody className={BODY}>
          {operations.map((operation) => (
            <tr key={operation.id} className={operation.id === highlight ? HIGHLIGHT : ROW}>
              <td className={`${CELL} text-right tabular-nums ${MUTED}`}>{operation.id}</td>
              {accountNumbers && (
                <td className={CELL}>
                  <Code>{accountNumbers.get(operation.accountId) ?? operation.accountId}</Code>
                </td>
              )}
              <td className={`${CELL} whitespace-nowrap tabular-nums`}>{operation.operationDate}</td>
              <td className={CELL}>{operation.contractorName}</td>
              <td className={`${CELL} ${MUTED}`}>{operation.description}</td>
              <td className={`${CELL} ${AMOUNT} ${Number(operation.amount) > 0 ? INCOME : ''}`}>
                {formatAmount(operation.amount)}
              </td>
              <td className={CELL}>
                <div className="flex items-center justify-end gap-2">
                  {/* Every icon has its own place, so the icons of all rows stay in line. */}
                  {otherSideLinks && (
                    <span className="w-5 text-center">
                      {operation.transactionUuid && (
                        <IconButton
                          label={t('operation.show_other_side')}
                          onClick={() => goToOtherSide(operation)}
                          disabled={following}
                        >
                          🔗
                        </IconButton>
                      )}
                    </span>
                  )}
                  <IconButton
                    label={t('operation.change_confirmation_status')}
                    title={operation.confirmed ? t('app.confirmed') : t('app.unconfirmed')}
                    pressed={operation.confirmed}
                    disabled={busy}
                    onClick={() => {
                      clearMessages()
                      setConfirmed.mutate({ operationId: operation.id, confirmed: !operation.confirmed })
                    }}
                  >
                    {operation.confirmed
                      ? <CheckCircleIcon className="size-5 text-green-600 dark:text-green-500" />
                      : <QuestionMarkCircleIcon className="size-5 text-amber-500" />}
                  </IconButton>
                  <EditLink to={`/operation/${operation.id}/edit`} />
                  <DeleteButton
                    disabled={busy}
                    onDelete={() => {
                      clearMessages()
                      remove.mutate(operation.id)
                    }}
                  />
                </div>
              </td>
            </tr>
          ))}
          {operations.length === 0 && (
            <EmptyRow columns={accountNumbers ? 7 : 6} text={emptyText ?? t('operation.none_found')} />
          )}
        </tbody>
      </table>
    </div>
  )
}
