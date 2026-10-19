<template>
  <div class="page-narrow">
    <div v-if="loading" class="loading">Loading…</div>
    <form v-else @submit.prevent="save(false)" novalidate>
      <div class="page-h">
        <div>
          <RouterLink to="/encoding-sheet" class="back"><Icon name="back" :size="16" />Encoding Sheet</RouterLink>
          <h1>{{ isNew ? 'Encode an activity' : a.title }}</h1>
          <p v-if="!isNew">
            <span class="status" :class="WORKFLOW[a.workflow].cls">{{ WORKFLOW[a.workflow].label }}</span>
            <span class="muted">&nbsp; {{ a.office_name }}</span>
          </p>
          <p v-else>{{ officeName }}, FY {{ fy }}</p>
        </div>
      </div>

      <!-- rows 1-2 of the Encoding Sheet -->
      <BudgetStrip v-if="budget" :mooe-total="budget.mooe_total" :mooe-remaining="after.mooe" :co-total="budget.co_total" :co-remaining="after.co"
                   note="In '000 PhP, whole hospital, including this activity." />
      <div v-if="after.mooe < 0 || after.co < 0" class="note err" style="margin-bottom:16px">
        You have entered more than the allocated budget. Reduce or realign the cost to stay within the {{ after.mooe < 0 ? 'MOOE' : 'CO' }} limit.
      </div>

      <div v-if="a.workflow === 'returned' && a.review_note" class="note warn" style="margin-bottom:16px">
        <b>Returned by the Planning Office:</b> {{ a.review_note }}
      </div>
      <FormLegend v-if="!readonly" />
      <div v-if="closedForMe" class="note warn" style="margin-bottom:16px">
        Encoding for FY {{ fy }} is closed. Ask the Planning Office if you still need to add an activity.
      </div>
      <div v-if="readonly" class="note info" style="margin-bottom:16px">
        {{ a.workflow === 'draft' || a.workflow === 'returned' ? `Encoding for FY ${fy} is closed.` : `This activity is ${WORKFLOW[a.workflow].label.toLowerCase()}. Only the Planning Office can change it now.` }}
      </div>
      <div v-if="formError" class="note err" style="margin-bottom:16px">{{ formError }}</div>

      <fieldset :disabled="readonly" class="form-cols">
        <!-- left: what the activity is (A-H); right: when, how much, who (I-S). One column on narrow screens. -->
        <section class="panel">
          <div class="panel-h"><h2>Activity</h2></div>
          <div class="panel-b grid sheet-grid">
            <Field v-if="isReviewer" label="Office" class="span-all" :error="e.office_id">
              <Combobox v-model="a.office_id" :options="officeOptions()" placeholder="Which office is this for?" :invalid="!!e.office_id" :disabled="readonly" />
            </Field>

            <Field class="span-all" :error="e.function_type">
              <span class="lbl"><i class="col">A</i>Type of function</span>
              <div class="seg">
                <button type="button" v-for="t in FUNCTION_TYPES" :key="t" :class="{ on: a.function_type === t }" :disabled="readonly" @click="setFunction(t)">{{ t }}</button>
              </div>
            </Field>

            <Field class="span-all" :error="e.objective_id">
              <span class="lbl"><i class="col">B</i>Objectives</span>
              <Combobox v-model="a.objective_id" :options="objectiveOptions(a.function_type)" :disabled="readonly || !a.function_type"
                        :placeholder="a.function_type ? 'Choose the objective' : 'Choose the type of function first'" :invalid="!!e.objective_id"
                        @change="a.indicator_id = null" />
            </Field>

            <Field class="span-all" :error="e.indicator_id">
              <span class="lbl"><i class="col">C</i>Success indicators and targets</span>
              <Combobox v-model="a.indicator_id" :options="indicatorOptions(a.objective_id)" :disabled="readonly || !a.objective_id"
                        :placeholder="a.objective_id ? 'Choose the success indicator' : 'Choose the objective first'" :invalid="!!e.indicator_id" />
            </Field>

            <Field class="span-all" :error="e.title">
              <span class="lbl"><i class="col">D</i>Activity</span>
              <textarea v-model="a.title" rows="2" maxlength="600" placeholder="Activity 9. HIS Improvements"></textarea>
            </Field>

            <Field class="span-all" :error="e.classification_id">
              <span class="lbl"><i class="col">E</i>Activity classification</span>
              <Combobox v-model="a.classification_id" :options="classificationOptions()" placeholder="Choose the classification" :invalid="!!e.classification_id" :disabled="readonly" />
            </Field>

            <Field class="span-all" :error="e.line_item_id">
              <span class="lbl"><i class="col">F</i>Budget line item</span>
              <Combobox v-model="a.line_item_id" :options="lineItemOptions()" placeholder="Choose the budget line item" :invalid="!!e.line_item_id" :disabled="readonly" />
            </Field>

            <Field :error="e.saa">
              <span class="lbl"><i class="col">G</i>SAA</span>
              <span class="help">Is this for sub-allotment to other office?</span>
              <div class="seg">
                <button type="button" :class="{ on: !a.saa }" :disabled="readonly" @click="a.saa = 0">No</button>
                <button type="button" :class="{ on: !!a.saa }" :disabled="readonly" @click="a.saa = 1">Yes</button>
              </div>
            </Field>

            <Field :error="e.expense_class">
              <span class="lbl"><i class="col">H</i>Expense class</span>
              <span class="help align-only">&nbsp;</span>
              <div class="seg">
                <button type="button" v-for="x in EXPENSE_CLASSES" :key="x" :class="{ on: a.expense_class === x }" :disabled="readonly" @click="a.expense_class = x">{{ x }}</button>
              </div>
            </Field>
          </div>
        </section>

        <section class="panel">
          <div class="group-h first">Timeframe</div>
          <div class="panel-b grid g2">
            <Field :error="e.start_date">
              <span class="lbl"><i class="col">I</i>Start date</span>
              <input type="date" v-model="a.start_date" :min="`${fy}-01-01`" :max="`${fy}-12-31`" />
            </Field>
            <Field :error="e.end_date">
              <span class="lbl"><i class="col">J</i>End date</span>
              <input type="date" v-model="a.end_date" :min="a.start_date || `${fy}-01-01`" :max="`${fy}-12-31`" />
            </Field>
          </div>

          <div class="group-h">Target <span class="muted">(at least one quarter)</span></div>
          <div class="panel-b grid targets">
            <Field v-for="(qc, i) in ['K', 'L', 'M', 'N']" :key="qc">
              <span class="lbl"><i class="col">{{ qc }}</i>Q{{ i + 1 }}</span>
              <input v-model="a['target_q' + (i + 1)]" :class="{ err: e.targets }" />
            </Field>
            <Field class="unit" :error="e.target_unit">
              <span class="lbl"><i class="col">O</i>Unit</span>
              <input v-model="a.target_unit" placeholder="Trainings, Activities, Meetings, Boxes, etc." />
            </Field>
            <span v-if="e.targets" class="err-msg span-all">{{ e.targets }}</span>
          </div>

          <div class="group-h">Resource requirement</div>
          <div class="panel-b grid g2">
            <Field :error="e.cost" :help="a.cost !== '' && a.cost !== null ? '= ' + php((+a.cost || 0) * 1000) : ''">
              <span class="lbl"><i class="col">P</i>Cost (in '000 PhP)</span>
              <input type="number" v-model="a.cost" min="0" step="0.01" inputmode="decimal" class="num-in" />
            </Field>
            <Field :error="e.fund_source_id">
              <span class="lbl"><i class="col">Q</i>Source of funds</span>
              <select v-model="a.fund_source_id">
                <option :value="null" disabled>Choose</option>
                <option v-for="f in fundOptions()" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </Field>
          </div>

          <div class="panel-b grid g2 last">
            <Field :error="e.responsible">
              <span class="lbl"><i class="col">R</i>Responsible unit</span>
              <input v-model="a.responsible" />
            </Field>
            <Field :error="e.impl_status">
              <span class="lbl"><i class="col">S</i>Status</span>
              <select v-model="a.impl_status">
                <option v-for="st in STATUSES" :key="st">{{ st }}</option>
              </select>
            </Field>
          </div>
        </section>
      </fieldset>

      <div class="actions" :class="{ sticky: !readonly || isReviewer }">
        <button v-if="!isNew && a.can_edit" type="button" class="btn danger" @click="remove"><Icon name="trash" />Delete</button>
        <span class="spacer"></span>
        <template v-if="isReviewer && !isNew">
          <button v-if="a.workflow !== 'draft'" type="button" class="btn" @click="review('reopen')">Reopen as draft</button>
          <button v-if="a.workflow === 'submitted' || a.workflow === 'approved'" type="button" class="btn warn" @click="review('return')"><Icon name="undo" />Return to office</button>
          <button v-if="a.workflow === 'submitted'" type="button" class="btn approve" @click="review('approve')"><Icon name="check" />Approve</button>
        </template>
        <template v-if="!readonly">
          <button class="btn" :disabled="busy || closedForMe">{{ busy ? 'Saving…' : 'Save draft' }}</button>
          <button v-if="isNew || ['draft', 'returned'].includes(a.workflow)" type="button" class="btn primary" :disabled="busy || closedForMe" @click="save(true)">
            <Icon name="send" />Save and submit
          </button>
        </template>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useAuth } from '../composables/useAuth'
