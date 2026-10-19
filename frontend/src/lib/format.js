const peso = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export const php = v => '₱' + peso.format(Number(v || 0))
// #,##0.00 - used for '000 PhP amounts (column P, General Info budgets, WFP Form 1)
export const num = v => peso.format(Number(v || 0))


const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const parts = d => d.slice(0, 10).split('-').map(Number)

// "Jan 1, 2026"
export function shortDate(d) {
  if (!d) return ''
  const [y, m, day] = parts(d)
  return `${MONTHS[m - 1]} ${day}, ${y}`
}
// "January 31, 2027"
export function longDate(d) {
  if (!d) return ''
  const [y, m, day] = parts(d)
  return `${MONTHS_LONG[m - 1]} ${day}, ${y}`
}

export function target(q, unit) {
  if (q === null || q === undefined || q === '') return ''
  // same text the DOH "Print (GAA)" sheet produces: "<target> of <unit>", percentages shown as-is
  return /%$/.test(String(q).trim()) || !unit ? q : `${q} of ${unit}`
}

export const WORKFLOW = {
  draft: { label: 'Draft', cls: 's-draft' },
  submitted: { label: 'Submitted', cls: 's-submitted' },
  returned: { label: 'Returned', cls: 's-returned' },
  approved: { label: 'Approved', cls: 's-approved' }
}

// "1 Jan 2026" — the d mmm yyyy format used on WFP Form 1
export function wfpDate(d) {
  if (!d) return ''
  const [y, m, day] = parts(d)
  return `${day} ${MONTHS[m - 1]} ${y}`
}
