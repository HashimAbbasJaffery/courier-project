import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import AppLayout from './layouts/AppLayout.vue';
import Toast from './components/Toast.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const page = resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue')
        ) as Promise<DefineComponent>;

        page.then((component) => {
            // Only set AppLayout if no layout is specified and layout is not explicitly false
            if (component.default.layout === undefined) {
                component.default.layout = AppLayout;
            }
        });

        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        
        // Register Toast component globally
        app.component('Toast', Toast);
        
        app.use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
});

// This will set light / dark mode on page load...
initializeTheme();
