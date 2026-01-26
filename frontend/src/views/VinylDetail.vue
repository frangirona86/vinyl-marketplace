<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { discogsApi } from '@/api/discogs'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const route = useRoute()
const router = useRouter()

const vinyl = ref(null)
const loading = ref(true)
const error = ref(null)
const activeVideo = ref(null)

onMounted(async () => {
  try {
    // First try to get from saved analyses (includes YouTube tracks)
    const savedResponse = await discogsApi.getSavedById(route.params.id)
    if (savedResponse.data) {
      vinyl.value = savedResponse.data
    } else {
      // Fallback to Discogs API
      const response = await discogsApi.getAnalysis(route.params.id)
      vinyl.value = response.data
    }
  } catch (err) {
    // Fallback to Discogs API if not in saved
    try {
      const response = await discogsApi.getAnalysis(route.params.id)
      vinyl.value = response.data?.release ? response.data : { ...response.data }
    } catch (err2) {
      error.value = err2.response?.data?.message || 'Error loading vinyl details'
    }
  } finally {
    loading.value = false
  }
})

const formatPrice = (price, currency = 'EUR') => {
  if (!price) return '—'
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: currency || 'EUR',
  }).format(price)
}

const goBack = () => {
  router.back()
}

const playVideo = (track) => {
  activeVideo.value = track
}

const closeVideo = () => {
  activeVideo.value = null
}

const genres = computed(() => {
  const g = vinyl.value?.genres || vinyl.value?.release?.genres
  return Array.isArray(g) ? g : []
})

const styles = computed(() => {
  const s = vinyl.value?.styles || vinyl.value?.release?.styles
  return Array.isArray(s) ? s : []
})

const youtubeTracks = computed(() => {
  const tracks = vinyl.value?.youtube_tracks
  return Array.isArray(tracks) ? tracks : []
})
</script>

