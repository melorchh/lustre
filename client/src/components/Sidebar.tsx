import type { ReactNode } from 'react';
import { initials } from '../api';
import type { PageKey } from '../types';
import { useLang } from '../lang';
import { LangToggle, ThemeToggle } from './Toggles';

interface SidebarProps {
  patientName: string;
  open: boolean;
  pinned: boolean;
  active: PageKey;
  onClose: () => void;
  onTogglePin: () => void;
  onHoverStart: () => void;
  onHoverEnd: () => void;
}

function IconCal() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" />
    </svg>
  );
}

function IconClipboard() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
      <path d="M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v0a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2z" />
      <path d="M9 12h6M9 16h4" />
    </svg>
  );
}

function IconFlask() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 2h6M10 2v5.5L4.5 18a2 2 0 0 0 2 3h11a2 2 0 0 0 2-3L14 7.5V2" />
      <path d="M7 14h10" />
    </svg>
  );
}

function IconHome() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M3 10.5 12 3l9 7.5" />
      <path d="M5 9.5V21h14V9.5" />
      <path d="M10 21v-6h4v6" />
    </svg>
  );
}

function IconLogout() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <path d="M16 17l5-5-5-5M21 12H9" />
    </svg>
  );
}

function IconDashboard() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="3" width="7" height="9" rx="1.5" />
      <rect x="14" y="3" width="7" height="5" rx="1.5" />
      <rect x="14" y="12" width="7" height="9" rx="1.5" />
      <rect x="3" y="16" width="7" height="5" rx="1.5" />
    </svg>
  );
}

function NavItem({ href, active, label, icon }: { href: string; active?: boolean; label: string; icon: ReactNode }) {
  return (
    <a href={href} data-tip={label} className={`nav-item ${active ? 'active' : ''}`} aria-label={label}>
      <span className="nav-icon">{icon}</span>
      <span className="rail-label">{label}</span>
    </a>
  );
}

export default function Sidebar({ patientName, open, pinned, active, onClose, onTogglePin, onHoverStart, onHoverEnd }: SidebarProps) {
  const { t } = useLang();
  return (
    <>
      <div className={`sidebar-overlay ${open ? 'active' : ''}`} onClick={onClose} aria-hidden="true" />
      <aside
        className={`sidebar ${open ? 'open' : ''} ${pinned ? 'pinned' : ''}`}
        data-tour="nav"
        onMouseEnter={onHoverStart}
        onMouseLeave={onHoverEnd}
      >
        <div className="rail-top">
          <button
            type="button"
            className="rail-brand"
            onClick={onTogglePin}
            aria-label={pinned ? 'Collapse menu' : 'Expand menu'}
            aria-expanded={pinned}
            data-tip={t('nav_menu')}
          >
            <span className="brand-icon">
              <img src="images/Lustre.png" alt="Logo" width="42" height="42" />
            </span>
            <span className="rail-label">LUSTRE MDC</span>
          </button>
          <nav className="rail-nav">
            <NavItem href="dashboard.php" active={active === 'home'} label={t('nav_dashboard')} icon={<IconDashboard />} />
            <NavItem href="index.php" active={active === 'book'} label={t('nav_book')} icon={<IconCal />} />
            <NavItem href="my_appointments.php" active={active === 'records'} label={t('nav_records')} icon={<IconClipboard />} />
            <NavItem href="lab_request.php" active={active === 'lab'} label={t('nav_lab')} icon={<IconFlask />} />
          </nav>
        </div>

        <div className="rail-bottom">
          <div className="rail-toggles">
            <LangToggle />
            <ThemeToggle />
          </div>
          <NavItem href="landing.php" label={t('nav_home')} icon={<IconHome />} />
          <a
            href="my_profile.php"
            className={`rail-user ${active === 'profile' ? 'active' : ''}`}
            data-tip={t('nav_profile')}
            aria-label={t('nav_profile')}
          >
            <span className="avatar avatar--sm">{initials(patientName)}</span>
            <span className="rail-label">{patientName}</span>
          </a>
          <a href="logout.php" className="rail-logout" data-tip={t('nav_logout')} aria-label={t('nav_logout')}>
            <span className="nav-icon">{<IconLogout />}</span>
            <span className="rail-label">{t('nav_logout')}</span>
          </a>
        </div>
      </aside>
    </>
  );
}