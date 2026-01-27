import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useSearch } from '@/composables/useSearch'

// Mock router object
const mockRouter = {
  push: vi.fn(),
}

// Mock the search API
vi.mock('@/api/search', () => ({
  searchApi: {
    suggest: vi.fn(),
    search: vi.fn(),
    getHistory: vi.fn(),
    clearHistory: vi.fn(),
    recordSelection: vi.fn(),
  },
}))

import { searchApi } from '@/api/search'

describe('useSearch', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRouter.push.mockClear()
    // Clear sessionStorage
    sessionStorage.clear()
    // Reset timers
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // ==================== Initial State Tests ====================

  it('should return all composable properties', () => {
    const {
      query,
      suggestions,
      recentSearches,
      popularSearches,
      isLoading,
      isSuggestionsOpen,
      selectedIndex,
      hasQuery,
      hasSuggestions,
      totalSuggestions,
      allItems,
      onInput,
      onKeyDown,
      selectItem,
      performSearch,
      openSuggestions,
      closeSuggestions,
      clearQuery,
      clearRecentSearches,
      removeFromRecent,
      addToRecent,
    } = useSearch()

    expect(query).toBeDefined()
    expect(suggestions).toBeDefined()
    expect(recentSearches).toBeDefined()
    expect(popularSearches).toBeDefined()
    expect(isLoading).toBeDefined()
    expect(isSuggestionsOpen).toBeDefined()
    expect(selectedIndex).toBeDefined()
    expect(hasQuery).toBeDefined()
    expect(hasSuggestions).toBeDefined()
    expect(totalSuggestions).toBeDefined()
    expect(allItems).toBeDefined()
    expect(onInput).toBeTypeOf('function')
    expect(onKeyDown).toBeTypeOf('function')
    expect(selectItem).toBeTypeOf('function')
    expect(performSearch).toBeTypeOf('function')
    expect(openSuggestions).toBeTypeOf('function')
    expect(closeSuggestions).toBeTypeOf('function')
    expect(clearQuery).toBeTypeOf('function')
    expect(clearRecentSearches).toBeTypeOf('function')
    expect(removeFromRecent).toBeTypeOf('function')
    expect(addToRecent).toBeTypeOf('function')
  })

  it('should have empty initial state', () => {
    const { query, suggestions, recentSearches, isLoading, isSuggestionsOpen } = useSearch()

    expect(query.value).toBe('')
    expect(suggestions.value).toEqual({
      artists: [],
      genres: [],
      labels: [],
      vinyls: [],
    })
    expect(recentSearches.value).toEqual([])
    expect(isLoading.value).toBe(false)
    expect(isSuggestionsOpen.value).toBe(false)
  })

  // ==================== Computed Properties Tests ====================

  it('hasQuery should return false when query is empty', () => {
    const { hasQuery, query } = useSearch()
    
    expect(hasQuery.value).toBe(false)
    
    query.value = '   '
    expect(hasQuery.value).toBe(false)
  })

  it('hasQuery should return true when query has content', () => {
    const { hasQuery, query } = useSearch()
    
    query.value = 'beatles'
    expect(hasQuery.value).toBe(true)
  })

  it('hasSuggestions should return true when there are suggestions', () => {
    const { hasSuggestions, suggestions } = useSearch()
    
    expect(hasSuggestions.value).toBe(false)
    
    suggestions.value.artists = [{ id: 1, name: 'The Beatles' }]
    expect(hasSuggestions.value).toBe(true)
  })

  it('totalSuggestions should count all suggestion types', () => {
    const { totalSuggestions, suggestions } = useSearch()
    
    suggestions.value = {
      artists: [{ id: 1 }, { id: 2 }],
      genres: [{ name: 'Rock' }],
      labels: [],
      vinyls: [{ id: 3 }, { id: 4 }, { id: 5 }],
    }
    
    expect(totalSuggestions.value).toBe(6)
  })

  // ==================== Input Handling Tests ====================

  it('onInput should update query and fetch suggestions', async () => {
    searchApi.suggest.mockResolvedValue({
      suggestions: {
        artists: [{ id: 1, name: 'The Beatles' }],
        genres: [],
        labels: [],
        vinyls: [],
      },
      recent: [],
    })

    const { query, suggestions, onInput } = useSearch()
    
    onInput('beatles')
    expect(query.value).toBe('beatles')
    
    // Fast-forward debounce timer
    await vi.runAllTimersAsync()
    
    expect(searchApi.suggest).toHaveBeenCalledWith('beatles')
  })

  it('onInput should debounce API calls', async () => {
    searchApi.suggest.mockResolvedValue({
      suggestions: { artists: [], genres: [], labels: [], vinyls: [] },
      recent: [],
    })

    const { onInput } = useSearch()
    
    // Rapid inputs
    onInput('b')
    onInput('be')
    onInput('bea')
    onInput('beat')
    
    // Only the last one should trigger after debounce
    await vi.runAllTimersAsync()
    
    // Should only be called once with final value
    expect(searchApi.suggest).toHaveBeenCalledTimes(1)
    expect(searchApi.suggest).toHaveBeenCalledWith('beat')
  })

  // ==================== Suggestions Dropdown Tests ====================

  it('openSuggestions should open dropdown', () => {
    const { isSuggestionsOpen, openSuggestions } = useSearch()
    
    expect(isSuggestionsOpen.value).toBe(false)
    openSuggestions()
    expect(isSuggestionsOpen.value).toBe(true)
  })

  it('closeSuggestions should close dropdown and reset selection', () => {
    const { isSuggestionsOpen, selectedIndex, openSuggestions, closeSuggestions } = useSearch()
    
    openSuggestions()
    selectedIndex.value = 2
    
    closeSuggestions()
    
    expect(isSuggestionsOpen.value).toBe(false)
    expect(selectedIndex.value).toBe(-1)
  })

  // ==================== Keyboard Navigation Tests ====================

  it('onKeyDown ArrowDown should increment selectedIndex', () => {
    const { selectedIndex, suggestions, onKeyDown } = useSearch()
    
    suggestions.value.artists = [{ id: 1 }, { id: 2 }]
    
    const event = { key: 'ArrowDown', preventDefault: vi.fn() }
    
    onKeyDown(event)
    expect(selectedIndex.value).toBe(0)
    expect(event.preventDefault).toHaveBeenCalled()
    
    onKeyDown(event)
    expect(selectedIndex.value).toBe(1)
  })

  it('onKeyDown ArrowUp should decrement selectedIndex', () => {
    const { selectedIndex, suggestions, onKeyDown } = useSearch()
    
    suggestions.value.artists = [{ id: 1 }, { id: 2 }]
    selectedIndex.value = 1
    
    const event = { key: 'ArrowUp', preventDefault: vi.fn() }
    
    onKeyDown(event)
    expect(selectedIndex.value).toBe(0)
    
    onKeyDown(event)
    expect(selectedIndex.value).toBe(-1)
  })

  it('onKeyDown Escape should close suggestions', () => {
    const { isSuggestionsOpen, openSuggestions, onKeyDown } = useSearch()
    
    openSuggestions()
    expect(isSuggestionsOpen.value).toBe(true)
    
    const event = { key: 'Escape', preventDefault: vi.fn() }
    onKeyDown(event)
    
    expect(isSuggestionsOpen.value).toBe(false)
  })

  // ==================== Recent Searches Tests ====================

  it('addToRecent should add search to recent list', () => {
    const { recentSearches, addToRecent } = useSearch()
    
    addToRecent('beatles', 'general')
    
    expect(recentSearches.value).toHaveLength(1)
    expect(recentSearches.value[0].query).toBe('beatles')
    expect(recentSearches.value[0].type).toBe('general')
  })

  it('addToRecent should avoid duplicates', () => {
    const { recentSearches, addToRecent } = useSearch()
    
    addToRecent('beatles', 'general')
    addToRecent('jazz', 'genre')
    addToRecent('beatles', 'general') // Duplicate
    
    expect(recentSearches.value).toHaveLength(2)
    // Most recent should be first
    expect(recentSearches.value[0].query).toBe('beatles')
  })

  it('addToRecent should limit to max items', () => {
    const { recentSearches, addToRecent } = useSearch()
    
    // Add 15 items (max is 10)
    for (let i = 0; i < 15; i++) {
      addToRecent(`search${i}`, 'general')
    }
    
    expect(recentSearches.value).toHaveLength(10)
  })

  it('removeFromRecent should remove item at index', () => {
    const { recentSearches, addToRecent, removeFromRecent } = useSearch()
    
    addToRecent('beatles', 'general')
    addToRecent('jazz', 'genre')
    addToRecent('rock', 'genre')
    
    removeFromRecent(1)
    
    expect(recentSearches.value).toHaveLength(2)
    expect(recentSearches.value.find(s => s.query === 'jazz')).toBeUndefined()
  })

  it('clearRecentSearches should clear all recent', () => {
    searchApi.clearHistory.mockResolvedValue({})
    
    const { recentSearches, addToRecent, clearRecentSearches } = useSearch()
    
    addToRecent('beatles', 'general')
    addToRecent('jazz', 'genre')
    
    clearRecentSearches()
    
    expect(recentSearches.value).toHaveLength(0)
  })

  // ==================== Session Storage Tests ====================

  it('should persist recent searches to sessionStorage', () => {
    const { addToRecent } = useSearch()
    
    addToRecent('beatles', 'general')
    
    const stored = sessionStorage.getItem('vinyl_recent_searches')
    expect(stored).toBeTruthy()
    
    const parsed = JSON.parse(stored)
    expect(parsed).toHaveLength(1)
    expect(parsed[0].query).toBe('beatles')
  })

  it('should load recent searches from sessionStorage on init', () => {
    // Pre-populate sessionStorage
    sessionStorage.setItem('vinyl_recent_searches', JSON.stringify([
      { query: 'jazz', type: 'genre', timestamp: Date.now() },
    ]))
    
    const { recentSearches, loadRecentSearches } = useSearch()
    loadRecentSearches()
    
    expect(recentSearches.value).toHaveLength(1)
    expect(recentSearches.value[0].query).toBe('jazz')
  })

  // ==================== Clear Query Tests ====================

  it('clearQuery should reset query and suggestions', () => {
    const { query, suggestions, selectedIndex, clearQuery, onInput } = useSearch()
    
    query.value = 'beatles'
    suggestions.value.artists = [{ id: 1 }]
    selectedIndex.value = 2
    
    clearQuery()
    
    expect(query.value).toBe('')
    expect(suggestions.value.artists).toHaveLength(0)
    expect(selectedIndex.value).toBe(-1)
  })

  // ==================== Select Item Tests ====================

  it('selectItem with vinyl should record selection', async () => {
    searchApi.recordSelection.mockResolvedValue({})
    
    const { selectItem, query, setRouter } = useSearch()
    setRouter(mockRouter)
    query.value = 'beatles'
    
    await selectItem({
      _type: 'vinyl',
      id: 123,
      title: 'Abbey Road',
    })
    
    expect(searchApi.recordSelection).toHaveBeenCalledWith(
      'beatles',
      123,
      'vinyl',
      'Abbey Road'
    )
    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'vinyl-detail', params: { id: 123 } })
  })

  it('selectItem with artist should add to recent', () => {
    searchApi.recordSelection.mockResolvedValue({})
    
    const { selectItem, recentSearches, query, setRouter } = useSearch()
    setRouter(mockRouter)
    query.value = 'beatles'
    
    selectItem({
      _type: 'artist',
      id: 1,
      name: 'The Beatles',
    })
    
    expect(recentSearches.value[0].query).toBe('The Beatles')
    expect(recentSearches.value[0].type).toBe('artist')
    expect(mockRouter.push).toHaveBeenCalled()
  })

  it('selectItem with recent should perform search', () => {
    const { selectItem, query } = useSearch()
    
    selectItem({
      _type: 'recent',
      query: 'jazz',
    })
    
    expect(query.value).toBe('jazz')
  })

  // ==================== All Items Computed Tests ====================

  it('allItems should combine recent and suggestions', () => {
    const { allItems, suggestions, recentSearches, query } = useSearch()
    
    // When no query, should include recent
    recentSearches.value = [
      { query: 'beatles', type: 'general', timestamp: Date.now() },
    ]
    
    suggestions.value = {
      artists: [{ id: 1, name: 'Artist 1' }],
      genres: [{ name: 'Rock' }],
      labels: [],
      vinyls: [{ id: 2, title: 'Vinyl 1' }],
    }
    
    // Without query, recent should be first
    expect(allItems.value.length).toBeGreaterThan(0)
    expect(allItems.value[0]._type).toBe('recent')
  })
})
