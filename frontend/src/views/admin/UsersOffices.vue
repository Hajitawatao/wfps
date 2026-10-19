<template>
  <div class="page">
    <div class="page-h">
      <div>
        <h1>Users and offices</h1>
        <p>Who can sign in, and what each person can reach. Access depends on the level: an encoder works only on their own area.</p>
      </div>
    </div>

    <!-- access requests from the sign-in page -->
    <section v-if="accessRequests.length" class="panel requests">
      <div class="panel-h"><h2>Asking for access ({{ accessRequests.length }})</h2></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Name</th><th>Username</th><th>Area</th><th>Asked for</th><th>Sent</th><th></th></tr></thead>
          <tbody>
            <tr v-for="r in accessRequests" :key="r.id">
              <td><b>{{ r.full_name }}</b><div v-if="r.position" class="small muted">{{ r.position }}</div><div v-if="r.note" class="small muted">"{{ r.note }}"</div></td>
              <td>{{ r.username }}</td>
              <td>{{ r.office_name || r.new_office }}<span v-if="!r.office_id" class="small muted"> (new area)</span></td>
              <td>{{ LEVELS[r.role].label }}</td>
              <td class="small muted nowrap">{{ r.created_at }}</td>
              <td class="num nowrap">
                <button class="btn sm" @click="decline(r)">Decline</button>
                <button class="btn sm primary" @click="openGrant(r)">Grant access</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- forgot-password requests -->
    <section v-if="requests.length" class="panel requests">
      <div class="panel-h"><h2>Asked for a new password</h2></div>
      <table class="tbl">
        <tbody>
          <tr v-for="u in requests" :key="u.id">
            <td><b>{{ u.username }}</b> <span class="muted">{{ u.full_name }}</span></td>
            <td class="muted">{{ u.reset_requested_at }}</td>
            <td class="num"><button class="btn sm primary" @click="resetPw(u)">Give a new password</button></td>
          </tr>
        </tbody>
      </table>
    </section>

    <div class="seg tabs">
      <button :class="{ on: tab === 'offices' }" @click="tab = 'offices'">Offices ({{ offices.length }})</button>
      <button :class="{ on: tab === 'users' }" @click="tab = 'users'">Users ({{ users.length }})</button>
    </div>

    <!-- offices -->
    <section v-if="tab === 'offices'" class="panel">
      <div class="panel-h">
        <p>Add the real areas of R1MC here. Each one gets an encoder account.</p>
        <span class="spacer"></span>
        <button class="btn primary sm" @click="bulk.show = true"><Icon name="plus" :size="14" />Add areas from a list</button>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Code</th><th>Office / area</th><th class="num">Encoder accounts</th><th>Shown</th><th></th></tr></thead>
          <tbody>
            <tr v-for="o in offices" :key="o.id" :class="{ off: !+o.active }">
              <td style="width:120px"><input v-model="o.code" @change="saveOffice(o)" /></td>
              <td><input v-model="o.name" @change="saveOffice(o)" /></td>
              <td class="num">{{ o.encoders }}</td>
              <td><label class="check"><input type="checkbox" :checked="!!+o.active" @change="o.active = $event.target.checked ? 1 : 0; saveOffice(o)" />Active</label></td>
              <td><button class="icon-btn" title="Delete office" @click="delOffice(o)"><Icon name="trash" :size="16" /></button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- users -->
    <section v-else class="panel">
      <div class="panel-h">
        <input v-model="q" type="search" placeholder="Search name, username or office" style="max-width:340px" />
        <span class="spacer"></span>
        <button class="btn primary sm" @click="edit(null)"><Icon name="plus" :size="14" />Add a user</button>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Username</th><th>Name</th><th>Access level</th><th>Office</th><th>Last sign-in</th><th></th></tr></thead>
          <tbody>
            <tr v-for="u in shownUsers" :key="u.id" :class="{ off: !+u.active }">
              <td><b>{{ u.username }}</b></td>
              <td>{{ u.full_name }}<div v-if="u.position" class="small muted">{{ u.position }}</div></td>
              <td>{{ LEVELS[u.role].label }}</td>
              <td>{{ u.office_name }}</td>
              <td class="small muted">
                <span v-if="!+u.active">Disabled</span>
                <span v-else-if="isLocked(u)" class="locked">Locked after wrong passwords</span>
                <template v-else>{{ u.last_login || 'Never' }}</template>
              </td>
              <td class="num nowrap">
                <button class="btn sm" @click="edit(u)">Edit</button>
                <button class="btn sm" @click="resetPw(u)">New password</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- add areas from a list -->
    <div v-if="bulk.show" class="modal-bg" @click.self="closeBulk">
      <div class="modal wide">
        <div class="panel-h"><h2>Add areas from a list</h2></div>
        <div v-if="!bulk.result" class="panel-b">
          <p>One area per line. Start with a short code if you have one, then a comma. Without a code, one is made from the initials.</p>
          <textarea v-model="bulk.lines" rows="10" placeholder="ER, Emergency Room&#10;OPD, Out-Patient Department&#10;Department of Surgery" v-focus></textarea>
          <p class="small muted">The username of each encoder is the code in small letters. Every new account gets a temporary password, and the user sets their own at first sign-in.</p>
        </div>
        <div v-else class="panel-b">
          <div class="note ok">{{ bulk.result.created.length }} areas added. Give each office its username and temporary password. This list is shown only once, so print it now.</div>
          <table class="tbl creds">
            <thead><tr><th>Office</th><th>Username</th><th>Temporary password</th></tr></thead>
            <tbody><tr v-for="c in bulk.result.created" :key="c.username"><td>{{ c.office }}</td><td><b>{{ c.username }}</b></td><td class="pw">{{ c.password }}</td></tr></tbody>
          </table>
          <p v-if="bulk.result.skipped.length" class="small muted">Already existed, skipped: {{ bulk.result.skipped.join(', ') }}</p>
        </div>
        <div class="panel-f">
          <template v-if="!bulk.result">
            <button class="btn" @click="closeBulk">Cancel</button>
            <button class="btn primary" :disabled="!bulk.lines.trim()" @click="addBulk">Add areas</button>
          </template>
          <template v-else>
            <button class="btn" @click="printCreds"><Icon name="print" />Print list</button>
            <button class="btn primary" @click="closeBulk">Done</button>
          </template>
        </div>
      </div>
    </div>

    <!-- grant access -->
    <div v-if="g" class="modal-bg" @click.self="g = null">
      <form class="modal" @submit.prevent="grant">
        <div class="panel-h"><h2>Grant access to {{ g.req.full_name }}</h2></div>
        <div class="panel-b">
          <p class="muted">Username <b>{{ g.req.username }}</b>, with the password they chose. Set the level and area they should have.</p>
          <Field label="Access level" :help="LEVELS[g.role].desc">
            <select v-model="g.role">
              <option v-for="(l, key) in LEVELS" :key="key" :value="key">{{ l.label }}</option>
            </select>
          </Field>
          <Field label="Office / area" :optional="g.role !== 'encoder'" :help="g.new_office ? `New area: ${g.new_office}. It is added when you grant access.` : ''">
            <Combobox v-model="g.office_id" :options="areaOptions(g)" placeholder="Choose or type the area" clearable
                      creatable create-label='Add "{q}" as a new area' @create="name => { g.new_office = name; g.office_id = NEW_AREA }" />
          </Field>
        </div>
        <div class="panel-f"><button type="button" class="btn" @click="g = null">Cancel</button><button class="btn primary">Grant access</button></div>
      </form>
    </div>

    <!-- add / edit user -->
    <div v-if="m" class="modal-bg" @click.self="m = null">
      <form class="modal" @submit.prevent="saveUser">
        <div class="panel-h"><h2>{{ m.id ? 'Edit user' : 'Add a user' }}</h2></div>
        <div class="panel-b">
          <Field label="Username"><input v-model.trim="m.username" required :disabled="!!m.id" autocomplete="off" /></Field>
          <Field label="Full name"><input v-model="m.full_name" required /></Field>
          <Field label="Position" optional><input v-model="m.position" /></Field>
          <Field label="Access level" :help="LEVELS[m.role].desc">
            <select v-model="m.role">
              <option v-for="(l, key) in LEVELS" :key="key" :value="key">{{ l.label }}</option>
            </select>
          </Field>
          <Field label="Office / area" :optional="m.role !== 'encoder'"
                 :help="m.new_office ? `New area: ${m.new_office}. It is added when you save.` : 'Pick one, or type a new area name to add it.'">
            <Combobox v-model="m.office_id" :options="areaOptions(m)" placeholder="Choose or type the area" clearable
                      creatable create-label='Add "{q}" as a new area' @create="name => { m.new_office = name; m.office_id = NEW_AREA }" />
          </Field>
          <label class="check"><input type="checkbox" v-model="m.active" :true-value="1" :false-value="0" />Account can sign in</label>
          <p v-if="!m.id" class="small muted">A temporary password is created and shown after saving.</p>
        </div>
        <div class="panel-f"><button type="button" class="btn" @click="m = null">Cancel</button><button class="btn primary">Save</button></div>
      </form>
    </div>
  </div>
