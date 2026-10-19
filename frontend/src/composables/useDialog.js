// In-page replacement for confirm()/prompt()/alert(), rendered by components/AppDialog.vue.
// ask({ title, message, facts: [[label, value]], confirmText, tone, input: { label, type, multiline, value }, cancel })
// resolves true/false, or the typed text (null when cancelled) when `input` is given.
import { ref } from 'vue'

const dialog = ref(null)

export function useDialog() {
  function ask({ title, message = '', facts = null, confirmText = 'Confirm', tone = 'primary', input = null, cancel = true }) {
    return new Promise(resolve => {
      dialog.value = { title, message, facts, confirmText, tone, input, cancel, value: input?.value || '', resolve }
    })
  }
  function close(ok) {
    const d = dialog.value
    if (!d) return
    if (ok && d.input && !String(d.value).trim()) return
    dialog.value = null
    d.resolve(d.input ? (ok ? String(d.value).trim() : null) : ok)
  }
  return { dialog, ask, close }
}
