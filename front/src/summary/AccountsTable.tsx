import { EyeIcon, EyeSlashIcon, ListBulletIcon } from '@heroicons/react/24/outline'
import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { errorMessage } from '../messages'
import { formatAmount } from '../money'
import { SortHeader } from '../ui/SortHeader'
import { sortRows, type SortState } from '../ui/sorting'
import { type SummaryAccount, useSetAccountHidden, useSetMainAccount } from './summaryApi'

const CELL = 'px-3 py-2'

type Column = {
  key: string
  // the translation key of its header
  label: string
  // what the column sorts by
  value: (account: SummaryAccount) => string | number
  right?: boolean
}

// The sortable columns of the web app's table; the balance and the rate sort as numbers.
const COLUMNS: Column[] = [
  { key: 'bank', label: 'account.bank', value: (account) => account.bank },
  { key: 'type', label: 'account.type', value: (account) => account.type },
  { key: 'number', label: 'account.number', value: (account) => account.account_number },
  { key: 'rate', label: 'home.rate', value: (account) => Number(account.rate?.amount ?? 0), right: true },
  { key: 'saldo', label: 'account.balance', value: (account) => Number(account.saldo.amount), right: true },
  { key: 'currency', label: 'account.currency', value: (account) => account.currency },
]

export function AccountsTable({ accounts }: { accounts: SummaryAccount[] }) {
  const { t } = useTranslation()
  // Not sorted at first: the order of the API (by account number), as in the web app.
  const [sort, setSort] = React.useState<SortState>(null)
  const column = COLUMNS.find((candidate) => candidate.key === sort?.column)
  const rows = sort && column ? sortRows(accounts, column.value, sort.direction) : accounts
  const setMain = useSetMainAccount()
  const setHidden = useSetAccountHidden()
  // One change at a time: the buttons wait until the table shows the result of the previous one.
  const busy = setMain.isPending || setHidden.isPending
  const failure = setMain.isError
    ? errorMessage(setMain.error, t('app.set_main_failed'))
    : setHidden.isError && errorMessage(setHidden.error, t('app.hidden_failed'))

  // Each action clears the message of the other one, so only the last failure is on the screen.
  function makeMain(account: SummaryAccount) {
    setHidden.reset()
    setMain.mutate(account.id)
  }

  function toggleHidden(account: SummaryAccount) {
    setMain.reset()
    setHidden.mutate({ accountId: account.id, hidden: !account.hidden })
  }

  return (
    <div className="overflow-x-auto rounded-xl bg-white dark:bg-slate-900 shadow">
      {failure && (
        <p role="alert" className="bg-amber-100 dark:bg-amber-950 px-3 py-2 text-sm text-amber-900 dark:text-amber-200">
          {failure}
        </p>
      )}
      <table className="min-w-full text-sm">
        <thead className="bg-slate-800 text-left text-white">
          <tr>
            {COLUMNS.map((item) => (
              <SortHeader
                key={item.key}
                label={t(item.label)}
                direction={sort?.column === item.key ? sort.direction : undefined}
                onSort={(direction) => setSort({ column: item.key, direction })}
                className={item.right ? `${CELL} text-right` : CELL}
              />
            ))}
            <th className={CELL}><span className="sr-only">{t('app.actions')}</span></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
          {rows.map((account) => (
            <tr
              key={account.id}
              className={account.hidden ? 'text-slate-400 dark:text-slate-500' : 'text-slate-800 dark:text-slate-200'}
            >
              <td className={CELL}>{account.bank}</td>
              <td className={CELL}>{account.type}</td>
              <td className={CELL}>
                <code className="rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5">{account.account_number}</code>
              </td>
              <td className={`${CELL} text-right`}>
                {account.missing_rate ? (
                  <span className="rounded bg-red-600 px-2 py-0.5 text-xs text-white">{t('home.missing_rate')}</span>
                ) : (
                  account.rate && (
                    <span className="text-xs text-slate-500 dark:text-slate-400">
                      {formatAmount(account.rate.amount)}
                    </span>
                  )
                )}
              </td>
              <td className={`${CELL} text-right font-semibold tabular-nums`}>{formatAmount(account.saldo.amount)}</td>
              <td className={CELL}>{account.currency}</td>
              <td className={`${CELL} whitespace-nowrap text-right`}>
                {account.is_main ? (
                  <span className="mr-3" title={t('home.main_account')}>★</span>
                ) : (
                  <button
                    type="button"
                    onClick={() => makeMain(account)}
                    disabled={busy}
                    title={t('home.set_main')}
                    aria-label={t('home.set_main')}
                    className="mr-3 cursor-pointer text-slate-400 dark:text-slate-500 hover:text-slate-800
                      dark:hover:text-slate-200 disabled:cursor-wait"
                  >
                    ☆
                  </button>
                )}
                <Link
                  to={`/account/${account.id}/operations`}
                  title={t('home.history')}
                  aria-label={t('home.history')}
                  className="inline-block align-middle text-slate-500 dark:text-slate-400 hover:text-slate-800
                    dark:hover:text-slate-200"
                >
                  <ListBulletIcon className="size-5" />
                </Link>
                <button
                  type="button"
                  onClick={() => toggleHidden(account)}
                  disabled={busy}
                  title={account.hidden ? t('home.show') : t('home.hide')}
                  aria-label={account.hidden ? t('home.show') : t('home.hide')}
                  className="ml-3 cursor-pointer align-middle text-slate-500 dark:text-slate-400 hover:text-slate-800
                    dark:hover:text-slate-200 disabled:cursor-wait"
                >
                  {account.hidden ? <EyeIcon className="size-5" /> : <EyeSlashIcon className="size-5" />}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
