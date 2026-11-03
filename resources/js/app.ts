import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import AdminLayout from '@/layouts/AdminLayout.vue';
import StaffLayout from '@/layouts/StaffLayout.vue';

const standalonePages = ['Home', 'Auth/Login', 'Auth/ChangePassword', 'Kiosk/Show', 'Kiosk/Unpaired'];

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} · King Maelick` : 'King Maelick · Pointage'),
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

        return pages[`./pages/${name}.vue`]() as Promise<DefineComponent>;
    },
    layout: (name) => {
        if (standalonePages.includes(name)) {
            return undefined;
        }

        return name.startsWith('Staff/') ? StaffLayout : AdminLayout;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#92bc35',
    },
});
