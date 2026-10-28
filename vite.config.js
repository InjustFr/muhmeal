import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import symfonyPlugin from 'vite-plugin-symfony';

const assetsDir = process.env.ASSETS_DIR ?? 'build';
const devPort = Number(process.env.VITE_DEV_PORT ?? 5176);

export default defineConfig({
    plugins: [vue(), symfonyPlugin()],
    base: `/${assetsDir}/`,
    server: {
        host: '0.0.0.0',
        port: 5176,
        strictPort: true,
        origin: `http://localhost:${devPort}`,
        cors: true,
    },
    build: {
        outDir: `public/${assetsDir}`,
        emptyOutDir: true,
        rollupOptions: {
            input: {
                app: './assets/app.js',
                auth: './assets/auth.js',
            },
        },
    },
    test: {
        include: ['assets/**/*.test.js'],
        environment: 'node',
    },
});
