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

onMounted(async () => {
  try {
    const response = await discogsApi.getAnalysis(route.params.id)
    vinyl.value = response.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Error loading vinyl details'
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
</script>

<template>
  <div class="min-h-screen bg-primary">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-primary/95 backdrop-blur border-b border-border-light">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center gap-4">
          <button 
            class="btn btn-ghost p-2"
            @click="goBack"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <h1 class="font-display text-xl text-text-primary truncate">
            {{ vinyl?.release?.title || 'Vinyl Details' }}
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
      <div class="bg-error/10 border border-error/30 rounded-lg p-6 text-center">
        <p class="text-error mb-4">{{ error }}</p>
        <button class="btn btn-primary" @click="goBack">Go Back</button>
      </div>
    </div>

    <!-- Content -->
    <main v-else-if="vinyl" class="container mx-auto px-4 py-8">
      <div class="grid lg:grid-cols-3 gap-8">
        <!-- Left Column - Image -->
        <div class="lg:col-span-1">
          <div class="card overflow-hidden sticky top-24">
            <img 
              :src="vinyl.release?.images?.[0]?.uri || '/placeholder-vinyl.svg'"
              :alt="vinyl.release?.title"
              class="w-full aspect-square object-cover"
            >
          </div>
        </div>

        <!-- Right Column - Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Title & Artist -->
          <div>
            <p class="text-accent-lilac text-lg mb-1">
              {{ vinyl.release?.artist_name }}
            </p>
            <h2 class="font-display text-3xl text-text-primary mb-4">
              {{ vinyl.release?.title }}
            </h2>
            
            <!-- Meta -->
            <div class="flex flex-wrap gap-3">
              <span v-if="vinyl.release?.year" class="badge badge-lilac">
                {{ vinyl.release?.year }}
              </span>
              <span v-if="vinyl.release?.country" class="badge bg-surface text-text-secondary">
                {{ vinyl.release?.country }}
              </span>
              <span v-if="vinyl.release?.label" class="badge bg-surface text-text-secondary">
                {{ vinyl.release?.label }}
              </span>
            </div>
          </div>

          <!-- Stats Cards -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card p-4 text-center">
              <p class="text-2xl font-bold text-text-primary">{{ vinyl.community?.have || 0 }}</p>
              <p class="text-sm text-text-secondary">Have</p>
            </div>
            <div class="card p-4 text-center">
              <p class="text-2xl font-bold text-text-primary">{{ vinyl.community?.want || 0 }}</p>
              <p class="text-sm text-text-secondary">Want</p>
            </div>
            <div class="card p-4 text-center">
              <p class="text-2xl font-bold text-accent-coral">
                {{ vinyl.analysis?.demand_ratio?.toFixed(2) || '0.00' }}
              </p>
              <p class="text-sm text-text-secondary">Demand Ratio</p>
            </div>
            <div class="card p-4 text-center">
              <p class="text-2xl font-bold text-success">
                {{ formatPrice(vinyl.marketplace?.stats?.lowest_price?.value) }}
              </p>
              <p class="text-sm text-text-secondary">From</p>
            </div>
          </div>

          <!-- Genres & Styles -->
          <div v-if="vinyl.release?.genres?.length || vinyl.release?.styles?.length" class="card p-6">
            <h3 class="font-display text-lg text-text-primary mb-4">Genres & Styles</h3>
            <div class="space-y-3">
              <div v-if="vinyl.release?.genres?.length">
                <p class="text-sm text-text-secondary mb-2">Genres</p>
                <div class="flex flex-wrap gap-2">
                  <span 
                    v-for="genre in vinyl.release.genres" 
                    :key="genre"
                    class="badge badge-coral"
                  >
                    {{ genre }}
                  </span>
                </div>
              </div>
              <div v-if="vinyl.release?.styles?.length">
                <p class="text-sm text-text-secondary mb-2">Styles</p>
                <div class="flex flex-wrap gap-2">
                  <span 
                    v-for="style in vinyl.release.styles" 
                    :key="style"
                    class="badge badge-lilac"
                  >
                    {{ style }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tracklist -->
          <div v-if="vinyl.release?.tracklist?.length" class="card p-6">
            <h3 class="font-display text-lg text-text-primary mb-4">Tracklist</h3>
            <ol class="space-y-2">
              <li 
                v-for="(track, index) in vinyl.release.tracklist" 
                :key="index"
                class="flex items-center gap-4 py-2 border-b border-border-light last:border-0"
              >
                <span class="text-text-secondary text-sm w-8">{{ track.position || index + 1 }}</span>
                <span class="flex-1 text-text-primary">{{ track.title }}</span>
                <span v-if="track.duration" class="text-text-secondary text-sm">{{ track.duration }}</span>
              </li>
            </ol>
          </div>

          <!-- Actions -->
          <div class="flex gap-4">
            <button class="btn btn-primary flex-1">
              Save to Collection
            </button>
            <a 
              :href="`https://www.discogs.com/release/${route.params.id}`"
              target="_blank"
              class="btn btn-secondary"
            >
              View on Discogs
            </a>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
