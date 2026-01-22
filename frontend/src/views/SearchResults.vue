<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { discogsApi } from '@/api/discogs'
import VinylCard from '@/components/ui/VinylCard.vue'
import VinylCardSkeleton from '@/components/ui/VinylCardSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SearchBar from '@/components/ui/SearchBar.vue'

const route = useRoute()
const router = useRouter()

const results = ref([])
const loading = ref(false)
const error = ref(null)

const search = async (query) => {
  if (!query) return

  loading.value = true
  error.value = null

  try {
    const response = await discogsApi.search(query)
    results.value = response.results || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Search failed'
    results.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (route.query.q) {
    search(route.query.q)
  }
})

watch(() => route.query.q, (newQuery) => {
  if (newQuery) {
    search(newQuery)
  }
})

const handleVinylClick = (vinyl) => {
  router.push({ name: 'vinyl-detail', params: { id: vinyl.id } })
}
</script>

<template>
  <div class="min-h-screen bg-primary">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-primary/95 backdrop-blur border-b border-border-light">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center gap-4">
          <button 
            class="btn btn-ghost p-2"
            @click="router.push({ name: 'vinyls' })"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <SearchBar class="flex-1" />
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
      <h2 class="font-display text-xl text-text-primary mb-2">
        Search Results
      </h2>
      <p class="text-text-secondary mb-6">
        {{ results.length }} results for "{{ route.query.q }}"
      </p>

      <!-- Error -->
      <div v-if="error" class="bg-error/10 border border-error/30 rounded-lg p-4 mb-6">
        <p class="text-error">{{ error }}</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <VinylCardSkeleton v-for="i in 8" :key="i" />
      </div>

      <!-- Empty -->
      <EmptyState 
        v-else-if="results.length === 0"
        icon="search"
        title="No results found"
        description="Try a different search term or check your spelling."
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

      <!-- Results -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <article 
          v-for="vinyl in results" 
          :key="vinyl.id"
          class="card group cursor-pointer"
          @click="handleVinylClick(vinyl)"
        >
          <!-- Image -->
          <div class="relative aspect-square bg-surface overflow-hidden">
            <img 
              :src="vinyl.thumb || '/placeholder-vinyl.svg'" 
              :alt="vinyl.title"
              class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
              loading="lazy"
            >
            
            <!-- Quick Score -->
            <div 
              v-if="vinyl.insights?.quick_score"
              class="absolute top-3 left-3 px-2 py-1 rounded-md text-xs font-semibold"
              :class="vinyl.insights.quick_score >= 65 ? 'bg-success/20 text-success' : 'bg-warning/20 text-warning'"
            >
              {{ vinyl.insights.quick_score }}
            </div>

            <!-- Tags -->
            <div class="absolute bottom-3 left-3 right-3 flex flex-wrap gap-1">
              <span 
                v-for="tag in (vinyl.insights?.tags || []).slice(0, 2)" 
                :key="tag.value"
                class="text-xs px-2 py-0.5 rounded bg-black/60 text-white"
              >
                {{ tag.label }}
              </span>
            </div>
          </div>

          <!-- Content -->
          <div class="p-4">
            <p class="text-text-secondary text-sm truncate mb-1">
              {{ vinyl.artist || 'Unknown Artist' }}
            </p>
            <h3 class="text-text-primary font-medium truncate mb-3 group-hover:text-accent-lilac transition-colors">
              {{ vinyl.title }}
            </h3>

            <!-- Stats -->
            <div class="flex items-center justify-between text-sm">
              <span class="text-text-secondary">
                <span class="text-text-primary font-medium">{{ vinyl.have || 0 }}</span> have
              </span>
              <span class="text-text-secondary">
                <span class="text-text-primary font-medium">{{ vinyl.want || 0 }}</span> want
              </span>
            </div>

            <!-- Price -->
            <div v-if="vinyl.lowest_price" class="mt-3 pt-3 border-t border-border-light">
              <p class="text-lg font-semibold text-accent-coral">
                {{ vinyl.lowest_price }}€
              </p>
            </div>

            <!-- Insight -->
            <p v-if="vinyl.insights?.insight" class="mt-3 text-xs text-text-secondary line-clamp-2">
              {{ vinyl.insights.insight }}
            </p>
          </div>
        </article>
      </div>
    </main>
  </div>
</template>
