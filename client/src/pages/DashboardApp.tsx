import type { ReactNode } from 'react';
import { useState } from 'react';
import type { Appointment, LabTest, Vaccination } from '../types';
import { getInitialData, formatLongDate, formatTime12h, formatStamp, initials } from '../api';
import Shell from '../shell';
import Tour from '../components/Tour';
import {
  SvgActivity,
  SvgCalendar,
  SvgClock,
  SvgCheckCircle,
  SvgXCircle,
  SvgFlag,
  SvgFileText,
  SvgFlask,
  SvgSyringe,
  SvgClipboard,
  SvgArrowRight,
  SvgDownload,
} from '../components/icons';

function greeting(): string {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 18) return 'Good afternoon';
  return 'Good evening';
}

function statusOf(value: string): string {
  return (value || 'unknown').toLowerCase();
}

interface NextApptProps {
  apt: Appointment;
}

function NextAppointmentCard({ apt }: NextApptProps) {
  const s = statusOf(apt.status);
  return (
    <div className="db-card db-appt-card">
      <div className="db-card-head">
        <span className="db-card-label">{"Upcoming Appointment"}</span>
        <span className={`status-pill pill-${s}`}>{apt.status}</span>
      </div>
      <div className="db-appt-main">
        <div className="db-appt-date">
          <span className="db-appt-day">{formatLongDate(apt.appointment_date).split(',')[0]}</span>
        </div>
        <div className="db-appt-detail">
          <div className="db-appt-doctor">Dr. {apt.doctor_name}</div>
          <div className="db-appt-specialty">{apt.specialty}</div>
          <div className="db-appt-meta">
            <span><SvgCalendar size={14} /> {formatLongDate(apt.appointment_date)}</span>
            <span><SvgClock size={14} /> {formatTime12h(apt.appointment_time)}</span>
          </div>
        </div>
      </div>
      <div className="db-card-links">
        <a className="db-card-link" href={`appointment_confirmation.php?id=${apt.id}`}>
          <SvgFileText size={15} /> {"Proof card"}
        </a>
        <span className="db-card-link-sep" />
        <a className="db-card-link" href="my_appointments.php">
          {"View details"} <SvgArrowRight size={15} />
        </a>
      </div>
    </div>
  );
}

function nextAppointment(appointments: Appointment[]): Appointment | null {
  const now = new Date();
  const upcoming = appointments
    .filter((a) => ['pending', 'confirmed'].includes(statusOf(a.status)))
    .filter((a) => new Date(`${a.appointment_date}T23:59:59`) >= now)
    .sort((a, b) => {
      const diff =
        new Date(`${a.appointment_date}T${a.appointment_time}`).getTime() -
        new Date(`${b.appointment_date}T${b.appointment_time}`).getTime();
      return diff;
    });
  return upcoming[0] ?? null;
}

function nextVaccine(vaccinations: Vaccination[]): Vaccination | null {
  const pending = vaccinations.filter((v) => v.next_due_date);
  if (pending.length === 0) return null;
  return pending.sort((a, b) => (a.next_due_date! < b.next_due_date! ? -1 : 1))[0];
}

function dueInfo(dueDate: string) {
  const target = new Date(dueDate + 'T00:00:00');
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const diff = Math.round((target.getTime() - today.getTime()) / 86400000);
  return diff;
}

function StatCard({
  icon,
  label,
  value,
  tone,
  href,
  sub,
}: {
  icon: ReactNode;
  label: string;
  value: string;
  tone: string;
  href: string;
  sub?: string;
}) {
  return (
    <a className={`db-stat db-stat--${tone}`} href={href}>
      <span className="db-stat-icon">{icon}</span>
      <div className="db-stat-body">
        <span className="db-stat-value">{value}</span>
        <span className="db-stat-label">{label}</span>
        {sub && <span className="db-stat-sub">{sub}</span>}
      </div>
    </a>
  );
}

function QuickAction({
  href,
  icon,
  title,
  sub,
  tone,
}: {
  href: string;
  icon: ReactNode;
  title: string;
  sub: string;
  tone: string;
}) {
  return (
    <a className={`db-quick db-quick--${tone}`} href={href}>
      <span className="db-quick-icon">{icon}</span>
      <div className="db-quick-body">
        <span className="db-quick-title">{title}</span>
        <span className="db-quick-sub">{sub}</span>
      </div>
      <span className="db-quick-arrow"><SvgArrowRight size={16} /></span>
    </a>
  );
}

