import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'
import './style.css'

createApp(App).use(router).mount('#app')

// Chromium quirk: pressing a button while a number field has focus can scroll the page between
// mousedown and mouseup, so the click is lost. Keeping focus where it is avoids that.
document.addEventListener('mousedown', e => {
  if (e.button === 0 && e.target.closest('button, a.btn')) e.preventDefault()
}, true)
