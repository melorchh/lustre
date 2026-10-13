import React from 'react';
import { createRoot } from 'react-dom/client';
import LabApp from './pages/LabApp';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <LabApp />
    </React.StrictMode>,
  );
}