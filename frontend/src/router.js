// History-mode routes. meta.roles limits a page to some roles (undefined = any signed-in user).
// server.php (php -S) and the Apache VirtualHost send every unknown path to index.html so deep links work.
import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from './composables/useAuth'

const REVIEWERS = ['admin', 'planning']
const ENCODERS = ['admin', 'planning', 'encoder']

const routes = [
  { path: '/', name: 'summary', component: () => import('./views/Summary.vue'), meta: { title: 'Summary' } },
  { path: '/encoding-sheet', name: 'sheet', component: () => import('./views/EncodingSheet.vue'), meta: { title: 'Encoding Sheet' } },
  { path: '/encoding-sheet/new', name: 'new', component: () => import('./views/ActivityForm.vue'), meta: { title: 'Encode an activity', roles: ENCODERS } },
  { path: '/encoding-sheet/:id(\\d+)', name: 'activity', component: () => import('./views/ActivityForm.vue'), meta: { title: 'Activity' } },
  { path: '/review', name: 'review', component: () => import('./views/EncodingSheet.vue'), meta: { title: 'For review', roles: REVIEWERS, review: true } },
  { path: '/print/:form(gaa|income)', name: 'print', component: () => import('./views/PrintForm.vue'), meta: { title: 'Print' } },
  { path: '/general-info', name: 'general-info', component: () => import('./views/admin/GeneralInfo.vue'), meta: { title: 'General Info', roles: REVIEWERS } },
  { path: '/dropdown-lists', name: 'lists', component: () => import('./views/admin/DropdownLists.vue'), meta: { title: 'Dropdown lists', roles: REVIEWERS } },
  { path: '/area-budgets', name: 'area-budgets', component: () => import('./views/AreaBudgets.vue'), meta: { title: 'Budget per area', roles: ['admin', 'planning', 'procurement'] } },
  { path: '/users', name: 'users', component: () => import('./views/admin/UsersOffices.vue'), meta: { title: 'Users and offices', roles: ['admin', 'planning'] } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./views/NotFound.vue'), meta: { title: 'Page not found' } }
]

export const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: () => ({ top: 0 }) })

router.beforeEach(to => {
  const { user, allowed } = useAuth()
  if (!user.value) return true // App.vue shows the sign-in page until there is a session
  return allowed(to.meta.roles) ? true : { name: 'summary' }
})
router.afterEach(to => { document.title = `${to.meta.title} - R1MC WFP` })
