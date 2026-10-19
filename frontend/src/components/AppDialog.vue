<template>
  <div v-if="dialog" class="modal-bg" @click.self="close(false)">
    <form class="modal" @submit.prevent="close(true)" @keydown.esc="close(false)">
      <div class="panel-h"><h2>{{ dialog.title }}</h2></div>
      <div class="panel-b">
        <p v-if="dialog.message" class="whitespace-pre-line">{{ dialog.message }}</p>
        <dl v-if="dialog.facts" class="facts">
          <template v-for="[label, value] in dialog.facts" :key="label"><dt>{{ label }}</dt><dd>{{ value }}</dd></template>
        </dl>
        <Field v-if="dialog.input" :label="dialog.input.label">
          <textarea v-if="dialog.input.multiline" v-model="dialog.value" required v-focus></textarea>
          <input v-else v-model="dialog.value" :type="dialog.input.type || 'text'" required v-focus />
        </Field>
      </div>
      <div class="panel-f">
        <button v-if="dialog.cancel" type="button" class="btn" @click="close(false)">Cancel</button>
        <button class="btn" :class="dialog.tone === 'danger' ? 'danger solid' : dialog.tone" v-focus="!dialog.input">{{ dialog.confirmText }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
// Renders useDialog().ask(): the in-page confirm / prompt used across the app.
import { useDialog } from '../composables/useDialog'
import Field from './Field.vue'

const { dialog, close } = useDialog()
const vFocus = { mounted: (el, b) => { if (b.value !== false) el.focus() } }
</script>

<style scoped>
.facts { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 0; padding: 12px 14px; background: var(--paper); border-radius: var(--r-sm); }
.facts dt { color: var(--ink-2); }
.facts dd { margin: 0; font-weight: 700; font-family: Consolas, 'Courier New', monospace; font-size: 15px; letter-spacing: .03em; }
</style>
