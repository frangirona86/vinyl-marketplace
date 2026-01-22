<script setup>
import { ref } from 'vue'

const props = defineProps({
  filters: {
    type: Object,
    required: true
  },
  availableFilters: {
    type: Object,
    required: true
  },
  hasActiveFilters: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['filter-change', 'clear-filters'])

const isOpen = ref(false)

const sortOptions = [
  { value: 'demand_ratio', label: 'Demand Ratio' },
  { value: 'lowest_price', label: 'Price' },
  { value: 'ai_score', label: 'AI Score' },
  { value: 'have', label: 'Owners' },
  { value: 'want', label: 'Wanted by' },
  { value: 'year', label: 'Year' },
  { value: 'created_at', label: 'Recently Added' },
]

const updateFilter = (key, value) => {
  const cleanValue = value === '' ? null : value
  emit('filter-change', key, cleanValue)
}

const toggleBooleanFilter = (key) => {
  const currentValue = props.filters[key]
  const newValue = currentValue === null ? true : (currentValue === true ? false : null)
  emit('filter-change', key, newValue)
}

const getBooleanFilterClass = (key) => {
  const value = props.filters[key]
  if (value === true) return 'bg-accent-coral text-white border-accent-coral'
  if (value === false) return 'bg-theme-surface text-theme-muted border-theme line-through'
  return 'bg-theme-surface text-theme-muted border-theme-light hover:border-theme'
}
</script>

<template>
  <aside class="w-full lg:w-72 shrink-0">
    <!-- Mobile Toggle -->
    <button 
      class="lg:hidden w-full flex items-center justify-between px-4 py-3 bg-theme-surface text-theme-primary border border-theme rounded-lg mb-4"
      @click="isOpen = !isOpen"
    >
      <span class="flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        Filters
        <span v-if="hasActiveFilters" class="w-2 h-2 rounded-full bg-accent-coral"></span>
      </span>
      <svg 
        class="w-5 h-5 transition-transform" 
        :class="{ 'rotate-180': isOpen }"
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Filters Content -->
    <div 
      class="space-y-6"
      :class="{ 'hidden lg:block': !isOpen }"
    >
      <!-- Clear Filters -->
      <div v-if="hasActiveFilters" class="flex justify-end">
        <button 
          class="text-sm text-accent-coral hover:text-accent-lilac transition-colors"
          @click="emit('clear-filters')"
        >
          Clear all filters
        </button>
      </div>

      <!-- Sort By -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-2">Sort By</label>
        <div class="flex gap-2">
          <select 
            class="flex-1 px-3 py-2.5 bg-theme-surface border border-theme-light rounded-lg text-theme-primary text-sm focus:outline-none focus:border-accent-lilac appearance-none cursor-pointer"
            :value="filters.sort"
            @change="updateFilter('sort', $event.target.value)"
          >
            <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
          <button 
            class="px-3 py-2 bg-theme-surface border border-theme-light rounded-lg text-theme-muted hover:text-theme-primary hover:border-theme transition-colors"
            @click="updateFilter('dir', filters.dir === 'desc' ? 'asc' : 'desc')"
            :title="filters.dir === 'desc' ? 'Descending' : 'Ascending'"
          >
            <svg 
              class="w-5 h-5 transition-transform" 
              :class="{ 'rotate-180': filters.dir === 'asc' }"
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Quick Filters -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-2">Quick Filters</label>
        <div class="flex flex-wrap gap-2">
          <button 
            class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
            :class="getBooleanFilterClass('rare')"
            @click="toggleBooleanFilter('rare')"
          >
            Rare
          </button>
          <button 
            class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
            :class="getBooleanFilterClass('inDemand')"
            @click="toggleBooleanFilter('inDemand')"
          >
            High Demand
          </button>
          <button 
            class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
            :class="getBooleanFilterClass('watchlist')"
            @click="toggleBooleanFilter('watchlist')"
          >
            Watchlist
          </button>
        </div>
      </div>

      <!-- Genre -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-2">Genre</label>
        <select 
          class="w-full px-3 py-2.5 bg-theme-surface border border-theme-light rounded-lg text-theme-primary text-sm focus:outline-none focus:border-accent-lilac appearance-none cursor-pointer"
          :value="filters.genre || ''"
          @change="updateFilter('genre', $event.target.value)"
        >
          <option value="">All Genres</option>
          <option 
            v-for="genre in availableFilters.genres" 
            :key="genre" 
            :value="genre"
          >
            {{ genre }}
          </option>
        </select>
      </div>

      <!-- Style -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-2">Style</label>
        <select 
          class="w-full px-3 py-2.5 bg-theme-surface border border-theme-light rounded-lg text-theme-primary text-sm focus:outline-none focus:border-accent-lilac appearance-none cursor-pointer"
          :value="filters.style || ''"
          @change="updateFilter('style', $event.target.value)"
        >
          <option value="">All Styles</option>
          <option 
            v-for="style in availableFilters.styles" 
            :key="style" 
            :value="style"
          >
            {{ style }}
          </option>
        </select>
      </div>

      <!-- Country -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-2">Country</label>
        <select 
          class="w-full px-3 py-2.5 bg-theme-surface border border-theme-light rounded-lg text-theme-primary text-sm focus:outline-none focus:border-accent-lilac appearance-none cursor-pointer"
          :value="filters.country || ''"
          @change="updateFilter('country', $event.target.value)"
        >
          <option value="">All Countries</option>
          <option 
            v-for="country in availableFilters.countries" 
            :key="country" 
            :value="country"
          >
            {{ country }}
          </option>
        </select>
      </div>

      <!-- Max Price -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-2">
          Max Price
          <span v-if="filters.maxPrice" class="text-theme-muted font-normal">
            ({{ filters.maxPrice }}€)
          </span>
        </label>
        <input 
          type="range"
          class="w-full accent-accent-coral"
          min="0"
          max="500"
          step="10"
          :value="filters.maxPrice || 500"
          @change="updateFilter('maxPrice', $event.target.value == 500 ? null : $event.target.value)"
        >
        <div class="flex justify-between text-xs text-theme-muted mt-1">
          <span>0€</span>
          <span>500€+</span>
        </div>
      </div>

      <!-- Min Demand Ratio -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-2">
          Min Demand Ratio
          <span v-if="filters.minDemand" class="text-theme-muted font-normal">
            ({{ filters.minDemand }})
          </span>
        </label>
        <input 
          type="range"
          class="w-full accent-accent-lilac"
          min="0"
          max="5"
          step="0.1"
          :value="filters.minDemand || 0"
          @change="updateFilter('minDemand', $event.target.value == 0 ? null : $event.target.value)"
        >
        <div class="flex justify-between text-xs text-theme-muted mt-1">
          <span>0</span>
          <span>5+</span>
        </div>
      </div>
    </div>
  </aside>
</template>
