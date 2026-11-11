import { CELL, MUTED } from './tableStyle'

// The one row of an empty list, across all its columns: "Brak …".
export function EmptyRow({ columns, text }: { columns: number; text: string }) {
  return (
    <tr>
      <td colSpan={columns} className={`${CELL} text-center ${MUTED}`}>{text}</td>
    </tr>
  )
}
