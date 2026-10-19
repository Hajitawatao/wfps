<template>
  <div class="page">
    <div class="page-h">
      <div>
        <h1>{{ encoder ? user.office_name : (settings?.office_name || 'Region I Medical Center') }}</h1>
        <p>FY {{ fy }} Work and Financial Plan</p>
      </div>
      <div class="spacer"></div>
      <RouterLink v-if="canEncode" to="/encoding-sheet/new" class="btn primary"><Icon name="plus" />Encode an activity</RouterLink>
    </div>

    <div v-if="canEncode && settings && !+settings.encoding_open" class="note warn" style="margin-bottom:16px">
      Encoding for FY {{ fy }} is closed. Ask the Planning Office if you still need to add or change activities.
    </div>
    <div v-else-if="canEncode && settings?.encoding_deadline" class="note info" style="margin-bottom:16px">
      Submit your activities to the Planning Office by <b>{{ longDate(settings.encoding_deadline) }}</b>.
    </div>

    <!-- filters -->
    <div class="filters">
      <div v-if="!encoder" class="f-area">
        <Combobox v-model="f.office_id" :options="officeOptions()" placeholder="Overall (whole hospital)" clearable />
      </div>
      <div class="seg" role="group" aria-label="Which entries to count">
        <button v-for="c in COUNTS" :key="c.v" type="button" :class="{ on: f.count === c.v }" @click="f.count = c.v">{{ c.label }}</button>
      </div>
    </div>

    <div v-if="!s" class="loading">Loading…</div>
    <template v-else>
      <!-- the hospital's budget: every area's cost comes off this one total -->
      <BudgetStrip big :mooe-total="s.budget.mooe_total" :mooe-remaining="s.budget.mooe_remaining" :co-total="s.budget.co_total" :co-remaining="s.budget.co_remaining"
                   :note="`Whole hospital, in '000 PhP, from General Info. Remaining = total minus ${COUNTS.find(c => c.v === s.count)?.note}. A negative amount means more was encoded than the budget allows.`" />

      <!-- what the chosen area (or everyone) asked for -->
      <section class="panel asked">
        <div class="asked-h">
          <h2>{{ s.scope.name ? `Requested by ${s.scope.name}` : 'Requested by all areas' }}</h2>
          <span class="muted small">{{ +s.scope.n }} activit{{ +s.scope.n === 1 ? 'y' : 'ies' }}, in '000 PhP</span>
        </div>
        <div class="asked-b">
          <div>
            <span>MOOE</span><b>{{ num(s.scope.mooe) }}</b>
            <div class="bar" :title="pct(s.scope.mooe, s.budget.mooe_total) + ' of the hospital MOOE'"><i :style="{ width: barW(s.scope.mooe, s.budget.mooe_total) }"></i></div>
            <small>{{ pct(s.scope.mooe, s.budget.mooe_total) }} of the hospital MOOE</small>
          </div>
          <div>
            <span>Capital Outlay</span><b>{{ num(s.scope.co) }}</b>
            <div class="bar" :title="pct(s.scope.co, s.budget.co_total) + ' of the hospital CO'"><i :style="{ width: barW(s.scope.co, s.budget.co_total) }"></i></div>
            <small>{{ pct(s.scope.co, s.budget.co_total) }} of the hospital CO</small>
          </div>
          <div><span>Total</span><b>{{ num(s.scope.cost) }}</b><small>MOOE + CO, all sources of funds</small></div>
        </div>
      </section>

      <div class="grid g2">
        <section class="panel">
          <div class="panel-h"><h2>WFP Form 1 function summary</h2></div>
          <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>Type of function</th><th class="num">Number of activities</th><th class="num">Total cost ('000 PhP)</th></tr></thead>
            <tbody>
              <tr v-for="t in FUNCTION_TYPES" :key="t">
                <td>{{ t }}</td><td class="num">{{ fn(t).n }}</td><td class="num">{{ num(fn(t).cost) }}</td>
              </tr>
            </tbody>
            <tfoot><tr><td>Total</td><td class="num">{{ totalN }}</td><td class="num">{{ num(totalCost) }}</td></tr></tfoot>
          </table></div>
        </section>

        <section class="panel">
          <div class="panel-h"><h2>Entries by status</h2><p>All entries{{ s.scope.name ? ` of ${s.scope.name}` : '' }}, whatever is counted above.</p></div>
          <table class="tbl">
            <tbody>
              <tr v-for="w in ['draft', 'submitted', 'returned', 'approved']" :key="w" class="clickable"
                  @click="$router.push({ path: '/encoding-sheet', query: { workflow: w, ...(f.office_id ? { office_id: f.office_id } : {}) } })">
                <td><span class="status" :class="WORKFLOW[w].cls">{{ WORKFLOW[w].label }}</span></td>
                <td class="num">{{ wf(w) }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <section v-if="s.by_office.length && !f.office_id" class="panel">
        <div class="panel-h"><h2>Per area</h2><p>What each area asked for, in '000 PhP. Click an area to see only its figures.</p></div>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead>
              <tr><th>Area</th><th class="num">Activities</th><th class="num">Submitted</th><th class="num">Approved</th>
                <th class="num">MOOE</th><th class="num">Capital Outlay</th><th class="num">Total</th><th class="num">Share of MOOE</th></tr>
            </thead>
            <tbody>
              <tr v-for="o in s.by_office" :key="o.id" class="clickable" @click="f.office_id = o.id">
                <td>{{ o.label }}</td>
                <td class="num">{{ +o.n || '–' }}</td>
                <td class="num">{{ +o.submitted || '' }}</td>
                <td class="num">{{ +o.approved || '' }}</td>
                <td class="num">{{ +o.mooe ? num(o.mooe) : '' }}</td>
                <td class="num">{{ +o.co ? num(o.co) : '' }}</td>
                <td class="num">{{ +o.cost ? num(o.cost) : '' }}</td>
                <td class="num muted">{{ +o.mooe ? pct(o.mooe, s.budget.mooe_total) : '' }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr><td>All areas</td><td class="num">{{ sumBy('n') }}</td><td class="num">{{ sumBy('submitted') }}</td><td class="num">{{ sumBy('approved') }}</td>
                <td class="num">{{ num(sumBy('mooe')) }}</td><td class="num">{{ num(sumBy('co')) }}</td><td class="num">{{ num(sumBy('cost')) }}</td>
                <td class="num">{{ pct(sumBy('mooe'), s.budget.mooe_total) }}</td></tr>
            </tfoot>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
// Dashboard: the hospital's Total / Remaining MOOE and CO (rows 1-2 of the Encoding Sheet), what each area asked for,
// and the Summary sheet (count and cost per type of function). Filters: one area or overall; which entries are counted.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useAuth } from '../composables/useAuth'
import { useReference } from '../composables/useReference'
import { useToast } from '../composables/useToast'
import { num, longDate, WORKFLOW } from '../lib/format'
import { FUNCTION_TYPES, officeOptions } from '../lib/options'
import Icon from '../components/Icon.vue'
import Combobox from '../components/Combobox.vue'
import BudgetStrip from '../components/BudgetStrip.vue'

const COUNTS = [
  { v: 'all', label: 'All encoded', note: 'everything encoded (as in the Excel file)' },
  { v: 'final', label: 'Submitted + approved', note: 'submitted and approved entries' },
  { v: 'approved', label: 'Approved only', note: 'approved entries' }
]
const route = useRoute()
const router = useRouter()
const api = useApi()
const { user, canEncode } = useAuth()
const { fy, lists } = useReference()
const { toastError } = useToast()
const encoder = user.value.role === 'encoder'

const f = reactive({
  office_id: route.query.office_id ? Number(route.query.office_id) : null,
  count: COUNTS.some(c => c.v === route.query.count) ? route.query.count : 'all'
})
const s = ref(null)
const settings = computed(() => lists.value?.settings)
const fn = t => { const r = s.value.by_function.find(x => x.label === t); return { n: r ? +r.n : 0, cost: r ? +r.cost : 0 } }
const wf = w => +(s.value.by_workflow.find(x => x.label === w)?.n || 0)
const totalN = computed(() => FUNCTION_TYPES.reduce((a, t) => a + fn(t).n, 0))
const totalCost = computed(() => FUNCTION_TYPES.reduce((a, t) => a + fn(t).cost, 0))
const sumBy = k => s.value.by_office.reduce((t, o) => t + (+o[k] || 0), 0)
const pct = (part, whole) => (+whole ? (100 * part / whole).toFixed(1) : '0.0') + '%'
const barW = (part, whole) => Math.min(100, +whole ? 100 * part / whole : 0) + '%'

async function load() {
  try { s.value = await api.get('summary', { fy: fy.value, office_id: f.office_id, count: f.count }) } catch (e) { toastError(e) }
}
watch(() => ({ ...f }), () => {
  load()
  router.replace({ query: Object.fromEntries(Object.entries(f).filter(([k, v]) => v && !(k === 'count' && v === 'all'))) })
})
onMounted(load)
</script>

<style scoped>
/* Figma style: white cards with a thin border, navy headings, muted labels */
.filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
.f-area { flex: 0 1 380px; min-width: 240px; }
.asked { margin-bottom: 16px; }
.asked-h { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; padding: 18px 20px 0; }
.asked-b { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.asked-b > div { padding: 12px 20px 18px; display: flex; flex-direction: column; gap: 6px; }
.asked-b > div + div { border-left: 1px solid var(--line-2); }
.asked-b span { font-size: 12px; color: var(--ink-3); font-weight: 500; }
.asked-b b { font-size: 26px; font-weight: 700; color: var(--brand-ink); font-variant-numeric: tabular-nums; }
.asked-b small { font-size: 11px; color: var(--ink-3); }
.bar { height: 8px; border-radius: 3px; background: var(--surface-3); overflow: hidden; margin-top: 2px; }
.bar i { display: block; height: 100%; background: var(--brand); border-radius: 3px; }
@media (max-width: 760px) { .asked-b { grid-template-columns: 1fr; } .asked-b > div + div { border-left: 0; border-top: 1px solid var(--line-2); } }
</style>
