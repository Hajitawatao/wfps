<template>
  <div v-if="!checked" class="loading">Loading…</div>

  <!-- ================= SIGN IN ================= -->
  <div v-else-if="!user" class="signin">
    <div class="signin-side">
      <div class="signin-head">
        <img src="/r1mc-logo-192.png" alt="Region I Medical Center seal" class="signin-seal" />
        <div>
          <div class="signin-org">Region I Medical Center</div>
          <div class="signin-motto">Service · Training · Excellence</div>
          <div class="signin-place">Arellano-Bani, Dagupan City, Pangasinan</div>
        </div>
      </div>
      <h1 class="signin-title">Work and Financial Plan Encoding System</h1>
      <div class="stripe" aria-hidden="true"></div>
    </div>
    <form class="signin-form" @submit.prevent="login">
      <h2>Sign in</h2>
      <p class="muted">Use the account given to your office.</p>
      <Field label="Username" for-id="u">
        <input id="u" v-model.trim="lf.username" autocomplete="username" autofocus required />
      </Field>
      <Field label="Password" for-id="p">
        <input id="p" v-model="lf.password" type="password" autocomplete="current-password" required />
      </Field>
      <div v-if="lf.error" class="note err">{{ lf.error }}</div>
      <button class="btn primary" :disabled="lf.busy">{{ lf.busy ? 'Signing in…' : 'Sign in' }}</button>
      <div class="signin-links">
        <button type="button" class="linkish" @click="forgot">Forgot your password?</button>
        <button type="button" class="linkish" @click="requesting = true">Request access</button>
      </div>
    </form>
    <RequestAccess v-if="requesting" @close="requesting = false" />
  </div>

  <!-- ================= APP ================= -->
  <div v-else class="shell" :class="{ open: drawerOpen, pinned }">
    <header class="topbar no-print">
      <button class="icon-btn" @click="toggleDrawer" :aria-label="drawerOpen ? 'Hide menu' : 'Show menu'" :aria-expanded="drawerOpen">
        <Icon name="menu" :size="20" />
      </button>
      <div class="app-title">
        <i class="accent" aria-hidden="true"></i>
        <span class="t">Work &amp; Financial Plan</span>
        <span class="w">R1MC</span>
      </div>
      <div class="spacer"></div>
      <label v-if="lists" class="fy">
        <Icon name="calendar" :size="16" />
        <select :value="fy" @change="changeFy($event.target.value)" aria-label="Fiscal year">
          <option v-for="y in lists.years" :key="y" :value="y">FY {{ y }}</option>
        </select>
      </label>
      <NotifyBell v-if="canEncode && lists" />
      <button class="icon-btn" @click="toggleTheme" :aria-label="theme === 'dark' ? 'Use light colors' : 'Use dark colors'" :title="theme === 'dark' ? 'Light colors' : 'Dark colors'">
        <Icon :name="theme === 'dark' ? 'sun' : 'moon'" />
      </button>
      <i class="tb-divider" aria-hidden="true"></i>
      <div class="who-wrap" ref="whoEl">
        <button class="who" :aria-expanded="userMenu" aria-haspopup="menu" @click="userMenu = !userMenu">
          <span class="avatar">{{ initials }}</span>
          <span class="who-t">
            <span class="n">{{ user.full_name }}</span>
            <span class="r">{{ roleLabel }}<template v-if="user.office_name && user.office_name !== roleLabel"> · {{ user.office_name }}</template></span>
          </span>
          <Icon name="chevron-down" :size="14" />
        </button>
        <div v-if="userMenu" class="user-menu" role="menu">
          <button role="menuitem" @click="openPw"><Icon name="key" :size="16" />Change password</button>
          <button role="menuitem" @click="logout"><Icon name="logout" :size="16" />Sign out</button>
        </div>
      </div>
    </header>

    <div class="backdrop no-print" @click="drawerOpen = false"></div>
    <aside class="drawer no-print" aria-label="Main menu">
      <button class="icon-btn close-d" @click="drawerOpen = false" aria-label="Close menu"><Icon name="close" /></button>
      <div class="brand">
        <img src="/r1mc-logo-192.png" alt="Region I Medical Center seal" class="brand-seal" />
        <div class="b1">Region I Medical Center</div>
        <div class="b2">Dagupan City, Pangasinan</div>
      </div>
      <nav @click="onNav">
        <div class="sec">Encoding</div>
        <RouterLink to="/" class="nav"><Icon name="dashboard" />Summary</RouterLink>
        <RouterLink v-if="canEncode" to="/encoding-sheet/new" class="nav"><Icon name="plus" />Encode an activity</RouterLink>
        <RouterLink to="/encoding-sheet" class="nav" :class="{ 'router-link-active': route.name === 'activity' }"><Icon name="clipboard-list" />Encoding Sheet<span v-if="notes.counts.returned" class="count warn" :title="`${notes.counts.returned} returned: fix and submit again`">{{ notes.counts.returned }}</span></RouterLink>
        <RouterLink v-if="isReviewer" to="/review" class="nav"><Icon name="circle-check" />For review<span v-if="notes.counts.review" class="count" :title="`${notes.counts.review} waiting for review`">{{ notes.counts.review }}</span></RouterLink>

        <div class="sec">Print</div>
        <RouterLink to="/print/gaa" class="nav"><Icon name="print" />Print (GAA)</RouterLink>
        <RouterLink to="/print/income" class="nav"><Icon name="print" />Print (Income)</RouterLink>

        <template v-if="seesBudgets">
          <div class="sec">Budget</div>
          <RouterLink to="/area-budgets" class="nav"><Icon name="wallet" />Budget per area</RouterLink>
        </template>

        <template v-if="isReviewer">
          <div class="sec">Planning Office</div>
          <RouterLink to="/general-info" class="nav"><Icon name="settings" />General Info</RouterLink>
          <RouterLink to="/dropdown-lists" class="nav"><Icon name="book" />Dropdown lists</RouterLink>
          <RouterLink v-if="isAdmin" to="/users" class="nav"><Icon name="building" />Users and offices</RouterLink>
        </template>
      </nav>
      <div class="d-foot">
        <button type="button" class="nav" @click="openAbout"><Icon name="book" />Guidelines</button>
      </div>
    </aside>

    <main class="main">
      <div v-if="loadError" class="note err">{{ loadError }}</div>
      <RouterView v-else-if="!mustChangePassword && lists" :key="fy + route.path" />
    </main>
  </div>

  <!-- about + how to use -->
  <div v-if="about" class="modal-bg" @click.self="about = false">
    <div class="modal about" role="dialog" aria-label="About">
      <div class="panel-h"><h2>About</h2><span class="spacer"></span><button class="icon-btn" @click="about = false" aria-label="Close"><Icon name="close" /></button></div>
      <div class="panel-b"><AboutPanel :role="user?.role" /></div>
    </div>
  </div>

  <!-- change password (forced after an admin reset) -->
  <div v-if="user && (pw.show || mustChangePassword)" class="modal-bg" @click.self="!mustChangePassword && (pw.show = false)">
    <form class="modal" @submit.prevent="savePw">
      <div class="panel-h"><h2>{{ mustChangePassword ? 'Set your own password' : 'Change password' }}</h2></div>
      <div class="panel-b">
        <p v-if="mustChangePassword">You signed in with a temporary password. Choose your own password to continue.</p>
        <Field :label="mustChangePassword ? 'Temporary password' : 'Current password'"><input v-model="pw.old" type="password" autocomplete="current-password" required /></Field>
        <Field label="New password" help="At least 8 characters."><input v-model="pw.new1" type="password" autocomplete="new-password" minlength="8" required /></Field>
        <Field label="Repeat new password"><input v-model="pw.new2" type="password" autocomplete="new-password" required /></Field>
        <div v-if="pw.error" class="note err">{{ pw.error }}</div>
      </div>
      <div class="panel-f">
        <button v-if="!mustChangePassword" type="button" class="btn" @click="pw.show = false">Cancel</button>
        <button v-else type="button" class="btn" @click="logout">Sign out</button>
        <button class="btn primary">Save password</button>
      </div>
    </form>
  </div>

  <AppDialog />

  <div class="toasts no-print" aria-live="polite">
    <div v-for="t in toasts" :key="t.id" class="toast" :class="t.type">{{ t.message }}</div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from './composables/useApi'
