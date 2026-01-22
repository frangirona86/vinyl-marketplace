import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useVinyls } from '@/composables/useVinyls'

// Mock the API
vi.mock('@/api/discogs', () => ({
  discogsApi: {
    getSaved: vi.fn(),
    getFilters: vi.fn(),
  }
}))

import { discogsApi } from '@/api/discogs'

describe('useVinyls', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should return all composable properties', () => {
    const {
      vinyls,
      loading,
      error,
      pagination,
      filters,
      availableFilters,
      hasActiveFilters,
      fetchVinyls,
      fetchFilters,
      setPage,
      setFilter,
      clearFilters,
      setSort,
    } = useVinyls()

    expect(vinyls).toBeDefined()
    expect(loading).toBeDefined()
    expect(error).toBeDefined()
    expect(pagination).toBeDefined()
    expect(filters).toBeDefined()
    expect(availableFilters).toBeDefined()
    expect(hasActiveFilters).toBeDefined()
    expect(fetchVinyls).toBeTypeOf('function')
    expect(fetchFilters).toBeTypeOf('function')
    expect(setPage).toBeTypeOf('function')
    expect(setFilter).toBeTypeOf('function')
    expect(clearFilters).toBeTypeOf('function')
    expect(setSort).toBeTypeOf('function')
  })

  it('should have default filter values', () => {
    const { filters } = useVinyls()

    expect(filters.genre).toBe(null)
    expect(filters.style).toBe(null)
    expect(filters.country).toBe(null)
    expect(filters.sort).toBe('demand_ratio')
    expect(filters.dir).toBe('desc')
  })

  it('should have default pagination values', () => {
    const { pagination } = useVinyls()

    expect(pagination.currentPage).toBe(1)
    expect(pagination.perPage).toBe(20)
  })

  it('hasActiveFilters should return false when no filters are set', () => {
    const { hasActiveFilters } = useVinyls()
    expect(hasActiveFilters.value).toBe(false)
  })

  it('hasActiveFilters should return true when a filter is set', () => {
    const { filters, hasActiveFilters } = useVinyls()
    
    filters.genre = 'Rock'
    expect(hasActiveFilters.value).toBe(true)
  })

  it('should fetch vinyls and update state', async () => {
    const mockResponse = {
      data: {
        data: [
          { id: 1, title: 'Test Vinyl', artist_name: 'Test Artist' }
        ],
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 1,
      }
    }

    discogsApi.getSaved.mockResolvedValue(mockResponse)

    const { vinyls, loading, fetchVinyls, pagination } = useVinyls()

    expect(loading.value).toBe(false)
    
    const fetchPromise = fetchVinyls()
    expect(loading.value).toBe(true)
    
    await fetchPromise
    
    expect(loading.value).toBe(false)
    expect(vinyls.value).toHaveLength(1)
    expect(vinyls.value[0].title).toBe('Test Vinyl')
    expect(pagination.total).toBe(1)
  })

  it('should handle fetch error', async () => {
    discogsApi.getSaved.mockRejectedValue(new Error('Network error'))

    const { error, fetchVinyls } = useVinyls()

    await fetchVinyls()

    expect(error.value).toBe('Error loading vinyls')
  })

  it('should fetch filters', async () => {
    const mockFilters = {
      genres: ['Rock', 'Electronic'],
      styles: ['House', 'Techno'],
      countries: ['US', 'UK'],
      years: { min: 1960, max: 2024 },
      labels: ['Label1'],
      formats: ['Vinyl', 'CD'],
    }

    discogsApi.getFilters.mockResolvedValue(mockFilters)

    const { availableFilters, fetchFilters } = useVinyls()

    await fetchFilters()

    expect(availableFilters.value.genres).toEqual(['Rock', 'Electronic'])
    expect(availableFilters.value.styles).toEqual(['House', 'Techno'])
  })

  it('should clear filters', () => {
    const { filters, clearFilters } = useVinyls()

    filters.genre = 'Rock'
    filters.style = 'House'
    filters.maxPrice = 100

    clearFilters()

    expect(filters.genre).toBe(null)
    expect(filters.style).toBe(null)
    expect(filters.maxPrice).toBe(null)
    expect(filters.sort).toBe('demand_ratio')
    expect(filters.dir).toBe('desc')
  })

  it('should set sort options', () => {
    const { filters, setSort } = useVinyls()

    setSort('lowest_price', 'asc')

    expect(filters.sort).toBe('lowest_price')
    expect(filters.dir).toBe('asc')
  })
})
