import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

// Server-rendered from the Site row (see the app-name meta in app.blade.php), so renaming the
// platform in CMS → Homepage takes effect without a rebuild. VITE_APP_NAME is only the fallback
// for a page served before that meta existed.
const appName = document.querySelector<HTMLMetaElement>('meta[name="app-name"]')?.content || import.meta.env.VITE_APP_NAME || 'Alpha Journal';

createInertiaApp({
    // A page with no title of its own (the public homepage) gets the site name alone. Without the
    // guard Inertia still runs this callback with an empty string, producing "- Alpha Journal".
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#005a9e',
    },
});

// This will set light / dark mode on load...
initializeTheme();
