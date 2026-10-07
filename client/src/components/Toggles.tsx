import { useLang } from '../lang';
import { useTheme } from '../hooks';

export function LangToggle() {
  const { lang, setLang, langLabel } = useLang();
  return (
    <button
      className="lang-toggle"
      onClick={() => setLang(lang === 'en' ? 'fil' : 'en')}
      title={lang === 'en' ? 'Switch to Filipino' : 'Switch to English'}
      aria-label={lang === 'en' ? 'Switch to Filipino' : 'Switch to English'}
    >
      {langLabel}
    </button>
  );
}

export function ThemeToggle() {
  const { t } = useLang();
  const { isDark, toggleTheme } = useTheme();
  return (
    <button
      className="theme-toggle"
      onClick={toggleTheme}
      aria-label={isDark ? t('tb_light') : t('tb_dark')}
      title={isDark ? t('tb_light') : t('tb_dark')}
    >
      <svg className="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
      </svg>
      <svg className="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
      </svg>
    </button>
  );
}
