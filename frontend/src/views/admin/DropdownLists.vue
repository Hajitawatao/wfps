<template>
  <div class="page">
    <div class="page-h">
      <div>
        <h1>Dropdown lists</h1>
        <p>The choices in the Encoding Sheet columns, taken from the hidden Validation sheet. Turn off a value instead of deleting it if offices already used it.</p>
      </div>
    </div>

    <div class="seg tabs" role="tablist">
      <button v-for="t in tabs" :key="t.key" role="tab" :aria-selected="tab === t.key" :class="{ on: tab === t.key }" @click="tab = t.key">{{ t.label }}</button>
    </div>

    <section class="panel">
      <div class="panel-h">
        <div v-if="tab === 'indicators'" class="obj-pick">
          <Combobox v-model="objectiveId" :options="objectiveOpts" placeholder="Choose the objective" />
        </div>
        <span class="spacer"></span>
        <button class="btn sm primary" @click="add" :disabled="tab === 'indicators' && !objectiveId"><Icon name="plus" :size="14" />Add a value</button>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th v-for="c in current.cols" :key="c.k">{{ c.label }}</th><th>In use</th><th></th></tr></thead>
          <tbody>
            <tr v-for="row in visibleRows" :key="row.id" :class="{ off: !+row.active }">
              <td v-for="c in current.cols" :key="c.k" :class="{ wide: c.wide }">
                <select v-if="c.options" v-model="row[c.k]" @change="save(row)"><option v-for="o in c.options" :key="o">{{ o }}</option></select>
                <label v-else-if="c.check" class="check"><input type="checkbox" :checked="!!+row[c.k]" @change="row[c.k] = $event.target.checked ? 1 : 0; save(row)" />{{ c.checkLabel }}</label>
                <input v-else-if="c.num" v-model="row[c.k]" type="number" class="order" @change="save(row)" />
                <textarea v-else v-model="row[c.k]" rows="1" @change="save(row)"></textarea>
              </td>
              <td><label class="check"><input type="checkbox" :checked="!!+row.active" @change="row.active = $event.target.checked ? 1 : 0; save(row)" />Shown</label></td>
              <td><button class="icon-btn" title="Delete" @click="del(row)"><Icon name="trash" :size="16" /></button></td>
            </tr>
            <tr v-if="!visibleRows.length"><td :colspan="current.cols.length + 2" class="muted">No values yet.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
// The hidden "Validation" sheet: choices of columns B, C, E, F and Q.
import { computed, ref, watch } from 'vue'
import { useApi } from '../../composables/useApi'
import { useReference } from '../../composables/useReference'
import { useDialog } from '../../composables/useDialog'
import { useToast } from '../../composables/useToast'
import { FUNCTION_TYPES } from '../../lib/options'
import Icon from '../../components/Icon.vue'
import Combobox from '../../components/Combobox.vue'

const api = useApi()
const { loadReference } = useReference()
const { ask } = useDialog()
const { toast, toastError } = useToast()
const refresh = () => loadReference().catch(toastError)

const tabs = [
  { key: 'objectives', label: 'B. Objectives', cols: [{ k: 'function_type', label: 'Type of function', options: FUNCTION_TYPES }, { k: 'title', label: 'Objective', wide: true }, { k: 'sort_order', label: 'Order', num: true }] },
  { key: 'indicators', label: 'C. Success indicators', cols: [{ k: 'title', label: 'Success indicator', wide: true }, { k: 'sort_order', label: 'Order', num: true }] },
  { key: 'classifications', label: 'E. Activity classification', cols: [{ k: 'name', label: 'Classification', wide: true }, { k: 'sort_order', label: 'Order', num: true }] },
  { key: 'line_items', label: 'F. Budget line item', cols: [{ k: 'name', label: 'Line item', wide: true }, { k: 'sort_order', label: 'Order', num: true }] },
  { key: 'fund_sources', label: 'Q. Source of funds', cols: [{ k: 'name', label: 'Source', wide: true }, { k: 'is_income', label: 'Prints on', check: true, checkLabel: 'Print (Income)' }, { k: 'sort_order', label: 'Order', num: true }] }
]
const tab = ref('objectives')
const current = computed(() => tabs.find(t => t.key === tab.value))
const rows = ref([])
const objectives = ref([])
const objectiveId = ref(null)
const objectiveOpts = computed(() => objectives.value.map(o => ({ value: o.id, label: o.title, group: o.function_type })))
const visibleRows = computed(() => tab.value === 'indicators' ? rows.value.filter(r => r.objective_id === objectiveId.value) : rows.value)

async function load() {
  try {
    rows.value = (await api.get('lists', { list: tab.value })).data
    if (tab.value === 'indicators') {
      objectives.value = (await api.get('lists', { list: 'objectives' })).data
      if (!objectiveId.value) objectiveId.value = objectives.value[0]?.id ?? null
    }
  } catch (e) { toastError(e) }
}
watch(tab, load, { immediate: true })

async function save(row) {
  const { id, ...data } = row
  try { await api.put('lists', data, { list: tab.value, id }); toast('Saved.'); refresh() }
  catch (e) { toastError(e) }
}
async function add() {
  const wide = current.value.cols.find(c => c.wide)
  const text = await ask({ title: 'Add a value', confirmText: 'Add', input: { label: wide.label, multiline: true } })
  if (!text) return
  const data = { [wide.k]: text, sort_order: rows.value.length + 1 }
  if (tab.value === 'objectives') data.function_type = 'Strategic'
  if (tab.value === 'indicators') data.objective_id = objectiveId.value
  try { await api.post('lists', data, { list: tab.value }); await load(); refresh() } catch (e) { toastError(e) }
}
async function del(row) {
  if (!(await ask({ title: 'Delete this value?', message: row.title || row.name, confirmText: 'Delete', tone: 'danger' }))) return
  try { await api.del('lists', { list: tab.value, id: row.id }); await load(); refresh() } catch (e) { toastError(e) }
}
</script>

<style scoped>
.tabs { margin-bottom: 14px; flex-wrap: wrap; }
.obj-pick { flex: 1 1 420px; max-width: 640px; }
td.wide { width: 70%; }
td textarea { min-height: 38px; resize: vertical; }
input.order { width: 76px; }
tr.off td { color: var(--ink-3); }
tr.off textarea { color: var(--ink-3); }
</style>
