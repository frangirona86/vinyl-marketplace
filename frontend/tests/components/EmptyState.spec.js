import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EmptyState from '@/components/ui/EmptyState.vue'

describe('EmptyState', () => {
  it('renders default title and description', () => {
    const wrapper = mount(EmptyState)

    expect(wrapper.text()).toContain('No results found')
    expect(wrapper.text()).toContain('Try adjusting your filters or search terms.')
  })

  it('renders custom title', () => {
    const wrapper = mount(EmptyState, {
      props: { title: 'Custom Title' }
    })

    expect(wrapper.text()).toContain('Custom Title')
  })

  it('renders custom description', () => {
    const wrapper = mount(EmptyState, {
      props: { description: 'Custom description text' }
    })

    expect(wrapper.text()).toContain('Custom description text')
  })

  it('renders vinyl icon by default', () => {
    const wrapper = mount(EmptyState)

    const svg = wrapper.find('svg')
    expect(svg.exists()).toBe(true)
  })

  it('renders search icon when specified', () => {
    const wrapper = mount(EmptyState, {
      props: { icon: 'search' }
    })

    const svg = wrapper.find('svg')
    expect(svg.exists()).toBe(true)
  })

  it('renders default action button', () => {
    const wrapper = mount(EmptyState)

    const button = wrapper.find('button')
    expect(button.exists()).toBe(true)
    expect(button.text()).toContain('Clear Filters')
  })

  it('emits action event when button clicked', async () => {
    const wrapper = mount(EmptyState)

    await wrapper.find('button').trigger('click')

    expect(wrapper.emitted('action')).toBeTruthy()
  })

  it('renders custom action slot', () => {
    const wrapper = mount(EmptyState, {
      slots: {
        action: '<button class="custom-btn">Custom Action</button>'
      }
    })

    expect(wrapper.find('.custom-btn').exists()).toBe(true)
    expect(wrapper.text()).toContain('Custom Action')
  })

  it('renders custom action-text slot', () => {
    const wrapper = mount(EmptyState, {
      slots: {
        'action-text': 'Reset All'
      }
    })

    expect(wrapper.text()).toContain('Reset All')
  })
})
