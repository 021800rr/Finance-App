import { useTranslation } from 'react-i18next'
import { formatAmount, type Money } from '../money'
import { BODY, CELL, HEAD, TABLE } from '../ui/tableStyle'
import type { Summary } from './summaryApi'

// The account types the web app sums up separately (App\Constant\AccountTypeNames).
const ROR = 'ROR'
const WAKACJE = 'Wakacje'

// A total, or a note when the summary has no accounts of its type.
function Amount({ money }: { money: Money | undefined }) {
  const { t } = useTranslation()

  return money
    ? <>{formatAmount(money.amount)}</>
    : <span className="font-normal text-red-600 dark:text-red-400">{t('home.category_not_found')}</span>
}

export function TotalsTable({ summary }: { summary: Summary }) {
  const { t } = useTranslation()
  const rows = [
    { label: 'home.category_ror', money: summary.byType[ROR]?.pln, muted: false },
    { label: 'home.category_wakacje', money: summary.byType[WAKACJE]?.pln, muted: false },
    { label: 'home.category_kasa', money: summary.retirementBalance, muted: true },
    { label: 'home.category_gold', money: summary.gold, muted: true },
    { label: 'home.category_backup', money: summary.retirementBalanceWithGold, muted: false },
  ]

  return (
    <div className="inline-block overflow-x-auto rounded-xl bg-white dark:bg-slate-900 shadow">
      <table className={TABLE}>
        <thead className={HEAD}>
          <tr>
            <th className={CELL}>{t('account.type')}</th>
            <th className={`${CELL} text-right`}>{t('home.currency_pln')}</th>
          </tr>
        </thead>
        <tbody className={BODY}>
          {rows.map((row) => (
            <tr
              key={row.label}
              className={row.muted ? 'text-slate-500 dark:text-slate-400' : 'text-slate-800 dark:text-slate-200'}
            >
              <td className={CELL}>{t(row.label)}</td>
              <td className={`${CELL} text-right tabular-nums ${row.muted ? '' : 'font-semibold'}`}>
                <Amount money={row.money} />
              </td>
            </tr>
          ))}
          {Object.entries(summary.byHousehold).map(([name, money]) => (
            <tr key={name} className={name === summary.ownFundsHouseholdName ? 'bg-slate-100 dark:bg-slate-800' : ''}>
              {/* As in the web app: home.<name> when the household has a translation, its name otherwise. */}
              <td className={CELL}>{t(`home.${name}`, { defaultValue: name })}</td>
              <td className={`${CELL} text-right font-semibold tabular-nums`}><Amount money={money} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