import { useReference } from '../composables/useReference'
import { useDialog } from '../composables/useDialog'
import { useToast } from '../composables/useToast'
import { php, WORKFLOW } from '../lib/format'
import { canEdit } from '../lib/gate'
import { FUNCTION_TYPES, EXPENSE_CLASSES, STATUSES, officeOptions, objectiveOptions, indicatorOptions,
  classificationOptions, lineItemOptions, fundOptions } from '../lib/options'
import Icon from '../components/Icon.vue'
import Field from '../components/Field.vue'
import Combobox from '../components/Combobox.vue'
import BudgetStrip from '../components/BudgetStrip.vue'
import FormLegend from '../components/FormLegend.vue'

const route = useRoute()
const router = useRouter()
const api = useApi()
const { user, isReviewer } = useAuth()
const { fy, lists } = useReference()
const { ask } = useDialog()
const { toast, toastError } = useToast()

const isNew = computed(() => route.name === 'new')
const loading = ref(true)
const busy = ref(false)
const e = ref({})
const formError = ref('')
const budget = ref(null)
const original = ref({ cost: 0, expense_class: null })
const encoder = user.value.role === 'encoder'

const a = ref({
  office_id: user.value.office_id, // the signed-in user's office; Planning/Admin can pick another
  function_type: null, objective_id: null, indicator_id: null, title: '', classification_id: null, line_item_id: null,
  saa: 0, expense_class: 'MOOE', start_date: `${fy.value}-01-01`, end_date: `${fy.value}-12-31`,
  target_q1: '', target_q2: '', target_q3: '', target_q4: '', target_unit: '', cost: '',
  fund_source_id: null, responsible: user.value.office_code || '', impl_status: 'Ongoing',
  workflow: 'draft', can_edit: true
})

