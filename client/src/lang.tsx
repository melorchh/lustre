import { createContext, useCallback, useContext, type ReactNode } from 'react';
import { dict, type Lang } from './i18n';

interface LangCtxType {
  lang: Lang;
  t: (key: string, vars?: Record<string, string | number>) => string;
}

const LangCtx = createContext<LangCtxType | null>(null);

export function LangProvider({ children }: { children: ReactNode }) {
  const lang: Lang = 'en';

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

  return (
    <LangCtx.Provider value={{ lang, t }}>{children}</LangCtx.Provider>
  );
}

export function useLang(): LangCtxType {
  const ctx = useContext(LangCtx);
  if (!ctx) throw new Error('useLang must be used within LangProvider');
  return ctx;
}