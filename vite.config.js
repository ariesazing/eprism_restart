import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pdf-review.js',
                'resources/js/submission-editor.js'
            ],
            refresh: true,
        }),
    ],
    build: {
        // ✅ Make sure Vercel finds the output
        outDir: 'dist',

        // ✅ Silence warnings unless chunks exceed 1 MB
        chunkSizeWarningLimit: 1000,

        // ✅ Optional: split heavy dependencies into separate chunks
        rollupOptions: {
            output: {
                manualChunks: {
                    pdf: ['pdfjs-dist'],
                    editor: ['@your/editor-package'],
                }
            }
        }
    }
});
