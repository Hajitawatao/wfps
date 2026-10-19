// Light / dark theme. Remembered per browser; follows Windows' setting until the user picks one.
import { ref, watchEffect } from 'vue'

const KEY = 'wfp.theme'
const saved = (() => { try { return localStorage.getItem(KEY) } catch { return null } })()
const theme = ref(saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))

watchEffect(() => { document.documentElement.dataset.theme = theme.value })

export function useTheme() {
  const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
    try { localStorage.setItem(KEY, theme.value) } catch { /* private window */ }
  }
  return { theme, toggleTheme }
}
