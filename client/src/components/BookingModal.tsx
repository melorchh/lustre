import { useEffect, useMemo, useState } from 'react';
import type { Doctor, MedicalDate } from '../types';
import { fetchAvailableDates, fetchAvailableTimes, bookAppointment, formatTime12h, formatLongDate, initials } from '../api';
import { servicesFor, OTHER_SERVICE } from '../services';
import Select from './Select';
import { useLang } from '../lang';

interface BookingModalProps {
  doctor: Doctor;
  onClose: () => void;
}

function SkeletonChips({ count }: { count: number }) {
  return (
    <>
      {Array.from({ length: count }).map((_, i) => (
        <div className="chip chip--skeleton" key={i} />
      ))}
    </>
  );
}

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const pad2 = (n: number) => String(n).padStart(2, '0');

export function CalendarPicker({
  dates,
  selectedDate,
  onPick,
}: {
  dates: MedicalDate[];
  selectedDate: string;
  onPick: (date: string) => void;
}) {
  const available = useMemo(() => new Set(dates.map((d) => d.date)), [dates]);
  const first = useMemo(() => new Date(`${dates[0].date}T00:00:00`), [dates]);
  const last = useMemo(() => new Date(`${dates[dates.length - 1].date}T00:00:00`), [dates]);
  const [view, setView] = useState(() => ({ y: first.getFullYear(), m: first.getMonth() }));
  const { t } = useLang();

  const canPrev =
    view.y > first.getFullYear() || (view.y === first.getFullYear() && view.m > first.getMonth());
  const canNext =
    view.y < last.getFullYear() || (view.y === last.getFullYear() && view.m < last.getMonth());

  const firstDow = new Date(view.y, view.m, 1).getDay();
  const daysInMonth = new Date(view.y, view.m + 1, 0).getDate();
  const monthLabel = new Date(view.y, view.m, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

  const cells: (number | null)[] = [];
  for (let i = 0; i < firstDow; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);

  const shiftMonth = (dir: 1 | -1) =>
    setView((v) => {
      const m = v.m + dir;
      return m < 0 ? { y: v.y - 1, m: 11 } : m > 11 ? { y: v.y + 1, m: 0 } : { y: v.y, m };
    });

  return (
    <div className="calendar" onKeyDown={(e) => e.stopPropagation()}>
      <div className="cal-head">
        <button
          type="button"
          className="cal-nav"
          disabled={!canPrev}
          onClick={() => shiftMonth(-1)}
          aria-label="Previous month"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        </button>
        <span className="cal-month">{monthLabel}</span>
        <button
          type="button"
          className="cal-nav"
          disabled={!canNext}
          onClick={() => shiftMonth(1)}
          aria-label="Next month"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </div>

      <div className="cal-weekdays">
        {WEEKDAYS.map((w) => (
          <span key={w}>{w}</span>
        ))}
      </div>

      <div className="cal-days">
        {cells.map((day, i) => {
          if (day === null) return <span key={`blank-${i}`} className="cal-day cal-day--blank" />;
          const dateStr = `${view.y}-${pad2(view.m + 1)}-${pad2(day)}`;
          if (!available.has(dateStr)) {
            return (
              <span key={dateStr} className="cal-day cal-day--disabled">
                <span className="cal-day-num">{day}</span>
              </span>
            );
          }
          return (
            <span key={dateStr} className="cal-day">
              <button
                type="button"
                className={`cal-btn ${selectedDate === dateStr ? 'active' : ''}`}
                onClick={() => onPick(dateStr)}
              >
                <span className="cal-day-num">{day}</span>
              </button>
            </span>
          );
        })}
      </div>

      <div className="cal-legend">
        <span className="cal-legend-dot" />
        <span>{t('bm_cal_legend')}</span>
        {selectedDate && (
          <span className="cal-picked">{t('bm_cal_selected', { d: formatLongDate(selectedDate) })}</span>
        )}
      </div>
    </div>
  );
}

export default function BookingModal({ doctor, onClose }: BookingModalProps) {
  const [dates, setDates] = useState<MedicalDate[]>([]);
  const [datesLoading, setDatesLoading] = useState(true);
  const [datesError, setDatesError] = useState(false);

  const [selectedDate, setSelectedDate] = useState('');
  const [times, setTimes] = useState<string[]>([]);
  const [timesLoading, setTimesLoading] = useState(false);
  const [timesError, setTimesError] = useState(false);

  const [selectedTime, setSelectedTime] = useState('');
  const [service, setService] = useState('');
  const [serviceOther, setServiceOther] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);
  const [bookedApptId, setBookedApptId] = useState(0);
  const [bookingError, setBookingError] = useState<{
    conflict: boolean;
    detail: string;
    date: string;
    time: string;
  } | null>(null);
  const { t } = useLang();

  const services = useMemo(() => servicesFor(doctor.specialty), [doctor.specialty]);
  const serviceOptions = useMemo(() => [...services, OTHER_SERVICE], [services]);
  const procs = (doctor.test_procedures || '')
    .split('\n')
    .map((p) => p.trim())
    .filter(Boolean);

  const serviceValue = service === OTHER_SERVICE ? serviceOther.trim() : service.trim();

  useEffect(() => {
    let cancelled = false;
    setDatesLoading(true);
    setDatesError(false);
    fetchAvailableDates(doctor.id)
      .then((d) => {
        if (cancelled) return;
        setDates(d);
        setDatesLoading(false);
        if (d.length === 0) setDatesError(false);
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
  }, [doctor.id]);

  useEffect(() => {
    if (success) {
      const t = window.setTimeout(() => {
        window.location.href = 'my_appointments.php';
      }, 6000);
      return () => window.clearTimeout(t);
    }
  }, [success]);

  const loadTimes = (date: string) => {
    setSelectedTime('');
    setTimes([]);
    setTimesLoading(true);
    setTimesError(false);
    fetchAvailableTimes(doctor.id, date)
      .then((list) => {
        setTimes(list);
        setTimesLoading(false);
      })
      .catch(() => {
        setTimes([]);
        setTimesLoading(false);
        setTimesError(true);
      });
  };

  const pickDate = (date: string) => {
    if (date === selectedDate) return;
    setSelectedDate(date);
    loadTimes(date);
  };

  const pickTime = (time: string) => setSelectedTime(time);

  const submit = async () => {
    if (!selectedDate || !selectedTime || !serviceValue) return;
    setSubmitting(true);
    try {
      const id = await bookAppointment(doctor.id, selectedDate, selectedTime, serviceValue);
      setBookedApptId(id);
      setSuccess(true);
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Booking failed. Please try again.';
      // "slot just taken by someone else" — refresh the list so the stale
      // time disappears and the patient can pick another one
      const conflict = /already booked|overlapping/i.test(msg);
      const stale = conflict || /not available|time off/i.test(msg);
      setBookingError({ conflict, detail: msg, date: selectedDate, time: selectedTime });
      if (stale) loadTimes(selectedDate);
    } finally {
      setSubmitting(false);
    }
  };

  const canConfirm = !!(selectedDate && selectedTime && serviceValue) && !submitting;

  return (
    <div className="modal" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal-sheet" role="dialog" aria-modal="true">
        {success ? (
          <>
            <button className="modal-close-btn" onClick={onClose} aria-label="Close">
              ✕
            </button>
            <div className="success-box success-box--card">
              <div className="success-check">
                <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
              </div>
              <h2 className="success-title">{t('bm_success_title')}</h2>
              <p className="success-sub">
                <span dangerouslySetInnerHTML={{ __html: t('bm_success_sub', { n: doctor.name }) }} />
              </p>

              <div className="conf-card">
                <div className="conf-card-head">
                  <div>
                    <span className="conf-card-label">LustreMDC Clinics &amp; Diagnostics</span>
                    <span className="conf-card-title">{t('bm_apt_details')}</span>
                  </div>
                </div>
                <div className="conf-card-row">
                  <span className="conf-card-key">{t('bm_doctor')}</span>
                  <span className="conf-card-val">Dr. {doctor.name}</span>
                </div>
                <div className="conf-card-row">
                  <span className="conf-card-key">{t('bm_specialty')}</span>
                  <span className="conf-card-val">{doctor.specialty}</span>
                </div>
                <div className="conf-card-row">
                  <span className="conf-card-key">{t('bm_service')}</span>
                  <span className="conf-card-val">{serviceValue}</span>
                </div>
                <div className="conf-card-row">
                  <span className="conf-card-key">{t('bm_date')}</span>
                  <span className="conf-card-val">{formatLongDate(selectedDate)}</span>
                </div>
                <div className="conf-card-row">
                  <span className="conf-card-key">{t('bm_time')}</span>
                  <span className="conf-card-val">{formatTime12h(selectedTime)}</span>
                </div>
                <div className="conf-card-row">
                  <span className="conf-card-key">{t('bm_status')}</span>
                  <span className="conf-card-val conf-card-status">{t('bm_pending')}</span>
                </div>
              </div>

              <div className="conf-card-actions">
                {bookedApptId > 0 ? (
                  <>
                    <a className="btn-confirm" href={`appointment_confirmation.php?id=${bookedApptId}`} data-anim="pulse">
                      {t('bm_view_confirm')}
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a className="btn-confirm btn-confirm--ghost" href={`appointment_confirmation_pdf.php?id=${bookedApptId}`}>
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                      {t('bm_download_proof')}
                    </a>
                  </>
                ) : (
                  <button className="btn-confirm" disabled>
                    <span className="btn-spinner" aria-hidden="true" />
                  </button>
                )}
              </div>
              <p className="success-sub success-sub--small">{t('bm_redirecting')}</p>
            </div>
          </>
        ) : (
          <>
            <div className="modal-handle" aria-hidden="true" />
            <button className="modal-close-btn" onClick={onClose} aria-label="Close">
              ✕
            </button>

            <div className="modal-doctor">
              <div className="avatar avatar--lg">{initials(doctor.name)}</div>
              <div className="modal-doctor-info">
                <div className="modal-title">Dr. {doctor.name}</div>
                <div className="modal-sub">{doctor.specialty}</div>
              </div>
            </div>

            <div className="step-pill">
              <span className="step-pill-dot" />
              {t('bm_step1')}
            </div>

            <div className="picker-block">
              <span className="picker-label">{t('bm_need')}</span>
              <Select
                value={service}
                onChange={setService}
                options={serviceOptions.map((s) => ({ value: s, label: s }))}
                placeholder={t('bm_select_service')}
                icon={
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m14-4v4M5 12h14" />
                  </svg>
                }
              />
              {service === OTHER_SERVICE && (
                <input
                  type="text"
                  className="service-other"
placeholder={t('bm_other_ph')}
                  value={serviceOther}
                  onChange={(e) => setServiceOther(e.target.value)}
                />
              )}
              {procs.length > 0 && (
                <div className="proc-row">
                  <span className="proc-row-label">{t('bm_procs')}</span>
                  <div className="procs-list">
                    {procs.map((p) => (
                      <span className="proc-chip" key={p}>
                        {p}
                      </span>
                    ))}
                  </div>
                </div>
              )}
              <span className="picker-hint">{t('bm_hint', { s: doctor.specialty })}</span>
            </div>

            <div className="step-pill step-pill--muted">
              <span className="step-pill-dot" />
              {t('bm_step2')}
            </div>

            <div className="picker-block">
              <span className="picker-label">{t('bm_dates')}</span>
              {datesLoading ? (
                <div className="dates-grid" onKeyDown={(e) => e.stopPropagation()}>
                  <SkeletonChips count={6} />
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
                  <SkeletonChips count={9} />
                ) : timesError ? (
                  <div className="muted-msg wrap">{t('bm_times_err')}</div>
                ) : times.length === 0 ? (
                  <div className="muted-msg wrap">{t('bm_no_times')}</div>
                ) : (
                  times.map((time) => (
                    <button
                      type="button"
                      key={time}
                      className={`time-btn ${selectedTime === time ? 'active' : ''}`}
                      onClick={() => pickTime(time)}
                    >
                      {formatTime12h(time)}
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
                  {t('bm_confirm')}
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M5 12h14M13 6l6 6-6 6" />
                  </svg>
                </>
              )}
            </button>
          </>
        )}
      </div>

      {bookingError && (
        <div className="modal-alert" onClick={() => setBookingError(null)}>
          <div
            className="modal-alert-card"
            role="alertdialog"
            aria-modal="true"
            onClick={(e) => e.stopPropagation()}
          >
            <span className="modal-alert-icon" aria-hidden="true">
              <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 9v4M12 17h.01" />
                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
              </svg>
            </span>
            <h3 className="modal-alert-title">
              {bookingError.conflict ? t('bm_conflict_title') : t('bm_error_title')}
            </h3>
            <p className="modal-alert-msg">
              {bookingError.conflict
                ? t('bm_conflict_msg', {
                    d: formatLongDate(bookingError.date),
                    time: formatTime12h(bookingError.time),
                  })
                : bookingError.detail}
            </p>
            <button className="btn-confirm" onClick={() => setBookingError(null)}>
              {t('bm_error_ok')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}