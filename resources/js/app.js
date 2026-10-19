import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = (import.meta.env.VITE_APP_NAME && import.meta.env.VITE_APP_NAME !== 'Laravel') 
    ? import.meta.env.VITE_APP_NAME 
    : 'NOO+';

// Clean address bar URL globally on initial load & after any filter / navigation completes
const cleanUrlAddressBar = () => {
    if (typeof window !== 'undefined' && window.location.search) {
        window.history.replaceState({}, '', window.location.pathname);
    }
};

cleanUrlAddressBar();

if (typeof window !== 'undefined' && window.Ziggy) {
    window.Ziggy.url = window.location.origin;
}

router.on('finish', () => {
    cleanUrlAddressBar();
});

// Global Body Scroll Lock Observer
// Automatically locks background scrolling when any modal / backdrop overlay is present in DOM across all pages (Admin, Spv, Edp)
if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    let isLockedByObserver = false;

    const checkModalPresent = () => {
        const modalBackdrops = document.querySelectorAll(
            '.fixed.inset-0:not(.z-40), [role="dialog"], dialog[open], [data-modal]'
        );

        let hasActiveModal = false;
        for (const el of modalBackdrops) {
            const style = window.getComputedStyle(el);
            if (style.display !== 'none' && style.visibility !== 'hidden' && el.offsetHeight > 0) {
                const className = el.className || '';
                if (
                    className.includes('z-[9') ||
                    className.includes('z-50') ||
                    className.includes('bg-black') ||
                    className.includes('bg-slate') ||
                    el.tagName.toLowerCase() === 'dialog' ||
                    el.getAttribute('role') === 'dialog' ||
                    el.querySelector('.bg-white, .rounded-xl, .rounded-lg, [role="document"]')
                ) {
                    hasActiveModal = true;
                    break;
                }
            }
        }

        if (hasActiveModal && !isLockedByObserver) {
            document.body.classList.add('modal-open');
            document.documentElement.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            isLockedByObserver = true;
        } else if (!hasActiveModal && isLockedByObserver) {
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            document.body.style.overflow = '';
            isLockedByObserver = false;
        }
    };

    const observer = new MutationObserver(() => {
        checkModalPresent();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'open'],
    });

    router.on('navigate', () => {
        isLockedByObserver = false;
        document.body.classList.remove('modal-open');
        document.documentElement.classList.remove('modal-open');
        document.body.style.overflow = '';
    });
}

createInertiaApp({
    title: (title) => {
        if (!title) return appName;
        const cleanTitle = title.replace(/\s*-\s*Laravel\s*$/i, '').trim();
        return cleanTitle || appName;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
