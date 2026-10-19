<template>
  <div class="cbx" :class="{ open, disabled, invalid }">
    <button ref="trigger" type="button" class="ctrl cbx-trigger" :disabled="disabled"
            :aria-expanded="open" aria-haspopup="listbox" @click="toggle" @keydown="onTriggerKey">
      <span v-if="selected" class="cbx-value">{{ selected.label }}</span>
      <span v-else class="cbx-ph">{{ placeholder }}</span>
      <svg class="cbx-chev" width="12" height="8" viewBox="0 0 12 8" aria-hidden="true"><path d="M1 1.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <Teleport to="body">
      <div v-if="open" ref="pop" class="cbx-pop" :style="popStyle" @mousedown.prevent>
        <div v-if="searchable" class="cbx-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
          <input ref="search" v-model="query" :placeholder="searchPlaceholder" @keydown="onSearchKey" aria-label="Search options" />
        </div>
        <ul ref="list" class="cbx-list" role="listbox">
          <template v-for="(row, i) in rows" :key="row.key">
            <li v-if="row.header" class="cbx-group">{{ row.header }}</li>
            <li v-else role="option" class="cbx-opt" :aria-selected="row.opt.value === modelValue"
                :class="{ active: i === active, sel: row.opt.value === modelValue }"
                @mousemove="active = i" @click="choose(row.opt)">
              <span>{{ row.opt.label }}</span>
              <small v-if="row.opt.hint">{{ row.opt.hint }}</small>
            </li>
          </template>
          <li v-if="canCreate" role="option" class="cbx-opt cbx-create" :class="{ active: active === rows.length }"
              @mousemove="active = rows.length" @click="create">{{ createLabel.replace('{q}', query.trim()) }}</li>
          <li v-else-if="!rows.length" class="cbx-none">No match for "{{ query }}"</li>
        </ul>
        <div v-if="clearable && modelValue !== null && modelValue !== ''" class="cbx-foot">
          <button type="button" class="btn ghost sm" @click="choose(null)">Clear selection</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
// Searchable dropdown for long DOH lists (objectives, success indicators, line items...).
// Options: [{ value, label, hint?, group? }]. Long labels wrap instead of being cut off.
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { default: null },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select…' },
  searchPlaceholder: { type: String, default: 'Type to search' },
  disabled: Boolean,
  invalid: Boolean,
  clearable: Boolean,
  searchable: { type: Boolean, default: true },
  // creatable: typing a value that is not in the list offers "Add …" and emits create(text)
  creatable: Boolean,
  createLabel: { type: String, default: 'Add "{q}"' }
})
const emit = defineEmits(['update:modelValue', 'change', 'create'])

const open = ref(false)
const query = ref('')
const active = ref(0)
const trigger = ref(null)
const pop = ref(null)
const search = ref(null)
const list = ref(null)
const popStyle = ref({})

const selected = computed(() => props.options.find(o => o.value === props.modelValue) || null)

const rows = computed(() => {
  const words = query.value.toLowerCase().split(/\s+/).filter(Boolean)
  const match = o => words.every(w => (o.label + ' ' + (o.hint || '') + ' ' + (o.group || '')).toLowerCase().includes(w))
  const out = []
  let lastGroup
  for (const o of props.options) {
    if (!match(o)) continue
    if (o.group && o.group !== lastGroup) { out.push({ key: 'g:' + o.group, header: o.group }); lastGroup = o.group }
    out.push({ key: 'o:' + o.value, opt: o })
  }
  return out
})
const canCreate = computed(() => {
  const q = query.value.trim().toLowerCase()
  return props.creatable && q !== '' && !props.options.some(o => o.label.toLowerCase() === q)
})
const selectable = i => (rows.value[i] && !rows.value[i].header) || (i === rows.value.length && canCreate.value)

function place() {
  if (!trigger.value) return
  const r = trigger.value.getBoundingClientRect()
  const below = window.innerHeight - r.bottom
  const maxH = 360
  const up = below < 260 && r.top > below
  popStyle.value = {
    left: Math.max(8, Math.min(r.left, window.innerWidth - Math.max(r.width, 320) - 8)) + 'px',
    width: Math.max(r.width, 320) + 'px',
    ...(up ? { bottom: (window.innerHeight - r.top + 4) + 'px', maxHeight: Math.min(maxH, r.top - 12) + 'px' }
           : { top: (r.bottom + 4) + 'px', maxHeight: Math.min(maxH, below - 12) + 'px' })
  }
}

