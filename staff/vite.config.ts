import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Staff portals (admin + doctor). One rollup entry per page; the PHP shell
// pages load these fixed filenames (dist/assets/<name>.js) so no hashing on
// entries. Shared code is split into hashed chunks; CSS is a single file.
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: {
        admin_dashboard: 'src/entries/admin_dashboard.tsx',
        admin_appointments: 'src/entries/admin_appointments.tsx',
        admin_clients: 'src/entries/admin_clients.tsx',
        admin_doctors: 'src/entries/admin_doctors.tsx',
        admin_schedules: 'src/entries/admin_schedules.tsx',
        admin_laboratory: 'src/entries/admin_laboratory.tsx',
        admin_vitals: 'src/entries/admin_vitals.tsx',
        admin_walkins: 'src/entries/admin_walkins.tsx',
        admin_reports: 'src/entries/admin_reports.tsx',
        admin_history: 'src/entries/admin_history.tsx',
        admin_change_password: 'src/entries/admin_change_password.tsx',
        doctor_dashboard: 'src/entries/doctor_dashboard.tsx',
        doctor_appointments: 'src/entries/doctor_appointments.tsx',
        doctor_clients: 'src/entries/doctor_clients.tsx',
        doctor_laboratory: 'src/entries/doctor_laboratory.tsx',
        doctor_schedule: 'src/entries/doctor_schedule.tsx',
        doctor_change_password: 'src/entries/doctor_change_password.tsx',
        client_profile: 'src/entries/client_profile.tsx',
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
