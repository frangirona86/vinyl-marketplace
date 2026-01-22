<script setup>
defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  }
})

const sizeClasses = {
  sm: 'w-5 h-5 border-2',
  md: 'w-8 h-8 border-2',
  lg: 'w-12 h-12 border-3',
}
</script>

<template>
  <div 
    class="animate-spin rounded-full border-accent-lilac border-t-transparent"
    :class="sizeClasses[size]"
  ></div>
</template>
