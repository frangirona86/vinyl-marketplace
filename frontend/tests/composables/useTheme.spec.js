import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { useTheme } from '@/composables/useTheme'

describe('useTheme', () => {
  beforeEach(() => {
    // Reset DOM classes
    document.documentElement.classList.remove('dark', 'light')
    vi.clearAllMocks()
    localStorage.getItem.mockReturnValue(null)
  })

  it('should return theme utilities', () => {
    const { theme, toggleTheme, setTheme, isDark, initTheme } = useTheme()
    
    expect(theme).toBeDefined()
    expect(toggleTheme).toBeTypeOf('function')
    expect(setTheme).toBeTypeOf('function')
    expect(isDark).toBeTypeOf('function')
    expect(initTheme).toBeTypeOf('function')
  })

  it('should default to dark theme', () => {
    const { theme } = useTheme()
    expect(theme.value).toBe('dark')
  })

  it('should toggle theme from dark to light', () => {
    const { theme, toggleTheme } = useTheme()
    
    expect(theme.value).toBe('dark')
    toggleTheme()
    expect(theme.value).toBe('light')
  })

  it('should toggle theme from light to dark', () => {
    const { theme, toggleTheme, setTheme } = useTheme()
    
    setTheme('light')
    expect(theme.value).toBe('light')
    toggleTheme()
    expect(theme.value).toBe('dark')
  })

  it('should set specific theme', () => {
    const { theme, setTheme } = useTheme()
    
    setTheme('light')
    expect(theme.value).toBe('light')
    
    setTheme('dark')
    expect(theme.value).toBe('dark')
  })

  it('isDark should return correct boolean', () => {
    const { isDark, setTheme } = useTheme()
    
    setTheme('dark')
    expect(isDark()).toBe(true)
    
    setTheme('light')
    expect(isDark()).toBe(false)
  })

  it('should load theme from localStorage if available', () => {
    localStorage.getItem.mockReturnValue('light')
    
    const { initTheme, theme } = useTheme()
    initTheme()
    
    expect(localStorage.getItem).toHaveBeenCalledWith('vinyl-theme')
  })

  it('initTheme should detect system preference when no saved theme', () => {
    localStorage.getItem.mockReturnValue(null)
    
    const { initTheme } = useTheme()
    initTheme()
    
    expect(localStorage.getItem).toHaveBeenCalledWith('vinyl-theme')
  })
})
