import { ref, computed } from 'vue'
import { searchApi } from '@/api/search'

// Session storage keys
const RECENT_SEARCHES_KEY = 'vinyl_recent_searches'
const MAX_RECENT_SEARCHES = 10

/**
 * Composable for smart search with history and suggestions
 * Note: Router navigation is handled by the component that uses this composable
 */
export function useSearch() {
  // Router will be injected by the component
  let router = null
  
  const setRouter = (r) => {
    router = r
  }
  
  // State
  const query = ref('')
  const suggestions = ref({
    artists: [],
    genres: [],
    labels: [],
    vinyls: [],
  })
  const recentSearches = ref([])
  const popularSearches = ref([])
  const isLoading = ref(false)
  const isSuggestionsOpen = ref(false)
  const selectedIndex = ref(-1)
  const debounceTimer = ref(null)
  
  // Computed
  const hasQuery = computed(() => query.value.trim().length > 0)
  const hasSuggestions = computed(() => {
    return suggestions.value.artists.length > 0 ||
           suggestions.value.genres.length > 0 ||
           suggestions.value.labels.length > 0 ||
           suggestions.value.vinyls.length > 0
  })
  
  const totalSuggestions = computed(() => {
    return suggestions.value.artists.length +
           suggestions.value.genres.length +
           suggestions.value.labels.length +
           suggestions.value.vinyls.length
  })
  
  // All items flattened for keyboard navigation
  const allItems = computed(() => {
    const items = []
    
    // Add recent searches first if no query
    if (!hasQuery.value) {
      recentSearches.value.forEach((item, idx) => {
        items.push({ ...item, _type: 'recent', _index: idx })
      })
    }
    
    // Add suggestions
    suggestions.value.artists.forEach((item, idx) => {
      items.push({ ...item, _type: 'artist', _index: idx })
    })
    suggestions.value.genres.forEach((item, idx) => {
      items.push({ ...item, _type: 'genre', _index: idx })
    })
    suggestions.value.labels.forEach((item, idx) => {
      items.push({ ...item, _type: 'label', _index: idx })
    })
    suggestions.value.vinyls.forEach((item, idx) => {
      items.push({ ...item, _type: 'vinyl', _index: idx })
    })
    
    return items
  })

  // Load recent searches from session storage
  const loadRecentSearches = () => {
    try {
      const stored = sessionStorage.getItem(RECENT_SEARCHES_KEY)
      if (stored) {
        recentSearches.value = JSON.parse(stored)
      }
    } catch (e) {
      console.warn('Failed to load recent searches:', e)
      recentSearches.value = []
    }
  }

  // Save recent searches to session storage
  const saveRecentSearches = () => {
    try {
      sessionStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(recentSearches.value))
    } catch (e) {
      console.warn('Failed to save recent searches:', e)
    }
  }

  // Add a search to recent
  const addToRecent = (searchQuery, type = 'general', result = null) => {
    const newSearch = {
      query: searchQuery,
      type,
      result,
      timestamp: Date.now(),
    }
    
    // Remove duplicates
    recentSearches.value = recentSearches.value.filter(
      s => s.query.toLowerCase() !== searchQuery.toLowerCase()
    )
    
    // Add to front
    recentSearches.value.unshift(newSearch)
    
    // Limit size
    if (recentSearches.value.length > MAX_RECENT_SEARCHES) {
      recentSearches.value = recentSearches.value.slice(0, MAX_RECENT_SEARCHES)
    }
    
    saveRecentSearches()
  }

  // Clear recent searches
  const clearRecentSearches = () => {
    recentSearches.value = []
    saveRecentSearches()
    searchApi.clearHistory().catch(console.warn)
  }

  // Remove single search from recent
  const removeFromRecent = (index) => {
    recentSearches.value.splice(index, 1)
    saveRecentSearches()
  }

  // Fetch suggestions with debounce
  const fetchSuggestions = async (searchQuery) => {
    if (!searchQuery || searchQuery.length < 1) {
      suggestions.value = { artists: [], genres: [], labels: [], vinyls: [] }
      return
    }

    isLoading.value = true
    
    try {
      const response = await searchApi.suggest(searchQuery)
      suggestions.value = response.suggestions || { artists: [], genres: [], labels: [], vinyls: [] }
      
      // Update popular if provided
      if (response.popular) {
        popularSearches.value = response.popular
      }
    } catch (error) {
      console.error('Failed to fetch suggestions:', error)
      suggestions.value = { artists: [], genres: [], labels: [], vinyls: [] }
    } finally {
      isLoading.value = false
    }
  }

  // Debounced search input handler
  const onInput = (value) => {
    query.value = value
    selectedIndex.value = -1
    
    if (debounceTimer.value) {
      clearTimeout(debounceTimer.value)
    }
    
    debounceTimer.value = setTimeout(() => {
      fetchSuggestions(value)
    }, 150) // Fast debounce for good UX
  }

  // Handle keyboard navigation
  const onKeyDown = (event) => {
    const items = allItems.value
    
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        selectedIndex.value = Math.min(selectedIndex.value + 1, items.length - 1)
        break
        
      case 'ArrowUp':
        event.preventDefault()
        selectedIndex.value = Math.max(selectedIndex.value - 1, -1)
        break
        
      case 'Enter':
        event.preventDefault()
        if (selectedIndex.value >= 0 && items[selectedIndex.value]) {
          selectItem(items[selectedIndex.value])
        } else if (hasQuery.value) {
          performSearch()
        }
        break
        
      case 'Escape':
        event.preventDefault()
        closeSuggestions()
        break
    }
  }

  // Select an item from suggestions
  const selectItem = (item) => {
    const type = item._type || item.type
    
    switch (type) {
      case 'recent':
        query.value = item.query
        performSearch()
        break
        
      case 'artist':
        addToRecent(item.name, 'artist', item)
        searchApi.recordSelection(query.value, item.id, 'artist', item.name).catch(console.warn)
        if (router) router.push({ name: 'search', query: { q: item.name, type: 'artist' } })
        break
        
      case 'genre':
        addToRecent(item.name, 'genre', item)
        searchApi.recordSelection(query.value, item.name, 'genre', item.name).catch(console.warn)
        if (router) router.push({ name: 'search', query: { q: item.name, type: 'genre', genre: item.name } })
        break
        
      case 'label':
        addToRecent(item.name, 'label', item)
        searchApi.recordSelection(query.value, item.name, 'label', item.name).catch(console.warn)
        if (router) router.push({ name: 'search', query: { q: item.name, type: 'label' } })
        break
        
      case 'vinyl':
        addToRecent(item.title, 'vinyl', item)
        searchApi.recordSelection(query.value, item.id, 'vinyl', item.title).catch(console.warn)
        if (router) router.push({ name: 'vinyl-detail', params: { id: item.id } })
        break
    }
    
    closeSuggestions()
  }

  // Perform full search
  const performSearch = () => {
    if (!hasQuery.value) return
    
    const searchQuery = query.value.trim()
    addToRecent(searchQuery, 'general')
    
    if (router) router.push({ name: 'search', query: { q: searchQuery } })
    closeSuggestions()
  }

  // Open suggestions dropdown
  const openSuggestions = () => {
    isSuggestionsOpen.value = true
    loadRecentSearches()
    
    if (!hasQuery.value) {
      // Show recent searches when empty
      fetchSuggestions('')
    }
  }

  // Close suggestions dropdown
  const closeSuggestions = () => {
    isSuggestionsOpen.value = false
    selectedIndex.value = -1
  }

  // Clear query
  const clearQuery = () => {
    query.value = ''
    suggestions.value = { artists: [], genres: [], labels: [], vinyls: [] }
    selectedIndex.value = -1
  }

  // Initialize
  loadRecentSearches()

  return {
    // State
    query,
    suggestions,
    recentSearches,
    popularSearches,
    isLoading,
    isSuggestionsOpen,
    selectedIndex,
    
    // Computed
    hasQuery,
    hasSuggestions,
    totalSuggestions,
    allItems,
    
    // Methods
    setRouter,
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
    loadRecentSearches,
  }
}

export default useSearch
