<template>
  <div class="field" :class="{ 'has-err': !!error }">
    <label v-if="label" class="lbl" :for="forId">{{ label }}<span v-if="optional" class="opt"> (optional)</span></label>
    <slot />
    <span v-if="error" class="err-msg">{{ error }}</span>
    <span v-else-if="help" class="help">{{ help }}</span>
  </div>
</template>

<script setup>
defineProps({ label: String, help: String, error: String, optional: Boolean, forId: String })
</script>
