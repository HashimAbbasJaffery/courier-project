<template>
  <div class="performance-optimized-table">
    <!-- Lazy loading placeholder -->
    <div v-if="!isTableReady" class="table-placeholder">
      <div class="placeholder-content">
        <div class="placeholder-header"></div>
        <div class="placeholder-rows">
          <div v-for="i in 5" :key="i" class="placeholder-row"></div>
        </div>
      </div>
    </div>
    
    <!-- Actual table (lazy loaded) -->
    <div v-else>
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const isTableReady = ref(false)
let observer: IntersectionObserver | null = null

onMounted(() => {
  // Use Intersection Observer for lazy loading
  observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && !isTableReady.value) {
        // Small delay to ensure smooth loading
        setTimeout(() => {
          isTableReady.value = true
        }, 100)
      }
    })
  }, {
    threshold: 0.1,
    rootMargin: '50px'
  })
  
  observer.observe(document.querySelector('.performance-optimized-table')!)
})

onUnmounted(() => {
  if (observer) {
    observer.disconnect()
  }
})
</script>

<style scoped>
.performance-optimized-table {
  min-height: 200px;
}

.table-placeholder {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 20px;
}

.placeholder-content {
  animation: pulse 1.5s ease-in-out infinite;
}

.placeholder-header {
  height: 40px;
  background: #e9ecef;
  border-radius: 4px;
  margin-bottom: 20px;
}

.placeholder-rows {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.placeholder-row {
  height: 24px;
  background: #e9ecef;
  border-radius: 4px;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>










