import React from 'react';
import { createRoot } from 'react-dom/client';
import LabApp from './pages/LabApp';
import { LangProvider } from './lang';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <LangProvider>
        <LabApp />
      </LangProvider>
    </React.StrictMode>,
  );
}