<template>
  <div class="min-h-screen bg-theme-primary">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-theme-primary/95 backdrop-blur border-b border-theme-light">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center gap-4">
          <button 
            class="p-2 rounded-lg hover:bg-theme-surface transition-colors"
            @click="goBack"
          >
            <svg class="w-6 h-6 text-theme-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <h1 class="font-display text-xl text-theme-primary truncate">
            {{ vinyl?.title || vinyl?.release?.title || 'Vinyl Details' }}
          </h1>
        </div>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24">
      <LoadingSpinner size="lg" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="container mx-auto px-4 py-12">
      <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-6 text-center">
        <p class="text-red-500 mb-4">{{ error }}</p>
        <button class="px-4 py-2 bg-accent-coral text-white rounded-lg" @click="goBack">Go Back</button>
      </div>
    </div>

    <!-- Content -->
    <main v-else-if="vinyl" class="container mx-auto px-4 py-8">
      <div class="grid lg:grid-cols-3 gap-8">
        <!-- Left Column - Image -->
        <div class="lg:col-span-1">
          <div class="card-theme border rounded-xl overflow-hidden sticky top-24">
            <img 
              :src="vinyl.cover_image || vinyl.thumb || vinyl.release?.images?.[0]?.uri || '/placeholder-vinyl.svg'"
              :alt="vinyl.title"
              class="w-full aspect-square object-cover"
            >
            
            <!-- YouTube Badge -->
            <div v-if="vinyl.has_youtube" class="absolute top-4 right-4 bg-red-600 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z"/>
              </svg>
              Preview Available
            </div>
          </div>
        </div>

        <!-- Right Column - Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Title & Artist -->
          <div>
            <p class="text-accent-lilac text-lg mb-1">
              {{ vinyl.artist_name || vinyl.release?.artist_name }}
            </p>
            <h2 class="font-display text-3xl text-theme-primary mb-4">
              {{ vinyl.title || vinyl.release?.title }}
            </h2>
            
            <!-- Meta -->
            <div class="flex flex-wrap gap-3">
              <span v-if="vinyl.year || vinyl.release?.year" class="px-3 py-1 rounded-full text-sm bg-accent-lilac/20 text-accent-lilac">
                {{ vinyl.year || vinyl.release?.year }}
              </span>
              <span v-if="vinyl.country || vinyl.release?.country" class="px-3 py-1 rounded-full text-sm bg-theme-surface text-theme-muted">
                {{ vinyl.country || vinyl.release?.country }}
              </span>
              <span v-if="vinyl.label || vinyl.release?.label" class="px-3 py-1 rounded-full text-sm bg-theme-surface text-theme-muted">
                {{ vinyl.label || vinyl.release?.label }}
              </span>
            </div>
          </div>

          <!-- AI Analysis -->
          <div v-if="vinyl.ai_score" class="card-theme border rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-display text-lg text-theme-primary">AI Analysis</h3>
              <span 
                class="px-3 py-1 rounded-full text-sm font-semibold"
                :class="{
                  'bg-green-500/20 text-green-500': vinyl.ai_recommendation === 'BUY',
                  'bg-amber-500/20 text-amber-500': vinyl.ai_recommendation === 'HOLD',
                  'bg-red-500/20 text-red-500': vinyl.ai_recommendation === 'AVOID'
                }"
              >
                {{ vinyl.ai_recommendation }}
              </span>
            </div>
            
            <div class="grid grid-cols-3 gap-4 mb-4">
              <div class="text-center">
                <p class="text-3xl font-bold text-accent-lilac">{{ vinyl.ai_score }}</p>
                <p class="text-sm text-theme-muted">AI Score</p>
              </div>
              <div class="text-center">
                <p class="text-xl font-bold text-theme-primary">
                  {{ formatPrice(vinyl.recommended_price_min) }}
                </p>
                <p class="text-sm text-theme-muted">Min Price</p>
              </div>
              <div class="text-center">
                <p class="text-xl font-bold text-theme-primary">
                  {{ formatPrice(vinyl.recommended_price_max) }}
                </p>
                <p class="text-sm text-theme-muted">Max Price</p>
              </div>
            </div>

            <p v-if="vinyl.ai_analysis" class="text-theme-muted text-sm leading-relaxed">
              {{ vinyl.ai_analysis.substring(0, 500) }}{{ vinyl.ai_analysis.length > 500 ? '...' : '' }}
            </p>
          </div>

          <!-- Stats Cards -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card-theme border rounded-xl p-4 text-center">
              <p class="text-2xl font-bold text-theme-primary">{{ vinyl.have || vinyl.community?.have || 0 }}</p>
              <p class="text-sm text-theme-muted">Have</p>
            </div>
            <div class="card-theme border rounded-xl p-4 text-center">
              <p class="text-2xl font-bold text-theme-primary">{{ vinyl.want || vinyl.community?.want || 0 }}</p>
              <p class="text-sm text-theme-muted">Want</p>
            </div>
            <div class="card-theme border rounded-xl p-4 text-center">
              <p class="text-2xl font-bold text-accent-coral">
                {{ parseFloat(vinyl.demand_ratio || vinyl.analysis?.demand_ratio || 0).toFixed(2) }}
              </p>
              <p class="text-sm text-theme-muted">Demand Ratio</p>
            </div>
            <div class="card-theme border rounded-xl p-4 text-center">
              <p class="text-2xl font-bold text-green-500">
                {{ formatPrice(vinyl.lowest_price || vinyl.marketplace?.stats?.lowest_price?.value) }}
              </p>
              <p class="text-sm text-theme-muted">From</p>
            </div>
          </div>

          <!-- Genres & Styles -->
          <div v-if="genres.length || styles.length" class="card-theme border rounded-xl p-6">
            <h3 class="font-display text-lg text-theme-primary mb-4">Genres & Styles</h3>
            <div class="space-y-3">
              <div v-if="genres.length">
                <p class="text-sm text-theme-muted mb-2">Genres</p>
                <div class="flex flex-wrap gap-2">
                  <span 
                    v-for="genre in genres" 
                    :key="genre"
                    class="px-3 py-1 rounded-full text-sm bg-accent-coral/20 text-accent-coral"
                  >
                    {{ genre }}
                  </span>
                </div>
              </div>
              <div v-if="styles.length">
                <p class="text-sm text-theme-muted mb-2">Styles</p>
                <div class="flex flex-wrap gap-2">
                  <span 
                    v-for="style in styles" 
                    :key="style"
                    class="px-3 py-1 rounded-full text-sm bg-accent-lilac/20 text-accent-lilac"
                  >
                    {{ style }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- YouTube Tracks -->
          <div v-if="youtubeTracks.length" class="card-theme border rounded-xl p-6">
            <h3 class="font-display text-lg text-theme-primary mb-4 flex items-center gap-2">
              <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
              </svg>
              Listen on YouTube
            </h3>
            <div class="space-y-3">
              <div 
                v-for="track in youtubeTracks" 
                :key="track.video_id"
                class="flex items-center gap-4 p-3 rounded-lg bg-theme-surface hover:bg-theme-surface-hover transition-colors cursor-pointer group"
                @click="playVideo(track)"
              >
                <div class="relative w-24 h-16 rounded overflow-hidden flex-shrink-0">
                  <img 
                    :src="track.thumbnail" 
                    :alt="track.title"
                    class="w-full h-full object-cover"
                  >
                  <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M8 5v14l11-7z"/>
                    </svg>
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-theme-primary font-medium truncate">{{ track.title }}</p>
                  <p class="text-sm text-theme-muted truncate">{{ track.channel }}</p>
                </div>
                <a 
                  :href="track.url" 
                  target="_blank"
                  class="p-2 text-theme-muted hover:text-red-500 transition-colors"
                  @click.stop
                  title="Open in YouTube"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-4">
            <button class="flex-1 px-6 py-3 bg-accent-coral text-white font-semibold rounded-lg hover:bg-accent-coral/90 transition-colors">
              Save to Collection
            </button>
            <a 
              :href="`https://www.discogs.com/release/${route.params.id}`"
              target="_blank"
              class="px-6 py-3 border border-theme text-theme-primary font-semibold rounded-lg hover:bg-theme-surface transition-colors"
            >
              View on Discogs
            </a>
          </div>
        </div>
      </div>
    </main>

    <!-- Video Modal -->
    <Teleport to="body">
      <div 
        v-if="activeVideo" 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
        @click="closeVideo"
      >
        <div 
          class="relative w-full max-w-4xl mx-4"
          @click.stop
        >
          <button 
            class="absolute -top-12 right-0 text-white hover:text-accent-coral transition-colors"
            @click="closeVideo"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <div class="aspect-video bg-black rounded-lg overflow-hidden">
            <iframe
              :src="`${activeVideo.embed_url}?autoplay=1`"
              class="w-full h-full"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            ></iframe>
          </div>
          <p class="text-white text-center mt-4">{{ activeVideo.title }}</p>
        </div>
      </div>
    </Teleport>
  </div>
</template>
