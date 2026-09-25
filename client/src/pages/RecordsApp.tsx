import { useEffect, useState, type ReactNode } from 'react';
import type { Appointment, LabTest, Vital, Vaccination, MedicalDate } from '../types';
import { getInitialData, formatLongDate, formatTime12h, formatStamp, initials, fetchAvailableDates, fetchAvailableTimes, rescheduleAppointment } from '../api';
import Shell from '../shell';
import Hero from '../components/Hero';
import { CalendarPicker } from '../components/BookingModal';
import {
  SvgActivity,
  SvgView,
  SvgCalendar,
  SvgClock,
  SvgCheckCircle,
  SvgXCircle,
  SvgFlag,
  SvgFileText,
  SvgStickyNote,
  SvgClipboard,
  SvgFlask,
  SvgCreditCard,
  SvgUser,
  SvgDownload,
  SvgSyringe,
} from '../components/icons';
import { useScrollLock, useToast } from '../hooks';
import { useLang } from '../lang';

type Tab = 'appointments' | 'labtests' | 'health';

function dueInfo(dueDate: string) {
  const target = new Date(dueDate + 'T00:00:00');
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const diff = Math.round((target.getTime() - today.getTime()) / 86400000);
  return { kind: diff < 0 ? 'overdue' : diff === 0 ? 'due' : diff <= 14 ? 'soon' : 'later', diff };
}

function VitalCard({ v }: { v: Vital }) {
  const { t } = useLang();
  return (
    <div className="appt-card">
      <div className="appt-stripe stripe-vital" />
      <div className="appt-body">
        <div className="appt-icon-wrap vital">
          <SvgActivity size={22} />
        </div>
        <div className="appt-main">
          <div className="appt-doctor">{t('rec_prenatal')}</div>
          <div className="appt-specialty">{t('rec_vitals_record')}</div>
          <div className="appt-meta">
            <span className="meta-chip"><SvgCalendar size={13} /> {formatLongDate(v.visit_date)}</span>
          </div>
          <div className="vital-grid">
            {v.weight_kg != null && (
              <span className="vital-chip"><strong>{v.weight_kg} kg</strong><label>{t('rec_weight')}</label></span>
            )}
            {v.blood_pressure && (
              <span className="vital-chip"><strong>{v.blood_pressure}</strong><label>{t('rec_bp')}</label></span>
            )}
            {v.heart_rate != null && (
              <span className="vital-chip"><strong>{v.heart_rate} bpm</strong><label>{t('rec_hr')}</label></span>
            )}
            {v.fundal_height != null && (
              <span className="vital-chip"><strong>{v.fundal_height} cm</strong><label>{t('rec_fundal')}</label></span>
            )}
          </div>
          {v.notes && <p className="health-note"><span>{t('rec_note')}</span>{v.notes}</p>}
        </div>
        <div className="appt-right">
          <span className="status-pill pill-confirmed">{t('rec_vitals')}</span>
        </div>
      </div>
    </div>
  );
}

function VaccineCard({ v }: { v: Vaccination }) {
  const { t } = useLang();
  const due = v.next_due_date ? dueInfo(v.next_due_date) : null;
  const dueText = (() => {
    if (!due) return '';
    if (due.kind === 'overdue') return t('rec_due_overdue', { d: formatLongDate(v.next_due_date!) });
    if (due.kind === 'due') return t('rec_due_today');
    if (due.kind === 'soon') return t('rec_due_soon', { d: formatLongDate(v.next_due_date!), n: due.diff });
    return t('rec_due_later', { d: formatLongDate(v.next_due_date!) });
  })();
  return (
    <div className="appt-card">
      <div className="appt-stripe stripe-vaccine" />
      <div className="appt-body">
        <div className="appt-icon-wrap vaccine">
          <SvgSyringe size={22} />
        </div>
        <div className="appt-main">
          <div className="appt-doctor">{v.vaccine_name}</div>
          <div className="appt-specialty">{t('rec_vax_record')}</div>
          <div className="appt-meta">
            <span className="meta-chip"><SvgCalendar size={13} /> {t('rec_given_on', { d: formatLongDate(v.administered_date) })}</span>
            <span className="meta-chip"><SvgClock size={13} /> {v.dose_label}</span>
          </div>
          {due && (
            <div className={`vax-due vax-due--${due.kind}`}>
              {due.kind === 'overdue' || due.kind === 'due' || due.kind === 'soon' ? <SvgView size={13} /> : <SvgCalendar size={13} />}
              {t('rec_next_dose', { v: v.vaccine_name, d: dueText })}
            </div>
          )}
          {v.notes && <p className="health-note"><span>{t('rec_note')}</span>{v.notes}</p>}
          <div className="appt-actions">
            <a className="btn-proof-appt" href={`vaccine_card.php?id=${v.id}`}>
              <SvgView size={14} /> {t('rec_view_card')}
            </a>
            <a className="btn-result-appt" href={`vaccine_card_pdf.php?id=${v.id}`} onClick={(e) => { e.preventDefault(); window.open(`vaccine_card_pdf.php?id=${v.id}`, '_blank', 'noopener'); }}>
              <SvgDownload size={14} /> {t('rec_download_pdf')}
            </a>
          </div>
        </div>
        <div className="appt-right">
          <span className="status-pill pill-completed">{t('rec_completed')}</span>
        </div>
      </div>
    </div>
  );
}

