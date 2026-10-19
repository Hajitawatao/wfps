// Signed-in user and roles. Same role names as backend/lib/auth.php.
import { computed, ref } from 'vue'
import { useApi, onSignedOut } from './useApi'

const user = ref(null)
const checked = ref(false)
const api = useApi()

onSignedOut.value = () => { user.value = null }

const role = computed(() => user.value?.role)
const isAdmin = computed(() => role.value === 'admin')
const isReviewer = computed(() => ['admin', 'planning'].includes(role.value))
const canEncode = computed(() => ['admin', 'planning', 'encoder'].includes(role.value))
const mustChangePassword = computed(() => !!+(user.value?.must_change || 0))

async function loadSession() {
  try { user.value = (await api.get('me')).user } finally { checked.value = true }
  return user.value
}
async function signIn(username, password) {
  user.value = (await api.post('login', { username, password })).user
  return user.value
}
async function signOut() {
  await api.post('logout').catch(() => {})
  user.value = null
}
async function changePassword(oldPassword, newPassword) {
  user.value = (await api.post('password', { old_password: oldPassword, new_password: newPassword })).user
}
/** route meta.roles: undefined = any signed-in user */
function allowed(roles) { return !roles || roles.includes(role.value) }

export function useAuth() {
  return { user, checked, role, isAdmin, isReviewer, canEncode, mustChangePassword, loadSession, signIn, signOut, changePassword, allowed }
}