async function show() {
  if (props.disabled) return
  open.value = true
  query.value = ''
  place()
  const idx = rows.value.findIndex(r => !r.header && r.opt.value === props.modelValue)
  active.value = idx >= 0 ? idx : rows.value.findIndex(r => !r.header)
  await nextTick()
  if (props.searchable) search.value?.focus()
  scrollActive()
  window.addEventListener('scroll', place, true)
  window.addEventListener('resize', place)
  document.addEventListener('mousedown', outside, true)
}
function hide(refocus = true) {
  open.value = false
  window.removeEventListener('scroll', place, true)
  window.removeEventListener('resize', place)
  document.removeEventListener('mousedown', outside, true)
  if (refocus) trigger.value?.focus()
}
function toggle() { open.value ? hide() : show() }
function outside(e) {
  if (pop.value?.contains(e.target) || trigger.value?.contains(e.target)) return
  hide(false)
}
function choose(opt) {
  const v = opt ? opt.value : null
  emit('update:modelValue', v)
  emit('change', v)
  hide()
}
function create() {
  emit('create', query.value.trim())
  hide()
}
function move(d) {
  const len = rows.value.length + (canCreate.value ? 1 : 0)
  let i = active.value
  for (let n = 0; n < len; n++) {
    i = (i + d + len) % len
    if (selectable(i)) { active.value = i; break }
  }
  scrollActive()
}
function scrollActive() {
  nextTick(() => list.value?.querySelector('.cbx-opt.active')?.scrollIntoView({ block: 'nearest' }))
}
function onSearchKey(e) {
  if (e.key === 'ArrowDown') { e.preventDefault(); move(1) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1) }
  else if (e.key === 'Enter') { e.preventDefault(); if (active.value === rows.value.length && canCreate.value) create(); else if (selectable(active.value)) choose(rows.value[active.value].opt) }
  else if (e.key === 'Escape') { e.preventDefault(); hide() }
  else if (e.key === 'Tab') hide(false)
}
function onTriggerKey(e) {
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key) && !open.value) { e.preventDefault(); show() }
}
watch(query, () => { const i = rows.value.findIndex(r => !r.header); active.value = i >= 0 ? i : rows.value.length })
onBeforeUnmount(() => { if (open.value) hide(false) })
</script>

<style>
.cbx { position: relative; min-width: 0; }
.cbx-trigger {
  width: 100%; min-height: 38px; display: flex; align-items: center; gap: 10px; text-align: left; cursor: pointer;
  font: inherit; color: var(--ink); background: var(--surface); border: 1px solid var(--field); border-radius: var(--r-sm); padding: 7px 12px 7px 10px;
}
.cbx-trigger:focus-visible, .cbx.open .cbx-trigger { border-color: var(--brand-3); box-shadow: var(--focus); outline: none; }
.cbx.invalid .cbx-trigger { border-color: var(--signal); }
.cbx-trigger:disabled { background: var(--paper); color: var(--ink-2); cursor: not-allowed; }
.cbx-value { flex: 1; line-height: 1.4; }
.cbx-ph { flex: 1; color: var(--ink-3); }
.cbx-chev { flex: none; color: var(--ink-2); transition: transform .15s; }
.cbx.open .cbx-chev { transform: rotate(180deg); }

.cbx-pop {
  position: fixed; z-index: 95; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-sm);
  box-shadow: 0 14px 40px rgba(var(--shade), .18); display: flex; flex-direction: column; overflow: hidden;
  font-family: var(--font-sans); font-size: 14px; color: var(--ink);
}
.cbx-search { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-bottom: 1px solid var(--line-2); color: var(--ink-3); }
.cbx-search input { border: 0; background: transparent; padding: 4px 0; min-height: 0; box-shadow: none !important; }
.cbx-list { list-style: none; margin: 0; padding: 4px; overflow-y: auto; flex: 1; }
.cbx-group { padding: 10px 10px 4px; font-size: 12px; font-weight: 700; color: var(--brand); }
.cbx-opt { padding: 8px 10px 8px 12px; border-radius: 4px; cursor: pointer; line-height: 1.4; display: flex; flex-direction: column; gap: 2px; border-left: 3px solid transparent; }
.cbx-opt small { color: var(--ink-3); font-size: 12px; }
.cbx-opt.active { background: var(--brand-wash); }
.cbx-opt.sel { border-left-color: var(--brand); font-weight: 600; }
.cbx-none { padding: 14px 12px; color: var(--ink-3); }
.cbx-create { color: var(--brand-3); font-weight: 600; }
.cbx-foot { border-top: 1px solid var(--line-2); padding: 6px; }
</style>
