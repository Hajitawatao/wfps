<template>
  <div>
    <div class="page-h">
      <div>
        <h1>{{ review ? 'For review' : 'Encoding Sheet' }}</h1>
        <p v-if="review">Activities the offices have submitted. Open one to check it, or approve from the list.</p>
        <p v-else>{{ encoder ? user.office_name : 'All offices' }}, FY {{ fy }}. Click a row to open it.</p>
      </div>
      <div class="spacer"></div>
      <button v-if="!review && draftIds.length && canEncode" class="btn" @click="submitDrafts"><Icon name="send" />Submit {{ draftIds.length }} draft{{ draftIds.length === 1 ? '' : 's' }}</button>
      <RouterLink v-if="canEncode && !review" to="/encoding-sheet/new" class="btn primary"><Icon name="plus" />Encode an activity</RouterLink>
    </div>

    <BudgetStrip v-if="budget && !review" :mooe-total="budget.mooe_total" :mooe-remaining="budget.mooe_remaining"
                 :co-total="budget.co_total" :co-remaining="budget.co_remaining" />

    <div class="filters">
      <input v-model="f.q" type="search" placeholder="Search activity or responsible unit" class="f-search" />
      <div v-if="!encoder" class="f-office">
        <Combobox v-model="f.office_id" :options="officeOptions()" placeholder="All offices" clearable />
      </div>
      <select v-model="f.function_type" aria-label="Type of function">
        <option value="">All functions</option>
        <option v-for="t in FUNCTION_TYPES" :key="t">{{ t }}</option>
      </select>
      <select v-if="!review" v-model="f.workflow" aria-label="Status of the entry">
        <option value="">Any status</option>
        <option value="draft">Draft</option><option value="submitted">Submitted</option>
        <option value="returned">Returned</option><option value="approved">Approved</option>
      </select>
    </div>

    <div v-if="review && selected.length" class="note info row" style="margin-bottom:12px">
      {{ selected.length }} selected, {{ num(selectedTotal) }} ('000 PhP)
      <span class="spacer"></span>
      <button class="btn sm approve" @click="approveSelected"><Icon name="check" :size="14" />Approve selected</button>
    </div>

    <div class="panel">
      <div v-if="loading" class="loading">Loading…</div>
      <div v-else-if="!rows.length" class="empty">
        <b>{{ review ? 'Nothing waiting for review.' : 'No activities yet.' }}</b>
        <span v-if="!review && canEncode">Click "Encode an activity" to add the first row.</span>
      </div>
      <div v-else class="sheet-wrap">
        <table class="sheet">
          <thead>
            <tr class="letters">
              <th v-if="review"></th>
              <th v-if="showOffice"></th>
              <th v-for="c in COLS" :key="c">{{ c }}</th>
              <th></th>
            </tr>
            <tr>
              <th v-if="review"><input type="checkbox" :checked="selected.length === rows.length" @change="selected = $event.target.checked ? rows.map(r => r.id) : []" aria-label="Select all" /></th>
              <th v-if="showOffice">Office</th>
              <th>Type of function</th><th>Objectives</th><th>Success indicators and targets</th><th class="w-act">Activity</th>
              <th>Activity classification</th><th>Budget line item</th><th>SAA</th><th>Expense class</th>
              <th>Start date</th><th>End date</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Unit</th>
              <th class="num">Cost ('000 PhP)</th><th>Source of funds</th><th>Responsible unit</th><th>Status</th><th>Entry</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" @click="open(r, $event)">
              <td v-if="review" @click.stop><input type="checkbox" :value="r.id" v-model="selected" :aria-label="'Select ' + r.title" /></td>
              <td v-if="showOffice" class="clip"><div class="clamp">{{ r.office_name }}</div></td>
              <td>{{ r.function_type }}</td>
              <td class="clip"><div class="clamp">{{ r.objective }}</div></td>
              <td class="clip"><div class="clamp">{{ r.indicator }}</div></td>
              <td class="w-act"><b class="clamp">{{ r.title }}</b><div v-if="r.workflow === 'returned' && r.review_note" class="ret">Returned: {{ r.review_note }}</div></td>
              <td class="clip"><div class="clamp">{{ r.classification }}</div></td>
              <td class="clip"><div class="clamp">{{ r.line_item }}</div></td>
              <td>{{ +r.saa ? 'Yes' : 'No' }}</td>
              <td>{{ r.expense_class }}</td>
              <td class="nowrap">{{ shortDate(r.start_date) }}</td>
              <td class="nowrap">{{ shortDate(r.end_date) }}</td>
              <td>{{ r.target_q1 }}</td><td>{{ r.target_q2 }}</td><td>{{ r.target_q3 }}</td><td>{{ r.target_q4 }}</td>
              <td class="clip"><div class="clamp">{{ r.target_unit }}</div></td>
              <td class="num">{{ num(r.cost) }}</td>
              <td class="nowrap">{{ fundLabel(r.fund_source) }}</td>
              <td class="clip"><div class="clamp">{{ r.responsible }}</div></td>
              <td>{{ r.impl_status }}</td>
              <td class="nowrap">
                <template v-if="review">
                  <button class="btn sm approve" @click.stop="act(r, 'approve')">Approve</button>
                  <button class="btn sm warn" @click.stop="returnOne(r)">Return</button>
                </template>
                <span v-else class="status" :class="WORKFLOW[r.workflow].cls">{{ WORKFLOW[r.workflow].label }}</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td :colspan="16 + (review ? 1 : 0) + (showOffice ? 1 : 0)">Total, {{ rows.length }} activit{{ rows.length === 1 ? 'y' : 'ies' }}</td>
              <td class="num">{{ num(total) }}</td><td colspan="4"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
// The Encoding Sheet (columns A-S, one row per activity). With route meta.review it is the Planning Office's "For review" list.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useAuth } from '../composables/useAuth'
import { useReference } from '../composables/useReference'
import { useDialog } from '../composables/useDialog'
import { useToast } from '../composables/useToast'
import { num, shortDate, WORKFLOW } from '../lib/format'
import { FUNCTION_TYPES, officeOptions } from '../lib/options'
import Icon from '../components/Icon.vue'
import Combobox from '../components/Combobox.vue'
import BudgetStrip from '../components/BudgetStrip.vue'

const COLS = 'ABCDEFGHIJKLMNOPQRS'.split('')
const route = useRoute()
const router = useRouter()
const api = useApi()
const { user, canEncode } = useAuth()
const { fy, fundLabel } = useReference()
const { ask } = useDialog()
const { toast, toastError } = useToast()

const review = computed(() => !!route.meta.review)
const encoder = user.value.role === 'encoder'
const showOffice = !encoder
const f = reactive({
  q: route.query.q || '',
  office_id: route.query.office_id ? Number(route.query.office_id) : null,
  function_type: route.query.function_type || '',
  workflow: review.value ? 'submitted' : (route.query.workflow || '')
})
const rows = ref([])
const budget = ref(null)
const loading = ref(true)
const selected = ref([])

const total = computed(() => rows.value.reduce((s, r) => s + Number(r.cost), 0))
const selectedTotal = computed(() => rows.value.filter(r => selected.value.includes(r.id)).reduce((s, r) => s + Number(r.cost), 0))
const draftIds = computed(() => rows.value.filter(r => ['draft', 'returned'].includes(r.workflow) && r.can_edit).map(r => r.id))

async function load() {
  loading.value = true
  try {
    const params = { fy: fy.value, ...f }
    const [list, sum] = await Promise.all([api.get('activities', params), review.value ? null : api.get('summary', { fy: fy.value })])
    rows.value = list.data
    if (sum) budget.value = sum.budget
    selected.value = []
  } catch (e) { toastError(e) } finally { loading.value = false }
}

let timer
watch(() => ({ ...f }), () => {
  clearTimeout(timer)
  timer = setTimeout(load, 250)
  const q = Object.fromEntries(Object.entries(f).filter(([key, v]) => v !== '' && v !== null && !(review.value && key === 'workflow')))
  router.replace({ query: q })
})
onMounted(load)

function open(r, ev) {
  if (ev.target.closest('button, input, a')) return
  router.push(`/encoding-sheet/${r.id}`)
}

async function act(r, action, note = '') {
  try {
    await api.post('activity-status', { action, note }, { id: r.id })
    toast(action === 'approve' ? 'Approved.' : 'Returned to the office.')
    load()
  } catch (e) { toastError(e) }
}
async function returnOne(r) {
  const note = await ask({ title: `Return to ${r.office_name}`, message: r.title, confirmText: 'Return', tone: 'warn', input: { label: 'What should the office fix?', multiline: true } })
  if (note !== null) act(r, 'return', note)
}
async function approveSelected() {
  if (!(await ask({ title: `Approve ${selected.value.length} activities?`, confirmText: 'Approve', tone: 'approve' }))) return
  let ok = 0
  for (const id of selected.value) {
    try { await api.post('activity-status', { action: 'approve' }, { id }); ok++ } catch (e) { toastError(e) }
  }
  toast(`${ok} approved.`)
  load()
}
async function submitDrafts() {
  const n = draftIds.value.length
  if (!(await ask({ title: `Submit ${n} activit${n === 1 ? 'y' : 'ies'} to the Planning Office?`, message: 'These are the draft and returned rows listed here. After submitting, your office can no longer change them unless Planning returns them.', confirmText: 'Submit' }))) return
  try {
    const r = await api.post('submit', { ids: draftIds.value })
    toast(`${r.submitted} submitted to the Planning Office.`)
    load()
  } catch (e) { toastError(e) }
}
</script>

<style scoped>
.filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.filters > * { width: auto; }
.f-search { flex: 1 1 260px; }
.f-office { flex: 0 1 320px; min-width: 220px; }
.filters select { flex: 0 0 auto; min-width: 150px; }

.sheet-wrap { overflow: auto; max-height: calc(100vh - 300px); min-height: 200px; }
table.sheet { border-collapse: separate; border-spacing: 0; font-size: 13px; min-width: 2300px; }
.sheet th, .sheet td { border-right: 1px solid var(--line-2); border-bottom: 1px solid var(--line-2); padding: 7px 9px; vertical-align: top; text-align: left; }
.sheet thead th { position: sticky; background: var(--surface-2); font-weight: 600; color: var(--ink-2); z-index: 2; }
.sheet thead tr:first-child th { top: 0; height: 26px; padding: 3px 9px; text-align: center; font-size: 11.5px; color: var(--ink-3); background: var(--surface-3); }
.sheet thead tr:nth-child(2) th { top: 26px; border-bottom: 1px solid var(--line); }
.sheet tbody tr { cursor: pointer; }
.sheet tbody tr:hover td { background: var(--hover); }
.sheet .clip { max-width: 240px; min-width: 140px; }
.sheet .clamp { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
.sheet .w-act { min-width: 260px; max-width: 320px; }
.sheet .nowrap { white-space: nowrap; }
.sheet .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.sheet tfoot td { position: sticky; bottom: 0; background: var(--surface-2); font-weight: 700; border-top: 1px solid var(--line); }
.ret { color: var(--sun); font-size: 12px; margin-top: 3px; }
</style>
