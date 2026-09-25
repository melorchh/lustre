import React from 'react';
import { createRoot } from 'react-dom/client';
import DashboardApp from './pages/DashboardApp';
import { LangProvider } from './lang';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <LangProvider>
        <DashboardApp />
      </LangProvider>
    </React.StrictMode>,
  );
}
