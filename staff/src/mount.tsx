import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './staff.css';
import './staff_date_fixes.css';

/** Mount a page component on #root (guarded, like the patient client). */
export function mount(Component: () => JSX.Element): void {
  const rootEl = document.getElementById('root');
  if (rootEl) {
    createRoot(rootEl).render(
      <StrictMode>
        <Component />
      </StrictMode>,
    );
  }
}
