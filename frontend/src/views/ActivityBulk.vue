<template>
  <div class="page">
    <div v-if="loading" class="loading">Loading…</div>
    <form v-else @submit.prevent="save(false)" novalidate>
      <div class="page-h">
        <div>
          <RouterLink to="/encoding-sheet" class="back"><Icon name="back" :size="16" />Encoding Sheet</RouterLink>
          <h1>Encode many activities</h1>
          <p>{{ officeName }}, FY {{ fy }}. For a list such as the items of your PPMP: fill the shared columns once, then one row per activity.</p>
        </div>
      </div>

      <BudgetStrip v-if="budget" :mooe-total="budget.mooe_total" :mooe-remaining="after.mooe" :co-total="budget.co_total" :co-remaining="after.co"
                   note="In '000 PhP, whole hospital, including these rows." />
      <div v-if="after.mooe < 0 || after.co < 0" class="note err" style="margin-bottom:16px">
        You have entered more than the allocated budget. Reduce or realign the cost to stay within the {{ after.mooe < 0 ? 'MOOE' : 'CO' }} limit.
      </div>
      <template v-if="areaBudget">
        <BudgetStrip area :mooe-total="areaBudget.mooe_total" :mooe-remaining="areaAfter.mooe" :co-total="areaBudget.co_total" :co-remaining="areaAfter.co"
                     :note="`In '000 PhP, budget of ${officeName}, including these rows.`" />
        <div v-if="areaAfter.mooe < 0 || areaAfter.co < 0" class="note warn" style="margin-bottom:16px">
          This is more than the {{ areaAfter.mooe < 0 ? 'MOOE' : 'Capital Outlay' }} budget of {{ officeName }}. You can still save; the Planning Office will see that the area is over its budget.
        </div>
      </template>

      <div v-if="closedForMe" class="note warn" style="margin-bottom:16px">
        Encoding for FY {{ fy }} is closed. Ask the Planning Office if you still need to add activities.
      </div>
      <div v-if="formError" class="note err" style="margin-bottom:16px">{{ formError }}</div>

      <fieldset :disabled="closedForMe">
        <section class="panel">
          <div class="panel-h"><h2>Same for every row</h2></div>
          <div class="panel-b grid common">
            <Field v-if="isReviewer" label="Office" class="span-all" :error="ce.office_id">
              <Combobox v-model="c.office_id" :options="officeOptions()" placeholder="Which office is this for?" :invalid="!!ce.office_id" />
            </Field>

            <Field class="span-all" :error="ce.function_type">
              <span class="lbl"><i class="col">A</i>Type of function</span>
              <div class="seg">
                <button type="button" v-for="t in FUNCTION_TYPES" :key="t" :class="{ on: c.function_type === t }" @click="setFunction(t)">{{ t }}</button>
              </div>
            </Field>

            <Field class="span-all" :error="ce.objective_id">
              <span class="lbl"><i class="col">B</i>Objectives</span>
              <Combobox v-model="c.objective_id" :options="objectiveOptions(c.function_type)" :disabled="!c.function_type"
                        :placeholder="c.function_type ? 'Choose the objective' : 'Choose the type of function first'" :invalid="!!ce.objective_id"
                        @change="c.indicator_id = null" />
            </Field>
            <Field v-if="isOthers(objectiveOptions(c.function_type), c.objective_id)" class="span-all" :error="ce.objective_other">
              <span class="lbl">Type the objective</span>
              <textarea v-model="c.objective_other" rows="2" maxlength="600" placeholder="Printed instead of “Others, please insert note/remarks”"></textarea>
            </Field>

            <Field class="span-all" :error="ce.indicator_id">
              <span class="lbl"><i class="col">C</i>Success indicators and targets</span>
              <Combobox v-model="c.indicator_id" :options="indicatorOptions(c.objective_id)" :disabled="!c.objective_id"
                        :placeholder="c.objective_id ? 'Choose the success indicator' : 'Choose the objective first'" :invalid="!!ce.indicator_id" />
            </Field>
            <Field v-if="isOthers(indicatorOptions(c.objective_id), c.indicator_id)" class="span-all" :error="ce.indicator_other">
              <span class="lbl">Type the success indicator</span>
              <textarea v-model="c.indicator_other" rows="2" maxlength="600" placeholder="Printed instead of “Others, please insert note/remarks”"></textarea>
            </Field>

            <Field :error="ce.classification_id">
              <span class="lbl"><i class="col">E</i>Activity classification</span>
              <Combobox v-model="c.classification_id" :options="classificationOptions()" placeholder="Choose the classification" :invalid="!!ce.classification_id" />
            </Field>
            <Field :error="ce.line_item_id">
              <span class="lbl"><i class="col">F</i>Budget line item</span>
              <Combobox v-model="c.line_item_id" :options="lineItemOptions()" placeholder="Choose the budget line item" :invalid="!!ce.line_item_id" />
            </Field>

            <Field :error="ce.saa">
              <span class="lbl"><i class="col">G</i>SAA</span>
              <span class="help">Is this for sub-allotment to other office?</span>
              <div class="seg">
                <button type="button" :class="{ on: !c.saa }" @click="c.saa = 0">No</button>
                <button type="button" :class="{ on: !!c.saa }" @click="c.saa = 1">Yes</button>
              </div>
            </Field>
            <Field :error="ce.expense_class">
              <span class="lbl"><i class="col">H</i>Expense class</span>
              <span class="help align-only">&nbsp;</span>
              <div class="seg">
                <button type="button" v-for="x in EXPENSE_CLASSES" :key="x" :class="{ on: c.expense_class === x }" @click="c.expense_class = x">{{ x }}</button>
              </div>
            </Field>

            <Field :error="ce.fund_source_id">
              <span class="lbl"><i class="col">Q</i>Source of funds</span>
              <select v-model="c.fund_source_id">
                <option :value="null" disabled>Choose</option>
                <option v-for="f in fundOptions()" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </Field>
            <Field :error="ce.responsible">
              <span class="lbl"><i class="col">R</i>Responsible unit</span>
              <input v-model="c.responsible" />
            </Field>
            <Field :error="ce.impl_status">
              <span class="lbl"><i class="col">S</i>Status</span>
              <select v-model="c.impl_status">
                <option v-for="st in STATUSES" :key="st">{{ st }}</option>
              </select>
            </Field>
          </div>
        </section>

        <section class="panel">
          <div class="panel-h rows-h">
            <div>
              <h2>Rows <span class="muted">({{ filled.length }})</span></h2>
              <p>Copy the cells from Excel in this order and paste them into any cell: D Activity, I Start, J End, K–N Q1–Q4, O Unit, P Cost.</p>
            </div>
            <button type="button" class="btn sm" @click="pasteDialog">Paste from Excel</button>
          </div>
          <div class="tbl-wrap">
            <table class="tbl rows">
              <thead>
                <tr>
                  <th class="n">#</th>
                  <th class="d"><i class="col">D</i>Activity</th>
                  <th class="dt"><i class="col">I</i>Start date</th>
                  <th class="dt"><i class="col">J</i>End date</th>
                  <th v-for="(qc, i) in ['K', 'L', 'M', 'N']" :key="qc" class="q"><i class="col">{{ qc }}</i>Q{{ i + 1 }}</th>
                  <th class="u"><i class="col">O</i>Unit</th>
                  <th class="p"><i class="col">P</i>Cost ('000)</th>
                  <th class="x"><span class="sr-only">Remove</span></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(r, i) in rows" :key="r.key">
                  <tr :class="{ bad: rowErr(i) }" @paste="onPaste($event, i)">
                    <td class="n muted">{{ i + 1 }}</td>
                    <td><textarea v-model="r.title" rows="1" maxlength="600" :class="{ err: rowErr(i)?.title }" :aria-label="`Activity, row ${i + 1}`" data-col="0"></textarea></td>
                    <td><input type="date" v-model="r.start_date" :min="`${fy}-01-01`" :max="`${fy}-12-31`" :class="{ err: rowErr(i)?.start_date }" :aria-label="`Start date, row ${i + 1}`" data-col="1" /></td>
                    <td><input type="date" v-model="r.end_date" :min="r.start_date || `${fy}-01-01`" :max="`${fy}-12-31`" :class="{ err: rowErr(i)?.end_date }" :aria-label="`End date, row ${i + 1}`" data-col="2" /></td>
                    <td v-for="q in 4" :key="q"><input v-model="r['target_q' + q]" :class="{ err: rowErr(i)?.targets }" :aria-label="`Q${q}, row ${i + 1}`" :data-col="2 + q" /></td>
                    <td><input v-model="r.target_unit" :class="{ err: rowErr(i)?.target_unit }" :aria-label="`Unit, row ${i + 1}`" data-col="7" /></td>
                    <td><input type="number" v-model="r.cost" min="0" step="0.01" inputmode="decimal" class="num-in" :class="{ err: rowErr(i)?.cost }" :aria-label="`Cost, row ${i + 1}`" data-col="8" /></td>
                    <td><button type="button" class="btn sm ghost" :aria-label="`Remove row ${i + 1}`" @click="removeRow(i)"><Icon name="trash" :size="14" /></button></td>
                  </tr>
                  <tr v-if="rowErr(i)" class="err-row"><td></td><td colspan="9">{{ rowMsg(i) }}</td></tr>
                </template>
              </tbody>
              <tfoot>
                <tr><td></td><td colspan="7">Total ({{ c.expense_class }})</td><td class="num">{{ num(totalCost) }}</td><td></td></tr>
              </tfoot>
            </table>
          </div>
          <div class="panel-f left">
            <button type="button" class="btn sm" @click="addRow()"><Icon name="plus" :size="14" />Add row</button>
          </div>
        </section>
      </fieldset>

      <div class="actions sticky">
        <span class="muted small">{{ filled.length }} row{{ filled.length === 1 ? '' : 's' }}, {{ num(totalCost) }} ('000 PhP). Empty rows are skipped.</span>
        <span class="spacer"></span>
        <button class="btn" :disabled="busy || closedForMe || !filled.length">{{ busy ? 'Saving…' : 'Save all as draft' }}</button>
        <button type="button" class="btn primary" :disabled="busy || closedForMe || !filled.length" @click="save(true)"><Icon name="send" />Save all and submit</button>
      </div>
    </form>
  </div>
</template>

<script setup>
// Encodes many rows of the Encoding Sheet at once (for example the items of a PPMP).
// Columns A-C, E-H and Q-S are the same for every row; each row has D, I-J, K-N, O and P.
// The server saves all rows or none (backend/api/activities-bulk.php).
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useAuth } from '../composables/useAuth'
import { useReference } from '../composables/useReference'
import { useDialog } from '../composables/useDialog'
import { useToast } from '../composables/useToast'
import { num } from '../lib/format'
import { canEdit } from '../lib/gate'
import { FUNCTION_TYPES, EXPENSE_CLASSES, STATUSES, officeOptions, objectiveOptions, indicatorOptions,
  classificationOptions, lineItemOptions, fundOptions } from '../lib/options'
import Icon from '../components/Icon.vue'
import Field from '../components/Field.vue'
import Combobox from '../components/Combobox.vue'
import BudgetStrip from '../components/BudgetStrip.vue'

const router = useRouter()
const api = useApi()
const { user, isReviewer } = useAuth()
const { fy, lists } = useReference()
const { ask } = useDialog()
const { toast } = useToast()

const ROW_COLS = ['title', 'start_date', 'end_date', 'target_q1', 'target_q2', 'target_q3', 'target_q4', 'target_unit', 'cost']
const isOthers = (opts, id) => /^others/i.test(opts.find(o => o.value === id)?.label || '')

const loading = ref(true)
const busy = ref(false)
const formError = ref('')
const ce = ref({})       // errors of the shared columns
const rowErrors = ref({}) // { rowIndex: { field: message } }
const budget = ref(null)
const areaBudget = ref(null)

const c = ref({
  office_id: user.value.office_id,
  function_type: null, objective_id: null, indicator_id: null, objective_other: '', indicator_other: '',
  classification_id: null, line_item_id: null, saa: 0, expense_class: 'MOOE',
  fund_source_id: null, responsible: user.value.office_code || '', impl_status: 'Ongoing'
})
let nextKey = 0
const blankRow = () => ({ key: nextKey++, title: '', start_date: `${fy.value}-01-01`, end_date: `${fy.value}-12-31`,
  target_q1: '', target_q2: '', target_q3: '', target_q4: '', target_unit: '', cost: '' })
const rows = ref([blankRow(), blankRow(), blankRow()])
// a row counts once something other than the default dates is typed in it
const isFilled = r => ROW_COLS.some(k => !['start_date', 'end_date'].includes(k) && String(r[k] ?? '').trim() !== '')
const filled = computed(() => rows.value.filter(isFilled))
const totalCost = computed(() => filled.value.reduce((t, r) => t + (Number(r.cost) || 0), 0))

const closedForMe = computed(() => !canEdit(user.value, { workflow: 'draft', office_id: c.value.office_id }, +lists.value?.settings?.encoding_open))
const officeName = computed(() => lists.value?.offices?.find(o => o.id === c.value.office_id)?.name || user.value.office_name)

const minus = (b, total) => b ? { mooe: b.mooe_remaining - (c.value.expense_class === 'MOOE' ? total : 0), co: b.co_remaining - (c.value.expense_class === 'CO' ? total : 0) } : { mooe: 0, co: 0 }
const after = computed(() => minus(budget.value, totalCost.value))
const areaAfter = computed(() => minus(areaBudget.value, totalCost.value))

async function loadAreaBudget() {
  try { areaBudget.value = (await api.get('summary', { fy: fy.value, office_id: c.value.office_id })).area_budget } catch { areaBudget.value = null }
}
watch(() => c.value.office_id, () => { if (!loading.value) loadAreaBudget() })

function setFunction(t) {
  if (c.value.function_type === t) return
  c.value.function_type = t
  c.value.objective_id = null
  c.value.indicator_id = null
}
function addRow(r = blankRow()) { rows.value.push(r) }
function removeRow(i) {
  rows.value.splice(i, 1)
  rowErrors.value = {}
  if (!rows.value.length) addRow()
}

// errors come back by position among the filled rows
const filledIndex = computed(() => { const m = {}; let n = 0; rows.value.forEach((r, i) => { if (isFilled(r)) m[i] = n++ }); return m })
const rowErr = i => filledIndex.value[i] !== undefined ? rowErrors.value[filledIndex.value[i]] : null
const rowMsg = i => Object.values(rowErr(i) || {}).join(' ')

// Excel copies cells as tab-separated lines; a cell with a line break is wrapped in quotes
function parseTsv(text) {
  const out = []; let row = []; let cell = ''; let q = false
  text = text.replace(/\r\n?/g, '\n')
  for (let i = 0; i < text.length; i++) {
    const ch = text[i]
    if (q) {
      if (ch === '"' && text[i + 1] === '"') { cell += '"'; i++ }
      else if (ch === '"') q = false
      else cell += ch
    } else if (ch === '"' && cell === '') q = true
    else if (ch === '\t') { row.push(cell); cell = '' }
    else if (ch === '\n') { row.push(cell); out.push(row); row = []; cell = '' }
    else cell += ch
  }
  if (cell !== '' || row.length) { row.push(cell); out.push(row) }
  return out.filter(r => r.some(v => v.trim() !== ''))
}
const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']
const pad = n => String(n).padStart(2, '0')
// 2027-01-15, 1/15/2027, January 15, 2027, 15-Jan-27, or an Excel date number
function toDate(v) {
  v = String(v).trim()
  if (!v) return ''
  let m
  if ((m = v.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/))) return `${m[1]}-${pad(m[2])}-${pad(m[3])}`
  if ((m = v.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$/))) return `${m[3].length === 2 ? '20' + m[3] : m[3]}-${pad(m[1])}-${pad(m[2])}`
  if ((m = v.match(/^([a-z]{3})[a-z]*\.?\s+(\d{1,2}),?\s+(\d{4})$/i)) && MONTHS.includes(m[1].toLowerCase())) return `${m[3]}-${pad(MONTHS.indexOf(m[1].toLowerCase()) + 1)}-${pad(m[2])}`
  if ((m = v.match(/^(\d{1,2})[\s-]([a-z]{3})[a-z]*[\s-](\d{2,4})$/i)) && MONTHS.includes(m[2].toLowerCase())) return `${m[3].length === 2 ? '20' + m[3] : m[3]}-${pad(MONTHS.indexOf(m[2].toLowerCase()) + 1)}-${pad(m[1])}`
  if (/^\d{5}$/.test(v)) { const d = new Date(Date.UTC(1899, 11, 30) + +v * 86400000); return d.toISOString().slice(0, 10) }
  return v
}
const toNum = v => String(v).replace(/[₱,\s]/g, '')
function setCell(r, col, v) {
  const k = ROW_COLS[col]
  if (!k || (k.endsWith('_date') && !String(v).trim())) return // no date in Excel: keep the whole year
  r[k] = k.endsWith('_date') ? toDate(v) : k === 'cost' ? toNum(v) : String(v).trim()
}
// pasted cells fill the rows from where the cursor is, adding rows when needed
function fill(cells, startRow, startCol) {
  cells.forEach((line, n) => {
    while (rows.value.length <= startRow + n) addRow()
    line.forEach((v, j) => setCell(rows.value[startRow + n], startCol + j, v))
  })
  rowErrors.value = {}
}
function onPaste(ev, i) {
  const text = ev.clipboardData?.getData('text/plain') || ''
  if (!/[\t\n]/.test(text.trim())) return // one value: let the cell take it
  ev.preventDefault()
  fill(parseTsv(text), i, +(ev.target.dataset.col || 0))
}
async function pasteDialog() {
  const text = await ask({ title: 'Paste from Excel', message: 'Select the cells in Excel in this order: D Activity, I Start date, J End date, K–N Q1–Q4, O Unit, P Cost. Copy (Ctrl+C), then paste here (Ctrl+V).',
    confirmText: 'Add rows', input: { label: 'Cells from Excel', multiline: true } })
  if (text === null) return
  const cells = parseTsv(text)
  // start at the first empty row at the end
  let at = rows.value.length
  while (at > 0 && !isFilled(rows.value[at - 1])) at--
  fill(cells, at, 0)
  toast(`${cells.length} row${cells.length === 1 ? '' : 's'} pasted.`)
}