const STATUS_META: Record<string, { icon: ReactNode; label: string }> = {
  pending: { icon: <SvgClock size={22} />, label: 'Pending' },
  confirmed: { icon: <SvgCheckCircle size={22} />, label: 'Confirmed' },
  completed: { icon: <SvgFlag size={22} />, label: 'Completed' },
  cancelled: { icon: <SvgXCircle size={22} />, label: 'Cancelled' },
};

const LAB_META: Record<string, { icon: ReactNode; label: string }> = {
  pending: { icon: <SvgClock size={22} />, label: 'Pending' },
  processing: { icon: <SvgFlask size={22} />, label: 'Processing' },
  completed: { icon: <SvgFileText size={22} />, label: 'Completed' },
  cancelled: { icon: <SvgXCircle size={22} />, label: 'Cancelled' },
};

function statusOf(value: string) {
  return (value || 'unknown').toLowerCase();
}

function AppointmentCard({ apt, onCancel, onResched }: { apt: Appointment; onCancel: (a: Appointment) => void; onResched: (a: Appointment) => void }) {
  const { t } = useLang();
  const s = statusOf(apt.status);
  const meta = STATUS_META[s] ?? { icon: <SvgClipboard size={22} />, label: apt.status };
  const cancellable = s === 'pending' || s === 'confirmed';
  const reschedulable = s === 'pending' || s === 'confirmed';
  return (
    <div className="appt-card">
      <div className={`appt-stripe stripe-${s}`} />
      <div className="appt-body">
        <div className={`appt-icon-wrap ${s}`}>{meta.icon}</div>
        <div className="appt-main">
          <div className="appt-doctor">Dr. {apt.doctor_name}</div>
          <div className="appt-specialty">{apt.specialty}</div>
          <div className="appt-meta">
            <span className="meta-chip"><SvgCalendar size={13} /> {formatLongDate(apt.appointment_date)}</span>
            <span className="meta-chip"><SvgClock size={13} /> {formatTime12h(apt.appointment_time)}</span>
            <span className="meta-chip"><SvgClipboard size={13} /> {t('rec_booked_on', { d: formatStamp(apt.created_at) })}</span>
          </div>
          {(cancellable || reschedulable) && (
            <div className="appt-actions">
              {reschedulable && (
                <button type="button" className="btn-resched-appt" onClick={() => onResched(apt)}>
                  <SvgCalendar size={14} /> {t('rec_reschedule')}
                </button>
              )}
              {cancellable && (
                <button type="button" className="btn-cancel-appt" onClick={() => onCancel(apt)}>
                  {t('rec_cancel_apt')}
                </button>
              )}
            </div>
          )}
          {s !== 'cancelled' && (
            <a className="btn-proof-appt" href={`appointment_confirmation.php?id=${apt.id}`}>
              <SvgDownload size={14} /> {t('rec_view_proof')}
            </a>
          )}
          {s === 'completed' && apt.result && (
            <a className="btn-result-appt" href={`appointment_result.php?id=${apt.id}`}>
              <SvgFileText size={14} /> {t('rec_view_result')}
            </a>
          )}
        </div>
        <div className="appt-right">
          <span className={`status-pill pill-${s}`}>{meta.label}</span>
          {apt.payment_status && (
            <span className={`payment-pill payment-${statusOf(apt.payment_status)}`}>
              {apt.payment_status === 'paid' ? <><SvgCreditCard size={13} /> {t('rec_paid')}</> : <>{t('rec_unpaid')}</>}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

function LabCard({ test, index }: { test: LabTest; index: number }) {
  const { t } = useLang();
  const [open, setOpen] = useState(false);
  const s = statusOf(test.status);
  const meta = LAB_META[s] ?? { icon: <SvgFlask size={22} />, label: test.status };
  const prio = test.priority ? test.priority.toLowerCase() : '';
  const hasResult = !!test.result;
  const hasNotes = !!test.notes;
  return (
    <div className={`lab-card ${open ? 'open' : ''}`} style={{ animationDelay: `${index * 60}ms` }}>
      <button type="button" className="lab-card-header" onClick={() => setOpen((v) => !v)} aria-expanded={open}>
        <div className={`lab-icon ${s}`}>{meta.icon}</div>
        <div className="lab-info">
          <div className="lab-test-name">{test.test_type}</div>
          <div className="lab-sub">
            Dr. {test.doctor_name} • {formatStamp(test.created_at)}
          </div>
        </div>
        <div className="lab-badges">
          {prio && prio !== 'normal' && (
            <span className={`priority-badge prio-${prio}`}>{prio.toUpperCase()}</span>
          )}
          <span className={`lab-status-pill ls-${s}`}>{meta.label}</span>
          {hasResult && <span className="result-tag"><SvgFileText size={13} /> {t('rec_result_tag')}</span>}
          <span className="lab-chevron">▾</span>
        </div>
      </button>

      <div className="lab-result-panel">
        <div className="result-grid">
          <div className="result-item">
            <label>{t('rec_requesting_doc')}</label>
            <span>Dr. {test.doctor_name}</span>
          </div>
          <div className="result-item">
            <label>{t('bm_specialty')}</label>
            <span>{test.specialty}</span>
          </div>
          <div className="result-item">
            <label>{t('rec_requested_on')}</label>
            <span>{formatLongDate(test.created_at.slice(0, 10))}</span>
          </div>
          <div className="result-item">
            <label>{t('rec_scheduled_date')}</label>
            <span>{test.scheduled_date ? formatLongDate(test.scheduled_date) : '—'}</span>
          </div>
          <div className="result-item">
            <label>{t('rec_priority')}</label>
            <span>{test.priority ? test.priority.charAt(0).toUpperCase() + test.priority.slice(1) : '—'}</span>
          </div>
          <div className="result-item">
            <label>{t('rec_status')}</label>
            <span>{meta.label}</span>
          </div>
        </div>

        <div className="lab-actions">
          {s !== 'cancelled' && (
            <a className="btn-proof-appt" href={`lab_confirmation.php?id=${test.id}`} onClick={(e) => { e.preventDefault(); e.stopPropagation(); window.open(`lab_confirmation.php?id=${test.id}`, '_blank', 'noopener'); }}>
              <SvgView size={14} /> {t('rec_view_proof')}
            </a>
          )}
          {s === 'completed' && hasResult && (
            <a className="btn-result-appt" href={`lab_result.php?id=${test.id}`} onClick={(e) => { e.preventDefault(); e.stopPropagation(); window.open(`lab_result.php?id=${test.id}`, '_blank', 'noopener'); }}>
              <SvgFileText size={14} /> {t('rec_view_result')}
            </a>
          )}
        </div>
        {hasResult ? (
          <>
            <div className="completed-banner">
              <SvgCheckCircle size={20} />
              {t('rec_result_ready')}
            </div>
            <div className="result-box">
              <div className="result-box-label"><SvgClipboard size={15} /> {t('rec_findings')}</div>
              <div className="result-content">{test.result}</div>
            </div>
            {hasNotes && (
              <div className="result-box">
                <div className="result-box-label"><SvgStickyNote size={15} /> {t('rec_clinical_notes')}</div>
                <div className="result-content">{test.notes}</div>
              </div>
            )}
          </>
        ) : (
          <div className={`no-result-banner no-result--${s}`}>
            {s === 'processing' ? <SvgFlask size={20} /> : s === 'cancelled' ? <SvgXCircle size={20} /> : <SvgClock size={20} />}
            {s === 'processing' && t('rec_being_processed')}
            {s === 'cancelled' && t('rec_cancelled_lab')}
            {s !== 'processing' && s !== 'cancelled' && t('rec_pending_lab')}
          </div>
        )}
      </div>
    </div>
  );
}

interface CancelModalProps {
  apt: Appointment;
  onClose: () => void;
  onConfirm: () => void;
}

function CancelModal({ apt, onClose, onConfirm }: CancelModalProps) {
  const { t } = useLang();
  return (
    <div className="modal" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal-sheet modal-sheet--sm" role="dialog" aria-modal="true">
        <button className="modal-close-btn" onClick={onClose} aria-label="Close">
          ✕
        </button>
        <div className="modal-handle" aria-hidden="true" />
        <div className="modal-center-title">
          <span className="modal-icon-big"><SvgCalendar size={34} /></span>
          <h3>{t('rec_cancel_title')}</h3>
          <p>{t('rec_cancel_sub')}</p>
        </div>
        <div className="cancel-appt-info">
          <div className="row">
            <span><SvgUser size={16} /></span>
            <span className="lbl">{t('bm_doctor')}</span>
            <strong>Dr. {apt.doctor_name}</strong>
          </div>
          <div className="row">
            <span><SvgCalendar size={16} /></span>
            <span className="lbl">{t('bm_date')}</span>
            <strong>{formatLongDate(apt.appointment_date)}</strong>
          </div>
          <div className="row">
            <span><SvgClock size={16} /></span>
            <span className="lbl">{t('bm_time')}</span>
            <strong>{formatTime12h(apt.appointment_time)}</strong>
          </div>
        </div>
        <div className="modal-actions">
          <button className="btn-keep" onClick={onClose}>
            {t('rec_keep')}
          </button>
          <button className="btn-cancel-confirm" onClick={onConfirm}>
            {t('rec_yes_cancel')}
          </button>
        </div>
      </div>
    </div>
  );
}

function ReschedSkeleton({ count }: { count: number }) {
  return (
    <>
      {Array.from({ length: count }).map((_, i) => (
        <div className="chip chip--skeleton" key={i} />
      ))}
    </>
  );
}

interface RescheduleModalProps {
  apt: Appointment;
  onClose: () => void;
  onRescheduled: () => void;
}

function RescheduleModal({ apt, onClose, onRescheduled }: RescheduleModalProps) {
  const { t } = useLang();
  const [dates, setDates] = useState<MedicalDate[]>([]);
  const [datesLoading, setDatesLoading] = useState(true);
  const [datesError, setDatesError] = useState(false);
  const [selectedDate, setSelectedDate] = useState('');
  const [times, setTimes] = useState<string[]>([]);
  const [timesLoading, setTimesLoading] = useState(false);
  const [timesError, setTimesError] = useState(false);
  const [selectedTime, setSelectedTime] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setDatesLoading(true);
    setDatesError(false);
    fetchAvailableDates(apt.doctor_id)
      .then((d) => {
        if (cancelled) return;
        setDates(d);
        setDatesLoading(false);
      })
      .catch(() => {
        if (cancelled) return;
        setDates([]);
        setDatesLoading(false);
        setDatesError(true);
      });
    return () => {
      cancelled = true;
    };
  }, [apt.doctor_id]);

  const pickDate = (date: string) => {
    if (date === selectedDate) return;
    setSelectedDate(date);
    setSelectedTime('');
    setTimes([]);
    setTimesLoading(true);
    setTimesError(false);
    fetchAvailableTimes(apt.doctor_id, date)
      .then((t) => {
        setTimes(t);
        setTimesLoading(false);
      })
      .catch(() => {
        setTimes([]);
        setTimesLoading(false);
        setTimesError(true);
      });
  };

  const pickTime = (time: string) => setSelectedTime(time);

  const submit = async () => {
    if (!selectedDate || !selectedTime || submitting) return;
    setSubmitting(true);
    try {
      await rescheduleAppointment(apt.id, apt.doctor_id, selectedDate, selectedTime);
      onRescheduled();
    } catch (e) {
      const msg = e instanceof Error ? e.message : t('rec_resched_failed');
      if (typeof window !== 'undefined') {
        const t = document.getElementById('toast');
        if (t) {
          t.textContent = msg;
          t.className = 'toast show error';
          window.setTimeout(() => (t.className = 'toast'), 3500);
        }
      }
      setSubmitting(false);
    }
  };

  const canConfirm = !!(selectedDate && selectedTime) && !submitting;

  return (
    <div className="modal" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal-sheet" role="dialog" aria-modal="true">
        <div className="modal-handle" aria-hidden="true" />
        <button className="modal-close-btn" onClick={onClose} aria-label="Close">
          ✕
        </button>

        <div className="modal-doctor">
          <div className="avatar avatar--lg">{apt.doctor_name ? initials(apt.doctor_name) : 'Dr'}</div>
          <div className="modal-doctor-info">
            <div className="modal-title">Dr. {apt.doctor_name}</div>
            <div className="modal-sub">{apt.specialty}</div>
          </div>
        </div>

        <div className="step-pill">
          <span className="step-pill-dot" />
          {t('rec_resched_title')}
        </div>

        <div className="picker-block">
          <span className="picker-label">{t('bm_dates')}</span>
          {datesLoading ? (
            <div className="dates-grid" onKeyDown={(e) => e.stopPropagation()}>
              <ReschedSkeleton count={6} />
            </div>
          ) : datesError ? (
            <div className="muted-msg wrap">{t('bm_dates_err')}</div>
          ) : dates.length === 0 ? (
            <div className="muted-msg wrap">{t('bm_no_dates')}</div>
          ) : (
            <CalendarPicker dates={dates} selectedDate={selectedDate} onPick={pickDate} />
          )}
        </div>

        <div className="picker-block">
          <span className="picker-label">{t('bm_times')}</span>
          <div className="times-grid">
            {!selectedDate ? (
              <div className="muted-msg wrap">{t('bm_select_date_first')}</div>
            ) : timesLoading ? (
              <ReschedSkeleton count={9} />
            ) : timesError ? (
              <div className="muted-msg wrap">{t('bm_times_err')}</div>
            ) : times.length === 0 ? (
              <div className="muted-msg wrap">{t('bm_no_times')}</div>
            ) : (
              times.map((t) => (
                <button
                  type="button"
                  key={t}
                  className={`time-btn ${selectedTime === t ? 'active' : ''}`}
                  onClick={() => pickTime(t)}
                >
                  {formatTime12h(t)}
                </button>
              ))
            )}
          </div>
        </div>

        <div className="duration-note">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 3" />
          </svg>
          {t('bm_duration')}
        </div>

        <button className="btn-confirm" disabled={!canConfirm} onClick={submit} data-anim={canConfirm ? 'pulse' : undefined}>
          {submitting ? (
            <span className="btn-spinner" aria-hidden="true" />
          ) : (
            <>
              {t('rec_confirm_resched')}
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M5 12h14M13 6l6 6-6 6" />
              </svg>
            </>
          )}
        </button>
      </div>
    </div>
  );
}

export default function RecordsApp() {
  const { patientName, appointments = [], labTests = [], vitals = [], vaccinations = [] } = getInitialData();
  const { showToast } = useToast();
  const { t } = useLang();
  const [tab, setTab] = useState<Tab>(() => {
    const h = window.location.hash.replace('#', '');
    if (h === 'labtests') return 'labtests';
    if (h === 'health') return 'health';
    return 'appointments';
  });
  const [cancelTarget, setCancelTarget] = useState<Appointment | null>(null);
  const [cancelling, setCancelling] = useState(false);
  const [reschedTarget, setReschedTarget] = useState<Appointment | null>(null);

  useScrollLock(!!cancelTarget || !!reschedTarget);

  const confirmRescheduled = () => {
    setReschedTarget(null);
    showToast(t('rec_rescheduled_ok'), 'success');
    window.setTimeout(() => window.location.reload(), 900);
  };

  const confirmCancel = async () => {
    if (!cancelTarget) return;
    setCancelling(true);
    try {
      const res = await fetch('cancel_appointment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `appointment_id=${cancelTarget.id}`,
      });
      const text = await res.text();
      if (text.trim() === 'success') {
        setCancelTarget(null);
        showToast(t('rec_cancelled_ok'), 'success');
        window.setTimeout(() => window.location.reload(), 900);
      } else {
        showToast(text, 'error');
        setCancelTarget(null);
      }
    } catch (err) {
      showToast(t('rec_network_err'), 'error');
      setCancelTarget(null);
    } finally {
      setCancelling(false);
    }
  };

  const empty = (icon: ReactNode, title: string, sub: string, ctaHref?: string) => (
    <div className="empty-state">
      <span className="empty-state-icon">{icon}</span>
      <h3>{title}</h3>
      <p>{sub}</p>
      {ctaHref && (
        <a href={ctaHref} className="btn-primary">
          {ctaHref === 'index.php' ? t('rec_book_now') : t('rec_request_lab')}
        </a>
      )}
    </div>
  );

  const healthCount = vitals.length + vaccinations.length;

  return (
    <Shell page="records" title={t('rec_title')} patientName={patientName}>
      <main className="page-content">
        <Hero
          tag={t('rec_tag')}
          title={t('rec_hero_title')}
          sub={t('rec_hero_sub')}
        />

        <div className="tab-nav" role="tablist">
          <button
            type="button"
            className={`tab-btn ${tab === 'appointments' ? 'active' : ''}`}
            onClick={() => setTab('appointments')}
            role="tab"
            aria-selected={tab === 'appointments'}
          >
            <span className="tab-inline-icon"><SvgCalendar size={15} /></span>
            {t('rec_tab_appts')}
            <span className="tab-count">{appointments.length}</span>
          </button>
          <button
            type="button"
            className={`tab-btn ${tab === 'labtests' ? 'active' : ''}`}
            onClick={() => setTab('labtests')}
            role="tab"
            aria-selected={tab === 'labtests'}
          >
            <span className="tab-inline-icon"><SvgFlask size={15} /></span>
            {t('rec_tab_lab')}
            <span className="tab-count">{labTests.length}</span>
          </button>
          <button
            type="button"
            className={`tab-btn ${tab === 'health' ? 'active' : ''}`}
            onClick={() => setTab('health')}
            role="tab"
            aria-selected={tab === 'health'}
          >
            <span className="tab-inline-icon"><SvgActivity size={15} /></span>
            {t('rec_tab_health')}
            <span className="tab-count">{healthCount}</span>
          </button>
        </div>

        {tab === 'appointments' ? (
          appointments.length === 0 ? (
            empty(<SvgCalendar size={40} />, t('rec_empty_appts'), t('rec_empty_appts_sub'), 'index.php')
          ) : (
            <div className="appt-list">
              {appointments.map((apt) => (
                <AppointmentCard key={apt.id} apt={apt} onCancel={setCancelTarget} onResched={setReschedTarget} />
              ))}
            </div>
          )
        ) : tab === 'labtests' ? (
          labTests.length === 0 ? (
            empty(<SvgFlask size={40} />, t('rec_empty_lab'), t('rec_empty_lab_sub'), 'lab_request.php')
          ) : (
            <div className="lab-list">
              {labTests.map((lt, i) => (
                <LabCard key={lt.id} test={lt} index={i} />
              ))}
            </div>
          )
        ) : healthCount === 0 ? (
          empty(<SvgActivity size={40} />, t('rec_empty_health'), t('rec_empty_health_sub'))
        ) : (
          <div className="health-list">
            {vaccinations.length > 0 && (
              <>
                <h2 className="health-section-title">{t('rec_vaccinations')}</h2>
                {vaccinations.map((vc) => (
                  <VaccineCard key={vc.id} v={vc} />
                ))}
              </>
            )}
            {vitals.length > 0 && (
              <>
                <h2 className="health-section-title">{t('rec_vitals_log')}</h2>
                {vitals.map((vk) => (
                  <VitalCard key={vk.id} v={vk} />
                ))}
              </>
            )}
          </div>
        )}
      </main>

      {cancelTarget && (
        <CancelModal apt={cancelTarget} onClose={() => setCancelTarget(null)} onConfirm={confirmCancel} />
      )}

      {reschedTarget && (
        <RescheduleModal apt={reschedTarget} onClose={() => setReschedTarget(null)} onRescheduled={confirmRescheduled} />
      )}

      {cancelling && (
        <div className="loading-screen active">
          <div className="spinner" />
          <p>{t('rec_processing')}</p>
        </div>
      )}
    </Shell>
  );
}