import { formatAmount, type Money } from '../money'
import type { Summary } from './summaryApi'

// The account types the web app sums up separately (App\Constant\AccountTypeNames).
const ROR = 'ROR'
const WAKACJE = 'Wakacje'

// Household names as the web app shows them (translations, home.<name>); any other name is shown as it is.
const HOUSEHOLD_LABELS: Record<string, string> = { Matka: 'Mama' }

const CELL = 'px-3 py-2'

function Amount({ money }: { money: Money | null | undefined }) {
  return money
    ? <>{formatAmount(money.amount)}</>
    : <span className="font-normal text-red-600">Brak danych — sprawdź konfigurację</span>
}

export function TotalsTable({ summary }: { summary: Summary }) {
  const rows = [
    { label: 'ROR', money: summary.byType[ROR]?.pln, muted: false },
    { label: 'Wakacje', money: summary.byType[WAKACJE]?.pln, muted: false },
    { label: 'Kasa', money: summary.retirementBalance, muted: true },
    { label: 'Złoto', money: summary.gold, muted: true },
    { label: 'Backup', money: summary.retirementBalanceWithGold, muted: false },
  ]

  return (
    <div className="inline-block overflow-x-auto rounded-xl bg-white shadow">
      <table className="text-sm">
        <thead className="bg-slate-800 text-left text-white">
          <tr>
            <th className={CELL}>Typ</th>
            <th className={`${CELL} text-right`}>PLN</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((row) => (
            <tr key={row.label} className={row.muted ? 'text-slate-500' : 'text-slate-800'}>
              <td className={CELL}>{row.label}</td>
              <td className={`${CELL} text-right tabular-nums ${row.muted ? '' : 'font-semibold'}`}>
                <Amount money={row.money} />
              </td>
            </tr>
          ))}
          {Object.entries(summary.byHousehold).map(([name, money]) => (
            <tr key={name} className={name === summary.ownFundsHouseholdName ? 'bg-slate-100' : ''}>
              <td className={CELL}>{HOUSEHOLD_LABELS[name] ?? name}</td>
              <td className={`${CELL} text-right font-semibold tabular-nums`}><Amount money={money} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
