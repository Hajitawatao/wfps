<template>
  <div class="bell-wrap" ref="el">
    <button class="icon-btn bell" :aria-expanded="open" aria-haspopup="true" @click="toggle"
            :aria-label="n.unread ? `${n.unread} new submission${n.unread === 1 ? '' : 's'}` : 'Submissions for review'" title="Submissions for review">
      <Icon name="bell" />
      <span v-if="n.unread" class="dot">{{ n.unread > 99 ? '99+' : n.unread }}</span>
    </button>
    <div v-if="open" class="bell-panel">
      <div class="bp-h"><b>Submitted for review</b><span class="muted small">FY {{ fy }} · {{ n.waiting }} waiting</span></div>
      <p v-if="!n.items.length" class="bp-empty muted small">Nothing is waiting for review.</p>
      <button v-for="a in n.items" :key="a.id" class="bp-item" :class="{ new: +a.is_new }" @click="go(`/encoding-sheet/${a.id}`)">
        <span class="t">{{ a.title }}</span>
        <span class="m">{{ a.office }}<template v-if="a.submitted_by"> · {{ a.submitted_by }}</template> · {{ ago(a) }}</span>
      </button>
      <button v-if="n.waiting" class="bp-all" @click="go('/review')">Open For review<Icon name="chevron-down" :size="14" class="r90" /></button>
    </div>
  </div>
</template>

<script setup>
// Planning Office / admin: activities that offices submitted and that wait for review.
// Checks every minute; a toast says when a new one comes in. Opening the bell marks them as seen.
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { useToast } from '../composables/useToast'
import { shortDate } from '../lib/format'
import Icon from './Icon.vue'

const api = useApi()
const router = useRouter()
const { fy } = useReference()
const { toast } = useToast()
const n = reactive({ unread: 0, waiting: 0, items: [] })
const open = ref(false)
const el = ref(null)
let timer = null
let first = true

async function load() {
  try {
    const r = await api.get('notifications', { fy: fy.value })
    if (!first && r.unread > n.unread) {
      const fresh = r.items.filter(a => +a.is_new).slice(0, r.unread - n.unread)
      toast(fresh.length === 1 ? `Submitted for review: ${fresh[0].office} – ${fresh[0].title}` : `${r.unread - n.unread} new activities submitted for review.`)
    }
    first = false
    Object.assign(n, r)
  } catch { /* a missed check is retried next minute */ }
}
async function toggle() {
  open.value = !open.value
  if (!open.value) return
  await load()
  if (n.unread) { try { await api.put('notifications'); n.unread = 0 } catch { /* stays unread */ } }
}
function go(path) { open.value = false; router.push(path) }
// minutes are counted by the database, so the PC's clock does not matter
function ago(a) {
  const m = +a.mins
  if (m < 1) return 'just now'
  if (m < 60) return `${m} min ago`
  if (m < 1440) return `${Math.round(m / 60)} h ago`
  return shortDate(a.submitted_at.slice(0, 10))
}

const outside = e => { if (open.value && !el.value?.contains(e.target)) open.value = false }
const esc = e => { if (e.key === 'Escape') open.value = false }
onMounted(() => {
  load()
  timer = setInterval(() => { if (!document.hidden) load() }, 60000)
  document.addEventListener('mousedown', outside)
  document.addEventListener('keydown', esc)
})
onBeforeUnmount(() => { clearInterval(timer); document.removeEventListener('mousedown', outside); document.removeEventListener('keydown', esc) })
watch(fy, () => { first = true; load() })
</script>

<style scoped>
.bell-wrap { position: relative; }
.bell { position: relative; }
.dot { position: absolute; top: 2px; right: 1px; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 9px; background: var(--signal-solid); color: #fff;
  font-size: 10px; font-weight: 700; line-height: 17px; text-align: center; box-shadow: 0 0 0 2px var(--surface); }
.bell-panel { position: absolute; right: 0; top: calc(100% + 6px); width: 360px; max-width: calc(100vw - 24px); background: var(--surface); border: 1px solid var(--line);
  border-radius: var(--r-sm); box-shadow: 0 10px 30px rgba(var(--shade), .14); z-index: 60; overflow: hidden; }
.bp-h { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; padding: 12px 14px; border-bottom: 1px solid var(--line-2); }
.bp-h b { font-size: 13px; color: var(--ink); }
.bp-empty { padding: 18px 14px; }
.bp-item { display: grid; gap: 3px; width: 100%; padding: 10px 14px; border: 0; border-bottom: 1px solid var(--line-2); background: none; font: inherit;
  text-align: left; cursor: pointer; }
.bp-item:hover { background: var(--hover); }
.bp-item.new { background: var(--brand-wash); }
.bp-item .t { font-size: 13px; color: var(--ink); font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bp-item .m { font-size: 11px; color: var(--ink-3); }
.bp-all { display: flex; align-items: center; justify-content: center; gap: 4px; width: 100%; padding: 10px; border: 0; background: none; font: inherit;
  font-size: 12.5px; font-weight: 600; color: var(--brand); cursor: pointer; }
.bp-all:hover { background: var(--hover); }
.r90 { transform: rotate(-90deg); }
</style>