</template>

<script setup>
// Offices (areas) and their encoder accounts, password requests and resets. Administrator only.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '../../composables/useApi'
import { useReference } from '../../composables/useReference'
import { useDialog } from '../../composables/useDialog'
import { useToast } from '../../composables/useToast'
import { officeOptions } from '../../lib/options'
import Icon from '../../components/Icon.vue'
import Field from '../../components/Field.vue'
import Combobox from '../../components/Combobox.vue'

const api = useApi()
const { loadReference } = useReference()
const { ask } = useDialog()
const { toast, toastError } = useToast()
const refresh = () => loadReference().catch(toastError)
const isLocked = u => u.locked_until && new Date(u.locked_until.replace(' ', 'T')) > new Date()

const NEW_AREA = '__new'
// Access levels (same names as ROLES in backend/lib/auth.php)
const LEVELS = {
  encoder: { label: 'Encoder (own area)', desc: 'Encodes, edits and submits the activities of their own area only, while encoding is open. Sees only their area.' },
  viewer: { label: 'View only', desc: 'Sees the Summary, the Encoding Sheet and the printouts of all areas. Cannot change anything.' },
  procurement: { label: 'Procurement', desc: 'Gives each area its MOOE and CO budget (Budget per area). Sees the Summary, Encoding Sheet and printouts of all areas; cannot encode or approve.' },
  planning: { label: 'Planning Office', desc: 'Everything except Users and offices: all areas, review and approve, General Info, dropdown lists.' },
  admin: { label: 'Administrator (MIS)', desc: 'Everything, including Users and offices, access requests and passwords.' }
}
const vFocus = { mounted: el => el.focus() }
const tab = ref('offices')
const offices = ref([])
const users = ref([])
const q = ref('')
const m = ref(null)
const bulk = reactive({ show: false, lines: '', result: null })