onMounted(async () => {
  try {
    budget.value = (await api.get('summary', { fy: fy.value })).budget
    await loadAreaBudget()
  } catch { budget.value = null } finally { loading.value = false }
})

async function save(submit) {
  ce.value = {}; rowErrors.value = {}; formError.value = ''
  const list = filled.value
  if (submit && !(await ask({ title: `Submit ${list.length} activit${list.length === 1 ? 'y' : 'ies'} to the Planning Office?`,
    message: 'After submitting, your office can no longer change them unless Planning returns them.', confirmText: 'Save all and submit' }))) return
  busy.value = true
  try {
    const r = await api.post('activities-bulk', {
      common: { ...c.value, fiscal_year: fy.value },
      rows: list.map(x => Object.fromEntries(ROW_COLS.map(k => [k, x[k]]))),
      submit
    })
    toast(submit ? `${r.saved} submitted to the Planning Office.` : `${r.saved} drafts saved.`)
    router.push('/encoding-sheet')
  } catch (err) {
    formError.value = err.message
    // a wrong shared column shows once at the top; row columns show on their row
    const shared = {}; const per = {}
    for (const [i, f] of Object.entries(err.rows || {})) {
      for (const [k, msg] of Object.entries(f)) {
        if (ROW_COLS.includes(k) || k === 'targets') (per[i] ||= {})[k] = msg
        else shared[k] = msg
      }
    }
    ce.value = { ...shared, ...(err.fields || {}) }
    rowErrors.value = per
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally { busy.value = false }
}
</script>

<style scoped>
fieldset { border: 0; padding: 0; margin: 0; min-width: 0; display: grid; gap: 16px; }
fieldset > .panel { margin: 0; }
.back { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; text-decoration: none; margin-bottom: 6px; }
.col { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 4px; background: var(--brand-wash); color: var(--brand);
  font-style: normal; font-size: 11.5px; font-weight: 700; margin-right: 6px; vertical-align: 1px; }
.lbl { font-size: 13px; font-weight: 600; }
.common { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.common .span-all { grid-column: 1 / -1; }
@media (max-width: 1100px) { .common { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 760px) { .common { grid-template-columns: 1fr; } .align-only { display: none; } }
.rows-h { display: flex; gap: 12px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; }
.rows th { white-space: nowrap; font-size: 12px; }
.rows td { padding: 4px; vertical-align: top; }
.rows td.n { padding: 10px 6px; text-align: right; width: 32px; }
.rows th.d { min-width: 280px; }
.rows th.dt { width: 150px; }
.rows th.q { width: 76px; }
.rows th.u { width: 130px; }
.rows th.p { width: 120px; }
.rows th.x { width: 40px; }
.rows input, .rows textarea { width: 100%; min-height: 34px; padding: 5px 8px; font-size: 13px; }
.rows textarea { resize: vertical; field-sizing: content; min-height: 34px; }
.rows input.err, .rows textarea.err { border-color: var(--signal); }
.rows tr.bad td.n { color: var(--signal); font-weight: 700; }
.rows tr.err-row td { padding: 0 6px 8px; color: var(--signal); font-size: 12.5px; font-weight: 500; border-top: 0; }
.rows tfoot td { font-weight: 700; padding: 10px 6px; }
.num-in, .num { text-align: right; }
.panel-f.left { justify-content: flex-start; }
.actions { margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.actions.sticky { position: sticky; bottom: 0; z-index: 5; margin: 16px -4px 0; padding: 12px 4px; background: var(--paper); border-top: 1px solid var(--line); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