const readonly = computed(() => !isNew.value && !a.value.can_edit)
// a new row: same rule the server applies (encoders only while encoding is open)
const closedForMe = computed(() => isNew.value && !canEdit(user.value, a.value, +lists.value?.settings?.encoding_open))
const officeName = computed(() => lists.value?.offices?.find(o => o.id === a.value.office_id)?.name || user.value.office_name)

// Remaining MOOE / CO the way the Encoding Sheet shows it, with this activity's cost applied
const after = computed(() => {
  if (!budget.value) return { mooe: 0, co: 0 }
  const delta = { MOOE: 0, CO: 0 }
  if (original.value.expense_class) delta[original.value.expense_class] -= original.value.cost
  delta[a.value.expense_class] += Number(a.value.cost) || 0
  return { mooe: budget.value.mooe_remaining - delta.MOOE, co: budget.value.co_remaining - delta.CO }
})

function setFunction(t) {
  if (a.value.function_type === t) return
  a.value.function_type = t
  a.value.objective_id = null
  a.value.indicator_id = null
}

onMounted(async () => {
  try {
    const [s, r] = await Promise.all([
      api.get('summary', { fy: fy.value }),
      isNew.value ? null : api.get('activity', { id: route.params.id })
    ])
    budget.value = s.budget
    if (r) {
      a.value = { ...r.data, saa: +r.data.saa, cost: Number(r.data.cost) }
      original.value = { cost: Number(r.data.cost), expense_class: r.data.expense_class }
    }
  } catch (err) {
    toastError(err)
    router.replace('/encoding-sheet')
  } finally { loading.value = false }
})

