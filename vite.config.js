import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Admin panel: OdinCMS code on top of the OdinAdmin template (public/odinadmin).
                'resources/css/admin.scss',
                'resources/js/admin.js',
                // Frontend theme (Bootstrap 5).
                'resources/css/theme.scss',
                'resources/js/theme.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        // The TinyMCE chunk (~1.3 MB) is loaded only on pages with an editor.
        chunkSizeWarningLimit: 1500,
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses @import and global Sass functions.
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
