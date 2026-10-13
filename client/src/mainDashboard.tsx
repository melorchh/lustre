import React from 'react';
import { createRoot } from 'react-dom/client';
import DashboardApp from './pages/DashboardApp';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <DashboardApp />
    </React.StrictMode>,
  );
}
