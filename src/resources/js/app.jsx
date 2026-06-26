import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

router.on('invalid', (event) => {
    const status = event.detail.response?.status;

    if (status === 401 || status === 419) {
        window.location.href = '/login';
    }
});

createInertiaApp({
    title: (title) => `${title} - Sistema de Gestión de Calificaciones Académicas`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#1e40af' },
});
