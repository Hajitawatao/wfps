<template>
  <div class="page">
    <div class="page-h">
      <div>
        <h1>Budget per area</h1>
        <p>FY {{ fy }}. Each area's MOOE and Capital Outlay, given out of the hospital total in General Info. In '000 PhP.</p>
      </div>
    </div>

    <div v-if="!d" class="loading">Loading…</div>
    <template v-else>
      <section class="panel totals">
        <div><span>Hospital MOOE</span><b>{{ num(d.total.mooe) }}</b><small>Given to areas: {{ num(d.given.mooe) }}</small></div>
        <div><span>MOOE not yet given</span><b :class="{ neg: d.left.mooe < 0 }">{{ num(d.left.mooe) }}</b></div>
        <div><span>Hospital Capital Outlay</span><b>{{ num(d.total.co) }}</b><small>Given to areas: {{ num(d.given.co) }}</small></div>
        <div><span>Capital Outlay not yet given</span><b :class="{ neg: d.left.co < 0 }">{{ num(d.left.co) }}</b></div>
      </section>
      <p v-if="!+d.total.mooe && !+d.total.co" class="note warn" style="margin-bottom:16px">
        The hospital's Total MOOE and Capital Outlay are still 0. The Planning Office enters them in General Info first.
      </p>

      <section class="panel">
        <div class="panel-h">
          <h2>Areas</h2>
          <p>{{ canAllocate ? 'Type the budget and press Enter or leave the box to save. Encoded = everything the area has encoded, as in the Encoding Sheet.' : 'Set by Procurement. Encoded = everything the area has encoded.' }}</p>
        </div>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead>
              <tr><th rowspan="2">Area</th><th colspan="3" class="grp">MOOE</th><th colspan="3" class="grp">Capital Outlay</th></tr>
              <tr><th class="num">Budget</th><th class="num">Encoded</th><th class="num">Remaining</th><th class="num">Budget</th><th class="num">Encoded</th><th class="num">Remaining</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in d.data" :key="r.id">
                <td>{{ r.name }}<span v-if="!+r.is_set" class="muted small"> · not set</span></td>
                <td class="num">
                  <input v-if="canAllocate" v-model="r.mooe" type="number" min="0" step="0.01" inputmode="decimal" class="amt"
                         :aria-label="`MOOE budget of ${r.name}`" @change="save(r)" @keydown.enter="$event.target.blur()" />
                  <template v-else>{{ num(r.mooe) }}</template>
                </td>
                <td class="num muted">{{ num(r.used_mooe) }}</td>
                <td class="num" :class="{ neg: r.mooe - r.used_mooe < 0 }">{{ num(r.mooe - r.used_mooe) }}</td>
                <td class="num">
                  <input v-if="canAllocate" v-model="r.co" type="number" min="0" step="0.01" inputmode="decimal" class="amt"
                         :aria-label="`Capital Outlay budget of ${r.name}`" @change="save(r)" @keydown.enter="$event.target.blur()" />
                  <template v-else>{{ num(r.co) }}</template>
                </td>
                <td class="num muted">{{ num(r.used_co) }}</td>
                <td class="num" :class="{ neg: r.co - r.used_co < 0 }">{{ num(r.co - r.used_co) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr><td>All areas</td>
                <td class="num">{{ num(d.given.mooe) }}</td><td class="num">{{ num(sum('used_mooe')) }}</td><td class="num">{{ num(d.given.mooe - sum('used_mooe')) }}</td>
                <td class="num">{{ num(d.given.co) }}</td><td class="num">{{ num(sum('used_co')) }}</td><td class="num">{{ num(d.given.co - sum('used_co')) }}</td></tr>
            </tfoot>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
// Budget per area (Procurement): the MOOE and CO each area may plan for, out of the hospital total.
// The server refuses a budget that would make all areas together exceed the hospital total.
import { onMounted, ref } from 'vue'
import { useApi } from '../composables/useApi'
import { useAuth } from '../composables/useAuth'
import { useReference } from '../composables/useReference'
import { useToast } from '../composables/useToast'
import { num } from '../lib/format'

const api = useApi()
const { canAllocate } = useAuth()
const { fy } = useReference()
const { toast, toastError } = useToast()
const d = ref(null)
const sum = k => d.value.data.reduce((t, r) => t + (+r[k] || 0), 0)

async function load() {
  try { d.value = await api.get('area-budgets', { fy: fy.value }) } catch (e) { toastError(e) }
}
async function save(r) {
  try {
    d.value = await api.put('area-budgets', { mooe: r.mooe, co: r.co }, { fy: fy.value, office_id: r.id })
    toast(`Budget of ${r.name} saved.`)
  } catch (e) { toastError(e); load() }
}
onMounted(load)
</script>

<style scoped>
.totals { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 16px; }
.totals > div { padding: 16px 18px; display: grid; gap: 6px; }
.totals > div + div { border-left: 1px solid var(--line-2); }
.totals span { font-size: 12px; color: var(--ink-3); font-weight: 500; }
.totals b { font-size: 22px; font-weight: 700; color: var(--brand-ink); font-variant-numeric: tabular-nums; }
.totals small { font-size: 11px; color: var(--ink-3); }
.neg, .totals b.neg { color: var(--signal); }
th.grp { text-align: center; border-bottom: 1px solid var(--line-2); }
.tbl td { vertical-align: middle; }
.amt { width: 130px; text-align: right; min-height: 32px; padding: 4px 8px; }
@media (max-width: 860px) { .totals { grid-template-columns: repeat(2, minmax(0, 1fr)); } .totals > div:nth-child(3) { border-left: 0; } }
</style>
