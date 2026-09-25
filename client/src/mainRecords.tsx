import React from 'react';
import { createRoot } from 'react-dom/client';
import RecordsApp from './pages/RecordsApp';
import { LangProvider } from './lang';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <LangProvider>
        <RecordsApp />
      </LangProvider>
    </React.StrictMode>,
  );
}