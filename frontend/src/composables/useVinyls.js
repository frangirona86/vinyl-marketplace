import { ref, reactive, computed, watch } from 'vue'
import { discogsApi } from '@/api/discogs'

export function useVinyls() {
  const vinyls = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0,
  })

  const filters = reactive({
    genre: null,
    style: null,
    country: null,
    minPrice: null,
    maxPrice: null,
    minDemand: null,
    rare: null,
    inDemand: null,
    watchlist: null,
    sort: 'demand_ratio',
    dir: 'desc',
  })

  const availableFilters = ref({
    genres: [],
    styles: [],
    countries: [],
    years: { min: 1950, max: 2026 },
    labels: [],
    formats: [],
  })

  const hasActiveFilters = computed(() => {
    return Object.entries(filters).some(([key, value]) => {
      if (key === 'sort' || key === 'dir') return false
      return value !== null && value !== ''
    })
  })

  const buildParams = () => {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.perPage,
      sort: filters.sort,
      dir: filters.dir,
    }

    if (filters.genre) params.genre = filters.genre
    if (filters.style) params.style = filters.style
    if (filters.country) params.country = filters.country
    if (filters.maxPrice) params.max_price = filters.maxPrice
    if (filters.minDemand) params.min_demand = filters.minDemand
    if (filters.rare !== null) params.rare = filters.rare
    if (filters.inDemand !== null) params.in_demand = filters.inDemand
    if (filters.watchlist !== null) params.watchlist = filters.watchlist

    return params
  }

  const fetchVinyls = async () => {
    loading.value = true
    error.value = null

    try {
      const params = buildParams()
      const response = await discogsApi.getSaved(params)
      
      vinyls.value = response.data.data || []
      pagination.currentPage = response.data.current_page
      pagination.lastPage = response.data.last_page
      pagination.perPage = response.data.per_page
      pagination.total = response.data.total
    } catch (err) {
      error.value = err.response?.data?.message || 'Error loading vinyls'
      vinyls.value = []
    } finally {
      loading.value = false
    }
  }

  const fetchFilters = async () => {
    try {
      const response = await discogsApi.getFilters()
      availableFilters.value = response
    } catch (err) {
      console.error('Error loading filters:', err)
    }
  }

  const setPage = (page) => {
    pagination.currentPage = page
    fetchVinyls()
  }

  const setFilter = (key, value) => {
    filters[key] = value
    pagination.currentPage = 1
    fetchVinyls()
  }

  const clearFilters = () => {
    Object.keys(filters).forEach(key => {
      if (key === 'sort') {
        filters[key] = 'demand_ratio'
      } else if (key === 'dir') {
        filters[key] = 'desc'
      } else {
        filters[key] = null
      }
    })
    pagination.currentPage = 1
    fetchVinyls()
  }

  const setSort = (sortBy, direction = 'desc') => {
    filters.sort = sortBy
    filters.dir = direction
    pagination.currentPage = 1
    fetchVinyls()
  }

  return {
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
  }
}
