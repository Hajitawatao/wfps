<template>
  <div class="bell-wrap" ref="el">
    <button class="icon-btn bell" :aria-expanded="open" aria-haspopup="true" @click="toggle"
            :aria-label="n.unread ? `${n.unread} new notification${n.unread === 1 ? '' : 's'}` : 'Notifications'" title="Notifications">
      <Icon name="bell" />
      <span v-if="n.unread" class="dot">{{ n.unread > 99 ? '99+' : n.unread }}</span>
    </button>
    <div v-if="open" class="bell-panel">
      <div class="bp-h">
        <b>{{ review ? 'Submitted for review' : 'Your activities' }}</b>
        <span class="muted small">FY {{ fy }}<template v-if="review"> · {{ n.counts.review || 0 }} waiting</template><template v-else-if="n.counts.returned"> · {{ n.counts.returned }} to fix</template></span>
      </div>
      <p v-if="!n.items.length" class="bp-empty muted small">{{ review ? 'Nothing is waiting for review.' : 'No approved or returned activities yet.' }}</p>
      <button v-for="a in n.items" :key="a.id" class="bp-item" :class="{ new: +a.is_new }" @click="go(`/encoding-sheet/${a.id}`)">
        <span class="t"><span v-if="!review" class="status" :class="WORKFLOW[a.workflow].cls">{{ WORKFLOW[a.workflow].label }}</span>{{ a.title }}</span>
        <span v-if="a.note" class="note-l">“{{ a.note }}”</span>
        <span class="m"><template v-if="review">{{ a.office }}<template v-if="a.who"> · {{ a.who }}</template></template><template v-else>{{ a.who ? `By ${a.who}` : 'Planning Office' }}</template> · {{ ago(a) }}</span>
      </button>
      <button v-if="review && n.counts.review" class="bp-all" @click="go('/review')">Open For review<Icon name="chevron-down" :size="14" class="r90" /></button>
      <button v-else-if="!review && n.counts.returned" class="bp-all" @click="go({ path: '/encoding-sheet', query: { workflow: 'returned' } })">Show returned activities<Icon name="chevron-down" :size="14" class="r90" /></button>
    </div>
  </div>
</template>

<script setup>
// Bell in the top bar. Planning Office / admin: activities waiting for review.
// Encoders: their area's activities approved or returned, with the Planning Office's note. Opening it marks all as seen.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useReference } from '../composables/useReference'
import { useNotifications } from '../composables/useNotifications'
import { shortDate, WORKFLOW } from '../lib/format'
import Icon from './Icon.vue'

const router = useRouter()
const { fy } = useReference()
const { n, load, markSeen } = useNotifications()
const review = computed(() => n.kind === 'review')
const open = ref(false)
const el = ref(null)

async function toggle() {
  open.value = !open.value
  if (!open.value) return
  await load()
  markSeen()
}
function go(to) { open.value = false; router.push(to) }
// minutes are counted by the database, so the PC's clock does not matter
function ago(a) {
  const m = +a.mins
  if (m < 1) return 'just now'
  if (m < 60) return `${m} min ago`
  if (m < 1440) return `${Math.round(m / 60)} h ago`
  return shortDate(a.at.slice(0, 10))
}

const outside = e => { if (open.value && !el.value?.contains(e.target)) open.value = false }
const esc = e => { if (e.key === 'Escape') open.value = false }
onMounted(() => { document.addEventListener('mousedown', outside); document.addEventListener('keydown', esc) })
onBeforeUnmount(() => { document.removeEventListener('mousedown', outside); document.removeEventListener('keydown', esc) })
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
.bp-item .status { margin-right: 6px; vertical-align: 1px; }
.note-l { font-size: 12px; color: var(--sun-ink); font-style: italic; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bp-all { display: flex; align-items: center; justify-content: center; gap: 4px; width: 100%; padding: 10px; border: 0; background: none; font: inherit;
  font-size: 12.5px; font-weight: 600; color: var(--brand); cursor: pointer; }
.bp-all:hover { background: var(--hover); }
.r90 { transform: rotate(-90deg); }
</style>