import { useAuth } from './composables/useAuth'
import { useReference } from './composables/useReference'
import { useLayout } from './composables/useLayout'
import { useTheme } from './composables/useTheme'
import { useDialog } from './composables/useDialog'
import { useToast } from './composables/useToast'
import Icon from './components/Icon.vue'
import Field from './components/Field.vue'
import AboutPanel from './components/AboutPanel.vue'
import AppDialog from './components/AppDialog.vue'
import RequestAccess from './components/RequestAccess.vue'
import NotifyBell from './components/NotifyBell.vue'
import { useNotifications } from './composables/useNotifications'

const route = useRoute()
const router = useRouter()
const api = useApi()
const { user, checked, isAdmin, isReviewer, canEncode, seesBudgets, mustChangePassword, loadSession, signIn, signOut, changePassword, allowed } = useAuth()
const { fy, lists, loadReference, setFy } = useReference()
const { pinned, drawerOpen, toggleDrawer, closeOnNarrow } = useLayout()
const { theme, toggleTheme } = useTheme()
const { ask } = useDialog()
const { toasts, toast } = useToast()
// bell + counts in the menu (waiting for review / returned to the area)
const { n: notes, start: startNotes, stop: stopNotes } = useNotifications()
watch(user, u => { if (!u) stopNotes() })

