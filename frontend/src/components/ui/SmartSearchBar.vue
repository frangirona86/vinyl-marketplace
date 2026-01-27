<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSearch } from '@/composables/useSearch'

const props = defineProps({
  placeholder: {
    type: String,
    default: 'Search vinyls, artists, genres...'
  },
  autofocus: {
    type: Boolean,
    default: false
  },
  initialQuery: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['search', 'select'])

const router = useRouter()

const {
  query,
  suggestions,
  recentSearches,
  isLoading,
  isSuggestionsOpen,
  selectedIndex,
  hasQuery,
  hasSuggestions,
  allItems,
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
} = useSearch()

// Set router for navigation
setRouter(router)

const inputRef = ref(null)
const containerRef = ref(null)

// Set initial query
watch(() => props.initialQuery, (newVal) => {
  if (newVal) {
    query.value = newVal
  }
}, { immediate: true })

// Handle click outside
const handleClickOutside = (event) => {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    closeSuggestions()
  }
}

// Handle input change
const handleInput = (event) => {
  onInput(event.target.value)
}

// Handle focus
const handleFocus = () => {
  openSuggestions()
}

// Handle item select
const handleSelect = (item) => {
  selectItem(item)
  emit('select', item)
}

// Handle search
const handleSearch = () => {
  performSearch()
  emit('search', query.value)
}

// Handle clear
const handleClear = () => {
  clearQuery()
  inputRef.value?.focus()
}

