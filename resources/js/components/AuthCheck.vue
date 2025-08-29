<template>
  <div v-if="!auth.isAuthenticated" class="auth-required">
    <div class="auth-required-content">
      <div class="auth-icon">
        <i class="ri-lock-line"></i>
      </div>
      <h3>Authentication Required</h3>
      <p>You must be logged in to access this page.</p>
      <a :href="route('login')" class="btn btn-primary">
        <i class="ri-login-box-line me-2"></i>
        Login Now
      </a>
    </div>
  </div>
  <slot v-else />
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth);
</script>

<style scoped>
.auth-required {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 2rem;
}

.auth-required-content {
  text-align: center;
  max-width: 400px;
}

.auth-icon {
  font-size: 4rem;
  color: #6c757d;
  margin-bottom: 1.5rem;
}

.auth-required-content h3 {
  color: #495057;
  font-weight: 600;
  margin-bottom: 1rem;
}

.auth-required-content p {
  color: #6c757d;
  margin-bottom: 2rem;
  line-height: 1.5;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 8px;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  color: white;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s ease;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  color: white;
  text-decoration: none;
}
</style>