const accessRequests = ref([])
const g = ref(null) // grant-access form

// the office list, plus an area being added from a form (user form or grant form)
const areaOptions = obj => obj?.new_office ? [...officeOptions(), { value: NEW_AREA, label: `${obj.new_office} (new area)` }] : officeOptions()
watch(() => m.value?.office_id, v => { if (m.value && v !== NEW_AREA) m.value.new_office = '' })
watch(() => g.value?.office_id, v => { if (g.value && v !== NEW_AREA) g.value.new_office = '' })
const requests = computed(() => users.value.filter(u => u.reset_requested_at))
const shownUsers = computed(() => {
  const s = q.value.toLowerCase()
  return users.value.filter(u => !s || [u.username, u.full_name, u.office_name].some(x => (x || '').toLowerCase().includes(s)))
})

async function load() {
  try {
    const [o, u, r] = await Promise.all([api.get('offices'), api.get('users'), api.get('access-requests')])
    offices.value = o.data
    users.value = u.data
    accessRequests.value = r.data
  } catch (e) { toastError(e) }
}
onMounted(load)

function openGrant(r) {
  g.value = { req: r, role: r.role, office_id: r.office_id || (r.new_office ? NEW_AREA : null), new_office: r.new_office || '' }
}
async function grant() {
  const { req, role, office_id, new_office } = g.value
  try {
    await api.put('access-requests', { role, office_id: office_id === NEW_AREA ? null : office_id, new_office }, { id: req.id })
    g.value = null
    toast(`${req.full_name} can now sign in as ${req.username}.`)
    load(); if (new_office) refresh()
  } catch (e) { toastError(e) }
}
async function decline(r) {
  if (!(await ask({ title: `Decline the request of ${r.full_name}?`, message: 'The request is removed. They can send a new one.', confirmText: 'Decline', tone: 'danger' }))) return
  try { await api.del('access-requests', { id: r.id }); toast('Request declined.'); load() } catch (e) { toastError(e) }
}

