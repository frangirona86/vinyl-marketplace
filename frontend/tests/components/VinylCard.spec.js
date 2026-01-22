import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import VinylCard from '@/components/ui/VinylCard.vue'

const mockVinyl = {
  id: 1,
  discogs_id: 12345,
  title: 'Abbey Road',
  artist_name: 'The Beatles',
  year: 1969,
  genres: ['Rock', 'Pop'],
  have: 5000,
  want: 3000,
  demand_ratio: '0.60',
  lowest_price: '25.00',
  lowest_price_currency: 'EUR',
  cover_image: 'https://example.com/cover.jpg',
  thumb: 'https://example.com/thumb.jpg',
  is_rare: false,
  is_watchlist: false,
  ai_score: 75,
  ai_recommendation: 'BUY',
}

describe('VinylCard', () => {
  it('renders vinyl title and artist', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    expect(wrapper.text()).toContain('Abbey Road')
    expect(wrapper.text()).toContain('The Beatles')
  })

  it('renders year and genres', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    expect(wrapper.text()).toContain('1969')
    expect(wrapper.text()).toContain('Rock')
  })

  it('renders have/want stats', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    expect(wrapper.text()).toContain('5000')
    expect(wrapper.text()).toContain('3000')
    expect(wrapper.text()).toContain('have')
    expect(wrapper.text()).toContain('want')
  })

  it('renders demand ratio', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    expect(wrapper.text()).toContain('0.60')
    expect(wrapper.text()).toContain('Medium') // demand label for ratio 0.5-1
  })

  it('renders price', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    expect(wrapper.text()).toContain('25')
  })

  it('renders AI recommendation badge when present', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    expect(wrapper.text()).toContain('BUY')
  })

  it('renders AI score when present', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    expect(wrapper.text()).toContain('75')
  })

  it('shows rare badge when vinyl is rare', () => {
    const rareVinyl = { ...mockVinyl, is_rare: true }
    const wrapper = mount(VinylCard, {
      props: { vinyl: rareVinyl }
    })

    expect(wrapper.text()).toContain('Rare')
  })

  it('does not show rare badge when vinyl is not rare', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    // Check that "Rare" appears only in expected places (not as a badge)
    const rareBadge = wrapper.find('[class*="bg-accent-lilac"]')
    expect(rareBadge.exists()).toBe(false)
  })

  it('emits click event when clicked', async () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    await wrapper.trigger('click')

    expect(wrapper.emitted('click')).toBeTruthy()
    expect(wrapper.emitted('click')[0]).toEqual([mockVinyl])
  })

  it('renders cover image', () => {
    const wrapper = mount(VinylCard, {
      props: { vinyl: mockVinyl }
    })

    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe(mockVinyl.thumb)
    expect(img.attributes('alt')).toBe(mockVinyl.title)
  })

  it('uses placeholder when no image', () => {
    const vinylNoImage = { ...mockVinyl, thumb: null, cover_image: null }
    const wrapper = mount(VinylCard, {
      props: { vinyl: vinylNoImage }
    })

    const img = wrapper.find('img')
    expect(img.attributes('src')).toBe('/placeholder-vinyl.svg')
  })

  it('handles missing artist gracefully', () => {
    const vinylNoArtist = { ...mockVinyl, artist_name: null }
    const wrapper = mount(VinylCard, {
      props: { vinyl: vinylNoArtist }
    })

    expect(wrapper.text()).toContain('Unknown Artist')
  })

  it('handles different demand ratios correctly', () => {
    // Very High (>= 2)
    const veryHighDemand = { ...mockVinyl, demand_ratio: '2.5' }
    let wrapper = mount(VinylCard, { props: { vinyl: veryHighDemand } })
    expect(wrapper.text()).toContain('Very High')

    // High (>= 1)
    const highDemand = { ...mockVinyl, demand_ratio: '1.5' }
    wrapper = mount(VinylCard, { props: { vinyl: highDemand } })
    expect(wrapper.text()).toContain('High')

    // Low (< 0.5)
    const lowDemand = { ...mockVinyl, demand_ratio: '0.3' }
    wrapper = mount(VinylCard, { props: { vinyl: lowDemand } })
    expect(wrapper.text()).toContain('Low')
  })

  it('applies correct recommendation badge colors', () => {
    // BUY recommendation
    let wrapper = mount(VinylCard, { props: { vinyl: mockVinyl } })
    let badge = wrapper.find('[class*="text-green-500"]')
    expect(badge.exists()).toBe(true)

    // HOLD recommendation
    const holdVinyl = { ...mockVinyl, ai_recommendation: 'HOLD' }
    wrapper = mount(VinylCard, { props: { vinyl: holdVinyl } })
    badge = wrapper.find('[class*="text-amber-500"]')
    expect(badge.exists()).toBe(true)

    // AVOID recommendation
    const avoidVinyl = { ...mockVinyl, ai_recommendation: 'AVOID' }
    wrapper = mount(VinylCard, { props: { vinyl: avoidVinyl } })
    badge = wrapper.find('[class*="text-accent-coral"]')
    expect(badge.exists()).toBe(true)
  })
})
