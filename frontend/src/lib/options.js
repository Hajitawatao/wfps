// Dropdown choices for the Encoding Sheet columns (from the hidden "Validation" sheet).
// Same lists as FUNCTION_TYPES / EXPENSE_CLASSES / STATUSES in backend/lib/wfp.php.
import { useReference } from '../composables/useReference'

const { lists, fundLabel } = useReference()
const L = () => lists.value || {}

export const FUNCTION_TYPES = ['Strategic', 'Core', 'Support']                         // column A
export const EXPENSE_CLASSES = ['MOOE', 'CO']                                           // column H
export const STATUSES = ['Obligated', 'Disbursed', 'Deferred', 'Ongoing', 'Conducted']  // column S

export const officeOptions = () => (L().offices || []).map(o => ({ value: o.id, label: o.name, hint: o.code }))
export const objectiveOptions = ft => (L().objectives || []).filter(o => o.function_type === ft).map(o => ({ value: o.id, label: o.title }))
export const indicatorOptions = objectiveId => ((L().objectives || []).find(o => o.id === objectiveId)?.indicators || [])
  .map(i => ({ value: i.id, label: i.title }))
export const classificationOptions = () => (L().classifications || []).map(c => ({ value: c.id, label: c.name }))
export const lineItemOptions = () => (L().line_items || []).map(l => ({ value: l.id, label: l.name }))
export const fundOptions = () => (L().fund_sources || []).map(f => ({ value: f.id, label: fundLabel(f.name) }))
