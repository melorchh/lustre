import { useEffect, useRef, useState, type ReactNode } from 'react';
import type { PageKey } from './types';
import Sidebar from './components/Sidebar';
import TopBar from './components/TopBar';
import { useScrollLock, ToastCtx, type ToastHandle } from './hooks';
import { useLang } from './lang';

interface ShellProps {
  page: PageKey;
  title: string;
  patientName: string;
  children: ReactNode;
}

const NAV_KEYS: { page: string; href: string; key: string; icon: string }[] = [
  { page: 'home', href: 'dashboard.php', key: 'nav_dashboard', icon: 'M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5M9 21v-6h6v6' },
  { page: 'book', href: 'index.php', key: 'nav_book', icon: 'M6 2v6M18 2v6M3 4h18v18H3zM3 10h18' },
  { page: 'records', href: 'my_appointments.php', key: 'nav_records', icon: 'M12 21C12 21 4 14.5 4 9a4 4 0 0 1 8-1 4 4 0 0 1 8 1c0 5.5-8 12-8 12z' },
  { page: 'lab', href: 'lab_request.php', key: 'nav_lab', icon: 'M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2M8.5 2h7M7 15h10' },
  { page: 'profile', href: 'my_profile.php', key: 'nav_profile', icon: 'M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4m0 2c-4 0-8 2-8 5v2h16v-2c0-3-4-5-8-5z' },
];

function BottomNav({ active }: { active: PageKey }) {
  const { t } = useLang();
  const NAV_ITEMS = NAV_KEYS.map((item) => ({ ...item, label: t(item.key) }));
  return (
    <nav className="bottom-nav" aria-label="Primary">
      {NAV_ITEMS.map((item) => {
        const isActive = item.page === active;
        return (
          <a key={item.href} href={item.href} className={isActive ? 'active' : ''} aria-current={isActive ? 'page' : undefined}>
            <span className="bn-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d={item.icon} />
              </svg>
            </span>
            <span>{item.label}</span>
          </a>
        );
      })}
    </nav>
  );
}

export default function Shell({ page, title, patientName, children }: ShellProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [pinned, setPinned] = useState(false);
  const [hovered, setHovered] = useState(false);
  const [toast, setToast] = useState<{ msg: string; type: 'success' | 'error' } | null>(null);
  const toastTimer = useRef<number | undefined>(undefined);
  const [isDesktop, setIsDesktop] = useState(() => window.matchMedia('(min-width: 769px)').matches);

  useEffect(() => {
    const mql = window.matchMedia('(min-width: 769px)');
    const onResize = (e: MediaQueryListEvent) => setIsDesktop(e.matches);
    mql.addEventListener('change', onResize);
    return () => mql.removeEventListener('change', onResize);
  }, []);

  useEffect(() => {
    if (isDesktop) setSidebarOpen(true);
  }, [isDesktop]);

  useEffect(() => {
    if (isDesktop) setPinned(false);
  }, [isDesktop]);

  const railExpanded = isDesktop && (pinned || hovered);

  useScrollLock(!isDesktop && sidebarOpen);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setSidebarOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      const target = e.target as HTMLElement | null;
      if (!target) return;
      const link = target.closest('a[href]') as HTMLAnchorElement | null;
      if (!link) return;
      const href = link.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('javascript') || href.startsWith('http') || link.getAttribute('target') === '_blank') return;
      e.preventDefault();
      document.body.classList.add('page-leaving');
      window.setTimeout(() => {
        window.location.href = href;
      }, 220);
    };
    document.addEventListener('click', onClick);
    return () => document.removeEventListener('click', onClick);
  }, []);

  const showToast: ToastHandle['showToast'] = (msg, type: 'success' | 'error' = 'success') => {
    window.clearTimeout(toastTimer.current);
    setToast({ msg, type });
    toastTimer.current = window.setTimeout(() => setToast(null), 3400);
  };

  return (
    <div className={`app ${railExpanded ? 'rail-expanded' : ''}`}>
      <Sidebar
        patientName={patientName}
        open={sidebarOpen}
        pinned={pinned}
        active={page}
        onClose={() => setSidebarOpen(false)}
        onTogglePin={() => {
          if (isDesktop) setPinned((v) => !v);
          else setSidebarOpen(false);
        }}
        onHoverStart={() => setHovered(true)}
        onHoverEnd={() => setHovered(false)}
      />

      <div className="main">
        <div className="top-row">
          <TopBar title={title} patientName={patientName} onMenu={() => setSidebarOpen((v) => !v)} />
        </div>
        <ToastCtx.Provider value={{ showToast }}>{children}</ToastCtx.Provider>
      </div>

      <BottomNav active={page} />

      <div className={`toast ${toast ? 'show' : ''} ${toast?.type ?? ''}`} role="status">
        {toast?.msg}
      </div>
    </div>
  );
}