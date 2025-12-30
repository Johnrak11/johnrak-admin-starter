export function normalizeSortOrder(v) {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}
