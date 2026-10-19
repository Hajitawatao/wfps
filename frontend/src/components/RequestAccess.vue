<template>
  <div class="modal-bg" @click.self="emit('close')">
    <form v-if="!sent" class="modal" @submit.prevent="send" novalidate>
      <div class="panel-h"><h2>Request access</h2></div>
      <div class="panel-b">
        <p class="muted">The administrator (MIS) checks your request and sets your access level. You can sign in once it is granted.</p>
        <Field label="Full name" :error="e.full_name"><input v-model="f.full_name" autocomplete="name" v-focus /></Field>
        <Field label="Position" optional><input v-model="f.position" /></Field>
        <Field label="Area / office" :error="e.office_id"
               :help="f.new_office ? `New area: ${f.new_office}. MIS adds it when your access is granted.` : 'Not in the list? Type its name.'">
          <Combobox v-model="f.office_id" :options="areaOptions" placeholder="Choose or type your area" :invalid="!!e.office_id"
                    creatable create-label='My area is "{q}"' @create="name => { f.new_office = name; f.office_id = NEW_AREA }" />
        </Field>
        <Field label="Access needed">
          <div class="seg">
            <button type="button" :class="{ on: f.role === 'encoder' }" @click="f.role = 'encoder'">Encode for my area</button>
            <button type="button" :class="{ on: f.role === 'viewer' }" @click="f.role = 'viewer'">View only</button>
          </div>
        </Field>
        <Field label="Username" :error="e.username" help="Small letters and numbers, e.g. jdelacruz.">
          <input v-model.trim="f.username" autocomplete="username" />
        </Field>
        <div class="grid g2">
          <Field label="Password" :error="e.password"><input v-model="f.password" type="password" autocomplete="new-password" /></Field>
          <Field label="Repeat password" :error="e.password2"><input v-model="f.password2" type="password" autocomplete="new-password" /></Field>
        </div>
        <Field label="Note to MIS" optional><input v-model="f.note" maxlength="300" placeholder="e.g. new staff of the Pharmacy" /></Field>
        <div v-if="error" class="note err">{{ error }}</div>
      </div>
      <div class="panel-f">
        <button type="button" class="btn" @click="emit('close')">Cancel</button>
        <button class="btn primary" :disabled="busy">{{ busy ? 'Sending…' : 'Send request' }}</button>
      </div>
    </form>
    <div v-else class="modal">
      <div class="panel-h"><h2>Request sent</h2></div>
      <div class="panel-b"><p>MIS will review it. Sign in with <b>{{ f.username }}</b> and the password you chose once your access is granted.</p></div>
      <div class="panel-f"><button class="btn primary" @click="emit('close')">OK</button></div>
    </div>
  </div>
</template>

<script setup>
// Sign-in page: ask the administrator for an account. Areas come from areas.php (no sign-in needed).
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '../composables/useApi'
import Field from './Field.vue'
import Combobox from './Combobox.vue'

const emit = defineEmits(['close'])
const api = useApi()
const NEW_AREA = '__new'
const vFocus = { mounted: el => el.focus() }

const areas = ref([])
const f = reactive({ full_name: '', position: '', office_id: null, new_office: '', role: 'encoder', username: '', password: '', password2: '', note: '' })
const e = ref({})
const error = ref('')
const busy = ref(false)
const sent = ref(false)

const areaOptions = computed(() => {
  const list = areas.value.map(a => ({ value: a.id, label: a.name }))
  return f.new_office ? [...list, { value: NEW_AREA, label: `${f.new_office} (new area)` }] : list
})
watch(() => f.office_id, v => { if (v !== NEW_AREA) f.new_office = '' })
onMounted(async () => { try { areas.value = (await api.get('areas')).data } catch (err) { error.value = err.message } })

async function send() {
  e.value = {}
  error.value = ''
  if (f.password !== f.password2) { e.value = { password2: 'The passwords do not match.' }; return }
  busy.value = true
  try {
    const { password2, ...body } = f
    if (body.office_id === NEW_AREA) body.office_id = null
    await api.post('access-requests', body)
    sent.value = true
  } catch (err) {
    e.value = err.fields || {}
    error.value = err.message
  } finally { busy.value = false }
}
</script>
