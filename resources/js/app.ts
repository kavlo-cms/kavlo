import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { router } from '@inertiajs/vue3';

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
    }
}

const appName = document.title || import.meta.env.VITE_APP_NAME || 'Kavlo';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./admin/${name}.vue`,
            import.meta.glob<DefineComponent>('./admin/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('navigate', (event) => {
    if (import.meta.env.PROD && typeof window.gtag === 'function') {
        window.gtag('config', 'G-VX7GKCQ6FP', {
            page_path: event.detail.page.url,
            page_location: window.location.href,
        });
    }
});
