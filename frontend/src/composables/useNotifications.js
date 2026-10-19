// Notifications shared by the bell and the counts in the menu.
// Planning Office / admin: activities waiting for review. Encoders: their area's activities approved or returned.
// Checked every minute while the page is open; a toast says when something new comes in.
import { reactive, watch } from 'vue'
import { useApi } from './useApi'
import { useReference } from './useReference'
import { useToast } from './useToast'

const n = reactive({ kind: '', unread: 0, counts: {}, items: [] })
let timer = null
let first = true
let started = false

export function useNotifications() {
  const api = useApi()
  const { fy } = useReference()
  const { toast } = useToast()

  async function load() {
    try {
      const r = await api.get('notifications', { fy: fy.value })
      if (!first && r.unread > n.unread) {
        const fresh = r.items.filter(a => +a.is_new).slice(0, r.unread - n.unread)
        if (r.kind === 'review') {
          toast(fresh.length === 1 ? `Submitted for review: ${fresh[0].office} – ${fresh[0].title}` : `${fresh.length} new activities submitted for review.`)
        } else {
          for (const a of fresh.slice(0, 3)) toast(`${a.workflow === 'approved' ? 'Approved' : 'Returned'}: ${a.title}`, a.workflow === 'returned' ? 'warn' : 'ok')
        }
      }
      first = false
      Object.assign(n, r)
    } catch { /* a missed check is retried next minute */ }
  }

  async function markSeen() {
    if (!n.unread) return
    try { await api.put('notifications'); n.unread = 0 } catch { /* stays unread */ }
  }

  /** Called once after sign-in; stop() on sign-out. */
  function start() {
    if (started) return
    started = true
    first = true
    load()
    timer = setInterval(() => { if (!document.hidden) load() }, 60000)
  }
  function stop() {
    started = false
    clearInterval(timer)
    Object.assign(n, { kind: '', unread: 0, counts: {}, items: [] })
  }
  watch(fy, () => { if (started) { first = true; load() } })

  return { n, load, markSeen, start, stop }
}
