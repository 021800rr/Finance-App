import { useTranslation } from 'react-i18next'
import type { SortDirection } from './sorting'

const ARROWS = {
  asc: { empty: '△', filled: '▲', label: 'common.sort_ascending' },
  desc: { empty: '▽', filled: '▼', label: 'common.sort_descending' },
}

type Props = {
  label: string
  // the order of the table by this column; undefined while it is sorted by another one or not at all
  direction: SortDirection | undefined
  onSort: (direction: SortDirection) => void
  className?: string
}

// A header of a sortable table, as in the web app (assets/js/sort-table.js): a click on the header switches the
// order, an arrow sets one; the filled arrow shows the current order.
export function SortHeader({ label, direction, onSort, className = '' }: Props) {
  const ariaSort = { asc: 'ascending', desc: 'descending' } as const

  return (
    <th
      aria-sort={direction && ariaSort[direction]}
      onClick={() => onSort(direction === 'asc' ? 'desc' : 'asc')}
      className={`cursor-pointer select-none ${className}`}
    >
      {label}
      <span className="ml-1 inline-flex flex-col align-middle text-[0.55rem] leading-none">
        <Arrow direction="asc" active={direction === 'asc'} onSort={onSort} />
        <Arrow direction="desc" active={direction === 'desc'} onSort={onSort} />
      </span>
    </th>
  )
}

function Arrow({ direction, active, onSort }: {
  direction: SortDirection
  active: boolean
  onSort: (direction: SortDirection) => void
}) {
  const { t } = useTranslation()
  const arrow = ARROWS[direction]

  return (
    <button
      type="button"
      aria-label={t(arrow.label)}
      aria-pressed={active}
      onClick={(event) => {
        // An arrow sets the order; the click must not also reach the header, which would switch it back.
        event.stopPropagation()
        onSort(direction)
      }}
      className="cursor-pointer"
    >
      {active ? arrow.filled : arrow.empty}
    </button>
  )
}
