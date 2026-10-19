// Short messages at the bottom right ("Draft saved.", errors).
import { ref } from 'vue'

const toasts = ref([])

export function useToast() {
  function toast(message, type = 'ok') {
    const id = Math.random()
    toasts.value.push({ id, message, type })
    setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id) }, type === 'error' ? 6000 : 3000)
  }
  return { toasts, toast, toastError: e => toast(e.message || String(e), 'error') }
}
