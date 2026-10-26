import { createContext, useContext, useEffect, useState } from 'react';

export type ToastType = '' | 'success' | 'error';

export interface ToastHandle {
  showToast: (msg: string, type?: ToastType) => void;
}

export const ToastCtx = createContext<ToastHandle>({ showToast: () => {} });
export const useToast = (): ToastHandle => useContext(ToastCtx);

/** Toast state + DOM, mounted once per Shell. */
export function useToastState() {
  const [toast, setToast] = useState<{ msg: string; type: ToastType; show: boolean }>({
    msg: '',
    type: '',
    show: false,
  });

  const showToast = (msg: string, type: ToastType = '') => {
    setToast({ msg, type, show: true });
    window.setTimeout(() => setToast((t) => ({ ...t, show: false })), 3200);
  };

  return { toast, showToast };
}

function readTheme(): 'dark' | 'light' {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

/** Shares the portal's meTheme local storage key + data-theme attribute. */
export function useTheme(): { isDark: boolean; toggleTheme: () => void } {
  const [isDark, setIsDark] = useState(readTheme() === 'dark');

  useEffect(() => {
    const onChange = () => setIsDark(readTheme() === 'dark');
    window.addEventListener('themechange', onChange);
    return () => window.removeEventListener('themechange', onChange);
  }, []);

  const toggleTheme = () => {
    const next = readTheme() === 'dark' ? 'light' : 'dark';
    if (next === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    else document.documentElement.removeAttribute('data-theme');
    try {
      localStorage.setItem('meTheme', next);
    } catch {
      /* ignore */
    }
    window.dispatchEvent(new CustomEvent('themechange'));
    setIsDark(next === 'dark');
  };

  return { isDark, toggleTheme };
}

export function useScrollLock(locked: boolean): void {
  useEffect(() => {
    if (!locked) return;
    const body = document.body;
    const prev = body.style.overflow;
    body.style.overflow = 'hidden';
    return () => {
      body.style.overflow = prev;
    };
  }, [locked]);
}
