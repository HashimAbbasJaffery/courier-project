import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import path from 'path';


export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
        ziggy: path.resolve(__dirname, 'resources/js/ziggy.js'), // this line is key!
        },
    },
    build: {
        // Enable code splitting
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': ['vue', '@inertiajs/vue3'],
                    'ui': ['lucide-vue-next', 'ziggy-js'],
                    'data': ['axios'],
                }
            }
        },
        // Optimize chunk size
        chunkSizeWarningLimit: 1000,
        // Enable source maps for debugging
        sourcemap: false,
        // Minify CSS
        cssMinify: true,
        // Minify JS
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            }
        }
    },
    // Optimize development experience
    server: {
        hmr: {
            overlay: false,
        },
    },
    // Optimize dependencies
    optimizeDeps: {
        include: ['vue', '@inertiajs/vue3', 'axios'],
        exclude: ['@tailwindcss/vite']
    }
});
