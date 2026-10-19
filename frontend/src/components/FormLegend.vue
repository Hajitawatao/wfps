<template>
  <details class="legend panel" :open="open" @toggle="remember">
    <summary>Legend: how to read this form</summary>
    <div class="lg-body">
      <dl>
        <dt><i class="col">A</i> to <i class="col">S</i></dt>
        <dd>The column of the Excel Encoding Sheet that the field fills. Same order as in Excel.</dd>
        <dt>Required</dt>
        <dd>All columns A to S. For the targets, fill in at least one quarter (K to N).</dd>
        <dt>Linked lists</dt>
        <dd><b>B</b> shows only the objectives of the type picked in <b>A</b>; <b>C</b> shows only the indicators of the objective picked in <b>B</b>.</dd>
        <dt>Search</dt>
        <dd>Open a long list and type a few words, then press Enter.</dd>
        <dt>Cost (P)</dt>
        <dd>In thousand pesos: <b>1</b> = ₱1,000, <b>540</b> = ₱540,000, <b>1,500</b> = ₱1,500,000. Use 0 if no budget is needed.</dd>
        <dt>Budget boxes</dt>
        <dd>Remaining MOOE / CO of the whole hospital, including this activity. <span class="neg">A red number</span> means the budget is exceeded.</dd>
        <dt>Entry status</dt>
        <dd class="sts">
          <span class="status s-draft">Draft</span> saved, can still be changed
          <span class="status s-submitted">Submitted</span> sent to Planning
          <span class="status s-returned">Returned</span> fix it and submit again
          <span class="status s-approved">Approved</span> final
        </dd>
        <dt>Buttons</dt>
        <dd><b>Save draft</b> keeps it for later. <b>Save and submit</b> sends it to the Planning Office; you can no longer change it unless they return it.</dd>
      </dl>
    </div>
  </details>
</template>

<script setup>
// Collapsible legend at the top of "Encode an activity". Starts closed; remembers if the user opened it (per browser).
import { ref } from 'vue'

const KEY = 'wfp.legend'
const open = ref((() => { try { return localStorage.getItem(KEY) === 'open' } catch { return false } })())
function remember(e) {
  open.value = e.target.open
  try { localStorage.setItem(KEY, open.value ? 'open' : 'closed') } catch { /* private window */ }
}
</script>

<style scoped>
.legend { margin-bottom: 16px; }
summary { padding: 12px 18px; cursor: pointer; font-weight: 600; color: var(--ink-2); user-select: none; }
summary:hover { color: var(--ink); }
.lg-body { padding: 0 18px 14px; border-top: 1px solid var(--line-2); }
dl { display: grid; grid-template-columns: 130px 1fr; gap: 8px 16px; margin: 12px 0 0; font-size: 13px; line-height: 1.5; }
dt { font-weight: 600; color: var(--ink); }
dd { margin: 0; color: var(--ink-2); }
.sts { display: flex; flex-wrap: wrap; gap: 4px 14px; align-items: center; }
.col { display: inline-grid; place-items: center; width: 18px; height: 18px; border-radius: 4px; background: var(--brand-wash); color: var(--brand-ink);
  font-style: normal; font-size: 11px; font-weight: 700; }
.neg { color: var(--signal); font-weight: 600; }
@media (max-width: 640px) { dl { grid-template-columns: 1fr; } dt { margin-top: 6px; } }
</style>
