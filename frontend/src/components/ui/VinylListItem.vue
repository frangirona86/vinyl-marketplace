<script setup>
import { computed } from 'vue'

const props = defineProps({
  vinyl: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['click'])

const demandRatio = computed(() => parseFloat(props.vinyl.demand_ratio) || 0)

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
  if (Array.isArray(g)) return g.slice(0, 3)
  return []
})
</script>

<template>
  <article 
    class="flex gap-4 p-4 card-theme border rounded-xl cursor-pointer group hover:border-accent-lilac/50"
    @click="emit('click', vinyl)"
  >
    <!-- Image -->
    <div class="w-20 h-20 sm:w-24 sm:h-24 shrink-0 rounded-lg overflow-hidden bg-theme-surface">
      <img 
        :src="coverImage" 
        :alt="vinyl.title"
        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
        loading="lazy"
      >
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-theme-muted text-sm truncate">
            {{ vinyl.artist_name || 'Unknown Artist' }}
          </p>
          <h3 class="text-theme-primary font-medium truncate group-hover:text-accent-lilac transition-colors">
            {{ vinyl.title }}
          </h3>
        </div>

        <!-- Price (Desktop) -->
        <div class="hidden sm:block text-right shrink-0">
          <p class="text-xs text-theme-muted">From</p>
          <p class="text-lg font-semibold text-theme-primary">
            {{ formatPrice(vinyl.lowest_price, vinyl.lowest_price_currency) }}
          </p>
        </div>
      </div>

      <!-- Tags -->
      <div class="flex flex-wrap items-center gap-2 mt-2">
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
        <span v-if="vinyl.is_rare" class="text-xs px-2 py-0.5 rounded-full bg-accent-lilac/15 text-accent-lilac">
          Rare
        </span>
        <span 
          v-if="vinyl.ai_recommendation" 
          class="text-xs px-2 py-0.5 rounded-full"
          :class="{
            'bg-green-500/15 text-green-500': vinyl.ai_recommendation === 'BUY',
            'bg-amber-500/15 text-amber-500': vinyl.ai_recommendation === 'HOLD',
            'bg-accent-coral/15 text-accent-coral': vinyl.ai_recommendation === 'AVOID'
          }"
        >
          {{ vinyl.ai_recommendation }}
        </span>
        <!-- YouTube indicator -->
        <span 
          v-if="vinyl.has_youtube" 
          class="text-xs px-2 py-0.5 rounded-full bg-red-600/15 text-red-500 flex items-center gap-1"
          title="Preview available"
        >
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z"/>
          </svg>
          Preview
        </span>
      </div>

      <!-- Stats -->
      <div class="flex items-center gap-4 mt-3 text-sm">
        <span class="text-theme-muted">
          <span class="text-theme-primary font-medium">{{ vinyl.have || 0 }}</span> have
        </span>
        <span class="text-theme-muted">
          <span class="text-theme-primary font-medium">{{ vinyl.want || 0 }}</span> want
        </span>
        <span class="text-theme-muted">
          Demand: <span class="font-medium" :class="demandColor">{{ demandRatio.toFixed(2) }}</span>
        </span>

        <!-- Price (Mobile) -->
        <span class="sm:hidden ml-auto text-theme-primary font-semibold">
          {{ formatPrice(vinyl.lowest_price, vinyl.lowest_price_currency) }}
        </span>
      </div>
    </div>
  </article>
</template>
