import { useMemo, useState } from 'react';
import type { Doctor } from '../types';
import { getInitialData } from '../api';
import Shell from '../shell';
import Hero from '../components/Hero';
import DoctorCard from '../components/DoctorCard';
import BookingModal from '../components/BookingModal';
import { useScrollLock } from '../hooks';
import { useLang } from '../lang';

export default function BookApp() {
  const { patientName, doctors } = getInitialData();
  const { t } = useLang();

  const [search, setSearch] = useState('');
  const [selectedDoctor, setSelectedDoctor] = useState<Doctor | null>(null);
  const [modalDoctor, setModalDoctor] = useState<Doctor | null>(null);

  useScrollLock(!!modalDoctor);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return doctors ?? [];
    return (doctors ?? []).filter((d) => {
      if (d.name.toLowerCase().includes(q) || d.specialty.toLowerCase().includes(q)) return true;
      return (d.test_procedures || '')
        .split('\n')
        .some((p) => p.trim().toLowerCase().includes(q));
    });
  }, [doctors, search]);

  return (
    <Shell page="book" title={t('book_title')} patientName={patientName}>
      <main className="page-content">
        <Hero
          tag={t('book_tag')}
          title={t('book_hero_title')}
          sub={t('book_hero_sub')}
        />

        <div className="section-hdr">
          <h2>{t('book_section')}</h2>
          <p>{t('book_section_sub')}</p>
        </div>

        <div className="search-box">
          <svg className="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="11" cy="11" r="7" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <input
            type="text"
            className="search-input"
            placeholder={t('book_search_ph')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>

        {filtered.length === 0 ? (
          <div className="empty-state">
            <span className="empty-state-icon">
              <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.35-4.35" />
              </svg>
            </span>
            <h3>{t('book_no_doctors')}</h3>
            <p>{t('book_no_doctors_sub')}</p>
          </div>
        ) : (
          <div className="doctors-grid">
            {filtered.map((d, i) => (
              <DoctorCard
                key={d.id}
                doctor={d}
                index={i}
                selected={selectedDoctor?.id === d.id}
                onSelect={() => setSelectedDoctor(d)}
              />
            ))}
          </div>
        )}
      </main>

      <div className={`cta-bar ${selectedDoctor ? 'cta-bar--visible' : ''}`}>
        <div className="cta-bar-info">
          {selectedDoctor ? (
            <>
              <span className="cta-bar-label">{t('book_selected')}</span>
              <span className="cta-bar-doctor">Dr. {selectedDoctor.name}</span>
            </>
          ) : (
            <span className="cta-bar-label cta-bar-hint">{t('book_select_hint')}</span>
          )}
        </div>
        <button
          className="cta-btn"
          disabled={!selectedDoctor}
          onClick={() => selectedDoctor && setModalDoctor(selectedDoctor)}
        >
          {t('book_continue')}
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M5 12h14M13 6l6 6-6 6" />
          </svg>
        </button>
      </div>

      {modalDoctor && (
        <BookingModal doctor={modalDoctor} onClose={() => setModalDoctor(null)} />
      )}
    </Shell>
  );
}