async function save(submit) {
  e.value = {}
  formError.value = ''
  if (submit && !(await ask({ title: 'Submit to the Planning Office?', message: 'After submitting, your office can no longer change this activity unless Planning returns it.', confirmText: 'Save and submit' }))) return
  busy.value = true
  try {
    const body = { ...a.value, fiscal_year: fy.value }
    const r = isNew.value ? await api.post('activities', body) : await api.put('activity', body, { id: a.value.id })
    if (submit) {
      await api.post('activity-status', { action: 'submit' }, { id: r.data.id })
      toast('Submitted to the Planning Office.')
      router.push('/encoding-sheet')
    } else {
      toast('Draft saved.')
      if (isNew.value) router.replace(`/encoding-sheet/${r.data.id}`)
      else original.value = { cost: Number(r.data.cost), expense_class: r.data.expense_class }
    }
  } catch (err) {
    e.value = err.fields || {}
    formError.value = err.message
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally { busy.value = false }
}

async function remove() {
  if (!(await ask({ title: 'Delete this activity?', message: a.value.title, confirmText: 'Delete', tone: 'danger' }))) return
  try { await api.del('activity', { id: a.value.id }); toast('Activity deleted.'); router.push('/encoding-sheet') }
  catch (err) { toastError(err) }
}

async function review(action) {
  let note = ''
  if (action === 'return') {
    note = await ask({ title: 'Return to the office', confirmText: 'Return', tone: 'warn', input: { label: 'What should the office fix?', multiline: true } })
    if (note === null) return
  }
  try {
    await api.post('activity-status', { action, note }, { id: a.value.id })
    toast({ approve: 'Approved.', return: 'Returned to the office.', reopen: 'Reopened as draft.' }[action])
    router.push(action === 'reopen' ? '/encoding-sheet' : '/review')
  } catch (err) { toastError(err) }
}
</script>

<style scoped>
fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
.back { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; text-decoration: none; margin-bottom: 6px; }
.page-h h1 { max-width: 60ch; }
.col { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 4px; background: var(--brand-wash); color: var(--brand);
  font-style: normal; font-size: 11.5px; font-weight: 700; margin-right: 8px; vertical-align: 1px; }
.lbl { font-size: 13px; font-weight: 600; }
.sheet-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.group-h { padding: 14px 18px 0; font-weight: 700; color: var(--brand); border-top: 1px solid var(--line-2); }
.panel-b + .group-h { margin-top: 2px; }
.panel-b.last { border-top: 1px solid var(--line-2); }
.targets { grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(0, 2fr); }
@media (max-width: 760px) { .targets { grid-template-columns: repeat(2, minmax(0, 1fr)); } .targets .unit { grid-column: 1 / -1; } .sheet-grid { grid-template-columns: 1fr; } }
input.err { border-color: var(--signal); }
.err-msg { font-size: 12.5px; color: var(--signal); font-weight: 500; }
.num-in { text-align: right; }
@media (max-width: 760px) { .align-only { display: none; } }
.actions { margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
/* the buttons stay in view at the bottom of the window while scrolling the form */
.actions.sticky { position: sticky; bottom: 0; z-index: 5; margin: 16px -4px 0; padding: 12px 4px; background: var(--paper);
  border-top: 1px solid var(--line); }
.form-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
.form-cols > .panel { margin-top: 0; }
@media (min-width: 1280px) { .form-cols { grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); } }
.group-h.first { border-top: 0; }
</style>
