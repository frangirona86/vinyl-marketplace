<script setup>
import { computed } from 'vue'

const props = defineProps({
  vinyl: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['click', 'save', 'remove'])

const demandRatio = computed(() => parseFloat(props.vinyl.demand_ratio) || 0)

const demandLabel = computed(() => {
  if (demandRatio.value >= 2) return 'Very High'
  if (demandRatio.value >= 1) return 'High'
  if (demandRatio.value >= 0.5) return 'Medium'
  return 'Low'
})

const demandColor = computed(() => {
  if (demandRatio.value >= 2) return 'text-accent-coral'
  if (demandRatio.value >= 1) return 'text-amber-500'
  if (demandRatio.value >= 0.5) return 'text-green-500'
  return 'text-theme-muted'
})

const formatPrice = (price, currency = 'EUR') => {
  if (!price) return '—'
  const numPrice = parseFloat(price)
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: currency || 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(numPrice)
}

const coverImage = computed(() => {
  return props.vinyl.thumb || props.vinyl.cover_image || '/placeholder-vinyl.svg'
})

const genres = computed(() => {
  const g = props.vinyl.genres
  if (Array.isArray(g)) return g.slice(0, 2)
  return []
})
</script>

<template>
  <article 
    class="card-theme border rounded-xl overflow-hidden cursor-pointer group"
    @click="emit('click', vinyl)"
  >
    <!-- Image -->
    <div class="relative aspect-square bg-theme-surface overflow-hidden">
      <img 
        :src="coverImage" 
        :alt="vinyl.title"
        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
        loading="lazy"
        @error="(e) => e.target.src = '/placeholder-vinyl.svg'"
      >
      
      <!-- Score Badge -->
      <div 
        v-if="vinyl.ai_score"
        class="absolute top-3 left-3 px-2 py-1 rounded-md text-xs font-semibold bg-green-500/20 text-green-500"
      >
        {{ vinyl.ai_score }}
      </div>

      <!-- Rarity Badge -->
      <div 
        v-if="vinyl.is_rare"
        class="absolute top-3 right-3 px-2 py-1 rounded-full text-xs font-medium bg-accent-lilac/20 text-accent-lilac"
      >
        Rare
      </div>

      <!-- Watchlist Button -->
      <button
        v-if="vinyl.is_watchlist"
        class="absolute bottom-3 right-3 w-8 h-8 rounded-full bg-accent-coral flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
        @click.stop="emit('remove', vinyl)"
      >
        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
        </svg>
      </button>
    </div>

    <!-- Content -->
    <div class="p-4">
      <!-- Artist & Title -->
      <p class="text-theme-muted text-sm truncate mb-1">
        {{ vinyl.artist_name || 'Unknown Artist' }}
      </p>
      <h3 class="text-theme-primary font-medium truncate mb-2 group-hover:text-accent-lilac transition-colors">
        {{ vinyl.title }}
      </h3>

      <!-- Genres -->
      <div class="flex flex-wrap gap-1 mb-3">
        <span 
          v-for="genre in genres" 
          :key="genre"
          class="text-xs px-2 py-0.5 rounded bg-theme-surface text-theme-muted"
        >
          {{ genre }}
        </span>
        <span v-if="vinyl.year" class="text-xs px-2 py-0.5 rounded bg-theme-surface text-theme-muted">
          {{ vinyl.year }}
        </span>
      </div>

      <!-- Stats Row -->
      <div class="flex items-center justify-between text-sm mb-3">
        <div class="flex items-center gap-3">
          <span class="text-theme-muted">
            <span class="text-theme-primary font-medium">{{ vinyl.have || 0 }}</span> have
          </span>
          <span class="text-theme-muted">
            <span class="text-theme-primary font-medium">{{ vinyl.want || 0 }}</span> want
          </span>
        </div>
      </div>

      <!-- Demand & Price -->
      <div class="flex items-center justify-between pt-3 border-t border-theme-light">
        <div>
          <p class="text-xs text-theme-muted mb-0.5">Demand</p>
          <p class="font-semibold" :class="demandColor">
            {{ demandLabel }}
            <span class="text-xs font-normal">({{ demandRatio.toFixed(2) }})</span>
          </p>
        </div>
        <div class="text-right">
          <p class="text-xs text-theme-muted mb-0.5">From</p>
          <p class="font-semibold text-theme-primary">
            {{ formatPrice(vinyl.lowest_price, vinyl.lowest_price_currency) }}
          </p>
        </div>
      </div>

      <!-- Recommendation Badge -->
      <div v-if="vinyl.ai_recommendation" class="mt-3">
        <span 
          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
          :class="{
            'bg-green-500/15 text-green-500': vinyl.ai_recommendation === 'BUY',
            'bg-amber-500/15 text-amber-500': vinyl.ai_recommendation === 'HOLD',
            'bg-accent-coral/15 text-accent-coral': vinyl.ai_recommendation === 'AVOID'
          }"
        >
          {{ vinyl.ai_recommendation }}
        </span>
      </div>
    </div>
  </article>
</template>
