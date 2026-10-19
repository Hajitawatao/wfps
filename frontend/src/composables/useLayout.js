// Drawer (hamburger menu): pinned open on wide screens, slides over the page on narrow ones.
import { ref } from 'vue'

const wide = () => window.matchMedia('(min-width: 1100px)').matches
const pinned = ref(wide())
const drawerOpen = ref(wide())
window.addEventListener('resize', () => {
  const w = wide()
  if (w !== pinned.value) { pinned.value = w; drawerOpen.value = w }
})

export function useLayout() {
  const toggleDrawer = () => { drawerOpen.value = !drawerOpen.value }
  const closeOnNarrow = () => { if (!pinned.value) drawerOpen.value = false }
  return { pinned, drawerOpen, toggleDrawer, closeOnNarrow }
}
