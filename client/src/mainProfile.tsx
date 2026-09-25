import React from 'react';
import { createRoot } from 'react-dom/client';
import ProfileApp from './pages/ProfileApp';
import { LangProvider } from './lang';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <LangProvider>
        <ProfileApp />
      </LangProvider>
    </React.StrictMode>,
  );
}