<script setup>
import { onMounted, computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useVinyls } from '@/composables/useVinyls'
import { useTheme } from '@/composables/useTheme'

import VinylCard from '@/components/ui/VinylCard.vue'
import VinylCardSkeleton from '@/components/ui/VinylCardSkeleton.vue'
import VinylListItem from '@/components/ui/VinylListItem.vue'
import FiltersPanel from '@/components/ui/FiltersPanel.vue'
import Pagination from '@/components/ui/Pagination.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const router = useRouter()
const { initTheme } = useTheme()

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
} = useVinyls()

const viewMode = ref('grid')
const searchQuery = ref('')

onMounted(async () => {
  initTheme()
  await Promise.all([fetchVinyls(), fetchFilters()])
})

const handleVinylClick = (vinyl) => {
  router.push({ name: 'vinyl-detail', params: { id: vinyl.discogs_id } })
}

const handleFilterChange = (key, value) => {
  setFilter(key, value)
}

const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'search', query: { q: searchQuery.value.trim() } })
  }
}

const gridCols = computed(() => {
  return viewMode.value === 'grid' 
    ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
    : 'grid-cols-1'
})
</script>

<template>
  <div class="min-h-screen bg-theme-primary transition-colors duration-300">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-theme-primary/95 backdrop-blur border-b border-theme-light transition-colors duration-300">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between gap-4">
          <!-- Logo -->
          <router-link to="/" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-accent-coral flex items-center justify-center shrink-0">
              <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="12" cy="12" r="7" fill="none" stroke="currentColor" stroke-width="0.5"/>
                <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="0.5"/>
                <circle cx="12" cy="12" r="2" fill="currentColor"/>
              </svg>
            </div>
            <h1 class="font-display text-2xl text-theme-primary hidden sm:block">
              <span class="text-accent-coral">Vinyl</span> Marketplace
            </h1>
          </router-link>

          <!-- Search Bar (Desktop) -->
          <div class="hidden md:flex flex-1 max-w-lg mx-6">
            <div class="relative w-full">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search Discogs for new vinyls..."
                class="w-full px-4 py-2.5 pl-10 bg-theme-surface border border-theme-light rounded-lg text-theme-primary text-sm placeholder:text-theme-muted focus:outline-none focus:border-accent-lilac transition-colors"
                @keyup.enter="handleSearch"
              >
              <svg 
                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-theme-muted"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <!-- Right Actions -->
          <div class="flex items-center gap-2">
            <!-- Theme Toggle -->
            <ThemeToggle />

            <!-- View Toggle -->
            <div class="flex items-center gap-1 bg-theme-surface rounded-lg p-1">
              <button 
                class="p-2 rounded-md transition-colors"
                :class="viewMode === 'grid' ? 'bg-accent-coral text-white' : 'text-theme-muted hover:text-theme-primary'"
                @click="viewMode = 'grid'"
                title="Grid view"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
              </button>
              <button 
                class="p-2 rounded-md transition-colors"
                :class="viewMode === 'list' ? 'bg-accent-coral text-white' : 'text-theme-muted hover:text-theme-primary'"
                @click="viewMode = 'list'"
                title="List view"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Mobile Search -->
        <div class="md:hidden mt-4">
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search Discogs..."
              class="w-full px-4 py-2.5 pl-10 bg-theme-surface border border-theme-light rounded-lg text-theme-primary text-sm placeholder:text-theme-muted focus:outline-none focus:border-accent-lilac transition-colors"
              @keyup.enter="handleSearch"
            >
            <svg 
              class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-theme-muted"
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
      <div class="flex flex-col lg:flex-row gap-6">
        <!-- Filters Sidebar -->
        <FiltersPanel
          :filters="filters"
          :available-filters="availableFilters"
          :has-active-filters="hasActiveFilters"
          @filter-change="handleFilterChange"
          @clear-filters="clearFilters"
        />

        <!-- Results Section -->
        <section class="flex-1 min-w-0">
          <!-- Results Header -->
          <div class="flex items-center justify-between mb-6">
            <div>
              <h2 class="font-display text-xl text-theme-primary">Collection</h2>
              <p class="text-sm text-theme-muted mt-1">
                {{ pagination.total }} vinyls found
              </p>
            </div>
          </div>

          <!-- Error State -->
          <div v-if="error" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
            <p class="text-red-500">{{ error }}</p>
            <button 
              class="mt-2 px-4 py-2 bg-theme-surface text-theme-primary border border-theme rounded-lg hover:bg-theme-surface-hover transition-colors" 
              @click="fetchVinyls"
            >
              Try again
            </button>
          </div>

          <!-- Loading State -->
          <div v-if="loading" :class="['grid gap-4', gridCols]">
            <VinylCardSkeleton v-for="i in 12" :key="i" />
          </div>

          <!-- Empty State -->
          <EmptyState 
            v-else-if="vinyls.length === 0"
            title="No vinyls found"
            description="Try adjusting your filters or add some vinyls to your collection."
            @action="clearFilters"
          />

          <!-- Results Grid/List -->
          <template v-else>
            <!-- Grid View -->
            <div v-if="viewMode === 'grid'" :class="['grid gap-4', gridCols]">
              <VinylCard
                v-for="vinyl in vinyls"
                :key="vinyl.id"
                :vinyl="vinyl"
                @click="handleVinylClick"
              />
            </div>

            <!-- List View -->
            <div v-else class="flex flex-col gap-3">
              <VinylListItem
                v-for="vinyl in vinyls"
                :key="vinyl.id"
                :vinyl="vinyl"
                @click="handleVinylClick"
              />
            </div>

            <!-- Pagination -->
            <div class="mt-8 pt-6 border-t border-theme-light">
              <Pagination
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :total="pagination.total"
                :per-page="pagination.perPage"
                @page-change="setPage"
              />
            </div>
          </template>
        </section>
      </div>
    </main>

    <!-- Stats Bar -->
    <aside class="fixed bottom-0 left-0 right-0 bg-theme-secondary/95 backdrop-blur border-t border-theme-light py-3 hidden lg:block transition-colors duration-300">
      <div class="container mx-auto px-4">
        <div class="flex items-center justify-center gap-8 text-sm">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
            <span class="text-theme-muted">High Demand:</span>
            <span class="text-theme-primary font-medium">{{ vinyls.filter(v => parseFloat(v.demand_ratio) >= 1).length }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-accent-lilac"></span>
            <span class="text-theme-muted">Rare:</span>
            <span class="text-theme-primary font-medium">{{ vinyls.filter(v => v.is_rare).length }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-accent-coral"></span>
            <span class="text-theme-muted">BUY Recommended:</span>
            <span class="text-theme-primary font-medium">{{ vinyls.filter(v => v.ai_recommendation === 'BUY').length }}</span>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>
