import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import { dict, type Lang } from './i18n';

interface LangCtxType {
  lang: Lang;
  setLang: (l: Lang) => void;
  t: (key: string, vars?: Record<string, string | number>) => string;
  langLabel: string;
}

const LangCtx = createContext<LangCtxType | null>(null);

export function LangProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>(() => {
    try {
      return (localStorage.getItem('meLang') as Lang) || 'en';
    } catch {
      return 'en';
    }
  });

  const setLang = useCallback((l: Lang) => {
    setLangState(l);
    try {
      localStorage.setItem('meLang', l);
    } catch {
      /* storage unavailable */
    }
  }, []);

  const t = useCallback(
    (key: string, vars?: Record<string, string | number>): string => {
      let out = dict[key]?.[lang] ?? dict[key]?.['en'] ?? key;
      if (vars) {
        for (const [k, v] of Object.entries(vars)) {
          out = out.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v));
        }
      }
      return out;
    },
    [lang],
  );

  const langLabel = lang === 'en' ? 'FIL' : 'EN';

  return (
    <LangCtx.Provider value={{ lang, setLang, t, langLabel }}>{children}</LangCtx.Provider>
  );
}

export function useLang(): LangCtxType {
  const ctx = useContext(LangCtx);
  if (!ctx) throw new Error('useLang must be used within LangProvider');
  return ctx;
}