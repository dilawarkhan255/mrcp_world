import '../css/app.css';
// Local font packages, no external font CDN.
import '@fontsource/manrope/400.css';
import '@fontsource/manrope/500.css';
import '@fontsource/manrope/600.css';
import '@fontsource/manrope/700.css';
import '@fontsource/sora/600.css';
import '@fontsource/sora/700.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import ContentProtection from './components/ContentProtection.vue';
import LogoGradientDefs from './components/LogoGradientDefs.vue';
import { initializeTheme } from './composables/useAppearance';

import { APP_NAME } from './lib/brand';
import { watchRequiredLabels } from './lib/required-labels';

createInertiaApp({
    title: (title) => `${title} - ${APP_NAME}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // ContentProtection sits next to the page, so copy protection and the watermark cover every page.
        createApp({ render: () => [h(LogoGradientDefs), h(App, props), h(ContentProtection)] })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
        // A red asterisk on the label of every required field (lib/required-labels.ts).
        watchRequiredLabels();
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