const about = ref(false)
const requesting = ref(false)
// user menu (top right): Change password, Sign out
const userMenu = ref(false)
const whoEl = ref(null)
const closeUserMenu = e => { if (userMenu.value && !whoEl.value?.contains(e.target)) userMenu.value = false }
const escUserMenu = e => { if (e.key === 'Escape') userMenu.value = false }
document.addEventListener('mousedown', closeUserMenu)
document.addEventListener('keydown', escUserMenu)
onBeforeUnmount(() => { document.removeEventListener('mousedown', closeUserMenu); document.removeEventListener('keydown', escUserMenu) })
const loadError = ref('')
const lf = reactive({ username: '', password: '', error: '', busy: false })
const pw = reactive({ show: false, old: '', new1: '', new2: '', error: '' })
const initials = computed(() => (user.value?.full_name || '').split(/\s+/).filter(w => /^[A-Za-z]/.test(w)).slice(0, 2).map(w => w[0].toUpperCase()).join(''))
const roleLabel = computed(() => ({ admin: 'Administrator', planning: 'Planning Office', procurement: 'Procurement', encoder: 'Encoder', viewer: 'Viewer' })[user.value?.role])

function openAbout() { about.value = true; closeOnNarrow() }
function onNav(e) { if (e.target.closest('a')) closeOnNarrow() }

/** Dropdown lists + General Info. Not allowed by the server until a temporary password is replaced. */
async function loadData() {
  loadError.value = ''
  // a page opened before signing in (deep link) may not be allowed for this role
  await router.isReady()
  if (!allowed(route.meta.roles)) await router.replace('/')
  try { await loadReference() } catch (e) { loadError.value = e.message }
  if (canEncode.value) startNotes()
}

onMounted(async () => {
  try {
    await loadSession()
    if (user.value && !mustChangePassword.value) await loadData()
  } catch (e) {
    lf.error = e.message
  }
})

async function login() {
  lf.error = ''; lf.busy = true
  try {
    await signIn(lf.username, lf.password)
    if (mustChangePassword.value) pw.old = lf.password
    else await loadData()
    lf.password = ''
  } catch (e) {
    lf.error = e.message
  } finally { lf.busy = false }
}

async function logout() {
  userMenu.value = false
  stopNotes()
  await signOut()
  pw.show = false
  about.value = false
  router.replace('/')
}

async function forgot() {
  const username = await ask({
    title: 'Forgot your password?',
    message: 'Enter your username. The system administrator (MIS) will see your request and give you a temporary password.',
    confirmText: 'Send request', input: { label: 'Username', value: lf.username }
  })
  if (!username) return
  try {
    await api.post('forgot', { username })
    await ask({ title: 'Request sent', message: 'Contact MIS to get your temporary password. You will set your own password after signing in with it.', confirmText: 'OK', cancel: false })
  } catch (e) { lf.error = e.message }
}

async function changeFy(y) {
  try { await setFy(y) } catch (e) { toast(e.message, 'error') }
}

function openPw() { userMenu.value = false; Object.assign(pw, { show: true, old: '', new1: '', new2: '', error: '' }) }
async function savePw() {
  pw.error = ''
  if (pw.new1 !== pw.new2) { pw.error = 'The new passwords do not match.'; return }
  const first = mustChangePassword.value
  try {
    await changePassword(pw.old, pw.new1)
    Object.assign(pw, { show: false, old: '', new1: '', new2: '' })
    if (first) await loadData()
    toast(first ? 'Password saved. Welcome!' : 'Password changed.')
  } catch (e) { pw.error = e.message }
}
</script>