function RecentLab({ tests }: { tests: LabTest[] }) {
  const recent = [...tests].slice(0, 3);
  if (recent.length === 0) {
    return (
      <div className="db-empty">
        <SvgFlask size={28} />
        <p dangerouslySetInnerHTML={{ __html: "No lab tests yet.<br/>Your results will appear here once ordered." }} />
      </div>
    );
  }
  return (
    <div className="db-recent-list">
      {recent.map((lt) => {
        const s = statusOf(lt.status);
        const meta =
          s === 'completed' ? { icon: <SvgCheckCircle size={17} />, cls: 'ok' } :
          s === 'processing' ? { icon: <SvgFlask size={17} />, cls: 'busy' } :
          s === 'cancelled' ? { icon: <SvgXCircle size={17} />, cls: 'no' } :
          { icon: <SvgClock size={17} />, cls: 'wait' };
        const hasResult = !!lt.result;
        return (
          <a key={lt.id} href="lab_request.php" className="db-recent">
            <span className={`db-recent-icon ${meta.cls}`}>{meta.icon}</span>
            <div className="db-recent-body">
              <span className="db-recent-title">{lt.test_type}</span>
              <span className="db-recent-sub">
                Dr. {lt.doctor_name} • {formatStamp(lt.created_at)}
              </span>
            </div>
            <span className={`db-recent-pill ${s}`}>
              {hasResult ? <>{"Result"}</> : (s)}
            </span>
          </a>
        );
      })}
    </div>
  );
}

function VaccineAlert({ vaccinations }: { vaccinations: Vaccination[] }) {
  const next = nextVaccine(vaccinations);
  if (!next || !next.next_due_date) return null;
  const days = dueInfo(next.next_due_date);
  const overdue = days < 0;
  const urgent = overdue || days <= 0;
  const soon = days >= 0 && days <= 14;
  const tone = urgent ? 'danger' : soon ? 'warn' : 'info';
  return (
    <div className={`db-alert db-alert--${tone}`}>
      <span className="db-alert-icon"><SvgSyringe size={22} /></span>
      <div className="db-alert-body">
        <div className="db-alert-title">
          {overdue ? "Vaccine dose overdue" : soon ? "Vaccination due soon" : "Upcoming vaccination"}
        </div>
        <div className="db-alert-text">
          {next.vaccine_name} ({next.dose_label}) {overdue ? 'was due' : 'is scheduled'} {formatLongDate(next.next_due_date)}.
          {urgent ? ' Please schedule it with your doctor as soon as possible.' : ''}
        </div>
      </div>
      <a href="my_appointments.php#health" className="db-alert-action">
        {"View"}
      </a>
    </div>
  );
}

