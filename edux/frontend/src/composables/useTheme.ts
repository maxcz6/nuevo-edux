import { computed, ref } from 'vue'

export type Theme = 'light' | 'dark'

const STORAGE_KEY = 'modux-theme'
const theme = ref<Theme>('light')
let initialized = false

function applyTheme(value: Theme) {
  theme.value = value
  document.documentElement.classList.toggle('dark', value === 'dark')
  document.documentElement.style.colorScheme = value
}

export function initializeTheme() {
  if (initialized || typeof window === 'undefined') return

  const stored = window.localStorage.getItem(STORAGE_KEY)
  const preferred: Theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  applyTheme(stored === 'dark' || stored === 'light' ? stored : preferred)
  initialized = true
}

export function useTheme() {
  initializeTheme()

  const isDark = computed(() => theme.value === 'dark')

  function setTheme(value: Theme) {
    applyTheme(value)
    window.localStorage.setItem(STORAGE_KEY, value)
  }

  function toggleTheme() {
    setTheme(isDark.value ? 'light' : 'dark')
  }

  return { theme, isDark, setTheme, toggleTheme }
}