<style>
/* one accent band at the foot of the sign-in panel */
.stripe { height: 4px; background: var(--accent); }

/* sign in */
.signin { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 4fr); background: var(--paper); }
.signin-side { position: relative; isolation: isolate; overflow: hidden; background: var(--surface); border-right: 1px solid var(--line);
  padding: 48px 56px 0; display: flex; flex-direction: column; }
/* faint pattern of WFP icons (spreadsheet, budget, charts, calendar) behind the title, tinted by the theme */
.signin-side::before { content: ''; position: absolute; inset: 0; z-index: -1; background: var(--brand-3);
  -webkit-mask: url('/signin-pattern.svg') 0 0 / 580px 540px repeat; mask: url('/signin-pattern.svg') 0 0 / 580px 540px repeat; opacity: .13; }
.signin-side::after { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(to bottom, var(--surface) 6%, transparent 40%); }
.signin-side .stripe { position: absolute; left: 0; right: 0; bottom: 0; height: 6px; }
.signin-head { display: flex; align-items: center; gap: 16px; }
.signin-head > div { display: grid; gap: 3px; }
.signin-seal { width: 76px; height: 76px; flex: none; }
.signin-org { font-size: 22px; font-weight: 700; color: var(--brand-ink); line-height: 1.2; }
.signin-motto { font-size: 11.5px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--brand-3); }
.signin-place { color: var(--ink-3); font-size: 13px; }
.signin-title { margin: clamp(40px, 9vh, 110px) 0 auto; padding-bottom: 40px; font-size: clamp(52px, 6.2vw, 100px); font-weight: 800; line-height: 1.02;
  color: var(--brand-ink); letter-spacing: -.03em; max-width: 11ch; }
.signin-form { align-self: center; justify-self: center; width: min(360px, calc(100% - 32px)); display: grid; gap: 16px; padding: 32px 0; }
.signin-form h2 { font-size: 26px; }
@media (max-width: 820px) {
  .signin { grid-template-columns: 1fr; }
  .signin-side { padding: 24px 20px 34px; }
  .signin-seal { width: 56px; height: 56px; }
  .signin-org { font-size: 18px; }
  .signin-title { margin: 28px 0 0; padding: 0; font-size: 34px; }
}

.linkish { background: none; border: 0; padding: 0; font: inherit; color: var(--brand-3); text-decoration: underline; cursor: pointer; justify-self: start; }
.modal.about { width: min(760px, 100%); }

/* top bar (design: white, 76px, gold accent beside the title) */
.topbar { position: fixed; top: 0; left: 0; right: 0; height: 76px; background: var(--surface); border-bottom: 1px solid var(--line);
  display: flex; align-items: center; gap: 16px; padding: 0 32px 0 16px; z-index: 30; transition: padding-left .2s; }
.app-title { display: flex; align-items: center; gap: 12px; white-space: nowrap; min-width: 0; }
.app-title .accent { width: 3px; height: 26px; background: var(--accent); flex: none; }
.app-title .t { font-weight: 700; font-size: 18px; color: var(--brand-ink); }
.app-title .w { font-size: 11px; color: var(--ink-3); }
.fy { display: flex; align-items: center; gap: 6px; height: 36px; padding-left: 11px; border: 1px solid var(--line); border-radius: var(--r-sm);
  background: var(--surface); color: var(--ink-3); }
.fy select { width: auto; min-height: 0; height: 34px; border: 0; padding: 0 30px 0 0; font-weight: 600; font-size: 12px; color: var(--ink);
  background-color: transparent; background-position: right 10px center; box-shadow: none !important; }
.tb-divider { width: 1px; height: 30px; background: var(--line); flex: none; }
.who-wrap { position: relative; }
.who { display: flex; align-items: center; gap: 10px; background: none; border: 0; font: inherit; color: var(--ink-3); cursor: pointer; padding: 4px 6px;
  border-radius: var(--r-sm); text-align: left; }
.who:hover, .who[aria-expanded=true] { background: var(--hover); }
.avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--brand-wash); color: var(--brand); font-weight: 700; font-size: 12px;
  display: grid; place-items: center; flex: none; }
