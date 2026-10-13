import React from 'react';
import { createRoot } from 'react-dom/client';
import RecordsApp from './pages/RecordsApp';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <RecordsApp />
    </React.StrictMode>,
  );
}