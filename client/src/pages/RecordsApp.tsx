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

type Tab = 'appointments' | 'labtests' | 'health';

function dueInfo(dueDate: string) {
  const target = new Date(dueDate + 'T00:00:00');
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const diff = Math.round((target.getTime() - today.getTime()) / 86400000);
  return { kind: diff < 0 ? 'overdue' : diff === 0 ? 'due' : diff <= 14 ? 'soon' : 'later', diff };
}

function VitalCard({ v }: { v: Vital }) {
  return (
    <div className="appt-card">
      <div className="appt-stripe stripe-vital" />
      <div className="appt-body">
        <div className="appt-icon-wrap vital">
          <SvgActivity size={22} />
        </div>
        <div className="appt-main">
          <div className="appt-doctor">{"Prenatal Checkup"}</div>
          <div className="appt-specialty">{"Vitals Log"}</div>
          <div className="appt-meta">
            <span className="meta-chip"><SvgCalendar size={13} /> {formatLongDate(v.visit_date)}</span>
          </div>
          <div className="vital-grid">
            {v.weight_kg != null && (
              <span className="vital-chip"><strong>{v.weight_kg} kg</strong><label>{"Weight"}</label></span>
            )}
            {v.blood_pressure && (
              <span className="vital-chip"><strong>{v.blood_pressure}</strong><label>{"Blood Pressure"}</label></span>
            )}
            {v.heart_rate != null && (
              <span className="vital-chip"><strong>{v.heart_rate} bpm</strong><label>{"Heart Rate"}</label></span>
            )}
            {v.fundal_height != null && (
              <span className="vital-chip"><strong>{v.fundal_height} cm</strong><label>{"Fundal Height"}</label></span>
            )}
          </div>
          {v.notes && <p className="health-note"><span>{"Note"}</span>{v.notes}</p>}
        </div>
        <div className="appt-right">
          <span className="status-pill pill-confirmed">{"Vitals"}</span>
        </div>
      </div>
    </div>
  );
}

