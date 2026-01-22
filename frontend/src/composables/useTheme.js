import { ref, watch, onMounted } from 'vue'

const THEME_KEY = 'vinyl-theme'

// Global reactive state
const theme = ref('dark')

export function useTheme() {
  // Initialize theme from localStorage or system preference
  const initTheme = () => {
    const savedTheme = localStorage.getItem(THEME_KEY)
    
    if (savedTheme) {
      theme.value = savedTheme
    } else {
      // Detect system preference
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      theme.value = prefersDark ? 'dark' : 'light'
    }
    
    applyTheme(theme.value)
  }

  // Apply theme to DOM
  const applyTheme = (newTheme) => {
    const root = document.documentElement
    
    if (newTheme === 'dark') {
      root.classList.add('dark')
      root.classList.remove('light')
    } else {
      root.classList.add('light')
      root.classList.remove('dark')
    }
  }

  // Toggle theme
  const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  // Set specific theme
  const setTheme = (newTheme) => {
    theme.value = newTheme
  }

  // Check if dark mode is active
  const isDark = () => theme.value === 'dark'

  // Watch for changes to persist and apply
  watch(theme, (newTheme) => {
    localStorage.setItem(THEME_KEY, newTheme)
    applyTheme(newTheme)
  })

  // Initialize on mount
  onMounted(() => {
    initTheme()
  })

  return {
    theme,
    toggleTheme,
    setTheme,
    isDark,
    initTheme,
  }
}
