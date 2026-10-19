<template>
  <div class="page-narrow">
    <div class="page-h">
      <div>
        <h1>General Info, FY {{ fy }}</h1>
        <p>Same as the General Info sheet. The names print on WFP Form 1 (Print GAA and Print Income). They are the same for every area. The budgets feed Total and Remaining MOOE/CO of the whole hospital; every area's cost comes off them.</p>
      </div>
      <div class="spacer"></div>
      <button class="btn" @click="newYear"><Icon name="plus" />Open another fiscal year</button>
    </div>

    <div v-if="!d" class="loading">Loading…</div>
    <form v-else @submit.prevent="save">
      <section class="panel">
        <div class="panel-b grid g2">
          <Field label="Name of office" class="span-all"><input v-model="d.office_name" /></Field>
          <Field label="Prepared by"><input v-model="d.prepared_by" /></Field>
          <Field label="Position"><input v-model="d.prepared_pos" /></Field>
          <Field label="Recommending approval"><input v-model="d.recommending_by" /></Field>
          <Field label="Position"><input v-model="d.recommending_pos" /></Field>
          <Field label="Approved by"><input v-model="d.approved_by" /></Field>
          <Field label="Position"><input v-model="d.approved_pos" /></Field>
          <Field label="Document code" optional help="Printed at the upper right of WFP Form 1."><input v-model="d.document_code" /></Field>
        </div>

        <div class="group-h">Budget, in '000 PhP</div>
        <div class="panel-b grid g2">
          <Field :label="`Total MOOE budget for ${fy}`"><input type="number" min="0" step="0.01" v-model="d.mooe_budget" class="r" /></Field>
          <Field :label="`Total CO budget for ${fy}`"><input type="number" min="0" step="0.01" v-model="d.co_budget" class="r" /></Field>
          <Field label="CONAP (MOOE)"><input type="number" min="0" step="0.01" v-model="d.conap_mooe" class="r" /></Field>
          <Field label="CONAP (CO)"><input type="number" min="0" step="0.01" v-model="d.conap_co" class="r" /></Field>
          <Field label="Other fund source"><input type="number" min="0" step="0.01" v-model="d.other_fund" class="r" /></Field>
        </div>

        <div class="group-h">Encoding period</div>
        <div class="panel-b grid g2">
          <Field label="Offices can encode">
            <div class="seg">
              <button type="button" :class="{ on: +d.encoding_open }" @click="d.encoding_open = 1">Open</button>
              <button type="button" :class="{ on: !+d.encoding_open }" @click="d.encoding_open = 0">Closed</button>
            </div>
          </Field>
          <Field label="Submission deadline" optional help="Shown to the offices on their Summary page."><input type="date" v-model="d.encoding_deadline" /></Field>
        </div>

        <div class="panel-f"><span class="spacer"></span><button class="btn primary" :disabled="busy">Save General Info</button></div>
      </section>
    </form>

  </div>
</template>

<script setup>
// The General Info sheet: names printed on WFP Form 1, the year's budget, and the encoding period.
import { onMounted, ref } from 'vue'
import { useApi } from '../../composables/useApi'
import { useReference } from '../../composables/useReference'
import { useDialog } from '../../composables/useDialog'
import { useToast } from '../../composables/useToast'
import Icon from '../../components/Icon.vue'
import Field from '../../components/Field.vue'

const api = useApi()
const { fy, loadReference, setFy } = useReference()
const { ask } = useDialog()
const { toast, toastError } = useToast()
const d = ref(null)
const busy = ref(false)

onMounted(async () => {
  try { d.value = (await api.get('general-info', { fy: fy.value })).data } catch (e) { toastError(e) }
})

async function save() {
  busy.value = true
  try {
    await api.put('general-info', { ...d.value, fiscal_year: fy.value })
    await loadReference()
    toast('General Info saved.')
  } catch (e) { toastError(e) } finally { busy.value = false }
}

async function newYear() {
  const y = await ask({ title: 'Open another fiscal year', message: 'Names are copied from the latest year. Budgets start at zero.', confirmText: 'Open year', input: { label: 'Fiscal year', type: 'number', value: String(fy.value + 1) } })
  if (!y) return
  try {
    await api.post('fiscal-years', { fiscal_year: Number(y) })
    await setFy(Number(y)) // the page reloads for the new year
    toast(`FY ${y} is open. Enter its budget.`)
  } catch (e) { toastError(e) }
}
</script>

<style scoped>
.group-h { padding: 14px 18px 0; font-weight: 700; color: var(--brand); border-top: 1px solid var(--line-2); }
input.r { text-align: right; }
</style>
