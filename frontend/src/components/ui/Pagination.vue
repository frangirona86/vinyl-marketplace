<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true
  },
  lastPage: {
    type: Number,
    required: true
  },
  total: {
    type: Number,
    default: 0
  },
  perPage: {
    type: Number,
    default: 20
  }
})

const emit = defineEmits(['page-change'])

const pages = computed(() => {
  const current = props.currentPage
  const last = props.lastPage
  const delta = 2
  const range = []
  const rangeWithDots = []

  for (let i = 1; i <= last; i++) {
    if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
      range.push(i)
    }
  }

  let prev
  for (const i of range) {
    if (prev) {
      if (i - prev === 2) {
        rangeWithDots.push(prev + 1)
      } else if (i - prev !== 1) {
        rangeWithDots.push('...')
      }
    }
    rangeWithDots.push(i)
    prev = i
  }

  return rangeWithDots
})

const showingFrom = computed(() => {
  return (props.currentPage - 1) * props.perPage + 1
})

const showingTo = computed(() => {
  return Math.min(props.currentPage * props.perPage, props.total)
})

const goToPage = (page) => {
  if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
    emit('page-change', page)
  }
}
</script>

<template>
  <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
    <!-- Info -->
    <p class="text-sm text-theme-muted">
      Showing <span class="text-theme-primary font-medium">{{ showingFrom }}</span> 
      to <span class="text-theme-primary font-medium">{{ showingTo }}</span> 
      of <span class="text-theme-primary font-medium">{{ total }}</span> results
    </p>

    <!-- Pagination Controls -->
    <nav class="flex items-center gap-1" aria-label="Pagination">
      <!-- Previous -->
      <button
        class="px-3 py-2 rounded-md text-theme-muted hover:bg-theme-surface hover:text-theme-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="currentPage === 1"
        @click="goToPage(currentPage - 1)"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <!-- Page Numbers -->
      <template v-for="(page, index) in pages" :key="index">
        <span 
          v-if="page === '...'" 
          class="px-3 py-2 text-theme-muted"
        >
          ...
        </span>
        <button
          v-else
          class="min-w-[40px] px-3 py-2 rounded-md text-sm font-medium transition-colors"
          :class="[
            page === currentPage 
              ? 'bg-accent-coral text-white' 
              : 'text-theme-muted hover:bg-theme-surface hover:text-theme-primary'
          ]"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
      </template>

      <!-- Next -->
      <button
        class="px-3 py-2 rounded-md text-theme-muted hover:bg-theme-surface hover:text-theme-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="currentPage === lastPage"
        @click="goToPage(currentPage + 1)"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </nav>
  </div>
</template>
