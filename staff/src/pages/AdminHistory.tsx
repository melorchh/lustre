import { Fragment, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate, fmtTime } from '../api';
import { useToast } from '../hooks';
import { Modal } from '../components';
import { Card, EmptyState, Field, FilterBar, ModalHead, Stack, StatCard, StatGrid } from '../ui';
import {
  IconAppointments, IconCheck, IconLab, IconPrint, IconSearch, IconVaccine, IconVitals,
} from '../icons';
import type { AppointmentRow, LabTestRow, VaccinationRow, VitalRow } from '../types';

type TabKey = 'appointments' | 'labtests' | 'vitals' | 'vaccinations';

interface HistoryAppt extends AppointmentRow {
  contact?: string;
}

interface HistoryVacc extends VaccinationRow {
  patient_email?: string;
}

interface ReschedEntry {
  appointment_id: number;
  old_date: string;
  old_time: string;
  new_date: string;
  new_time: string;
  rescheduled_at: string;
}

interface HistoryBundle {
  stats?: Record<string, number>;
  appointments?: HistoryAppt[];
  labTests?: LabTestRow[];
  vitals?: VitalRow[];
  vaccinations?: HistoryVacc[];
  rescheduleHistory?: Record<string, ReschedEntry[]>;
  archiveDays?: Partial<Record<TabKey, Record<string, number>>>;
}

interface Filters {
  aptName: string;
  aptAddress: string;
  aptAge: string;
  aptStatus: string;
  aptPay: string;
  labPatient: string;
  labDoctor: string;
  labTest: string;
  labStatus: string;
  labPriority: string;
  vitalSearch: string;
  vaccPatient: string;
  vaccVaccine: string;
  vaccDose: string;
}

const TABS: { key: TabKey; label: string }[] = [
  { key: 'appointments', label: 'Appointments' },
  { key: 'labtests', label: 'Lab Tests' },
  { key: 'vitals', label: 'Vitals' },
  { key: 'vaccinations', label: 'Vaccinations' },
];

const EMPTY_FILTERS: Filters = {
  aptName: '',
  aptAddress: '',
  aptAge: '',
  aptStatus: '',
  aptPay: '',
  labPatient: '',
  labDoctor: '',
  labTest: '',
  labStatus: '',
  labPriority: '',
  vitalSearch: '',
  vaccPatient: '',
  vaccVaccine: '',
  vaccDose: '',
};

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

function pad2(n: number): string {
  return (n < 10 ? '0' : '') + n;
}

function dayKey(d: Date): string {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function monthIndex(day: string): number {
  const [y, m] = day.split('-').map((x) => parseInt(x, 10));
  if (!y || !m) return new Date().getFullYear() * 12 + new Date().getMonth();
  return y * 12 + (m - 1);
}

function nowMonthIndex(): number {
  const d = new Date();
  return d.getFullYear() * 12 + d.getMonth();
}

function fmtDayLong(day: string): string {
  if (!day) return 'Unknown date';
  const dt = new Date(`${day}T00:00:00`);
  if (isNaN(dt.getTime())) return day;
  return dt.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function fmtDayShort(day: string): string {
  if (!day) return '\u2014';
  const dt = new Date(`${day}T00:00:00`);
  if (isNaN(dt.getTime())) return day;
  return dt.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
}

function timeFromTs(ts?: string | null): string {
  if (!ts) return '\u2014';
  const m = String(ts).match(/(?:T|\s)(\d{2}):(\d{2})/);
  return m ? fmtTime(`${m[1]}:${m[2]}`) : '\u2014';
}

function cap(v?: string | null): string {
  const s = String(v || '');
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '\u2014';
}

function countLabel(n: number): string {
  return `${n} record${n === 1 ? '' : 's'}`;
}

function latestDay(map: Record<string, number> | undefined): string {
  const keys = Object.keys(map || {}).sort();
  return keys.length ? keys[keys.length - 1] : '';
}

function groupRows<T>(rows: T[], getDay: (r: T) => string): [string, T[]][] {
  const map: Record<string, T[]> = {};
  for (const r of rows) {
    const key = getDay(r);
    if (!map[key]) map[key] = [];
    map[key].push(r);
  }
  return Object.keys(map)
    .sort((a, b) => b.localeCompare(a))
    .map((key) => [key, map[key]] as [string, T[]]);
}

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function FolderIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
    </svg>
  );
}

function ChevronIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="m6 9 6 6 6-6" />
    </svg>
  );
}

function DayHeader({
  colSpan,
  day,
  count,
  collapsed,
  onToggle,
}: {
  colSpan: number;
  day: string;
  count: number;
  collapsed: boolean;
  onToggle: () => void;
}) {
  return (
    <tr className={`archive-day-row${collapsed ? ' collapsed' : ''}`} onClick={onToggle}>
      <td colSpan={colSpan} className="archive-day-cell">
        <div className="archive-day-inner">
          <span className="archive-day-ic"><FolderIcon /></span>
          <span className="archive-day-label">{fmtDayLong(day)}</span>
          <span className="archive-day-count">{countLabel(count)}</span>
          <span className="archive-day-arrow"><ChevronIcon /></span>
        </div>
      </td>
    </tr>
  );
}

