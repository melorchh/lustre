import { useEffect, useMemo, useRef, useState } from 'react';
import { getInitialData, initials } from '../api';
import { useLang } from '../lang';
import { LangToggle, ThemeToggle } from './Toggles';

interface TopBarProps {
  title: string;
  patientName: string;
  onMenu: () => void;
}

type IconKind = 'doctor' | 'appt' | 'lab' | 'vital' | 'vaccine' | 'cat';

interface SearchResult {
  group: string;
  href: string;
  label: string;
  sub?: string;
  icon: IconKind;
}

function SearchIcon({ kind }: { kind: IconKind }) {
  const common = {
    width: 16,
    height: 16,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
  } as const;
  switch (kind) {
    case 'doctor':
      return (
        <svg {...common} aria-hidden="true">
          <circle cx="12" cy="8" r="4" />
          <path d="M4 21v-1a8 8 0 0 1 16 0v1" />
        </svg>
      );
    case 'appt':
      return (
        <svg {...common} aria-hidden="true">
          <rect x="3" y="4" width="18" height="17" rx="2" />
          <path d="M8 2v4M16 2v4M3 9h18" />
        </svg>
      );
    case 'lab':
      return (
        <svg {...common} aria-hidden="true">
          <path d="M9 2h6M10 2v5.5L4.5 18a2 2 0 0 0 2 3h11a2 2 0 0 0 2-3L14 7.5V2M7 14h10" />
        </svg>
      );
    case 'vital':
      return (
        <svg {...common} aria-hidden="true">
          <path d="M3 12h4l2-6 4 12 2-6h6" />
        </svg>
      );
    case 'vaccine':
      return (
        <svg {...common} aria-hidden="true">
          <path d="m12 3 9 9M9 12l3 3M6 9l3 3M3 6l3 3" />
          <path d="M15 6 9 12" />
        </svg>
      );
    default:
      return (
        <svg {...common} aria-hidden="true">
          <path d="M3 11h18M5 11l3-7h8l3 7M5 11v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8" />
        </svg>
      );
  }
}

function buildResults(query: string): SearchResult[] {
  const data = getInitialData();
  const q = query.trim().toLowerCase();
  if (!q) return [];

  const res: SearchResult[] = [];
  const has = (s?: string | null) => !!(s && s.toLowerCase().includes(q));

  const doctors = (data.doctors || []).filter((d) => has(d.name) || has(d.specialty) || has(d.schedule));
  for (const d of doctors.slice(0, 6)) {
    res.push({ group: 'Clinics', href: 'index.php', label: d.name, sub: d.specialty, icon: 'doctor' });
  }

  const appts = (data.appointments || []).filter(
    (a) => has(a.doctor_name) || has(a.specialty) || has(a.status) || has(a.appointment_date),
  );
  for (const a of appts.slice(0, 6)) {
    res.push({
      group: 'My Appointments',
      href: 'my_appointments.php',
      label: a.doctor_name,
      sub: `${a.specialty} · ${a.appointment_date}${a.appointment_time ? ' ' + a.appointment_time : ''} · ${a.status}`,
      icon: 'appt',
    });
  }

  const labs = (data.labTests || []).filter(
    (l) => has(l.test_type) || has(l.doctor_name) || has(l.specialty) || has(l.status) || has(l.priority),
  );
  for (const l of labs.slice(0, 6)) {
    res.push({
      group: 'Lab Tests',
      href: 'my_appointments.php#labtests',
      label: l.test_type,
      sub: `${l.specialty || l.doctor_name} · ${l.status}`,
      icon: 'lab',
    });
  }

  const vitals = (data.vitals || []).filter((v) => has(v.blood_pressure) || has(v.notes) || has(v.visit_date));
  for (const v of vitals.slice(0, 4)) {
    res.push({
      group: 'Health Records',
      href: 'my_appointments.php#health',
      label: v.visit_date,
      sub: `${v.blood_pressure ? 'BP ' + v.blood_pressure + ' · ' : ''}${v.notes || 'Vitals check'}`,
      icon: 'vital',
    });
  }

  const vacs = (data.vaccinations || []).filter(
    (v) => has(v.vaccine_name) || has(v.dose_label) || has(v.notes),
  );
  for (const v of vacs.slice(0, 4)) {
    res.push({
      group: 'Health Records',
      href: 'my_appointments.php#health',
      label: v.vaccine_name,
      sub: `${v.dose_label || ''}${v.administered_date ? ' · ' + v.administered_date : ''}`.replace(/^ · /, ''),
      icon: 'vaccine',
    });
  }

  const cats = data.categories || {};
  const catEntries: { label: string; sub: string }[] = [];
  for (const [cat, tests] of Object.entries(cats)) {
    if (has(cat)) catEntries.push({ label: cat, sub: `${tests.length} available tests` });
    for (const t of tests || []) {
      if (has(t)) catEntries.push({ label: t, sub: cat });
    }
  }
  for (const c of catEntries.slice(0, 6)) {
    res.push({ group: 'Lab Categories', href: 'lab_request.php', label: c.label, sub: c.sub, icon: 'cat' });
  }

  return res;
}

