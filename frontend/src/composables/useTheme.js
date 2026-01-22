import { ref, watch, onMounted } from 'vue'

const THEME_KEY = 'vinyl-theme'

// Estado global reactivo
const theme = ref('dark')

export function useTheme() {
  // Inicializar tema desde localStorage o preferencia del sistema
  const initTheme = () => {
    const savedTheme = localStorage.getItem(THEME_KEY)
    
    if (savedTheme) {
      theme.value = savedTheme
    } else {
      // Detectar preferencia del sistema
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      theme.value = prefersDark ? 'dark' : 'light'
    }
    
    applyTheme(theme.value)
  }

  // Aplicar tema al DOM
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

  // Cambiar tema
  const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  // Establecer tema específico
  const setTheme = (newTheme) => {
    theme.value = newTheme
  }

  // Computed para saber si es dark mode
  const isDark = () => theme.value === 'dark'

  // Watch para persistir y aplicar cambios
  watch(theme, (newTheme) => {
    localStorage.setItem(THEME_KEY, newTheme)
    applyTheme(newTheme)
  })

  // Inicializar en el montaje
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
