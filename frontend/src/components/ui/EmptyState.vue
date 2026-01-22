<script setup>
defineProps({
  title: {
    type: String,
    default: 'No results found'
  },
  description: {
    type: String,
    default: 'Try adjusting your filters or search terms.'
  },
  icon: {
    type: String,
    default: 'vinyl'
  }
})

defineEmits(['action'])
</script>

<template>
  <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
    <!-- Vinyl Icon -->
    <div v-if="icon === 'vinyl'" class="mb-6 text-theme-muted opacity-40">
      <svg class="w-24 h-24" viewBox="0 0 100 100" fill="currentColor">
        <circle cx="50" cy="50" r="48" fill="none" stroke="currentColor" stroke-width="2"/>
        <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-width="1"/>
        <circle cx="50" cy="50" r="32" fill="none" stroke="currentColor" stroke-width="1"/>
        <circle cx="50" cy="50" r="24" fill="none" stroke="currentColor" stroke-width="1"/>
        <circle cx="50" cy="50" r="15" fill="currentColor" opacity="0.3"/>
        <circle cx="50" cy="50" r="5" fill="currentColor"/>
      </svg>
    </div>

    <!-- Search Icon -->
    <div v-else-if="icon === 'search'" class="mb-6 text-theme-muted opacity-40">
      <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
    </div>

    <h3 class="text-xl font-display text-theme-primary mb-2">{{ title }}</h3>
    <p class="text-theme-muted max-w-md mb-6">{{ description }}</p>
    
    <slot name="action">
      <button 
        class="px-5 py-2.5 bg-theme-surface text-theme-primary border border-theme rounded-lg hover:bg-theme-surface-hover hover:border-accent-lilac transition-colors"
        @click="$emit('action')"
      >
        <slot name="action-text">Clear Filters</slot>
      </button>
    </slot>
  </div>
</template>