function AppointmentResults({ appointments }: { appointments: Appointment[] }) {
  const results = appointments.filter(
    (a) => statusOf(a.status) === 'completed' && a.result && a.result.trim() !== '',
  );

  if (results.length === 0) {
    return (
      <section className="db-panel db-results">
        <div className="db-panel-head">
          <h2><SvgFileText size={18} /> {"Appointment Results"}</h2>
        </div>
        <div className="db-empty">
          <SvgClipboard size={28} />
          <p dangerouslySetInnerHTML={{ __html: "No appointment results yet.<br/>Your doctor's notes will appear here once your visit is complete." }} />
        </div>
      </section>
    );
  }

  return (
    <section className="db-panel db-results">
      <div className="db-panel-head">
        <h2><SvgFileText size={18} /> {"Appointment Results"}</h2>
        <a href="my_appointments.php" className="db-panel-link">{"View all records"} <SvgArrowRight size={14} /></a>
      </div>
      <div className="db-results-grid">
        {results.map((a) => (
          <div className="db-result-card" key={a.id}>
            <div className="db-result-top">
              <div>
                <div className="db-result-doctor">Dr. {a.doctor_name}</div>
                <div className="db-result-specialty">{a.specialty}</div>
              </div>
              <span className="db-result-date">
                <SvgCalendar size={14} /> {formatLongDate(a.appointment_date)}
              </span>
            </div>
            <hr className="db-result-divider" />
            <div className="db-result-body">{a.result}</div>
            <hr className="db-result-divider" />
            <div className="db-result-footer">
              <span className="db-result-by">
                {`Recorded ${a.result_date ? formatStamp(a.result_date) : ''}`}
              </span>
              <a className="db-result-pdf" href={`appointment_result_pdf.php?id=${a.id}`}>
                <SvgDownload size={15} /> {"Download PDF"}
              </a>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

export default function DashboardApp() {
  const {
    patientName,
    appointments = [],
    labTests = [],
    vitals = [],
    vaccinations = [],
  } = getInitialData();

  // First-login walkthrough: ?tour=1 from the login flow (new accounts only).
  // The param stays in the URL while the tour runs (so a refresh restarts it),
  // and is stripped once the tour is finished or skipped.
  const [showTour, setShowTour] = useState(() => {
    try {
      return (
        new URLSearchParams(window.location.search).get('tour') === '1' &&
        !localStorage.getItem('lustre_tour_done')
      );
    } catch {
      return false;
    }
  });
  const endTour = () => {
    try {
      localStorage.setItem('lustre_tour_done', '1');
    } catch {
      /* storage unavailable */
    }
    setShowTour(false);
    if (window.location.search) {
      history.replaceState(null, '', window.location.pathname);
    }
  };
  const startTour = () => setShowTour(true);

  const firstName = (patientName || 'Patient').split(' ')[0];
  const activeAppts = appointments.filter((a) => ['pending', 'confirmed'].includes(statusOf(a.status)));
  const totalAppts = appointments.length;
  const pendingLabs = labTests.filter((t) => ['pending', 'processing'].includes(statusOf(t.status)));
  const next = nextAppointment(appointments);
  const nextVax = nextVaccine(vaccinations);
  const healthCount = vitals.length + vaccinations.length;

  return (
    <Shell page="home" title={"My Dashboard"} patientName={patientName}>
      <main className="page-content">
        <section className="hero hero--dash" data-tour="hero">
          <div className="hero-blob hero-blob--1" aria-hidden="true" />
          <div className="hero-blob hero-blob--2" aria-hidden="true" />
          <div className="hero-content">
            <div className="hero-avatar-wrap">
              <span className="hero-avatar">{initials(patientName)}</span>
            </div>
            <div>
              <span className="hero-tag">{"Patient Portal"}</span>
              <h1>{greeting()}, {firstName}</h1>
              <p>{"Here's what's happening with your health at LustreMDC today."}</p>
              <button type="button" className="tour-replay" onClick={startTour}>
                <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <path d="M20 11a8 8 0 1 0-2.3 5.7" />
                  <path d="M20 4v7h-7" />
                </svg>
                {"Replay tour"}
              </button>
            </div>
          </div>
        </section>

        <VaccineAlert vaccinations={vaccinations} />

        <div className="db-stats" data-tour="stats">
          <StatCard
            href="my_appointments.php"
            tone="green"
            label={"Total Appointments"}
            value={String(totalAppts)}
            icon={<SvgCalendar size={22} />}
            sub={activeAppts.length ? `${activeAppts.length} upcoming` : "Book one today"}
          />
          <StatCard
            href="lab_request.php"
            tone="blue"
            label={"Pending Lab Tests"}
            value={String(pendingLabs.length)}
            icon={<SvgFlask size={22} />}
            sub={pendingLabs.length ? "In the lab" : "All results in"}
          />
          <StatCard
            href="my_appointments.php"
            tone="rose"
            label={"Vaccines Tracked"}
            value={String(vaccinations.length)}
            icon={<SvgSyringe size={22} />}
            sub={nextVax?.next_due_date ? `Next due ${formatLongDate(nextVax.next_due_date)}` : "Up to date"}
          />
          <StatCard
            href="my_appointments.php"
            tone="amber"
            label={"Health Records"}
            value={String(healthCount)}
            icon={<SvgActivity size={22} />}
            sub={"Vitals & checkups"}
          />
        </div>

        <div className="db-grid">
          <section className="db-panel db-next" data-tour="next">
            <div className="db-panel-head">
              <h2><SvgFlag size={18} /> {"Next Appointment"}</h2>
              <a href="index.php" className="db-panel-link">{"Book new"} <SvgArrowRight size={14} /></a>
            </div>
            {next ? (
              <NextAppointmentCard apt={next} />
            ) : (
              <div className="db-empty">
                <SvgCalendar size={28} />
                <p dangerouslySetInnerHTML={{ __html: "No upcoming appointments.<br/>Book a visit with one of our specialists today." }} />
                <a href="index.php" className="btn-primary">{"Book an Appointment"}</a>
              </div>
            )}
          </section>

          <section className="db-panel db-quick-panel" data-tour="quick">
            <div className="db-panel-head">
              <h2><SvgClipboard size={18} /> {"Quick Actions"}</h2>
            </div>
            <QuickAction
              href="index.php"
              tone="green"
              icon={<SvgCalendar size={22} />}
              title={"Book Appointment"}
              sub={"Pick a specialist and schedule"}
            />
            <QuickAction
              href="my_appointments.php"
              tone="blue"
              icon={<SvgFileText size={22} />}
              title={"My Records"}
              sub={"Appointments, results & vaccines"}
            />
            <QuickAction
              href="lab_request.php"
              tone="rose"
              icon={<SvgFlask size={22} />}
              title={"Lab Tests"}
              sub={"View results & request status"}
            />
            <QuickAction
              href="my_appointments.php#health"
              tone="amber"
              icon={<SvgActivity size={22} />}
              title={"Vitals & Vaccines"}
              sub={"Track health measurements"}
            />
          </section>
        </div>

        <section className="db-panel db-labs">
          <div className="db-panel-head">
            <h2><SvgFlask size={18} /> {"Recent Lab Activity"}</h2>
            <a href="lab_request.php" className="db-panel-link">{"All tests"} <SvgArrowRight size={14} /></a>
          </div>
          <RecentLab tests={labTests} />
        </section>

        <AppointmentResults appointments={appointments} />
      </main>
      <Tour active={showTour} onDone={endTour} />
    </Shell>
  );
}
