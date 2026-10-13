import React from 'react';
import { createRoot } from 'react-dom/client';
import ProfileApp from './pages/ProfileApp';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <ProfileApp />
    </React.StrictMode>,
  );
}