// Get icon for item type
const getTypeIcon = (type) => {
  const icons = {
    artist: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />`,
    genre: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />`,
    label: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />`,
    vinyl: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />`,
    recent: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />`,
  }
  return icons[type] || icons.vinyl
}

// Mount/unmount
onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  if (props.autofocus) {
    nextTick(() => inputRef.value?.focus())
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div ref="containerRef" class="smart-search relative w-full">
    <!-- Search Input -->
    <div 
      class="search-input-wrapper relative"
      :class="{ 'is-focused': isSuggestionsOpen }"
    >
      <!-- Search Icon -->
      <div class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none">
        <svg 
          v-if="!isLoading"
          class="w-5 h-5 text-theme-muted transition-colors"
          :class="{ 'text-accent-lilac': isSuggestionsOpen }"
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <!-- Loading Spinner -->
        <svg 
          v-else
          class="w-5 h-5 text-accent-lilac animate-spin"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
      </div>

      <!-- Input -->
      <input
        ref="inputRef"
        :value="query"
        type="text"
        :placeholder="placeholder"
        class="w-full h-12 pl-12 pr-12 bg-theme-secondary border border-theme-light rounded-xl
               text-theme-primary placeholder:text-theme-muted
               focus:outline-none focus:border-accent-lilac focus:ring-2 focus:ring-accent-lilac/20
               transition-all duration-200"
        autocomplete="off"
        @input="handleInput"
        @focus="handleFocus"
        @keydown="onKeyDown"
      >

      <!-- Clear Button -->
      <button 
        v-if="hasQuery"
        class="absolute right-4 top-1/2 -translate-y-1/2 p-1 rounded-full
               text-theme-muted hover:text-theme-primary hover:bg-theme-surface
               transition-all duration-150"
        @click="handleClear"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Suggestions Dropdown -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div 
        v-if="isSuggestionsOpen && (hasSuggestions || recentSearches.length > 0 || !hasQuery)"
        class="suggestions-dropdown absolute top-full left-0 right-0 mt-2 
               bg-theme-secondary border border-theme-light rounded-xl shadow-2xl
               overflow-hidden z-50 max-h-[70vh] overflow-y-auto"
      >
        <!-- Recent Searches -->
        <div v-if="!hasQuery && recentSearches.length > 0" class="suggestions-section">
          <div class="flex items-center justify-between px-4 py-2 border-b border-theme-light">
            <span class="text-xs font-semibold text-theme-muted uppercase tracking-wider">
              Recent Searches
            </span>
            <button 
              class="text-xs text-accent-lilac hover:text-accent-coral transition-colors"
              @click="clearRecentSearches"
            >
              Clear all
            </button>
          </div>
          <ul>
            <li 
              v-for="(item, index) in recentSearches.slice(0, 5)" 
              :key="'recent-' + index"
              class="suggestion-item group"
              :class="{ 'is-selected': selectedIndex === index }"
              @click="handleSelect({ ...item, _type: 'recent' })"
            >
              <div class="flex items-center gap-3 px-4 py-3 cursor-pointer
                          hover:bg-theme-surface transition-colors"
                   :class="{ 'bg-theme-surface': selectedIndex === index }">
                <svg class="w-4 h-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="flex-1 text-theme-primary">{{ item.query }}</span>
                <span v-if="item.type !== 'general'" class="text-xs text-theme-muted capitalize">
                  {{ item.type }}
                </span>
                <button 
                  class="p-1 opacity-0 group-hover:opacity-100 text-theme-muted hover:text-accent-coral transition-all"
                  @click.stop="removeFromRecent(index)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </li>
          </ul>
        </div>

        <!-- Artists -->
        <div v-if="suggestions.artists.length > 0" class="suggestions-section">
          <div class="px-4 py-2 border-b border-theme-light">
            <span class="text-xs font-semibold text-theme-muted uppercase tracking-wider flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Artists
            </span>
          </div>
          <ul>
            <li 
              v-for="(artist, index) in suggestions.artists" 
              :key="'artist-' + artist.id"
              class="suggestion-item"
              @click="handleSelect({ ...artist, _type: 'artist' })"
            >
              <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-theme-surface transition-colors">
                <div class="w-10 h-10 rounded-full bg-theme-surface overflow-hidden flex-shrink-0">
                  <img 
                    v-if="artist.thumbnail" 
                    :src="artist.thumbnail" 
                    :alt="artist.name"
                    class="w-full h-full object-cover"
                  >
                  <div v-else class="w-full h-full flex items-center justify-center bg-accent-lilac/20">
                    <svg class="w-5 h-5 text-accent-lilac" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-theme-primary font-medium truncate">{{ artist.name }}</p>
                  <p class="text-xs text-theme-muted">
                    {{ artist.vinyl_count }} vinyl{{ artist.vinyl_count !== 1 ? 's' : '' }}
                    <span v-if="artist.avg_demand" class="ml-2">
                      · {{ artist.avg_demand }} avg demand
                    </span>
                  </p>
                </div>
                <svg class="w-4 h-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </div>
            </li>
          </ul>
        </div>

        <!-- Genres -->
        <div v-if="suggestions.genres.length > 0" class="suggestions-section">
          <div class="px-4 py-2 border-b border-theme-light">
            <span class="text-xs font-semibold text-theme-muted uppercase tracking-wider flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
              </svg>
              Genres
            </span>
          </div>
          <ul class="flex flex-wrap gap-2 p-4">
            <li 
              v-for="genre in suggestions.genres" 
              :key="'genre-' + genre.name"
            >
              <button 
                class="px-3 py-1.5 rounded-full text-sm font-medium
                       bg-accent-lilac/10 text-accent-lilac border border-accent-lilac/30
                       hover:bg-accent-lilac hover:text-white
                       transition-all duration-200"
                @click="handleSelect({ ...genre, _type: 'genre' })"
              >
                {{ genre.name }}
                <span class="ml-1 opacity-70">({{ genre.count }})</span>
              </button>
            </li>
          </ul>
        </div>

        <!-- Labels -->
        <div v-if="suggestions.labels.length > 0" class="suggestions-section">
          <div class="px-4 py-2 border-b border-theme-light">
            <span class="text-xs font-semibold text-theme-muted uppercase tracking-wider flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
              Labels
            </span>
          </div>
          <ul>
            <li 
              v-for="label in suggestions.labels" 
              :key="'label-' + label.name"
              class="suggestion-item"
              @click="handleSelect({ ...label, _type: 'label' })"
            >
              <div class="flex items-center gap-3 px-4 py-2 cursor-pointer hover:bg-theme-surface transition-colors">
                <span class="text-theme-primary">{{ label.name }}</span>
                <span class="text-xs text-theme-muted">({{ label.count }} releases)</span>
              </div>
            </li>
          </ul>
        </div>

        <!-- Vinyls -->
        <div v-if="suggestions.vinyls.length > 0" class="suggestions-section">
          <div class="px-4 py-2 border-b border-theme-light">
            <span class="text-xs font-semibold text-theme-muted uppercase tracking-wider flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
              </svg>
              Vinyls
            </span>
          </div>
          <ul>
            <li 
              v-for="vinyl in suggestions.vinyls" 
              :key="'vinyl-' + vinyl.id"
              class="suggestion-item"
              @click="handleSelect({ ...vinyl, _type: 'vinyl' })"
            >
              <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-theme-surface transition-colors">
                <!-- Thumbnail -->
                <div class="w-12 h-12 rounded-lg bg-theme-surface overflow-hidden flex-shrink-0">
                  <img 
                    v-if="vinyl.thumb" 
                    :src="vinyl.thumb" 
                    :alt="vinyl.title"
                    class="w-full h-full object-cover"
                    loading="lazy"
                  >
                  <div v-else class="w-full h-full flex items-center justify-center bg-accent-coral/10">
                    <svg class="w-6 h-6 text-accent-coral" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                  </div>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <p class="text-theme-primary font-medium truncate">{{ vinyl.title }}</p>
                  <p class="text-sm text-theme-muted truncate">
                    {{ vinyl.artist }}
                    <span v-if="vinyl.year" class="ml-1">({{ vinyl.year }})</span>
                  </p>
                </div>

                <!-- Stats -->
                <div class="flex flex-col items-end gap-1">
                  <div v-if="vinyl.price" class="text-accent-coral font-semibold text-sm">
                    {{ vinyl.price }}€
                  </div>
                  <div class="flex items-center gap-2 text-xs text-theme-muted">
                    <span v-if="vinyl.is_rare" class="text-accent-lilac">Rare</span>
                    <span v-if="vinyl.demand_ratio >= 1.5" class="text-accent-coral">Hot</span>
                  </div>
                </div>
              </div>
            </li>
          </ul>
        </div>

        <!-- Search All -->
        <div v-if="hasQuery" class="border-t border-theme-light">
          <button 
            class="w-full flex items-center justify-center gap-2 px-4 py-3
                   text-accent-lilac font-medium hover:bg-theme-surface transition-colors"
            @click="handleSearch"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            Search all for "{{ query }}"
          </button>
        </div>

        <!-- Empty State -->
        <div 
          v-if="hasQuery && !hasSuggestions && !isLoading"
          class="px-4 py-8 text-center"
        >
          <svg class="w-12 h-12 mx-auto text-theme-muted mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-theme-muted text-sm">No suggestions found</p>
          <p class="text-theme-muted text-xs mt-1">Press Enter to search anyway</p>
        </div>

        <!-- Initial State -->
        <div 
          v-if="!hasQuery && recentSearches.length === 0"
          class="px-4 py-8 text-center"
        >
          <svg class="w-12 h-12 mx-auto text-accent-lilac mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <p class="text-theme-primary font-medium">Start typing to search</p>
          <p class="text-theme-muted text-sm mt-1">Find vinyls, artists, genres, and labels</p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.smart-search {
  --dropdown-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.search-input-wrapper.is-focused {
  transform: scale(1.01);
}

.suggestions-dropdown {
  box-shadow: var(--dropdown-shadow);
}

.suggestions-section:not(:last-child) {
  border-bottom: 1px solid var(--color-border-light, #444);
}

/* Custom scrollbar */
.suggestions-dropdown::-webkit-scrollbar {
  width: 6px;
}

.suggestions-dropdown::-webkit-scrollbar-track {
  background: transparent;
}

.suggestions-dropdown::-webkit-scrollbar-thumb {
  background: var(--color-text-secondary, #888);
  border-radius: 3px;
}

.suggestions-dropdown::-webkit-scrollbar-thumb:hover {
  background: var(--color-accent-lilac, #9A77FF);
}
</style>
