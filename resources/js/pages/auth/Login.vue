<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineOptions({
    layout: false,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isPasswordVisible = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
        onSuccess: () => {
            // Show success toast
            if (typeof window.showToast === 'function') {
                window.showToast('success', 'Login Successful', 'Welcome back!');
            }
        },
        onError: (errors) => {
            // Show error toast
            if (typeof window.showToast === 'function') {
                const errorMessage = Object.values(errors)[0] || 'Login failed. Please try again.';
                window.showToast('error', 'Login Failed', errorMessage);
            }
        },
    });
};

const togglePasswordVisibility = () => {
    isPasswordVisible.value = !isPasswordVisible.value;
};

// Check for session messages on mount
onMounted(() => {
    // Check if there's an error message from session
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    
    if (error && typeof window.showToast === 'function') {
        window.showToast('warning', 'Access Denied', error);
    }
});
</script>

<template>
    <Head title="Login" />
    
    <div class="auth-wrapper">
        <!-- Background with gradient -->
        <div class="auth-bg">
            <div class="auth-bg-pattern"></div>
        </div>
        
        <!-- Main content -->
        <div class="d-flex align-items-center justify-content-center min-vh-100">
            <div class="auth-card">
                <!-- Header -->
                <div class="auth-header text-center mb-5">
                    <h1 class="auth-title">materio</h1>
                    <h2 class="auth-subtitle">Welcome to materio! 👋🏻</h2>
                    <p class="auth-description">
                        Please sign-in to your account and start the adventure
                    </p>
                    
                    <!-- Demo credentials -->
                    <div class="demo-credentials">
                        <div class="credential-item">
                            <span class="credential-label">Admin Email:</span>
                            <span class="credential-value">admin@demo.com</span>
                            <span class="credential-separator">/</span>
                            <span class="credential-label">Pass:</span>
                            <span class="credential-value">admin</span>
                        </div>
                        <div class="credential-item">
                            <span class="credential-label">Client Email:</span>
                            <span class="credential-value">client@demo.com</span>
                            <span class="credential-separator">/</span>
                            <span class="credential-label">Pass:</span>
                            <span class="credential-value">client</span>
                        </div>
                    </div>
                </div>
                
                <!-- Login Form -->
                <form @submit.prevent="submit" class="auth-form">
                    <!-- Email Field -->
                    <div class="form-group mb-4">
                        <label for="email" class="form-label">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors.email }"
                            placeholder="Email"
                            required
                            autofocus
                        />
                        <div v-if="form.errors.email" class="invalid-feedback">
                            {{ form.errors.email }}
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="form-group mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-input-wrapper">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="isPasswordVisible ? 'text' : 'password'"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.password }"
                                placeholder="Password"
                                required
                            />
                            <button
                                type="button"
                                class="password-toggle"
                                @click="togglePasswordVisibility"
                            >
                                <i :class="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                            </button>
                        </div>
                        <div v-if="form.errors.password" class="invalid-feedback">
                            {{ form.errors.password }}
                        </div>
                    </div>
                    
                    <!-- Remember Me & Forgot Password -->
                    <div class="form-options d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input
                                id="remember"
                                v-model="form.remember"
                                type="checkbox"
                                class="form-check-input"
                            />
                            <label for="remember" class="form-check-label">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="forgot-password">Forgot Password?</a>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary w-100 mb-4" :disabled="form.processing">
                        <span v-if="form.processing">Signing in...</span>
                        <span v-else>Login</span>
                    </button>
                    
                    <!-- Create Account Link -->
                    <div class="text-center">
                        <span class="text-muted">New on our platform?</span>
                        <a href="#" class="create-account">Create an account</a>
                    </div>
                </form>
                
                <!-- Divider -->
                <div class="divider my-4">
                    <span>or</span>
                </div>
                
                <!-- Buy Now Button -->
                <div class="text-center">
                    <a href="#" class="btn btn-outline-primary">Buy Now</a>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-wrapper {
    position: relative;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    overflow: hidden;
}

.auth-bg {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.auth-bg-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(255,255,255,0.1) 0%, transparent 50%);
    opacity: 0.3;
}

.auth-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    padding: 3rem;
    max-width: 450px;
    width: 100%;
    margin: 2rem;
    position: relative;
    z-index: 10;
}

.auth-header {
    margin-bottom: 2.5rem;
}

.auth-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 1rem;
    letter-spacing: -0.5px;
}

.auth-subtitle {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.auth-description {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.demo-credentials {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e9ecef;
}

.credential-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.credential-item:last-child {
    margin-bottom: 0;
}

.credential-label {
    color: #6c757d;
    font-weight: 500;
}

.credential-value {
    color: #495057;
    font-weight: 600;
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
}

.credential-separator {
    color: #6c757d;
    margin: 0 0.25rem;
}

.auth-form {
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-control {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.2s ease;
    background: #fff;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    outline: none;
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.invalid-feedback {
    display: block;
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.password-input-wrapper {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s ease;
}

.password-toggle:hover {
    color: #667eea;
}

.form-options {
    margin-bottom: 1.5rem;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-check-input {
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid #e9ecef;
    border-radius: 4px;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

.form-check-label {
    color: #6c757d;
    font-size: 0.875rem;
    cursor: pointer;
}

.forgot-password {
    color: #667eea;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: color 0.2s ease;
}

.forgot-password:hover {
    color: #5a6fd8;
    text-decoration: underline;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 8px;
    padding: 0.875rem 1.5rem;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
    opacity: 0.7;
    transform: none;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.create-account {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    margin-left: 0.5rem;
    transition: color 0.2s ease;
}

.create-account:hover {
    color: #5a6fd8;
    text-decoration: underline;
}

.divider {
    position: relative;
    text-align: center;
}

.divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e9ecef;
}

.divider span {
    background: white;
    padding: 0 1rem;
    color: #6c757d;
    font-size: 0.875rem;
    position: relative;
    z-index: 1;
}

.btn-outline-primary {
    border: 2px solid #667eea;
    color: #667eea;
    background: transparent;
    border-radius: 8px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.btn-outline-primary:hover {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .auth-card {
        margin: 1rem;
        padding: 2rem;
    }
    
    .auth-title {
        font-size: 2rem;
    }
    
    .auth-subtitle {
        font-size: 1.25rem;
    }
    
    .demo-credentials {
        font-size: 0.75rem;
    }
    
    .credential-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
}
</style>
