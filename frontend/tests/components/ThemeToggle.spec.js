import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

// Create reactive mock
const mockTheme = ref('dark')
const mockToggleTheme = vi.fn()

// Mock useTheme composable
vi.mock('@/composables/useTheme', () => ({
  useTheme: () => ({
    theme: mockTheme,
    toggleTheme: mockToggleTheme,
  })
}))

describe('ThemeToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockTheme.value = 'dark'
  })

  it('renders toggle button', () => {
    const wrapper = mount(ThemeToggle)
    
    expect(wrapper.find('button').exists()).toBe(true)
  })

  it('has correct aria-label', () => {
    const wrapper = mount(ThemeToggle)
    
    expect(wrapper.find('button').attributes('aria-label')).toBe('Toggle theme')
  })

  it('shows sun icon in dark mode', async () => {
    mockTheme.value = 'dark'
    const wrapper = mount(ThemeToggle)
    
    // In dark mode, we show sun icon (to switch to light)
    const sunIcon = wrapper.find('.text-amber-400')
    expect(sunIcon.exists()).toBe(true)
  })

  it('shows moon icon in light mode', async () => {
    mockTheme.value = 'light'
    const wrapper = mount(ThemeToggle)
    
    // In light mode, we show moon icon (to switch to dark)
    const moonIcon = wrapper.find('.text-indigo-500')
    expect(moonIcon.exists()).toBe(true)
  })

  it('calls toggleTheme when clicked', async () => {
    const wrapper = mount(ThemeToggle)
    
    await wrapper.find('button').trigger('click')
    
    expect(mockToggleTheme).toHaveBeenCalledTimes(1)
  })

  it('has correct title in dark mode', async () => {
    mockTheme.value = 'dark'
    const wrapper = mount(ThemeToggle)
    
    expect(wrapper.find('button').attributes('title')).toBe('Switch to light mode')
  })

  it('has correct title in light mode', async () => {
    mockTheme.value = 'light'
    const wrapper = mount(ThemeToggle)
    
    expect(wrapper.find('button').attributes('title')).toBe('Switch to dark mode')
  })
})
