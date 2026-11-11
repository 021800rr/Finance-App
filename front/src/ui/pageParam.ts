// ?page=N from the address; anything but a whole number from 1 up (also '2.5' or '1e2') means the first page.
export function pageFromParam(value: string | null): number {
  return value !== null && /^[1-9]\d*$/.test(value) ? Number(value) : 1
}
