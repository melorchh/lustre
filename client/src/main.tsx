import React from 'react';
import { createRoot } from 'react-dom/client';
import BookApp from './pages/BookApp';
import './styles/app.css';

const rootEl = document.getElementById('root');
if (rootEl) {
  createRoot(rootEl).render(
    <React.StrictMode>
      <BookApp />
    </React.StrictMode>,
  );
}