async function saveOffice(o) {
  try { await api.put('offices', { code: o.code, name: o.name, active: +o.active }, { id: o.id }); toast('Saved.'); refresh() }
  catch (e) { toastError(e); load() }
}
async function delOffice(o) {
  if (!(await ask({ title: `Delete ${o.name}?`, message: 'Its encoder accounts are deleted too. Offices that already have activities cannot be deleted; set them inactive instead.', confirmText: 'Delete', tone: 'danger' }))) return
  try { await api.del('offices', { id: o.id }); toast('Office deleted.'); load(); refresh() } catch (e) { toastError(e) }
}

async function addBulk() {
  try {
    bulk.result = await api.post('offices-bulk', { lines: bulk.lines })
    load(); refresh()
  } catch (e) { toastError(e) }
}
function closeBulk() { Object.assign(bulk, { show: false, lines: '', result: null }) }
function printCreds() {
  const rows = bulk.result.created.map(c => `<tr><td>${esc(c.office)}</td><td>${esc(c.username)}</td><td>${esc(c.password)}</td></tr>`).join('')
  const w = window.open('', '_blank')
  w.document.write(`<title>WFP accounts</title><style>body{font-family:Arial;font-size:13px}td,th{border:1px solid #999;padding:6px 10px;text-align:left}table{border-collapse:collapse}</style>
    <h3>R1MC WFP system accounts</h3><p>Sign in at the address given by MIS. You will be asked to set your own password.</p>
    <table><tr><th>Office</th><th>Username</th><th>Temporary password</th></tr>${rows}</table>`)
  w.document.close(); w.print()
}
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c])

function edit(u) {
  m.value = u ? { id: u.id, username: u.username, full_name: u.full_name, position: u.position, role: u.role, office_id: u.office_id, active: +u.active, new_office: '' }
    : { username: '', full_name: '', position: '', role: 'encoder', office_id: null, active: 1, new_office: '' }
}
async function saveUser() {
  const { id, ...data } = m.value
  if (data.office_id === NEW_AREA) data.office_id = null
  try {
    const r = id ? await api.put('users', data, { id }) : await api.post('users', data)
    m.value = null
    load()
    if (data.new_office) refresh()
    if (r.password) await showPassword(data.username, r.password)
    else toast('User saved.')
  } catch (e) { toastError(e) }
}
async function resetPw(u) {
  if (!(await ask({ title: `Give ${u.username} a new password?`, message: 'Their old password stops working. They will set their own at the next sign-in.', confirmText: 'Create password' }))) return
  try {
    const r = await api.put('users', { reset_password: true }, { id: u.id })
    load()
    await showPassword(u.username, r.password)
  } catch (e) { toastError(e) }
}
function showPassword(username, password) {
  return ask({ title: 'Temporary password', message: 'Give this to the user. It is shown only once; they set their own password when they sign in.',
    facts: [['Username', username], ['Password', password]], confirmText: 'Done', cancel: false })
}
</script>

<style scoped>
.tabs { margin: 0 0 14px; }
.requests { margin-bottom: 16px; border-color: var(--sun-bright); }
.modal.wide { width: min(680px, 100%); }
.creds .pw { font-family: Consolas, 'Courier New', monospace; font-size: 14px; letter-spacing: .04em; }
tr.off td { color: var(--ink-3); }
.nowrap { white-space: nowrap; }
.locked { color: var(--signal); font-weight: 600; }
</style>
