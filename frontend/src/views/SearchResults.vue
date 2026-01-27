<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { searchApi } from '@/api/search'
import SmartSearchBar from '@/components/ui/SmartSearchBar.vue'
import VinylCardSkeleton from '@/components/ui/VinylCardSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const route = useRoute()
const router = useRouter()

// State
const results = ref({ local: { data: [] }, discogs: [] })
const loading = ref(false)
const error = ref(null)
const activeFilters = ref({})
const sortBy = ref('relevance')
const showFilters = ref(false)

// Available filters
const sortOptions = [
  { value: 'relevance', label: 'Most Relevant' },
  { value: 'demand', label: 'Highest Demand' },
  { value: 'price_asc', label: 'Price: Low to High' },
  { value: 'price_desc', label: 'Price: High to Low' },
  { value: 'year', label: 'Newest First' },
  { value: 'rare', label: 'Rarest First' },
]

// Computed
const currentQuery = computed(() => route.query.q || '')
const searchType = computed(() => route.query.type || 'all')
const genreFilter = computed(() => route.query.genre || null)

const allResults = computed(() => {
  const local = results.value.local?.data || []
  const discogs = results.value.discogs || []
  
  // Combine and deduplicate by ID
  const combined = [...local]
  const localIds = new Set(local.map(r => r.id))
  
  for (const item of discogs) {
    if (!localIds.has(item.id)) {
      combined.push(item)
    }
  }
  
  return combined
})

const totalResults = computed(() => {
  return (results.value.local?.total || 0) + (results.value.discogs?.length || 0)
})

const hasResults = computed(() => allResults.value.length > 0)

// Search function
const search = async (query) => {
  if (!query) return

  loading.value = true
  error.value = null

  try {
    const params = {
      sort: sortBy.value,
      ...activeFilters.value,
    }
    
    if (genreFilter.value) {
      params.genre = genreFilter.value
    }

    const response = await searchApi.search(query, params)
    results.value = response.results || { local: { data: [] }, discogs: [] }
  } catch (err) {
    error.value = err.response?.data?.message || 'Search failed. Please try again.'
    results.value = { local: { data: [] }, discogs: [] }
  } finally {
    loading.value = false
  }
}

// Handle vinyl click
const handleVinylClick = (vinyl) => {
  // Record selection for improving search
  searchApi.recordSelection(
    currentQuery.value,
    vinyl.id,
    'vinyl',
    vinyl.title
  ).catch(console.warn)
  
  router.push({ name: 'vinyl-detail', params: { id: vinyl.id } })
}

// Handle sort change
const handleSortChange = (newSort) => {
  sortBy.value = newSort
  search(currentQuery.value)
}

// Handle filter change
const applyFilter = (key, value) => {
  if (value) {
    activeFilters.value[key] = value
  } else {
    delete activeFilters.value[key]
  }
  search(currentQuery.value)
}

// Get score color
const getScoreColor = (score) => {
  if (score >= 70) return 'bg-success/20 text-success border-success/30'
  if (score >= 50) return 'bg-warning/20 text-warning border-warning/30'
  return 'bg-text-secondary/20 text-theme-muted border-text-secondary/30'
}

// Get recommendation badge
const getRecommendationBadge = (rec) => {
  const badges = {
    'BUY': { class: 'bg-success/20 text-success', icon: '↑' },
    'HOLD': { class: 'bg-warning/20 text-warning', icon: '→' },
    'PASS': { class: 'bg-error/20 text-error', icon: '↓' },
    'AVOID': { class: 'bg-error/20 text-error', icon: '×' },
  }
  return badges[rec] || badges['HOLD']
}

// Lifecycle
onMounted(() => {
  if (currentQuery.value) {
    search(currentQuery.value)
  }
})

watch(() => route.query.q, (newQuery) => {
  if (newQuery) {
    search(newQuery)
  }
})

watch(() => route.query.genre, () => {
  if (currentQuery.value) {
    search(currentQuery.value)
  }
})
</script>