.who-t { display: grid; gap: 3px; }
.who .n { font-weight: 600; font-size: 12px; color: var(--ink); line-height: 1.2; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.who .r { font-size: 10.5px; color: var(--ink-3); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-menu { position: absolute; right: 0; top: calc(100% + 6px); min-width: 190px; background: var(--surface); border: 1px solid var(--line);
  border-radius: var(--r-sm); box-shadow: 0 10px 30px rgba(var(--shade), .14); padding: 4px; z-index: 60; }
.user-menu button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 10px; border: 0; background: none; font: inherit;
  color: var(--ink); cursor: pointer; border-radius: 4px; text-align: left; }
.user-menu button:hover { background: var(--hover); }
.signin-links { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
@media (max-width: 520px) { .app-title { display: none; } }
@media (max-width: 760px) { .app-title .w, .who-t, .tb-divider { display: none; } .topbar { padding-right: 12px; gap: 10px; } .app-title .t { font-size: 15px; } }

/* menu: always dark, in light and dark mode (--menu* tokens in style.css) */
.drawer { position: fixed; top: 0; bottom: 0; left: 0; width: var(--drawer); background: var(--menu); color: var(--menu-ink); z-index: 50;
  transform: translateX(-100%); transition: transform .2s ease; display: flex; flex-direction: column; padding: 18px 14px 12px; gap: 12px; overflow: hidden; }
.shell.open .drawer { transform: none; }
.backdrop { position: fixed; inset: 0; background: rgba(var(--shade), .45); z-index: 40; opacity: 0; pointer-events: none; transition: opacity .2s; }
.shell.open:not(.pinned) .backdrop { opacity: 1; pointer-events: auto; }
.brand { display: flex; flex-direction: column; align-items: center; gap: 3px; text-align: center; padding-bottom: 12px; border-bottom: 1px solid var(--menu-line); }
.brand-seal { width: 56px; height: 56px; margin-bottom: 4px; }
.brand .b1 { font-weight: 700; color: var(--menu-hi); font-size: 15px; line-height: 1.2; }
.brand .b2 { font-size: 11px; color: var(--menu-ink); }
.close-d { position: absolute; top: 10px; right: 10px; color: var(--menu-ink); }
.shell.pinned .close-d { display: none; }
.drawer nav { display: flex; flex-direction: column; gap: 2px; flex: 1; min-height: 0; }
.drawer .sec { font-size: 10px; letter-spacing: .04em; text-transform: uppercase; color: var(--menu-ink); font-weight: 600; padding: 10px 0 2px; }
.drawer .sec:first-child { padding-top: 0; }
.drawer .nav { display: flex; align-items: center; gap: 11px; min-height: 36px; padding: 0 12px; border-radius: 6px; color: var(--menu-ink); text-decoration: none;
  font: inherit; font-size: 13px; background: none; border: 0; width: 100%; cursor: pointer; text-align: left; }
.drawer .nav svg { flex: none; }
/* how many items wait for this user: for review (Planning) or returned (area) */
.drawer .count { margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; background: var(--brand); color: #fff;
  font-size: 11px; font-weight: 700; line-height: 20px; text-align: center; }
.drawer .count.warn { background: var(--sun-solid); }
.drawer .nav:hover { background: var(--menu-hover); color: var(--menu-hi); }
.drawer .nav.router-link-active { background: var(--menu-active); color: var(--menu-hi); font-weight: 600; }
.d-foot { margin-top: auto; padding-top: 8px; border-top: 1px solid var(--menu-line); }
/* short screens (e.g. 1366x768 laptops with browser bars): shrink further so the menu never scrolls */
@media (max-height: 700px) {
  .brand-seal { width: 40px; height: 40px; }
  .brand .b2 { display: none; }
  .drawer .nav { min-height: 31px; }
  .drawer .sec { padding-top: 6px; }
}

.main { padding: 104px 20px 40px; transition: padding-left .2s; }
.shell.pinned.open .main { padding-left: calc(var(--drawer) + 32px); padding-right: 32px; }
.shell.pinned.open .topbar { padding-left: calc(var(--drawer) + 16px); }
@media (max-width: 820px) { .signin-side { border-right: 0; border-bottom: 1px solid var(--line); } }

@media print {
  .main, .shell.pinned.open .main { padding: 0 !important; }
}
</style>