export default function AdminHistory() {
  const d = getInitial().data as HistoryBundle;
  const { showToast } = useToast();

  const appointments = d.appointments || [];
  const labTests = d.labTests || [];
  const vitals = d.vitals || [];
  const vaccinations = d.vaccinations || [];
  const rescheduleMap = d.rescheduleHistory || {};
  const archive = d.archiveDays || {};
  const stats = d.stats || {};

  const [tab, setTab] = useState<TabKey>('appointments');
  const [filters, setFilters] = useState<Filters>({ ...EMPTY_FILTERS });
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

  const [slipFor, setSlipFor] = useState<HistoryAppt | null>(null);
  const [resultFor, setResultFor] = useState<LabTestRow | null>(null);
  const [reschedFor, setReschedFor] = useState<HistoryAppt | null>(null);

  const initialDay = latestDay(archive.appointments);
  const [pdfType, setPdfType] = useState<TabKey>('appointments');
  const [pdfMonth, setPdfMonth] = useState<number>(initialDay ? monthIndex(initialDay) : nowMonthIndex());
  const [pdfDay, setPdfDay] = useState<string>(initialDay);
  const [pdfSearch, setPdfSearch] = useState('');

  const pdfDaysMap = archive[pdfType] || {};
  const pdfDays = Object.keys(pdfDaysMap).sort().reverse();
  const pdfFilteredDays = pdfDays.filter((day) =>
    !pdfSearch || fmtDayShort(day).toLowerCase().includes(pdfSearch.toLowerCase()),
  );
  const pdfSelectedCount = pdfDay ? pdfDaysMap[pdfDay] : undefined;
  const canDownload = !!pdfDay && pdfSelectedCount !== undefined && pdfSelectedCount > 0;

  const yearRange = (() => {
    const years: number[] = [];
    for (const key of TABS.map((t) => t.key)) {
      for (const day of Object.keys(archive[key] || {})) {
        const y = parseInt(day.slice(0, 4), 10);
        if (y && !years.includes(y)) years.push(y);
      }
    }
    const now = new Date().getFullYear();
    years.push(now);
    return { min: Math.min(...years), max: Math.max(...years) };
  })();

  const calYear = Math.floor(pdfMonth / 12);
  const calMonth = ((pdfMonth % 12) + 12) % 12;
  const firstDow = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  const todayKey = dayKey(new Date());

  function toggleDay(key: string) {
    setCollapsed((prev) => ({ ...prev, [key]: !prev[key] }));
  }

  function dayCollapsed(key: string): boolean {
    return !!collapsed[key];
  }

  function selectTab(name: TabKey) {
    setTab(name);
    setPdfType(name);
    const days = archive[name] || {};
    const next = pdfDay && days[pdfDay] !== undefined ? pdfDay : latestDay(days);
    if (next) {
      setPdfDay(next);
      setPdfMonth(monthIndex(next));
    } else {
      setPdfDay('');
    }
  }

  function pickPdfDay(day: string) {
    setPdfDay(day);
    setPdfMonth(monthIndex(day));
  }

  function goMonth(delta: number) {
    let next = pdfMonth + delta;
    if (next > nowMonthIndex()) next = nowMonthIndex();
    if (next < 0) next = 0;
    setPdfMonth(next);
  }

  function downloadPdf() {
    if (!canDownload) return;
    showToast('PDF downloaded', 'success');
    window.location.href = `archive_day_pdf.php?tab=${pdfType}&day=${pdfDay}`;
  }

  function setFilter<K extends keyof Filters>(key: K, value: string) {
    setFilters((prev) => ({ ...prev, [key]: value }));
  }

  function resetAptFilters() {
    setFilters((prev) => ({ ...prev, aptName: '', aptAddress: '', aptAge: '', aptStatus: '', aptPay: '' }));
  }

  function resetLabFilters() {
    setFilters((prev) => ({ ...prev, labPatient: '', labDoctor: '', labTest: '', labStatus: '', labPriority: '' }));
  }

  function resetVitalFilters() {
    setFilters((prev) => ({ ...prev, vitalSearch: '' }));
  }

  function resetVaccFilters() {
    setFilters((prev) => ({ ...prev, vaccPatient: '', vaccVaccine: '', vaccDose: '' }));
  }

  function slipRows(a: HistoryAppt): [string, string][] {
    return [
      ['Patient', a.patient_name || '\u2014'],
      ['Doctor', `Dr. ${a.doctor_name || '\u2014'}`],
      ['Specialty', a.specialty || '\u2014'],
      ['Date', fmtDate(a.appointment_date)],
      ['Time', fmtTime(a.appointment_time)],
      ['Status', cap(a.status)],
      ['Payment', a.payment_status === 'paid' ? 'Paid' : 'Unpaid'],
    ];
  }

  function printSlip() {
    if (!slipFor) return;
    const rows = slipRows(slipFor)
      .map(([l, v]) => `<div class="row"><span>${esc(l)}:</span><strong>${esc(v)}</strong></div>`)
      .join('');
    const w = window.open('', '_blank', 'width=460,height=640');
    if (!w) {
      showToast('Popup blocked \u2014 allow popups to print', 'error');
      return;
    }
    w.document.write(
      '<!DOCTYPE html><html><head><title>Appointment Slip</title><style>' +
        'body{font-family:Arial,Helvetica,sans-serif;color:#111;padding:24px;margin:0}' +
        '.wrap{border:2px solid #16a34a;border-radius:14px;padding:22px;max-width:360px;margin:0 auto}' +
        '.head{text-align:center;border-bottom:2px solid #e2e8f0;padding-bottom:14px;margin-bottom:16px}' +
        '.head .name{font-size:20px;font-weight:800;color:#16a34a;letter-spacing:.5px}' +
        '.head .sub{font-size:12px;color:#475569;margin-top:2px}' +
        '.row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px}' +
        '.row span{color:#64748b}.row strong{color:#0f172a;text-align:right}' +
        '.foot{text-align:center;font-size:12px;color:#64748b;margin-top:16px}' +
        '</style></head><body><div class="wrap">' +
        '<div class="head"><div class="name">LustreMDC</div><div class="sub">CLINIC APPOINTMENT SLIP</div></div>' +
        rows +
        '<div class="foot">Please arrive 10-15 minutes early. Show this slip at the counter.</div>' +
        '</div><script>window.onload=function(){window.print();};<\/script></body></html>',
    );
    w.document.close();
    showToast('Slip ready to print', 'success');
  }

  function reschedEntries(a: HistoryAppt): ReschedEntry[] {
    return rescheduleMap[a.id] || [];
  }

  // ---- filtered + grouped appointment rows ----
  const filteredAppts = appointments.filter((a) => {
    if (filters.aptName && !String(a.patient_name || '').toLowerCase().includes(filters.aptName.toLowerCase().trim())) return false;
    if (filters.aptAddress && !String(a.patient_address || '').toLowerCase().includes(filters.aptAddress.toLowerCase().trim())) return false;
    if (filters.aptAge && !String(a.patient_age ?? '').includes(filters.aptAge.trim())) return false;
    if (filters.aptStatus && a.status !== filters.aptStatus) return false;
    if (filters.aptPay && a.payment_status !== filters.aptPay) return false;
    return true;
  });
  const apptGroups = groupRows(filteredAppts, (a) => String(a.updated_at || a.appointment_date || '').slice(0, 10));

  // ---- filtered + grouped lab rows ----
  const filteredLabs = labTests.filter((l) => {
    if (filters.labPatient && !String(l.patient_name || '').toLowerCase().includes(filters.labPatient.toLowerCase().trim())) return false;
    if (filters.labDoctor && !String(l.doctor_name || '').toLowerCase().includes(filters.labDoctor.toLowerCase().trim())) return false;
    if (filters.labTest && !String(l.test_type || '').toLowerCase().includes(filters.labTest.toLowerCase().trim())) return false;
    if (filters.labStatus && l.status !== filters.labStatus) return false;
    if (filters.labPriority && l.priority !== filters.labPriority) return false;
    return true;
  });
  const labGroups = groupRows(filteredLabs, (l) => String(l.updated_at || l.scheduled_date || l.created_at || '').slice(0, 10));

  // ---- filtered + grouped vitals ----
  const filteredVitals = vitals.filter((v) =>
    !filters.vitalSearch || String(v.patient_name || '').toLowerCase().includes(filters.vitalSearch.toLowerCase().trim()),
  );
  const vitalGroups = groupRows(filteredVitals, (v) => String(v.created_at || v.visit_date || '').slice(0, 10));

  // ---- filtered + grouped vaccinations ----
  const filteredVaccs = vaccinations.filter((v) => {
    if (filters.vaccPatient && !String(v.patient_name || '').toLowerCase().includes(filters.vaccPatient.toLowerCase().trim())) return false;
    if (filters.vaccVaccine && !String(v.vaccine_name || '').toLowerCase().includes(filters.vaccVaccine.toLowerCase().trim())) return false;
    if (filters.vaccDose && !String(v.dose_label || '').toLowerCase().includes(filters.vaccDose.toLowerCase().trim())) return false;
    return true;
  });
  const vaccGroups = groupRows(filteredVaccs, (v) => String(v.created_at || v.administered_date || '').slice(0, 10));

  const tabCounts: Record<TabKey, number> = {
    appointments: appointments.length,
    labtests: labTests.length,
    vitals: vitals.length,
    vaccinations: vaccinations.length,
  };

  return (
    <Shell active="history" title="Archives">
      <Stack>
      <div className="ap-hero">
        <div className="ap-hero-copy">
          <span className="ap-hero-eyebrow">Record archive</span>
          <h1>History &amp; archives</h1>
          <p>Complete archive of completed and cancelled appointments, lab tests, vitals and vaccinations.</p>
        </div>
      </div>

      {/* ---- day archive PDF downloader ---- */}
      <Card
        title="Download day archive PDF"
        subtitle="Pick a record type, then a day with archived records."
        actions={
          <div className="pdf-type-switch">
            {TABS.map((t) => (
              <button
                key={t.key}
                type="button"
                className={`pdf-type-btn${pdfType === t.key ? ' active' : ''}`}
                onClick={() => selectTab(t.key)}
              >
                {t.label}
              </button>
            ))}
          </div>
        }
      >
        <div className="pdf-panel-body">
          <div className="pdf-cal-col">
            <div className="pdf-cal-head">
              <div className="pdf-cal-sels">
                <button type="button" className="pdf-cal-arrow" onClick={() => goMonth(-1)} disabled={pdfMonth <= 0} aria-label="Previous month">&lsaquo;</button>
                <select
                  className="action-select"
                  aria-label="Month"
                  value={calMonth}
                  onChange={(e) => {
                    const next = calYear * 12 + parseInt(e.target.value, 10);
                    setPdfMonth(Math.min(next, nowMonthIndex()));
                  }}
                >
                  {MONTH_NAMES.map((m, i) => <option key={m} value={i}>{m}</option>)}
                </select>
                <select
                  className="action-select"
                  aria-label="Year"
                  value={calYear}
                  onChange={(e) => {
                    const next = parseInt(e.target.value, 10) * 12 + calMonth;
                    setPdfMonth(Math.min(next, nowMonthIndex()));
                  }}
                >
                  {Array.from({ length: yearRange.max - yearRange.min + 1 }, (_, i) => yearRange.max - i).map((y) => (
                    <option key={y} value={y}>{y}</option>
                  ))}
                </select>
                <button type="button" className="pdf-cal-arrow" onClick={() => goMonth(1)} disabled={pdfMonth >= nowMonthIndex()} aria-label="Next month">&rsaquo;</button>
              </div>
            </div>
            <div className="pdf-cal-week">
              {['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map((d) => <span key={d}>{d}</span>)}
            </div>
            <div className="pdf-cal-grid">
              {Array.from({ length: firstDow }, (_, i) => <span key={`b${i}`} className="pdf-cal-cell blank" />)}
              {Array.from({ length: daysInMonth }, (_, i) => {
                const dayNum = i + 1;
                const key = `${calYear}-${pad2(calMonth + 1)}-${pad2(dayNum)}`;
                const classes = ['pdf-cal-cell'];
                if (pdfDaysMap[key]) classes.push('has');
                if (key === pdfDay) classes.push('sel');
                if (key === todayKey) classes.push('today');
                return (
                  <button key={key} type="button" className={classes.join(' ')} onClick={() => pickPdfDay(key)}>
                    {dayNum}
                  </button>
                );
              })}
            </div>
            <div className="pdf-cal-legend"><span className="pdf-legend-dot" />Archived record days</div>
          </div>

          <div className="pdf-list-col">
            <div className="pdf-list-search">
              <input
                type="search"
                placeholder="Search archive days&hellip;"
                aria-label="Search archive days"
                value={pdfSearch}
                onChange={(e) => setPdfSearch(e.target.value)}
              />
            </div>
            <div className="pdf-day-list">
              {pdfDays.length === 0 ? (
                <div className="pdf-list-empty">No archive days for this record type yet.</div>
              ) : pdfFilteredDays.length === 0 ? (
                <div className="pdf-list-empty">No archive days match your search.</div>
              ) : pdfFilteredDays.map((day) => (
                <button
                  key={day}
                  type="button"
                  className={`pdf-day-item${day === pdfDay ? ' sel' : ''}`}
                  onClick={() => pickPdfDay(day)}
                >
                  <span className="pdf-day-name">{fmtDayShort(day)}</span>
                  <span className="pdf-day-count">{countLabel(pdfDaysMap[day])}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="pdf-actions-col">
            <div className="pdf-selected">
              <span className="pdf-selected-label">Selected Day</span>
              <span className="pdf-selected-day">{pdfDay ? fmtDayShort(pdfDay) : '\u2014'}</span>
              <span className="pdf-selected-meta">
                {!pdfDay
                  ? 'Pick an archive day.'
                  : pdfSelectedCount !== undefined && pdfSelectedCount > 0
                    ? `${countLabel(pdfSelectedCount)} archived on this day.`
                    : 'No records on this day for the selected type.'}
              </span>
            </div>
            <button type="button" className="pdf-dl-btn" onClick={downloadPdf} disabled={!canDownload}>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              Download PDF
            </button>
          </div>
        </div>
      </Card>

      {/* ---- stats ---- */}
      <StatGrid>
        <StatCard icon={<IconAppointments />} label="All appointments" value={stats.allAppointments ?? appointments.length} hint="Archived visits" />
        <StatCard icon={<IconCheck />} label="Completed" value={stats.completed ?? 0} hint="Served visits" tone="slate" />
        <StatCard icon={<IconAppointments />} label="Cancelled" value={stats.cancelled ?? 0} hint="Did not push through" tone="rose" />
        <StatCard icon={<IconCheck />} label="Payments collected" value={stats.paid ?? 0} hint="Paid visits" tone="amber" />
        <StatCard icon={<IconLab />} label="Lab tests done" value={stats.labDone ?? 0} hint="Results released" tone="blue" />
        <StatCard icon={<IconVitals />} label="Vitals recorded" value={stats.vitals ?? 0} hint="Patient check-ins" tone="violet" />
        <StatCard icon={<IconVaccine />} label="Vaccinations" value={stats.vaccinations ?? 0} hint="Doses administered" tone="teal" />
      </StatGrid>

      {/* ---- tabs ---- */}
      <div className="tab-nav">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            className={`tab-btn${tab === t.key ? ' active' : ''}`}
            onClick={() => selectTab(t.key)}
          >
            {t.label}
            <span className="tab-count">{tabCounts[t.key]}</span>
          </button>
        ))}
      </div>

      {tab === 'appointments' && (
        <Card
          title="Archived appointments"
          subtitle={`${countLabel(filteredAppts.length)} shown`}
          actions={
            (filters.aptName || filters.aptAddress || filters.aptAge || filters.aptStatus || filters.aptPay)
              ? <button type="button" className="ap-btn-text" onClick={resetAptFilters}>Reset filters</button>
              : undefined
          }
        >
          <FilterBar>
            <Field label="Patient" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Search patient name..." value={filters.aptName} onChange={(e) => setFilter('aptName', e.target.value)} />
              </div>
            </Field>
            <Field label="Address" className="ap-field--grow">
              <input type="search" className="apt-search" placeholder="Search address..." value={filters.aptAddress} onChange={(e) => setFilter('aptAddress', e.target.value)} />
            </Field>
            <Field label="Age" className="ap-field--xs">
              <input type="search" className="apt-search" placeholder="Any" value={filters.aptAge} onChange={(e) => setFilter('aptAge', e.target.value)} />
            </Field>
            <Field label="Status">
              <select className="apt-select" value={filters.aptStatus} onChange={(e) => setFilter('aptStatus', e.target.value)}>
                <option value="">All statuses</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </Field>
            <Field label="Payment">
              <select className="apt-select" value={filters.aptPay} onChange={(e) => setFilter('aptPay', e.target.value)}>
                <option value="">All payments</option>
                <option value="paid">Paid</option>
                <option value="pending">Unpaid</option>
              </select>
            </Field>
          </FilterBar>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Doctor</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Status</th>
                  <th>Payment</th>
                  <th>Archived On</th>
                </tr>
              </thead>
              <tbody>
                {filteredAppts.length === 0 ? (
                  <tr>
                    <td colSpan={7}>
                      <EmptyState
                        icon={<IconAppointments size={20} />}
                        title="No archived appointments"
                        hint="Completed and cancelled visits will appear here."
                      />
                    </td>
                  </tr>
                ) : apptGroups.map(([day, rows]) => (
                  <Fragment key={day}>
                    <DayHeader
                      colSpan={7}
                      day={day}
                      count={rows.length}
                      collapsed={dayCollapsed(`appointments:${day}`)}
                      onToggle={() => toggleDay(`appointments:${day}`)}
                    />
                    {!dayCollapsed(`appointments:${day}`) && rows.map((a) => {
                      const entries = reschedEntries(a);
                      const hasResched = Number(a.reschedule_count || 0) > 0 || entries.length > 0;
                      return (
                        <tr key={a.id}>
                          <td>
                            <span className="ap-strong">{a.patient_name}</span>
                            <small className="ap-sub">{a.patient_email || 'No email on file'}</small>
                            {a.contact ? <small className="ap-sub">{a.contact}</small> : null}
                          </td>
                          <td>
                            <span>Dr. {a.doctor_name}</span>
                            <small className="ap-sub">{a.specialty}</small>
                          </td>
                          <td>{fmtDate(a.appointment_date)}</td>
                          <td>{fmtTime(a.appointment_time)}</td>
                          <td>
                            <span className={`badge badge-${a.status}`}>{cap(a.status)}</span>
                            {hasResched && (
                              <div>
                                <button
                                  type="button"
                                  className="resched-badge"
                                  title="View reschedule history"
                                  onClick={() => setReschedFor(a)}
                                >
                                  &#10227; Rescheduled{Number(a.reschedule_count || 0) > 1 ? ` x${a.reschedule_count}` : ''}
                                </button>
                              </div>
                            )}
                          </td>
                          <td>
                            <span className={`badge badge-${a.payment_status === 'paid' ? 'paid' : 'unpaid'}`}>
                              {a.payment_status === 'paid' ? 'Paid' : 'Unpaid'}
                            </span>
                          </td>
                          <td>
                            <span className="ap-updated">
                              {fmtDate(a.updated_at)}
                              <small>{timeFromTs(a.updated_at)}</small>
                            </span>
                            <div className="ap-updated-actions">
                              <button type="button" className="btn-slip" title="Print appointment slip" onClick={() => setSlipFor(a)}>
                                <IconPrint size={15} /> Slip
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {tab === 'labtests' && (
        <Card
          title="Archived lab tests"
          subtitle={`${countLabel(filteredLabs.length)} shown`}
          actions={
            (filters.labPatient || filters.labDoctor || filters.labTest || filters.labStatus || filters.labPriority)
              ? <button type="button" className="ap-btn-text" onClick={resetLabFilters}>Reset filters</button>
              : undefined
          }
        >
          <FilterBar>
            <Field label="Patient" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Search patient..." value={filters.labPatient} onChange={(e) => setFilter('labPatient', e.target.value)} />
              </div>
            </Field>
            <Field label="Doctor" className="ap-field--grow">
              <input type="search" className="apt-search" placeholder="Search doctor..." value={filters.labDoctor} onChange={(e) => setFilter('labDoctor', e.target.value)} />
            </Field>
            <Field label="Test type" className="ap-field--grow">
              <input type="search" className="apt-search" placeholder="Search test type..." value={filters.labTest} onChange={(e) => setFilter('labTest', e.target.value)} />
            </Field>
            <Field label="Status">
              <select className="apt-select" value={filters.labStatus} onChange={(e) => setFilter('labStatus', e.target.value)}>
                <option value="">All statuses</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </Field>
            <Field label="Priority">
              <select className="apt-select" value={filters.labPriority} onChange={(e) => setFilter('labPriority', e.target.value)}>
                <option value="">All priorities</option>
                <option value="normal">Normal</option>
                <option value="urgent">Urgent</option>
                <option value="stat">STAT</option>
              </select>
            </Field>
          </FilterBar>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Doctor</th>
                  <th>Test Type</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Scheduled</th>
                  <th>Result</th>
                  <th>Archived On</th>
                </tr>
              </thead>
              <tbody>
                {filteredLabs.length === 0 ? (
                  <tr>
                    <td colSpan={8}>
                      <EmptyState
                        icon={<IconLab size={20} />}
                        title="No archived lab tests"
                        hint="Completed and cancelled lab requests will appear here."
                      />
                    </td>
                  </tr>
                ) : labGroups.map(([day, rows]) => (
                  <Fragment key={day}>
                    <DayHeader
                      colSpan={8}
                      day={day}
                      count={rows.length}
                      collapsed={dayCollapsed(`labtests:${day}`)}
                      onToggle={() => toggleDay(`labtests:${day}`)}
                    />
                    {!dayCollapsed(`labtests:${day}`) && rows.map((l) => (
                      <tr key={l.id}>
                        <td>
                          <span className="ap-strong">{l.patient_name}</span>
                          <small className="ap-sub">{l.patient_email || 'No email on file'}</small>
                        </td>
                        <td>
                          <span>Dr. {l.doctor_name}</span>
                          <small className="ap-sub">{l.specialty}</small>
                        </td>
                        <td><span className="ap-strong">{l.test_type}</span></td>
                        <td><span className={`badge badge-${l.priority}`}>{String(l.priority || '').toUpperCase()}</span></td>
                        <td><span className={`badge badge-${l.status}`}>{cap(l.status)}</span></td>
                        <td>{l.scheduled_date ? fmtDate(l.scheduled_date) : '\u2014'}</td>
                        <td>
                          {l.result ? (
                            <button type="button" className="btn-view btn-xs" onClick={() => setResultFor(l)}>View</button>
                          ) : (
                            <span className="ap-none">No result</span>
                          )}
                        </td>
                        <td>
                          <span className="ap-updated">
                            {fmtDate(l.updated_at)}
                            <small>{timeFromTs(l.updated_at)}</small>
                          </span>
                        </td>
                      </tr>
                    ))}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {tab === 'vitals' && (
        <Card
          title="Archived vitals"
          subtitle={`${countLabel(filteredVitals.length)} shown`}
          actions={filters.vitalSearch ? <button type="button" className="ap-btn-text" onClick={resetVitalFilters}>Reset filters</button> : undefined}
        >
          <FilterBar>
            <Field label="Search" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Search patient name..." value={filters.vitalSearch} onChange={(e) => setFilter('vitalSearch', e.target.value)} />
              </div>
            </Field>
          </FilterBar>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Visit Date</th>
                  <th>Weight</th>
                  <th>Blood Pressure</th>
                  <th>Heart Rate</th>
                  <th>Fundal Height</th>
                  <th>Notes</th>
                  <th>Recorded On</th>
                </tr>
              </thead>
              <tbody>
                {filteredVitals.length === 0 ? (
                  <tr>
                    <td colSpan={8}>
                      <EmptyState
                        icon={<IconVitals size={20} />}
                        title="No archived vitals"
                        hint="Vitals recorded during visits will be grouped by day here."
                      />
                    </td>
                  </tr>
                ) : vitalGroups.map(([day, rows]) => (
                  <Fragment key={day}>
                    <DayHeader
                      colSpan={8}
                      day={day}
                      count={rows.length}
                      collapsed={dayCollapsed(`vitals:${day}`)}
                      onToggle={() => toggleDay(`vitals:${day}`)}
                    />
                    {!dayCollapsed(`vitals:${day}`) && rows.map((v) => (
                      <tr key={v.id}>
                        <td>
                          <span className="ap-strong">{v.patient_name}</span>
                          <small className="ap-sub">{v.patient_email || 'No email on file'}</small>
                        </td>
                        <td><span className="ap-strong">{fmtDate(v.visit_date)}</span></td>
                        <td>{v.weight_kg ? `${v.weight_kg} kg` : '\u2014'}</td>
                        <td><span className="badge badge-active">{v.blood_pressure || '\u2014'}</span></td>
                        <td>{v.heart_rate ? `${v.heart_rate} bpm` : '\u2014'}</td>
                        <td>{v.fundal_height ? `${v.fundal_height} cm` : '\u2014'}</td>
                        <td>
                          <span className="ap-sub ap-sub--inline ap-clip">
                            {v.notes || '\u2014'}
                          </span>
                        </td>
                        <td>
                          <span className="ap-updated">
                            {fmtDate(v.created_at)}
                            <small>{timeFromTs(v.created_at)}</small>
                          </span>
                        </td>
                      </tr>
                    ))}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {tab === 'vaccinations' && (
        <Card
          title="Archived vaccinations"
          subtitle={`${countLabel(filteredVaccs.length)} shown`}
          actions={
            (filters.vaccPatient || filters.vaccVaccine || filters.vaccDose)
              ? <button type="button" className="ap-btn-text" onClick={resetVaccFilters}>Reset filters</button>
              : undefined
          }
        >
          <FilterBar>
            <Field label="Patient" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Search patient..." value={filters.vaccPatient} onChange={(e) => setFilter('vaccPatient', e.target.value)} />
              </div>
            </Field>
            <Field label="Vaccine" className="ap-field--grow">
              <input type="search" className="apt-search" placeholder="Search vaccine..." value={filters.vaccVaccine} onChange={(e) => setFilter('vaccVaccine', e.target.value)} />
            </Field>
            <Field label="Dose" className="ap-field--xs">
              <input type="search" className="apt-search" placeholder="Any" value={filters.vaccDose} onChange={(e) => setFilter('vaccDose', e.target.value)} />
            </Field>
          </FilterBar>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Vaccine</th>
                  <th>Dose</th>
                  <th>Administered</th>
                  <th>Next Due</th>
                  <th>Notes</th>
                  <th>Recorded On</th>
                </tr>
              </thead>
              <tbody>
                {filteredVaccs.length === 0 ? (
                  <tr>
                    <td colSpan={7}>
                      <EmptyState
                        icon={<IconVaccine size={20} />}
                        title="No archived vaccinations"
                        hint="Doses administered at the clinic will be grouped by day here."
                      />
                    </td>
                  </tr>
                ) : vaccGroups.map(([day, rows]) => (
                  <Fragment key={day}>
                    <DayHeader
                      colSpan={7}
                      day={day}
                      count={rows.length}
                      collapsed={dayCollapsed(`vaccinations:${day}`)}
                      onToggle={() => toggleDay(`vaccinations:${day}`)}
                    />
                    {!dayCollapsed(`vaccinations:${day}`) && rows.map((v) => {
                      const due = v.next_due_date ? (new Date(v.next_due_date).getTime() - new Date(todayKey).getTime()) / 86400000 : null;
                      return (
                        <tr key={v.id}>
                          <td>
                            <span className="ap-strong">{v.patient_name}</span>
                            <small className="ap-sub">{v.patient_email || 'No email on file'}</small>
                          </td>
                          <td><span className="ap-strong">{v.vaccine_name}</span></td>
                          <td><span className="pill">{v.dose_label}</span></td>
                          <td><span className="ap-strong">{fmtDate(v.administered_date)}</span></td>
                          <td>
                            {v.next_due_date ? (
                              <span className={`badge ${due !== null && due <= 14 ? 'badge-due' : 'badge-done'}`}>{fmtDate(v.next_due_date)}</span>
                            ) : (
                              <span className="badge badge-done">Complete</span>
                            )}
                          </td>
                          <td>
                            <span className="ap-sub ap-sub--inline ap-clip">
                              {v.notes || '\u2014'}
                            </span>
                          </td>
                          <td>
                            <span className="ap-updated">
                              {fmtDate(v.created_at)}
                              <small>{timeFromTs(v.created_at)}</small>
                            </span>
                          </td>
                        </tr>
                      );
                    })}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
      </Stack>

      {/* ---- appointment slip ---- */}
      <Modal open={!!slipFor} onClose={() => setSlipFor(null)} className="slip-modal">
        <ModalHead
          title="Appointment slip"
          hint={slipFor ? `${slipFor.patient_name} · Dr. ${slipFor.doctor_name}` : undefined}
        />
        {slipFor && (
          <div className="slip-body">
            <div className="slip-head">
              <div className="slip-logo">LustreMDC</div>
              <div className="slip-clinic">Clinic Appointment Slip</div>
            </div>
            {slipRows(slipFor).map(([label, value]) => (
              <div className="slip-row" key={label}><span>{label}:</span><strong>{value}</strong></div>
            ))}
            <div className="slip-foot">Please arrive 10-15 minutes early. Show this slip at the counter.</div>
          </div>
        )}
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setSlipFor(null)}>Close</button>
          <button type="button" className="btn-primary" onClick={printSlip}>Print Slip</button>
        </div>
      </Modal>

      {/* ---- reschedule history ---- */}
      <Modal open={!!reschedFor} onClose={() => setReschedFor(null)}>
        <ModalHead
          title="Reschedule history"
          hint={reschedFor ? `${reschedFor.patient_name} · Dr. ${reschedFor.doctor_name}` : undefined}
        />
        {reschedFor && (
          reschedEntries(reschedFor).length > 0 ? (
            <ul className="list-plain">
              {reschedEntries(reschedFor).map((e, i) => (
                <li className="list-row" key={i}>
                  <div className="list-row-body">
                    <strong>
                      {fmtDate(e.old_date)} &middot; {fmtTime(e.old_time)}
                      {' \u2192 '}
                      {fmtDate(e.new_date)} &middot; {fmtTime(e.new_time)}
                    </strong>
                    <span className="ap-sub ap-sub--inline">rescheduled on {fmtDate(e.rescheduled_at)} {timeFromTs(e.rescheduled_at)}</span>
                  </div>
                </li>
              ))}
            </ul>
          ) : reschedFor.last_rescheduled_at ? (
            <ul className="list-plain">
              <li className="list-row">
                <div className="list-row-body">
                  <strong>Rescheduled {'\u2192'} {fmtDate(reschedFor.appointment_date)} {fmtTime(reschedFor.appointment_time)}</strong>
                  <span className="ap-sub ap-sub--inline">last rescheduled on {fmtDate(reschedFor.last_rescheduled_at)} {timeFromTs(reschedFor.last_rescheduled_at)}</span>
                </div>
              </li>
            </ul>
          ) : (
            <div className="empty-note">This appointment was rescheduled.</div>
          )
        )}
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setReschedFor(null)}>Close</button>
        </div>
      </Modal>

      {/* ---- lab result ---- */}
      <Modal open={!!resultFor} onClose={() => setResultFor(null)}>
        <ModalHead
          title={resultFor?.test_type || 'Lab result'}
          hint={resultFor ? `${resultFor.patient_name} · Dr. ${resultFor.doctor_name}` : undefined}
        />
        {resultFor && (
          <>
            <div className="client-detail-grid ap-detail-grid">
              <div><span>Patient</span><strong>{resultFor.patient_name}</strong></div>
              <div><span>Doctor</span><strong>Dr. {resultFor.doctor_name}</strong></div>
              <div><span>Test</span><strong>{resultFor.test_type}</strong></div>
              <div><span>Scheduled</span><strong>{resultFor.scheduled_date ? fmtDate(resultFor.scheduled_date) : '\u2014'}</strong></div>
              <div><span>Status</span><strong style={{ textTransform: 'capitalize' }}>{cap(resultFor.status)}</strong></div>
            </div>
            <label className="form-label">Findings / result</label>
            <div className="ap-result-body">{resultFor.result || 'No result recorded.'}</div>
            {resultFor.notes && resultFor.notes.trim() ? (
              <>
                <label className="form-label ap-mt">Clinical notes</label>
                <div className="ap-result-body">{resultFor.notes}</div>
              </>
            ) : null}
          </>
        )}
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setResultFor(null)}>Close</button>
        </div>
      </Modal>
    </Shell>
  );
}
