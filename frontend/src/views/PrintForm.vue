<template>
  <div>
    <!-- controls (screen only) -->
    <div class="no-print">
      <div class="page-h">
        <div>
          <h1>Print ({{ form === 'INCOME' ? 'Income' : 'GAA' }})</h1>
          <p>WFP Form 1, same layout as the {{ form === 'INCOME' ? 'Print (Income)' : 'Print (GAA)' }} sheet. A4 landscape, cost in '000 PhP.</p>
        </div>
        <div class="spacer"></div>
        <div class="seg">
          <button :class="{ on: form === 'GAA' }" @click="$router.replace('/print/gaa')">GAA</button>
          <button :class="{ on: form === 'INCOME' }" @click="$router.replace('/print/income')">Income</button>
        </div>
        <button class="btn primary" @click="print" :disabled="!r"><Icon name="print" />Print or save as PDF</button>
      </div>
      <div class="filters">
        <div v-if="!encoder" class="f-office">
          <Combobox v-model="f.office_id" :options="officeOptions()" placeholder="Whole hospital" clearable />
        </div>
        <select v-model="f.workflow" aria-label="Which entries to include">
          <option value="">All encoded entries</option>
          <option value="final">Submitted and approved</option>
          <option value="approved">Approved only (copy for signing)</option>
        </select>
      </div>
      <div v-if="r && f.workflow !== 'approved' && hasUnapproved" class="note warn" style="margin-bottom:14px">
        This includes entries that are not approved yet. Choose "Approved only" before printing the copy for signing.
      </div>
    </div>

    <div v-if="loading" class="loading">Loading…</div>

    <!-- ================= THE FORM ================= -->
    <div v-else-if="r" class="sheet">
      <div v-if="f.workflow !== 'approved' && hasUnapproved" class="draft-mark">DRAFT: not all entries are approved</div>
      <!-- header: seal | centered titles | document code. The side columns are equal so the titles sit in the true center. -->
      <div class="hdr">
        <div class="hdr-l"><img class="seal" alt="DOH seal" src="/doh-seal.png" /></div>
        <div class="hdr-c">
          <div>Republic of the Philippines</div>
          <div class="doh">Department of Health</div>
          <div class="title">WFP Form 1. Work and Financial Plan Matrix{{ form === 'INCOME' ? ' (Income)' : '' }}</div>
        </div>
        <div class="hdr-r"><div class="doc-code">Document Code:<b>{{ r.settings?.document_code || '' }}</b></div></div>
      </div>

      <table class="meta">
        <tr><td class="lbl">Department:</td><td colspan="3">Department of Health</td></tr>
        <tr><td class="lbl">Central Office / Bureau / Office / CHD / Hospitals / Sanitaria / DA-TRC / Other Health Facilities:</td>
            <td colspan="3"><b>{{ r.settings?.office_name }}</b><span v-if="r.office"> — {{ r.office }}</span></td></tr>
        <tr><td class="lbl">Calendar Year:</td><td colspan="3">{{ r.fiscal_year }}</td></tr>
      </table>

      <table class="sign">
        <tr>
          <td class="lbl">Prepared by:</td><td class="nm"><b>{{ r.settings?.prepared_by }}</b><div>{{ r.settings?.prepared_pos }}</div></td>
          <td class="lbl">Recommending Approval by:</td><td class="nm"><b>{{ r.settings?.recommending_by }}</b><div>{{ r.settings?.recommending_pos }}</div></td>
          <td class="lbl">Approved by:</td><td class="nm"><b>{{ r.settings?.approved_by }}</b><div>{{ r.settings?.approved_pos }}</div></td>
        </tr>
        <tr><td class="lbl">Date:</td><td></td><td class="lbl">Date:</td><td></td><td class="lbl">Date:</td><td></td></tr>
      </table>

      <table class="wfp">
        <colgroup>
          <col style="width:10%"><col style="width:13%"><col style="width:18%">
          <col style="width:6%"><col style="width:6%">
          <col style="width:5.5%"><col style="width:5.5%"><col style="width:5.5%"><col style="width:5.5%">
          <col style="width:7.5%"><col style="width:6%"><col style="width:11%">
        </colgroup>
        <thead>
          <tr>
            <th rowspan="2">BUDGET LINE ITEM</th>
            <th rowspan="2">OBJECTIVE</th>
            <th rowspan="2">ACTIVITIES TO ATTAIN THE SUCCESS INDICATORS</th>
            <th colspan="2">TIMEFRAME</th>
            <th colspan="4">TARGETS</th>
            <th colspan="2">RESOURCE REQUIREMENTS</th>
            <th rowspan="2">RESPONSIBLE PERSON</th>
          </tr>
          <tr>
            <th>Start (Month)</th><th>End (Month)</th>
            <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th>
            <th>COST<br><span class="u">('000 PhP)</span></th><th>SOURCE OF FUND</th>
          </tr>
        </thead>
        <tbody v-if="!r.groups.length">
          <tr><td colspan="12" class="c" style="padding:24px">No activities to show for this selection.</td></tr>
        </tbody>
        <tbody v-for="g in r.groups" :key="g.line_item">
          <tr class="li"><td colspan="12">{{ g.line_item }}</td></tr>
          <template v-for="(fn, fi) in g.functions" :key="fn.function_type">
            <tr class="fn"><td colspan="12">{{ 'ABC'[fi] }}. {{ fn.function_type }} Function <span class="paren">({{ g.line_item }})</span></td></tr>
            <tr v-for="row in fn.rows" :key="row.id" class="act" :class="{ unap: row.workflow !== 'approved' }">
              <td>{{ g.line_item }}</td>
              <td>{{ row.objective }}</td>
              <td>{{ row.title }}</td>
              <td class="c">{{ wfpDate(row.start_date) }}</td>
              <td class="c">{{ wfpDate(row.end_date) }}</td>
              <td class="c">{{ target(row.q1, row.unit) }}</td>
              <td class="c">{{ target(row.q2, row.unit) }}</td>
              <td class="c">{{ target(row.q3, row.unit) }}</td>
              <td class="c">{{ target(row.q4, row.unit) }}</td>
              <td class="n">{{ num(row.cost) }}</td>
              <td class="c">{{ fundLabel(row.fund_source) }}</td>
              <td>{{ row.responsible }}</td>
            </tr>
            <tr class="sub"><td colspan="9" class="r">Sub-total {{ fn.function_type }} ({{ g.line_item }})</td><td class="n">{{ num(fn.subtotal) }}</td><td colspan="2"></td></tr>
          </template>
          <tr class="tot"><td colspan="9" class="r">Total Cost (Strategic + Core + Support) Functions ({{ g.line_item }})</td><td class="n">{{ num(g.total) }}</td><td colspan="2"></td></tr>
        </tbody>
        <tbody v-if="r.groups.length">
          <tr class="grand"><td colspan="9" class="r">Grand total, all budget line items</td><td class="n">{{ num(r.grand_total) }}</td><td colspan="2"></td></tr>
        </tbody>
      </table>
      <div class="foot">Printed from the R1MC WFP system on {{ today }}, {{ r.count }} activities.</div>
    </div>
  </div>
</template>

<script setup>
// WFP Form 1, laid out like the Print (GAA) sheet (all sources except Income) and the Print (Income) sheet.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useAuth } from '../composables/useAuth'
import { useReference } from '../composables/useReference'
import { useToast } from '../composables/useToast'
import { num, target, wfpDate } from '../lib/format'
import { officeOptions } from '../lib/options'
import Icon from '../components/Icon.vue'
import Combobox from '../components/Combobox.vue'

const route = useRoute()
const api = useApi()
const { user } = useAuth()
const { fy, fundLabel } = useReference()
const { toastError } = useToast()
const encoder = user.value.role === 'encoder'

const form = computed(() => route.params.form === 'income' ? 'INCOME' : 'GAA')
const f = reactive({ office_id: null, workflow: '' })
const r = ref(null)
const loading = ref(true)
const today = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
const hasUnapproved = computed(() => r.value?.groups.some(g => g.functions.some(fn => fn.rows.some(x => x.workflow !== 'approved'))))

async function load() {
  loading.value = true
  try { r.value = await api.get('wfp1', { fy: fy.value, form: form.value, workflow: f.workflow, office_id: f.office_id }) }
  catch (e) { toastError(e) }
  finally { loading.value = false }
}
watch(() => ({ ...f }), load)
onMounted(load)

function print() { window.print() }
</script>

<style scoped>
.filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.filters select { width: auto; min-width: 220px; }
.f-office { flex: 0 1 340px; min-width: 240px; }
.sheet { background: #fff; padding: 18px; border: 1px solid var(--line); border-radius: var(--r-md);
  font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; overflow-x: auto; position: relative; }
.sheet table { border-collapse: collapse; width: 100%; }
.hdr { display: grid; grid-template-columns: 1fr minmax(0, 2.2fr) 1fr; align-items: center; gap: 12px; }
.hdr .seal { width: 64px; height: 64px; display: block; }
.hdr-c { text-align: center; font-size: 11px; line-height: 1.35; }
.hdr-c .doh { font-weight: 700; font-size: 12px; }
.hdr-c .title { font-weight: 700; font-size: 13px; margin-top: 8px; }
.hdr-r { display: flex; justify-content: flex-end; }
.doc-code { border: 1px solid #000; padding: 4px 8px; font-size: 10px; min-height: 38px; width: 100%; max-width: 240px; display: grid; gap: 2px; }
.c { text-align: center; }
.r { text-align: right; }
.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.meta { margin-top: 10px; }
.meta td { padding: 2px 4px; }
.meta .lbl { width: 32%; }
.sign { margin-top: 8px; }
.sign td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
.sign .lbl { width: 9%; white-space: nowrap; }
.sign .nm { width: 24%; text-align: center; }
.sign .nm div { font-size: 9.5px; }
.wfp { margin-top: 10px; table-layout: fixed; min-width: 1000px; }
.wfp th, .wfp td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; word-wrap: break-word; }
.wfp th { background: #d9ead3; font-weight: 700; text-align: center; vertical-align: middle; font-size: 9.5px; }
.wfp th .u { font-weight: 400; }
.wfp tr.li td { background: #b6d7a8; font-weight: 700; font-size: 10.5px; }
.wfp tr.fn td { background: #eef5ea; font-weight: 700; }
.wfp tr.fn .paren { font-weight: 400; }
.wfp tr.sub td { font-style: italic; background: #fafafa; }
.wfp tr.tot td { font-weight: 700; background: #f1f1f1; }
.wfp tr.grand td { font-weight: 700; font-size: 11px; background: #d9ead3; }
.wfp tr.act.unap td:first-child { box-shadow: inset 3px 0 0 #e0a800; }
.foot { margin-top: 8px; font-size: 9px; color: #555; }
.draft-mark { position: absolute; top: 96px; right: 18px; font-weight: 700; color: #b42318; border: 2px solid #b42318; padding: 2px 8px; font-size: 11px; transform: rotate(-3deg); }

@media print {
  .sheet { border: 0; box-shadow: none; padding: 0; overflow: visible; }
  .wfp { min-width: 0; }
  .wfp tr { page-break-inside: avoid; }
  .wfp thead { display: table-header-group; }
  .wfp tr.act.unap td:first-child { box-shadow: none; }
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<style>
@media print { @page { size: A4 landscape; margin: 9mm; } }
</style>
