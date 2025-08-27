<template>
  <div v-if="toasts.length > 0" class="toast-container">
    <TransitionGroup name="toast" tag="div">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="['toast', `toast-${toast.type}`]"
        @click="removeToast(toast.id)"
      >
        <div class="toast-icon">
          <i :class="getIcon(toast.type)"></i>
        </div>
        <div class="toast-content">
          <div class="toast-title">{{ toast.title }}</div>
          <div v-if="toast.message" class="toast-message">{{ toast.message }}</div>
        </div>
        <button class="toast-close" @click.stop="removeToast(toast.id)">
          <i class="ri-close-line"></i>
        </button>
        <div class="toast-progress" :style="{ width: toast.progress + '%' }"></div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const toasts = ref([]);
let toastCounter = 0;

// Toast types and their configurations
const toastTypes = {
  success: { icon: 'ri-check-line', duration: 5000 },
  error: { icon: 'ri-error-warning-line', duration: 7000 },
  warning: { icon: 'ri-alert-line', duration: 6000 },
  info: { icon: 'ri-information-line', duration: 5000 }
};

// Function to add a new toast
const addToast = (type, title, message = '') => {
  const id = ++toastCounter;
  const toast = {
    id,
    type,
    title,
    message,
    progress: 100
  };
  
  toasts.value.push(toast);
  
  // Auto-remove toast after duration
  const duration = toastTypes[type].duration;
  const progressInterval = setInterval(() => {
    toast.progress -= 100 / (duration / 100);
    if (toast.progress <= 0) {
      clearInterval(progressInterval);
      removeToast(id);
    }
  }, 100);
  
  return id;
};

// Function to remove a toast
const removeToast = (id) => {
  const index = toasts.value.findIndex(toast => toast.id === id);
  if (index > -1) {
    toasts.value.splice(index, 0);
  }
};

// Function to get icon for toast type
const getIcon = (type) => {
  return toastTypes[type]?.icon || 'ri-information-line';
};

// Expose addToast function globally
const showToast = (type, title, message) => {
  return addToast(type, title, message);
};

// Make showToast available globally
if (typeof window !== 'undefined') {
  window.showToast = showToast;
}

// Cleanup on unmount
onUnmounted(() => {
  if (typeof window !== 'undefined') {
    delete window.showToast;
  }
});
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 400px;
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
  border: 1px solid #e9ecef;
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s ease;
  min-width: 300px;
}

.toast:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.toast-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: white;
}

.toast-success .toast-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.toast-error .toast-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.toast-warning .toast-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.toast-info .toast-icon {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.toast-content {
  flex: 1;
  min-width: 0;
}

.toast-title {
  font-weight: 600;
  font-size: 14px;
  color: #1f2937;
  margin-bottom: 4px;
  line-height: 1.4;
}

.toast-message {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.4;
}

.toast-close {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  border: none;
  background: none;
  color: #9ca3af;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.toast-close:hover {
  background: #f3f4f6;
  color: #6b7280;
}

.toast-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 3px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transition: width 0.1s linear;
}

/* Toast animations */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-move {
  transition: transform 0.3s ease;
}

/* Responsive */
@media (max-width: 480px) {
  .toast-container {
    right: 10px;
    left: 10px;
    max-width: none;
  }
  
  .toast {
    min-width: auto;
    width: 100%;
  }
}
</style>




