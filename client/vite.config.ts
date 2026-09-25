import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: {
        app: 'src/main.tsx',
        dashboard: 'src/mainDashboard.tsx',
        records: 'src/mainRecords.tsx',
        lab: 'src/mainLab.tsx',
        profile: 'src/mainProfile.tsx',
      },
      output: {
        entryFileNames: 'assets/[name].js',
        assetFileNames: (info) =>
          info.name === 'style.css' ? 'assets/app.css' : 'assets/[name][extname]',
        chunkFileNames: 'assets/[name]-[hash].js',
      },
    },
  },
});