import { useEffect, useMemo, type ReactNode } from 'react';
import { getInitial, initials, firstName } from './api';
import { ToastCtx, useTheme, useToastState } from './hooks';
import {
  IconDashboard, IconAppointments, IconPatients, IconWalkins, IconDoctors,
  IconSchedules, IconLab, IconVitals, IconReports, IconArchive, IconLogout,
  IconMenu, IconSun, IconMoon,
} from './icons';

interface NavItem {
  key: string;
  href: string;
  label: string;
  icon: ReactNode;
}

const ADMIN_NAV: NavItem[] = [
  { key: 'dashboard', href: 'admin_dashboard.php', label: 'Dashboard', icon: <IconDashboard /> },
  { key: 'appointments', href: 'admin_appointments.php', label: 'Appointments', icon: <IconAppointments /> },
  { key: 'clients', href: 'admin_clients.php', label: 'Patients', icon: <IconPatients /> },
  { key: 'walkins', href: 'admin_walkins.php', label: 'Walk-ins', icon: <IconWalkins /> },
  { key: 'doctors', href: 'admin_doctors.php', label: 'Doctors', icon: <IconDoctors /> },
  { key: 'schedules', href: 'admin_schedules.php', label: 'Schedules', icon: <IconSchedules /> },
  { key: 'laboratory', href: 'admin_laboratory.php', label: 'Laboratory', icon: <IconLab /> },
  { key: 'vitals', href: 'admin_vitals.php', label: 'Vaccines', icon: <IconVitals /> },
  { key: 'reports', href: 'admin_reports.php', label: 'Reports', icon: <IconReports /> },
  { key: 'history', href: 'admin_history.php', label: 'Archives', icon: <IconArchive /> },
];

const DOCTOR_NAV: NavItem[] = [
  { key: 'dashboard', href: 'doctor_dashboard.php', label: 'Dashboard', icon: <IconDashboard /> },
  { key: 'appointments', href: 'doctor_appointments.php', label: 'Appointments', icon: <IconAppointments /> },
  { key: 'clients', href: 'doctor_clients.php', label: 'Patients', icon: <IconPatients /> },
  { key: 'laboratory', href: 'doctor_laboratory.php', label: 'Laboratory', icon: <IconLab /> },
  { key: 'schedule', href: 'doctor_schedule.php', label: 'Availability', icon: <IconSchedules /> },
];

interface ShellProps {
  active: string;
  title: string;
  children: ReactNode;
}

/**
 * Sidebar + topbar + toast. Mirrors the exact class names the PHP portal used
 * so admin.css / doctor.css apply unchanged. Navigation uses plain links; the
 * PHP shell adds a short fade-out before following them.
 */
export default function Shell({ active, title, children }: ShellProps) {
  const data = getInitial();
  const staff = data.staff || ({} as Record<string, unknown>);
  const role = (staff.role as string) || 'admin';
  const name = (staff.name as string) || '';
  const nav = role === 'doctor' ? DOCTOR_NAV : ADMIN_NAV;
  const brand = role === 'doctor' ? 'Doctor' : 'Admin';
  const displayName = role === 'doctor' ? `Dr. ${name}` : name;

  const { toast, showToast } = useToastState();
  const { isDark, toggleTheme } = useTheme();

  // The PHP pages used onclick=closeSidebar()/toggleSidebar() globals.
  useEffect(() => {
    const toggle = () => {
      const sb = document.getElementById('adminSidebar');
      if (!sb) return;
      const overlay = document.getElementById('sidebarOverlay');
      const isOpen = sb.classList.toggle('open');
      if (isOpen && window.matchMedia('(max-width: 900px)').matches) {
        overlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
      } else {
        overlay?.classList.remove('active');
        document.body.style.overflow = '';
      }
    };
    const close = () => {
      document.getElementById('adminSidebar')?.classList.remove('open');
      document.getElementById('sidebarOverlay')?.classList.remove('active');
      document.body.style.overflow = '';
    };
    (window as unknown as Record<string, unknown>).toggleSidebar = toggle;
    (window as unknown as Record<string, unknown>).closeSidebar = close;
    return () => {
      delete (window as unknown as Record<string, unknown>).toggleSidebar;
      delete (window as unknown as Record<string, unknown>).closeSidebar;
    };
  }, []);

  const prompt = role === 'doctor' ? 'Signed in as' : 'Logged in as';
  const helloLabel = role === 'doctor' ? 'Hello,' : 'Hello,';

  const avatar = useMemo(() => initials(name), [name]);

  return (
    <ToastCtx.Provider value={{ showToast }}>
      <div className="sidebar-overlay" id="sidebarOverlay" onClick={() => (window as unknown as { closeSidebar?: () => void }).closeSidebar?.()}></div>
      <div className={`admin-layout ${role === 'doctor' ? 'doctor-page' : 'admin-portal'}`}>
        <aside className="admin-sidebar" id="adminSidebar">
          <div className="sidebar-brand">
            <span className="brand-icon"><img src="images/Lustre.png" alt="LustreMDC Logo" width="42" height="42" /></span>
            <span className="brand-text"><span>{brand}</span></span>
          </div>
          <div className="sidebar-user">
            <div className="avatar avatar--sm">{avatar}</div>
            <div className="sidebar-user-body">
              <div className="sidebar-user-label">{prompt}</div>
              <div className="sidebar-user-name">{displayName}</div>
            </div>
          </div>
          <nav className="sidebar-nav">
            {nav.map((item) => (
              <a key={item.key} href={item.href} className={`nav-item${item.key === active ? ' active' : ''}`}>
                <span className="nav-icon">{item.icon}</span>
                <span className="rail-label">{item.label}</span>
              </a>
            ))}
          </nav>
          <div className="sidebar-footer">
            <a href={(staff.logoutUrl as string) || 'admin_logout.php'} className="btn-logout" data-tip="Sign Out">
              <span className="nav-icon"><IconLogout /></span>
              <span className="rail-label">Sign Out</span>
            </a>
          </div>
        </aside>

        <main className="admin-content">
          <header className="topbar">
            <div className="topbar-left">
              <button className="mobile-menu-btn" onClick={() => (window as unknown as { toggleSidebar?: () => void }).toggleSidebar?.()} aria-label="Open menu">
                <IconMenu size={22} />
              </button>
              <span className="topbar-heading">
                {role !== 'doctor' ? <span className="topbar-kicker">{brand} portal</span> : null}
                <span className="topbar-title">{title}</span>
              </span>
            </div>
            <div className="topbar-actions">
              {/* Both icons stay mounted: admin.css crossfades them via
                  [data-theme], so rendering only one leaves the button blank. */}
              <button
                type="button"
                className="theme-toggle"
                title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                aria-pressed={isDark}
                onClick={toggleTheme}
              >
                <span className="theme-icon theme-icon--sun"><IconSun /></span>
                <span className="theme-icon theme-icon--moon"><IconMoon /></span>
              </button>
              <div className="topbar-hello">
                <span className="topbar-hello-label">{helloLabel}</span>
                <strong>{role === 'doctor' ? `Dr. ${firstName(name)}` : firstName(name)}</strong>
              </div>
              <div className="avatar avatar--topbar">{avatar}</div>
            </div>
          </header>
          {children}
        </main>
      </div>

      <div id="loadingScreen" className="loading-screen"><div className="spinner"></div><p>Processing...</p></div>
      <div id="toast" className={`toast${toast.show ? ' show' : ''}${toast.type ? ' ' + toast.type : ''}`} role="status">{toast.msg}</div>
    </ToastCtx.Provider>
  );
}
