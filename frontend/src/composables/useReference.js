// Dropdown lists (hidden "Validation" sheet), the open fiscal years, and the chosen year's General Info.
import { ref } from 'vue'
import { useApi } from './useApi'

const KEY = 'wfp.fy'
const read = () => { try { return Number(localStorage.getItem(KEY)) || null } catch { return null } }

const fy = ref(read())
const ref_ = ref(null)
const api = useApi()

async function loadReference() {
  let l = await api.get('lookups', { fy: fy.value })
  if (!fy.value || !l.years.includes(fy.value)) {
    // remembered year is not open: use the latest one and reload its General Info
    fy.value = l.years[0] || new Date().getFullYear()
    l = await api.get('lookups', { fy: fy.value })
  }
  ref_.value = l
  return l
}
async function setFy(y) {
  fy.value = Number(y)
  try { localStorage.setItem(KEY, String(fy.value)) } catch { /* private window */ }
  await loadReference()
}
const fundLabel = name => (name === 'GAA' ? `${fy.value} GAA` : name)

export function useReference() {
  return { fy, lists: ref_, loadReference, setFy, fundLabel }
}
