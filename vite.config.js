import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/pdf-review.js', 'resources/js/submission-editor.js'],
            refresh: true,
        }),
    ],
});