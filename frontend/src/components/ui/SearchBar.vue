<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const query = ref('')
const isFocused = ref(false)

const handleSearch = () => {
  if (query.value.trim()) {
    router.push({ name: 'search', query: { q: query.value.trim() } })
  }
}
</script>

<template>
  <div 
    class="relative flex-1 max-w-xl"
    :class="{ 'ring-2 ring-accent-lilac/30 rounded-lg': isFocused }"
  >
    <input
      v-model="query"
      type="text"
      placeholder="Search vinyls, artists, labels..."
      class="input pl-10 pr-4"
      @focus="isFocused = true"
      @blur="isFocused = false"
      @keyup.enter="handleSearch"
    >
    <svg 
      class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary"
      fill="none" 
      stroke="currentColor" 
      viewBox="0 0 24 24"
    >
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
    <button 
      v-if="query"
      class="absolute right-3 top-1/2 -translate-y-1/2 text-text-secondary hover:text-text-primary"
      @click="query = ''"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>
