<template>
  <section class="budget panel" :class="{ big }">
    <div><span>{{ area ? 'MOOE budget' : 'Total MOOE' }}</span><b>{{ num(mooeTotal) }}</b></div>
    <div><span>Remaining MOOE</span><b :class="{ neg: mooeRemaining < 0 }">{{ num(mooeRemaining) }}</b></div>
    <div><span>{{ area ? 'Capital Outlay budget' : 'Total Capital Outlay' }}</span><b>{{ num(coTotal) }}</b></div>
    <div><span>Remaining Capital Outlay</span><b :class="{ neg: coRemaining < 0 }">{{ num(coRemaining) }}</b></div>
    <p>{{ note }}</p>
  </section>
</template>

<script setup>
// Rows 1-2 of the Encoding Sheet: Total and Remaining MOOE / Capital Outlay, in '000 PhP (hospital, or one area's budget).
import { num } from '../lib/format'

defineProps({
  mooeTotal: Number, mooeRemaining: Number, coTotal: Number, coRemaining: Number,
  note: { type: String, default: "In '000 PhP, whole hospital." },
  big: Boolean,
  area: Boolean // an area's own budget (set by Procurement) instead of the hospital total
})
</script>

<style scoped>
/* four separate cards, muted label, large value; the value shrinks on narrow screens so it never overflows */
.budget { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; background: none; border: 0; }
.budget > div { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md); padding: 16px 18px; display: flex; flex-direction: column; gap: 8px; }
.budget span { font-size: 12px; color: var(--ink-3); font-weight: 500; }
.budget b { font-size: clamp(16px, 4.2vw, 20px); overflow-wrap: anywhere; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--brand-ink); }
.budget b.neg { color: var(--signal); }
.budget p { grid-column: 1 / -1; font-size: 11px; color: var(--ink-3); margin-top: -6px; }
.big > div { padding: 20px; gap: 12px; }
.big b { font-size: clamp(18px, 5vw, 28px); }
@media (max-width: 860px) { .budget { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; } .budget > div, .big > div { padding: 14px; } }
</style>