function VaccineCard({ v }: { v: Vaccination }) {
  const due = v.next_due_date ? dueInfo(v.next_due_date) : null;
  const dueText = (() => {
    if (!due) return '';
    if (due.kind === 'overdue') return `was overdue (${formatLongDate(v.next_due_date!)})`;
    if (due.kind === 'due') return "is due today";
    if (due.kind === 'soon') return `is due ${formatLongDate(v.next_due_date!)} (in ${due.diff} days)`;
    return `is due ${formatLongDate(v.next_due_date!)}`;
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
          <div className="appt-specialty">{"Vaccination Record"}</div>
          <div className="appt-meta">
            <span className="meta-chip"><SvgCalendar size={13} /> {`Given on ${formatLongDate(v.administered_date)}`}</span>
            <span className="meta-chip"><SvgClock size={13} /> {v.dose_label}</span>
          </div>
          {due && (
            <div className={`vax-due vax-due--${due.kind}`}>
              {due.kind === 'overdue' || due.kind === 'due' || due.kind === 'soon' ? <SvgView size={13} /> : <SvgCalendar size={13} />}
              {`Next dose: ${v.vaccine_name} — ${dueText}`}
            </div>
          )}
          {v.notes && <p className="health-note"><span>{"Note"}</span>{v.notes}</p>}
          <div className="appt-actions">
            <a className="btn-proof-appt" href={`vaccine_card.php?id=${v.id}`}>
              <SvgView size={14} /> {"View Card"}
            </a>
          </div>
        </div>
        <div className="appt-right">
          <span className="status-pill pill-completed">{"Completed"}</span>
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
            <span className="meta-chip"><SvgClipboard size={13} /> {`Booked ${formatStamp(apt.created_at)}`}</span>
          </div>
          {(cancellable || reschedulable) && (
            <div className="appt-actions">
              {reschedulable && (
                <button type="button" className="btn-resched-appt" onClick={() => onResched(apt)}>
                  <SvgCalendar size={14} /> {"Reschedule"}
                </button>
              )}
              {cancellable && (
                <button type="button" className="btn-cancel-appt" onClick={() => onCancel(apt)}>
                  {"Cancel Appointment"}
                </button>
              )}
            </div>
          )}
          {s !== 'cancelled' && (
            <a className="btn-proof-appt" href={`appointment_confirmation.php?id=${apt.id}`}>
              <SvgDownload size={14} /> {"View Proof"}
            </a>
          )}
          {s === 'completed' && apt.result && (
            <a className="btn-result-appt" href={`appointment_result.php?id=${apt.id}`}>
              <SvgFileText size={14} /> {"View Result"}
            </a>
          )}
        </div>
        <div className="appt-right">
          <span className={`status-pill pill-${s}`}>{meta.label}</span>
          {apt.payment_status && (
            <span className={`payment-pill payment-${statusOf(apt.payment_status)}`}>
              {apt.payment_status === 'paid' ? <><SvgCreditCard size={13} /> {"Paid"}</> : <>{"Unpaid"}</>}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

function LabCard({ test, index }: { test: LabTest; index: number }) {
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
          {hasResult && <span className="result-tag"><SvgFileText size={13} /> {"Result"}</span>}
          <span className="lab-chevron">▾</span>
        </div>
      </button>

      <div className="lab-result-panel">
        <div className="result-grid">
          <div className="result-item">
            <label>{"Requesting Doctor"}</label>
            <span>Dr. {test.doctor_name}</span>
          </div>
          <div className="result-item">
            <label>{"Specialty"}</label>
            <span>{test.specialty}</span>
          </div>
          <div className="result-item">
            <label>{"Requested On"}</label>
            <span>{formatLongDate(test.created_at.slice(0, 10))}</span>
          </div>
          <div className="result-item">
            <label>{"Scheduled Date"}</label>
            <span>{test.scheduled_date ? formatLongDate(test.scheduled_date) : '—'}</span>
          </div>
          <div className="result-item">
            <label>{"Priority"}</label>
            <span>{test.priority ? test.priority.charAt(0).toUpperCase() + test.priority.slice(1) : '—'}</span>
          </div>
          <div className="result-item">
            <label>{"Status"}</label>
            <span>{meta.label}</span>
          </div>
        </div>

        <div className="lab-actions">
          {s !== 'cancelled' && (
            <a className="btn-proof-appt" href={`lab_confirmation.php?id=${test.id}`} onClick={(e) => { e.preventDefault(); e.stopPropagation(); window.open(`lab_confirmation.php?id=${test.id}`, '_blank', 'noopener'); }}>
              <SvgView size={14} /> {"View Proof"}
            </a>
          )}
          {s === 'completed' && hasResult && (
            <a className="btn-result-appt" href={`lab_result.php?id=${test.id}`} onClick={(e) => { e.preventDefault(); e.stopPropagation(); window.open(`lab_result.php?id=${test.id}`, '_blank', 'noopener'); }}>
              <SvgFileText size={14} /> {"View Result"}
            </a>
          )}
        </div>
        {hasResult ? (
          <>
            <div className="completed-banner">
              <SvgCheckCircle size={20} />
              {"Your lab result is ready. Please also consult your doctor for interpretation."}
            </div>
            <div className="result-box">
              <div className="result-box-label"><SvgClipboard size={15} /> {"Lab Result / Findings"}</div>
              <div className="result-content">{test.result}</div>
            </div>
            {hasNotes && (
              <div className="result-box">
                <div className="result-box-label"><SvgStickyNote size={15} /> {"Clinical Notes"}</div>
                <div className="result-content">{test.notes}</div>
              </div>
            )}
          </>
        ) : (
          <div className={`no-result-banner no-result--${s}`}>
            {s === 'processing' ? <SvgFlask size={20} /> : s === 'cancelled' ? <SvgXCircle size={20} /> : <SvgClock size={20} />}
            {s === 'processing' && "Your sample is currently being processed. Please check back soon."}
            {s === 'cancelled' && "This lab test was cancelled. Please contact the clinic for more information."}
            {s !== 'processing' && s !== 'cancelled' && "Your test is pending. Results will be available once processed by the lab."}
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
  return (
    <div className="modal" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal-sheet modal-sheet--sm" role="dialog" aria-modal="true">
        <button className="modal-close-btn" onClick={onClose} aria-label="Close">
          ✕
        </button>
        <div className="modal-handle" aria-hidden="true" />
        <div className="modal-center-title">
          <span className="modal-icon-big"><SvgCalendar size={34} /></span>
          <h3>{"Cancel Appointment?"}</h3>
          <p>{"This action cannot be undone. Are you sure you want to cancel?"}</p>
        </div>
        <div className="cancel-appt-info">
          <div className="row">
            <span><SvgUser size={16} /></span>
            <span className="lbl">{"Doctor"}</span>
            <strong>Dr. {apt.doctor_name}</strong>
          </div>
          <div className="row">
            <span><SvgCalendar size={16} /></span>
            <span className="lbl">{"Date"}</span>
            <strong>{formatLongDate(apt.appointment_date)}</strong>
          </div>
          <div className="row">
            <span><SvgClock size={16} /></span>
            <span className="lbl">{"Time"}</span>
            <strong>{formatTime12h(apt.appointment_time)}</strong>
          </div>
        </div>
        <div className="modal-actions">
          <button className="btn-keep" onClick={onClose}>
            {"Keep Appointment"}
          </button>
          <button className="btn-cancel-confirm" onClick={onConfirm}>
            {"Yes, Cancel"}
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
  const [dates, setDates] = useState<MedicalDate[]>([]);
  const [datesLoading, setDatesLoading] = useState(true);
  const [datesError, setDatesError] = useState(false);
  const [selectedDate, setSelectedDate] = useState('');
  const [times, setTimes] = useState<string[]>([]);
  const [timesLoading, setTimesLoading] = useState(false);
  const [timesError, setTimesError] = useState(false);
  const [selectedTime, setSelectedTime] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [alertMsg, setAlertMsg] = useState<string | null>(null);

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
      const raw = e instanceof Error ? e.message : '';
      const usable =
        raw.length > 0 && raw.length <= 140 && !raw.includes('<') && raw.trim().charAt(0) !== '{';
      setAlertMsg(usable ? raw : "Rescheduling failed. Please try again.");
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
          {"Reschedule · Pick a new date & time"}
        </div>

        <div className="picker-block">
          <span className="picker-label">{"Available Dates"}</span>
          {datesLoading ? (
            <div className="dates-grid" onKeyDown={(e) => e.stopPropagation()}>
              <ReschedSkeleton count={6} />
            </div>
          ) : datesError ? (
            <div className="muted-msg wrap">{"Couldn't load dates. Please close and try again."}</div>
          ) : dates.length === 0 ? (
            <div className="muted-msg wrap">{"No available dates at this time."}</div>
          ) : (
            <CalendarPicker dates={dates} selectedDate={selectedDate} onPick={pickDate} />
          )}
        </div>

        <div className="picker-block">
          <span className="picker-label">{"Available Times"}</span>
          <div className="times-grid">
            {!selectedDate ? (
              <div className="muted-msg wrap">{"Select a date first"}</div>
            ) : timesLoading ? (
              <ReschedSkeleton count={9} />
            ) : timesError ? (
              <div className="muted-msg wrap">{"Couldn't load times. Try another date."}</div>
            ) : times.length === 0 ? (
              <div className="muted-msg wrap">{"No available times for this date."}</div>
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
          {"Appointment duration: 2 hours"}
        </div>

        <button className="btn-confirm" disabled={!canConfirm} onClick={submit} data-anim={canConfirm ? 'pulse' : undefined}>
          {submitting ? (
            <span className="btn-spinner" aria-hidden="true" />
          ) : (
            <>
              {"Confirm Reschedule"}
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M5 12h14M13 6l6 6-6 6" />
              </svg>
            </>
          )}
        </button>

        {alertMsg && (
          <div className="modal-alert" onClick={() => setAlertMsg(null)}>
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
              <h3 className="modal-alert-title">{"Reschedule Didn't Go Through"}</h3>
              <p className="modal-alert-msg">{alertMsg}</p>
              <button className="btn-confirm" onClick={() => setAlertMsg(null)}>
                {"OK, got it"}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default function RecordsApp() {
  const { patientName, appointments = [], labTests = [], vitals = [], vaccinations = [] } = getInitialData();
  const { showToast } = useToast();
  const [tab, setTab] = useState<Tab>(() => {
    const h = window.location.hash.replace('#', '');
    if (h === 'labtests') return 'labtests';
    if (h === 'health') return 'health';
    return 'appointments';
  });
  const [cancelTarget, setCancelTarget] = useState<Appointment | null>(null);
  const [cancelling, setCancelling] = useState(false);
  const [reschedTarget, setReschedTarget] = useState<Appointment | null>(null);
  const [doneMsg, setDoneMsg] = useState<string | null>(null);

  useScrollLock(!!cancelTarget || !!reschedTarget || !!doneMsg);

  const confirmRescheduled = () => {
    setReschedTarget(null);
    setDoneMsg("Appointment rescheduled.");
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
        setDoneMsg("Appointment cancelled.");
      } else {
        showToast(text, 'error');
        setCancelTarget(null);
      }
    } catch (err) {
      showToast("Network error. Please try again.", 'error');
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
          {ctaHref === 'index.php' ? "Book an Appointment" : "Request a Lab Test"}
        </a>
      )}
    </div>
  );

  const healthCount = vitals.length + vaccinations.length;

  return (
    <Shell page="records" title={"My Health Records"} patientName={patientName}>
      <main className="page-content">
        <Hero
          tag={"Health Records"}
          title={"My Appointments & Lab Tests"}
          sub={"View your appointments, health measurements and lab test results in one place."}
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
            {"Appointments"}
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
            {"Lab Tests"}
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
            {"Vitals & Vaccines"}
            <span className="tab-count">{healthCount}</span>
          </button>
        </div>

        {tab === 'appointments' ? (
          appointments.length === 0 ? (
            empty(<SvgCalendar size={40} />, "No Appointments Yet", "You haven't booked any appointments.", 'index.php')
          ) : (
            <div className="appt-list">
              {appointments.map((apt) => (
                <AppointmentCard key={apt.id} apt={apt} onCancel={setCancelTarget} onResched={setReschedTarget} />
              ))}
            </div>
          )
        ) : tab === 'labtests' ? (
          labTests.length === 0 ? (
            empty(<SvgFlask size={40} />, "No Lab Tests Yet", "Your lab test requests and results will appear here once ordered by your doctor.", 'lab_request.php')
          ) : (
            <div className="lab-list">
              {labTests.map((lt, i) => (
                <LabCard key={lt.id} test={lt} index={i} />
              ))}
            </div>
          )
        ) : healthCount === 0 ? (
          empty(<SvgActivity size={40} />, "No Health Records Yet", "Your vitals from prenatal checkups and vaccination schedule will appear here.")
        ) : (
          <div className="health-list">
            {vaccinations.length > 0 && (
              <>
                <h2 className="health-section-title">{"Vaccinations"}</h2>
                {vaccinations.map((vc) => (
                  <VaccineCard key={vc.id} v={vc} />
                ))}
              </>
            )}
            {vitals.length > 0 && (
              <>
                <h2 className="health-section-title">{"Vitals Log"}</h2>
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
          <p>{"Processing..."}</p>
        </div>
      )}

      {doneMsg && (
        <div className="modal-alert" onClick={() => window.location.reload()}>
          <div
            className="modal-alert-card"
            role="alertdialog"
            aria-modal="true"
            onClick={(e) => e.stopPropagation()}
          >
            <span className="modal-alert-icon modal-alert-icon--ok" aria-hidden="true">
              <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20 6 9 17l-5-5" />
              </svg>
            </span>
            <h3 className="modal-alert-title">{doneMsg}</h3>
            <p className="modal-alert-msg">{"Your appointment has been updated."}</p>
            <button className="btn-confirm" onClick={() => window.location.reload()}>
              {"OK, got it"}
            </button>
          </div>
        </div>
      )}
    </Shell>
  );
}
