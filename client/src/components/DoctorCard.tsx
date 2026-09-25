import type { Doctor } from '../types';
import { initials } from '../api';
import { useLang } from '../lang';

interface DoctorCardProps {
  doctor: Doctor;
  index: number;
  selected: boolean;
  onSelect: () => void;
}

const gradients = [
  ['#0f766e', '#2dd4bf'],
  ['#2563eb', '#38bdf8'],
  ['#115e59', '#14b8a6'],
  ['#1d4ed8', '#60a5fa'],
  ['#0d9488', '#22d3ee'],
  ['#1e40af', '#0ea5e9'],
];

export default function DoctorCard({ doctor, index, selected, onSelect }: DoctorCardProps) {
  const { t } = useLang();
  const g = gradients[index % gradients.length];
  return (
    <button
      type="button"
      className={`doctor-card ${selected ? 'selected' : ''}`}
      onClick={onSelect}
      style={{ animationDelay: `${index * 60}ms` }}
    >
      {selected && (
        <span className="check-mark">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20 6 9 17l-5-5" />
          </svg>
        </span>
      )}
      <div
        className="avatar avatar--card"
        style={{ background: `linear-gradient(135deg, ${g[0]}, ${g[1]})` }}
      >
        {initials(doctor.name)}
      </div>
      <div className="doctor-card-body">
        <div className="doctor-card-name">Dr. {doctor.name}</div>
        <div className="doctor-card-specialty">{doctor.specialty}</div>
        <div className="doctor-card-meta">
          {doctor.experience != null && doctor.experience > 0 && (
            <span className="doctor-chip">{t('doc_yrs_exp', { y: doctor.experience })}</span>
          )}
          <span className="doctor-schedule">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <path d="M16 2v4M8 2v4M3 10h18" />
            </svg>
            {doctor.schedule || t('doc_schedule_varies')}
          </span>
        </div>
      </div>
    </button>
  );
}