<template>
  <div class="min-h-screen bg-theme-primary">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-theme-primary/95 backdrop-blur-lg border-b border-theme-light">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center gap-4">
          <!-- Back Button -->
          <button 
            class="flex-shrink-0 p-2 rounded-lg text-theme-muted hover:text-theme-primary hover:bg-surface transition-all"
            @click="router.push({ name: 'vinyls' })"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          
          <!-- Smart Search Bar -->
          <SmartSearchBar 
            class="flex-1" 
            :initial-query="currentQuery"
          />
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
      <!-- Results Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 class="font-display text-2xl text-theme-primary">
            Search Results
          </h1>
          <p class="text-theme-muted mt-1">
            <span v-if="loading">Searching...</span>
            <span v-else-if="hasResults">
              {{ totalResults }} result{{ totalResults !== 1 ? 's' : '' }} for 
              "<span class="text-accent-lilac">{{ currentQuery }}</span>"
              <span v-if="genreFilter" class="ml-2">
                in <span class="text-accent-coral">{{ genreFilter }}</span>
              </span>
            </span>
            <span v-else>No results found</span>
          </p>
        </div>

        <!-- Controls -->
        <div class="flex items-center gap-3">
          <!-- Sort Dropdown -->
          <div class="relative">
            <select
              v-model="sortBy"
              class="appearance-none px-4 py-2 pr-10 bg-theme-secondary border border-theme-light rounded-lg
                     text-theme-primary text-sm cursor-pointer
                     focus:outline-none focus:border-accent-lilac focus:ring-2 focus:ring-accent-lilac/20
                     transition-all"
              @change="handleSortChange(sortBy)"
            >
              <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-theme-muted pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- Filter Toggle -->
          <button
            class="p-2 rounded-lg border border-theme-light text-theme-muted
                   hover:text-theme-primary hover:bg-surface hover:border-accent-lilac/50
                   transition-all"
            :class="{ 'bg-accent-lilac/10 border-accent-lilac text-accent-lilac': showFilters }"
            @click="showFilters = !showFilters"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Filters Panel -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-4"
      >
        <div v-if="showFilters" class="mb-6 p-4 bg-theme-secondary border border-theme-light rounded-xl">
          <h3 class="text-sm font-semibold text-theme-primary mb-3">Filters</h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <!-- Year Range -->
            <div>
              <label class="block text-xs text-theme-muted mb-1">Year From</label>
              <input
                type="number"
                placeholder="1950"
                min="1900"
                max="2030"
                class="input text-sm"
                @change="e => applyFilter('year_from', e.target.value)"
              >
            </div>
            <div>
              <label class="block text-xs text-theme-muted mb-1">Year To</label>
              <input
                type="number"
                placeholder="2025"
                min="1900"
                max="2030"
                class="input text-sm"
                @change="e => applyFilter('year_to', e.target.value)"
              >
            </div>
            
            <!-- Price Range -->
            <div>
              <label class="block text-xs text-theme-muted mb-1">Min Price (€)</label>
              <input
                type="number"
                placeholder="0"
                min="0"
                class="input text-sm"
                @change="e => applyFilter('price_min', e.target.value)"
              >
            </div>
            <div>
              <label class="block text-xs text-theme-muted mb-1">Max Price (€)</label>
              <input
                type="number"
                placeholder="1000"
                min="0"
                class="input text-sm"
                @change="e => applyFilter('price_max', e.target.value)"
              >
            </div>
          </div>
        </div>
      </Transition>

      <!-- Error State -->
      <div v-if="error" class="bg-error/10 border border-error/30 rounded-xl p-4 mb-6">
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-error flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-error">{{ error }}</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <VinylCardSkeleton v-for="i in 8" :key="i" />
      </div>

      <!-- Empty State -->
      <EmptyState 
        v-else-if="!hasResults && currentQuery"
        icon="search"
        title="No results found"
        description="Try different keywords or check your spelling. You can also try removing filters."
      >
        <template #action>
          <button 
            class="btn btn-primary"
            @click="router.push({ name: 'vinyls' })"
          >
            Browse Collection
          </button>
        </template>
      </EmptyState>

      <!-- Results Grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <article 
          v-for="vinyl in allResults" 
          :key="vinyl.id"
          class="vinyl-card group cursor-pointer bg-theme-secondary border border-theme-light rounded-xl overflow-hidden
                 hover:border-accent-lilac/50 hover:shadow-xl hover:shadow-accent-lilac/5
                 transition-all duration-300"
          @click="handleVinylClick(vinyl)"
        >
          <!-- Image -->
          <div class="relative aspect-square bg-theme-surface overflow-hidden">
            <img 
              :src="vinyl.thumb || vinyl.cover || '/placeholder-vinyl.svg'" 
              :alt="vinyl.title"
              class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
              loading="lazy"
              @error="(e) => e.target.src = '/placeholder-vinyl.svg'"
            >
            
            <!-- Score Badge -->
            <div 
              v-if="vinyl.insights?.quick_score"
              class="absolute top-3 left-3 px-2.5 py-1 rounded-lg text-sm font-bold border"
              :class="getScoreColor(vinyl.insights.quick_score)"
            >
              {{ vinyl.insights.quick_score }}
            </div>

            <!-- Recommendation Badge -->
            <div 
              v-if="vinyl.insights?.recommendation"
              class="absolute top-3 right-3 px-2 py-1 rounded-lg text-xs font-semibold"
              :class="getRecommendationBadge(vinyl.insights.recommendation).class"
            >
              {{ getRecommendationBadge(vinyl.insights.recommendation).icon }} {{ vinyl.insights.recommendation }}
            </div>

            <!-- Tags -->
            <div class="absolute bottom-3 left-3 right-3 flex flex-wrap gap-1.5">
              <span 
                v-for="tag in (vinyl.insights?.tags || []).slice(0, 3)" 
                :key="tag.value"
                class="text-xs px-2 py-0.5 rounded-full bg-black/70 backdrop-blur-sm text-white
                       border border-white/10"
              >
                {{ tag.label }}
              </span>
            </div>

            <!-- Source Badge -->
            <div 
              v-if="vinyl.source === 'discogs'"
              class="absolute bottom-3 right-3 px-2 py-0.5 rounded bg-accent-coral/80 text-white text-xs"
            >
              Discogs
            </div>

            <!-- Hover Overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent 
                        opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              <div class="absolute bottom-4 left-4 right-4">
                <button class="w-full py-2 rounded-lg bg-accent-coral text-white font-medium text-sm
                               hover:bg-accent-coral/90 transition-colors">
                  View Details
                </button>
              </div>
            </div>
          </div>

          <!-- Content -->
          <div class="p-4">
            <!-- Artist -->
            <p class="text-theme-muted text-sm truncate mb-0.5">
              {{ vinyl.artist || 'Unknown Artist' }}
            </p>
            
            <!-- Title -->
            <h3 class="text-theme-primary font-medium truncate mb-2 group-hover:text-accent-lilac transition-colors">
              {{ vinyl.title }}
            </h3>

            <!-- Meta -->
            <div class="flex items-center gap-2 text-xs text-theme-muted mb-3">
              <span v-if="vinyl.year">{{ vinyl.year }}</span>
              <span v-if="vinyl.year && vinyl.country">·</span>
              <span v-if="vinyl.country">{{ vinyl.country }}</span>
              <span v-if="vinyl.genres?.length" class="truncate">
                · {{ vinyl.genres[0] }}
              </span>
            </div>

            <!-- Stats Row -->
            <div class="flex items-center justify-between text-sm border-t border-theme-light pt-3">
              <div class="flex items-center gap-3">
                <span class="text-theme-muted">
                  <span class="text-theme-primary font-medium">{{ vinyl.have || 0 }}</span> have
                </span>
                <span class="text-theme-muted">
                  <span class="text-theme-primary font-medium">{{ vinyl.want || 0 }}</span> want
                </span>
              </div>
              
              <!-- Demand Ratio -->
              <div 
                v-if="vinyl.demand_ratio"
                class="text-xs px-2 py-0.5 rounded"
                :class="vinyl.demand_ratio >= 1.5 ? 'bg-accent-coral/10 text-accent-coral' : 'bg-surface text-theme-muted'"
              >
                {{ vinyl.demand_ratio }}x
              </div>
            </div>

            <!-- Price -->
            <div v-if="vinyl.lowest_price" class="mt-3 pt-3 border-t border-theme-light">
              <div class="flex items-center justify-between">
                <span class="text-theme-muted text-sm">From</span>
                <span class="text-xl font-bold text-accent-coral">
                  {{ vinyl.lowest_price }}€
                </span>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- Load More (if paginated) -->
      <div 
        v-if="hasResults && results.local?.last_page > results.local?.current_page"
        class="mt-8 text-center"
      >
        <button class="btn btn-secondary px-8">
          Load More Results
        </button>
      </div>
    </main>
  </div>
</template>

<style scoped>
.vinyl-card {
  will-change: transform, box-shadow;
}

.vinyl-card:hover {
  transform: translateY(-2px);
}
</style>
