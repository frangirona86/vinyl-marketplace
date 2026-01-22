<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const searchQuery = ref('')
const mobileMenuOpen = ref(false)

const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'search', query: { q: searchQuery.value.trim() } })
    searchQuery.value = ''
    mobileMenuOpen.value = false
  }
}

const navLinks = [
  { name: 'Collection', route: 'vinyls' },
]
</script>

<template>
  <header class="sticky top-0 z-50 bg-primary/95 backdrop-blur border-b border-border-light">
    <div class="container mx-auto px-4">
      <div class="flex items-center justify-between h-16">
        <!-- Logo -->
        <router-link to="/" class="flex items-center gap-2">
          <div class="w-10 h-10 rounded-full bg-accent-coral flex items-center justify-center">
            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
              <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/>
              <circle cx="12" cy="12" r="7" fill="none" stroke="currentColor" stroke-width="0.5"/>
              <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="0.5"/>
              <circle cx="12" cy="12" r="2" fill="currentColor"/>
            </svg>
          </div>
          <span class="font-display text-xl text-text-primary hidden sm:block">
            <span class="text-accent-coral">Vinyl</span> Market
          </span>
        </router-link>

        <!-- Desktop Navigation -->
        <nav class="hidden md:flex items-center gap-6">
          <router-link 
            v-for="link in navLinks" 
            :key="link.route"
            :to="{ name: link.route }"
            class="text-text-secondary hover:text-text-primary transition-colors"
          >
            {{ link.name }}
          </router-link>
        </nav>

        <!-- Search Bar -->
        <div class="hidden md:flex items-center gap-4 flex-1 max-w-md mx-8">
          <div class="relative w-full">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search vinyls..."
              class="input pl-10"
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
          </div>
        </div>

        <!-- Right Actions -->
        <div class="flex items-center gap-3">
          <!-- Mobile Search Toggle -->
          <button 
            class="md:hidden btn btn-ghost p-2"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </button>

          <!-- Mobile Menu Toggle -->
          <button 
            class="md:hidden btn btn-ghost p-2"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <svg v-if="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile Menu -->
      <div 
        v-if="mobileMenuOpen"
        class="md:hidden py-4 border-t border-border-light"
      >
        <!-- Mobile Search -->
        <div class="mb-4">
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search vinyls..."
              class="input pl-10"
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
          </div>
        </div>

        <!-- Mobile Nav Links -->
        <nav class="flex flex-col gap-2">
          <router-link 
            v-for="link in navLinks" 
            :key="link.route"
            :to="{ name: link.route }"
            class="py-2 px-3 text-text-secondary hover:text-text-primary hover:bg-surface rounded-md transition-colors"
            @click="mobileMenuOpen = false"
          >
            {{ link.name }}
          </router-link>
        </nav>
      </div>
    </div>
  </header>
</template>
