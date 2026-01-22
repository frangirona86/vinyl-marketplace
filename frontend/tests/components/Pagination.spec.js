import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from '@/components/ui/Pagination.vue'

describe('Pagination', () => {
  const defaultProps = {
    currentPage: 1,
    lastPage: 5,
    total: 100,
    perPage: 20,
  }

  it('renders pagination info correctly', () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    expect(wrapper.text()).toContain('Showing')
    expect(wrapper.text()).toContain('1')
    expect(wrapper.text()).toContain('20')
    expect(wrapper.text()).toContain('100')
  })

  it('renders correct page numbers', () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    expect(wrapper.text()).toContain('1')
    expect(wrapper.text()).toContain('2')
    expect(wrapper.text()).toContain('3')
  })

  it('disables previous button on first page', () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    const prevButton = wrapper.findAll('button')[0]
    expect(prevButton.attributes('disabled')).toBeDefined()
  })

  it('enables previous button when not on first page', () => {
    const wrapper = mount(Pagination, {
      props: { ...defaultProps, currentPage: 3 }
    })

    const prevButton = wrapper.findAll('button')[0]
    expect(prevButton.attributes('disabled')).toBeUndefined()
  })

  it('disables next button on last page', () => {
    const wrapper = mount(Pagination, {
      props: { ...defaultProps, currentPage: 5 }
    })

    const buttons = wrapper.findAll('button')
    const nextButton = buttons[buttons.length - 1]
    expect(nextButton.attributes('disabled')).toBeDefined()
  })

  it('enables next button when not on last page', () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    const buttons = wrapper.findAll('button')
    const nextButton = buttons[buttons.length - 1]
    expect(nextButton.attributes('disabled')).toBeUndefined()
  })

  it('emits page-change event when clicking page number', async () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    // Find page 2 button
    const buttons = wrapper.findAll('button')
    const page2Button = buttons.find(b => b.text() === '2')
    
    await page2Button.trigger('click')

    expect(wrapper.emitted('page-change')).toBeTruthy()
    expect(wrapper.emitted('page-change')[0]).toEqual([2])
  })

  it('emits page-change when clicking next', async () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    const buttons = wrapper.findAll('button')
    const nextButton = buttons[buttons.length - 1]
    
    await nextButton.trigger('click')

    expect(wrapper.emitted('page-change')).toBeTruthy()
    expect(wrapper.emitted('page-change')[0]).toEqual([2])
  })

  it('emits page-change when clicking previous', async () => {
    const wrapper = mount(Pagination, {
      props: { ...defaultProps, currentPage: 3 }
    })

    const prevButton = wrapper.findAll('button')[0]
    await prevButton.trigger('click')

    expect(wrapper.emitted('page-change')).toBeTruthy()
    expect(wrapper.emitted('page-change')[0]).toEqual([2])
  })

  it('does not emit when clicking current page', async () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    const buttons = wrapper.findAll('button')
    const page1Button = buttons.find(b => b.text() === '1')
    
    await page1Button.trigger('click')

    expect(wrapper.emitted('page-change')).toBeFalsy()
  })

  it('shows ellipsis for large page ranges', () => {
    const wrapper = mount(Pagination, {
      props: { ...defaultProps, lastPage: 20, currentPage: 10 }
    })

    expect(wrapper.text()).toContain('...')
  })

  it('calculates showing range correctly', () => {
    // Page 2 should show 21-40
    const wrapper = mount(Pagination, {
      props: { ...defaultProps, currentPage: 2 }
    })

    expect(wrapper.text()).toContain('21')
    expect(wrapper.text()).toContain('40')
  })

  it('handles last page with partial results', () => {
    // 95 total, page 5 should show 81-95
    const wrapper = mount(Pagination, {
      props: { ...defaultProps, currentPage: 5, total: 95 }
    })

    expect(wrapper.text()).toContain('81')
    expect(wrapper.text()).toContain('95')
  })

  it('highlights current page', () => {
    const wrapper = mount(Pagination, {
      props: defaultProps
    })

    const buttons = wrapper.findAll('button')
    const currentPageButton = buttons.find(b => b.text() === '1')
    
    expect(currentPageButton.classes()).toContain('bg-accent-coral')
  })
})
