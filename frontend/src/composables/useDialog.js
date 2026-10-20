// In-page replacement for confirm()/prompt()/alert(), rendered by components/AppDialog.vue.
// ask({ title, message, facts: [[label, value]], confirmText, tone, input: { label, type, multiline, value }, check: { label, value }, cancel })
// resolves true/false, or the typed text (null when cancelled) when `input` is given,
// or { checked } (false when cancelled) when `check` is given.
import { ref } from 'vue'

const dialog = ref(null)

export function useDialog() {
  function ask({ title, message = '', facts = null, confirmText = 'Confirm', tone = 'primary', input = null, check = null, cancel = true }) {
    return new Promise(resolve => {
      dialog.value = { title, message, facts, confirmText, tone, input, check, cancel, value: input?.value || '', checked: check ? check.value !== false : false, resolve }
    })
  }
  function close(ok) {
    const d = dialog.value
    if (!d) return
    if (ok && d.input && !String(d.value).trim()) return
    dialog.value = null
    d.resolve(d.input ? (ok ? String(d.value).trim() : null) : d.check ? (ok && { checked: d.checked }) : ok)
  }
  return { dialog, ask, close }
}