export default function TopBar({ title, patientName, onMenu }: TopBarProps) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const boxRef = useRef<HTMLDivElement | null>(null);
  const { t } = useLang();

  const results = useMemo(() => (open ? buildResults(query) : []), [query, open]);

  useEffect(() => {
    const onDocClick = (e: MouseEvent) => {
      const target = e.target as HTMLElement | null;
      if (target && boxRef.current && !boxRef.current.contains(target)) setOpen(false);
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, []);

  const grouped: { group: string; items: SearchResult[] }[] = [];
  for (const r of results) {
    const g = grouped.find((x) => x.group === r.group);
    if (g) g.items.push(r);
    else grouped.push({ group: r.group, items: [r] });
  }
  const showDropdown = open && query.trim().length > 0;
  const noResults = showDropdown && grouped.length === 0;

  return (
    <header className="topbar">
      <h1 className="topbar-title">{title}</h1>

      <div className="topbar-left">
        <button className="menu-btn" onClick={onMenu} aria-label="Open menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <path d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <div className="topbar-search" ref={boxRef}>
          <span className="topbar-search-label">{t('tb_search')}</span>
          <input
            className="topbar-search-input"
            type="text"
            placeholder={t('tb_search_ph')}
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setOpen(true);
            }}
            onFocus={() => setOpen(true)}
            aria-label={t('tb_search')}
          />

          {showDropdown && (
            <div className="search-dropdown">
              {grouped.map((g) => (
                <div className="search-group" key={g.group}>
                  <div className="search-group-title">{g.group}</div>
                  {g.items.map((r, i) => (
                    <a key={r.group + r.label + i} className="search-result" href={r.href}>
                      <span className={`search-result-icon search-result-icon--${r.icon}`}>
                        <SearchIcon kind={r.icon} />
                      </span>
                      <span className="search-result-text">
                        <span className="search-result-label">{r.label}</span>
                        {r.sub ? <span className="search-result-sub">{r.sub}</span> : null}
                      </span>
                    </a>
                  ))}
                </div>
              ))}
              {noResults && <div className="search-dropdown-empty">{t('tb_noresults', { q: query.trim() })}</div>}
            </div>
          )}
        </div>
      </div>

      <div className="topbar-actions">
        <LangToggle />
        <ThemeToggle />
        <div className="client-card">
          <div className="topbar-hello">
            <span className="topbar-hello-label">{t('tb_hello')}</span>
            <strong>{patientName.split(' ')[0] || patientName}</strong>
          </div>
          <div className="avatar avatar--topbar">{initials(patientName)}</div>
        </div>
      </div>
    </header>
  );
}
