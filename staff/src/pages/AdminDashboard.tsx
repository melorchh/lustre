import { useEffect, useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime, fmtDateTime, firstName } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal, Select } from '../components';
import { Card, EmptyState, ModalHead, Stack, StatCard, StatGrid } from '../ui';
import {
  IconAppointments, IconCheck, IconChevronRight, IconClock, IconDoctors, IconLab,
  IconPatients, IconPlus, IconPrint, IconReports, IconSchedules, IconVitals, IconWalkins,
} from '../icons';
import type { AppointmentRow, DoctorRow, PatientRow, WalkinRow } from '../types';

interface HistoryEntry {
  appointment_id: number;
  old_date: string;
  old_time: string;
  new_date: string;
  new_time: string;
  rescheduled_at: string;
}

interface DoctorProc extends DoctorRow {
  test_procedures?: string;
}

const AGE_BANDS: Record<string, { min: number; max: number; label: string }> = {
  infant: { min: 1, max: 2, label: '1-2 years' },
  child: { min: 3, max: 12, label: '3-12 years' },
  adolescent: { min: 13, max: 17, label: '13-17 years' },
  adult: { min: 18, max: 59, label: '18-59 years' },
  elderly: { min: 60, max: 150, label: '60 years and above' },
};

const OTHER_PROC = 'Other (specify below)';

const EMPTY_APPT = {
  patient_id: '', doctor_id: '', date: '', time: '',
  weight_kg: '', height_cm: '', blood_pressure: '', heart_rate: '', fundal_height: '', vitals_notes: '',
};

const EMPTY_WALKIN = {
  specialty: '', doctor_id: '', test_procedure: '', other: '', arrival_time: '',
  name: '', contact: '', gender: '', address: '', patient_type: '', age: '',
  weight_kg: '', height_cm: '', blood_pressure: '', heart_rate: '', fundal_height: '',
};

function todayISO(): string {
  return new Date().toISOString().slice(0, 10);
}

const ARRIVAL_START_MIN = 8 * 60;

function arrivalOptions(): { value: string; label: string }[] {
  const out: { value: string; label: string }[] = [];
  for (let m = ARRIVAL_START_MIN; m < 24 * 60; m += 5) {
    const h = Math.floor(m / 60);
    const mm = m % 60;
    const value = `${String(h).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
    out.push({ value, label: fmtTime(value) });
  }
  return out;
}

function nowHM(): string {
  const d = new Date();
  let m = Math.max(ARRIVAL_START_MIN, d.getHours() * 60 + d.getMinutes());
  m -= m % 5;
  return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

export default function AdminDashboard() {
  const data = getInitial();
  const adminName = String(data.staff?.name || '');
  const hour = new Date().getHours();
  const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
  const todayLabel = new Date().toLocaleDateString('en-PH', {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
  });
  const { showToast } = useToast();
  const [bundle, setBundle] = useState(data.data as Record<string, unknown>);

  const stats = (bundle.stats as Record<string, number>) || {};
  const recent = (bundle.recentAppointments as AppointmentRow[]) || [];
  const todayWalkins = (bundle.todayWalkins as WalkinRow[]) || [];
  const patientsList = (bundle.patientsList as PatientRow[]) || [];
  const doctors = (bundle.doctors as DoctorProc[]) || [];
  const specialties = (bundle.specialties as string[]) || [];
  const history = (bundle.rescheduleHistory as HistoryEntry[]) || [];

  const [apptOpen, setApptOpen] = useState(false);
  const [apptForm, setApptForm] = useState({ ...EMPTY_APPT });
  const [slots, setSlots] = useState<string[]>([]);
  const [slotLoading, setSlotLoading] = useState(false);

  const [walkinOpen, setWalkinOpen] = useState(false);
  const [walkinForm, setWalkinForm] = useState({ ...EMPTY_WALKIN });
  // Doctor ids with room left today (null = not fetched yet / unknown, in
  // which case every doctor stays selectable).
  const [availableDoctorIds, setAvailableDoctorIds] = useState<number[] | null>(null);

  const historyByAppt = useMemo(() => {
    const map: Record<number, HistoryEntry[]> = {};
    for (const h of history) (map[h.appointment_id] ||= []).push(h);
    return map;
  }, [history]);

  const walkinsByDoctor = useMemo(() => {
    const groups: { doctor: string; specialty: string; rows: WalkinRow[] }[] = [];
    for (const w of todayWalkins) {
      const name = w.doctor_name || 'Unassigned';
      let g = groups.find((x) => x.doctor === name);
      if (!g) {
        g = { doctor: name, specialty: w.specialty || '', rows: [] };
        groups.push(g);
      }
      g.rows.push(w);
    }
    return groups;
  }, [todayWalkins]);

  const arrival = useMemo(arrivalOptions, []);

  // Load available appointment times when the doctor/date pair changes.
  useEffect(() => {
    if (!apptOpen || !apptForm.doctor_id || !apptForm.date) {
      setSlots([]);
      return;
    }
    let alive = true;
    setSlotLoading(true);
    fetch(`get_available_times.php?doctor_id=${encodeURIComponent(apptForm.doctor_id)}&date=${encodeURIComponent(apptForm.date)}`, {
      headers: { 'X-Requested-With': 'LustreStaff' },
      cache: 'no-store',
    })
      .then((r) => r.json())
      .then((json: string[]) => { if (alive) setSlots(Array.isArray(json) ? json : []); })
      .catch(() => { if (alive) setSlots([]); })
      .finally(() => { if (alive) setSlotLoading(false); });
    return () => { alive = false; };
  }, [apptOpen, apptForm.doctor_id, apptForm.date]);

  // Load the doctors who can still take a walk-in today. The endpoint applies
  // the same capacity rule the server re-checks on submit; if it cannot be
  // reached we keep the full list rather than blocking the counter.
  useEffect(() => {
    if (!walkinOpen) return;
    let alive = true;
    setAvailableDoctorIds(null);
    fetch('get_available_doctors.php', {
      headers: { 'X-Requested-With': 'LustreStaff' },
      cache: 'no-store',
    })
      .then((r) => r.json())
      .then((json: unknown) => {
        if (!alive || !Array.isArray(json)) return;
        const ids = json
          .map((d) => Number((d as { id?: unknown }).id))
          .filter((n) => Number.isFinite(n));
        setAvailableDoctorIds(ids);
        // Drop a doctor that filled up between opening and loading the list.
        setWalkinForm((f) => (
          f.doctor_id && !ids.includes(Number(f.doctor_id))
            ? { ...f, doctor_id: '', test_procedure: '', other: '' }
            : f
        ));
      })
      .catch(() => { /* keep every doctor selectable */ });
    return () => { alive = false; };
  }, [walkinOpen]);

  async function refresh() {
    try {
      const json = await fetchData('api_admin_data.php', { view: 'dashboard' });
      if (json?.data) setBundle(json.data as Record<string, unknown>);
    } catch {
      /* keep current data */
    }
  }

  function openAppt() {
    setApptForm({ ...EMPTY_APPT });
    setSlots([]);
    setApptOpen(true);
  }

  function openWalkin() {
    setAvailableDoctorIds(null);
    setWalkinForm({ ...EMPTY_WALKIN, arrival_time: nowHM() });
    setWalkinOpen(true);
  }

  async function bookAppointment() {
    if (!apptForm.patient_id || !apptForm.doctor_id || !apptForm.date || !apptForm.time) {
      showToast('Patient, doctor, date and time are required', 'error');
      return;
    }
    showLoading();
    try {
      const res = await postAction('admin_book_appointment.php', apptForm);
      if (isSuccess(res)) {
        showToast('Appointment booked!', 'success');
        setApptOpen(false);
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  const walkinBand = AGE_BANDS[walkinForm.patient_type];
  const doctorProcs = useMemo(() => {
    const doc = doctors.find((d) => String(d.id) === String(walkinForm.doctor_id));
    if (!doc) return [] as string[];
    const lines = String(doc.test_procedures || '')
      .split('\n')
      .map((l) => l.trim())
      .filter(Boolean);
    const uniq = Array.from(new Set(lines));
    if (!uniq.includes(OTHER_PROC)) uniq.push(OTHER_PROC);
    return uniq;
  }, [doctors, walkinForm.doctor_id]);

  const walkinDoctors = useMemo(() => {
    const pool = availableDoctorIds
      ? doctors.filter((d) => availableDoctorIds.includes(Number(d.id)))
      : doctors;
    return pool.filter((d) => !walkinForm.specialty || d.specialty === walkinForm.specialty);
  }, [doctors, availableDoctorIds, walkinForm.specialty]);

  async function registerWalkin() {
    if (!walkinForm.name.trim()) { showToast('Patient name required', 'error'); return; }
    if (!walkinForm.contact.trim()) { showToast('Contact number required', 'error'); return; }
    if (!walkinForm.doctor_id) { showToast('Please select a doctor', 'error'); return; }
    if (!walkinForm.test_procedure) { showToast('Please select a test / procedure', 'error'); return; }
    if (!walkinForm.blood_pressure.trim()) { showToast('Blood pressure required', 'error'); return; }
    if (!walkinForm.heart_rate.trim()) { showToast('Heart rate required', 'error'); return; }
    if (walkinForm.patient_type) {
      const band = AGE_BANDS[walkinForm.patient_type];
      if (!walkinForm.age.trim()) { showToast(`Age is required for ${walkinForm.patient_type}`, 'error'); return; }
      const age = Number(walkinForm.age);
      if (band && (age < band.min || age > band.max)) {
        showToast(`Age must be ${band.label} for ${walkinForm.patient_type}`, 'error');
        return;
      }
    }
    const proc = walkinForm.test_procedure === OTHER_PROC ? walkinForm.other.trim() : walkinForm.test_procedure;
    if (!proc) { showToast('Please specify the test / procedure', 'error'); return; }

    showLoading();
    try {
      const res = await postAction('admin_walkin_action.php', {
        action: 'add_walkin',
        name: walkinForm.name,
        contact: walkinForm.contact,
        gender: walkinForm.gender,
        address: walkinForm.address,
        patient_type: walkinForm.patient_type,
        age: walkinForm.age,
        weight_kg: walkinForm.weight_kg,
        height_cm: walkinForm.height_cm,
        blood_pressure: walkinForm.blood_pressure,
        heart_rate: walkinForm.heart_rate,
        fundal_height: walkinForm.fundal_height,
        doctor_id: walkinForm.doctor_id,
        arrival_time: walkinForm.arrival_time,
        test_procedure: proc,
      });
      if (isSuccess(res)) {
        showToast('Walk-in client registered!', 'success');
        setWalkinOpen(false);
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function updateStatus(id: number, status: string) {
    showLoading();
    try {
      const res = await postAction('admin_update_appointment.php', { appointment_id: id, status });
      if (isSuccess(res)) {
        setBundle((b) => ({
          ...b,
          recentAppointments: (b.recentAppointments as AppointmentRow[]).map((a) => (a.id === id ? { ...a, status } : a)),
        }));
        showToast('Status updated!', 'success');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function updatePayment(id: number, payment_status: string) {
    showLoading();
    try {
      const res = await postAction('admin_update_appointment.php', { appointment_id: id, payment_status });
      if (isSuccess(res)) {
        setBundle((b) => ({
          ...b,
          recentAppointments: (b.recentAppointments as AppointmentRow[]).map((a) => (a.id === id ? { ...a, payment_status } : a)),
        }));
        showToast('Payment updated!', 'success');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  function printSlip(a: AppointmentRow) {
    const rows = [
      ['Patient', a.patient_name],
      ['Doctor', `Dr. ${a.doctor_name}`],
      ['Specialty', a.specialty],
      ['Date', fmtDate(a.appointment_date)],
      ['Time', fmtTime(a.appointment_time)],
      ['Status', a.status],
      ['Payment', a.payment_status === 'paid' ? 'Paid' : 'Unpaid'],
    ];
    const w = window.open('', '_blank', 'width=460,height=640');
    if (!w) { showToast('Popup blocked — allow popups to print', 'error'); return; }
    w.document.write(
      '<!DOCTYPE html><html><head><title>Appointment Slip</title><style>' +
      'body{font-family:Arial,Helvetica,sans-serif;color:#111;padding:24px;margin:0}' +
      '.wrap{border:2px solid #16a34a;border-radius:14px;padding:22px;max-width:360px;margin:0 auto}' +
      '.head{text-align:center;border-bottom:2px solid #e2e8f0;padding-bottom:14px;margin-bottom:16px}' +
      '.head .name{font-size:20px;font-weight:800;color:#16a34a;letter-spacing:.5px}' +
      '.head .sub{font-size:12px;color:#475569;margin-top:2px}' +
      '.row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px}' +
      '.row span{color:#64748b}.row strong{color:#0f172a}' +
      '.foot{text-align:center;font-size:12px;color:#64748b;margin-top:16px}' +
      '</style></head><body><div class="wrap">' +
      '<div class="head"><div class="name">LustreMDC</div><div class="sub">CLINIC APPOINTMENT SLIP</div></div>' +
      rows.map(([k, v]) => `<div class="row"><span>${k}:</span><strong>${escapeHtml(String(v ?? ''))}</strong></div>`).join('') +
      '<div class="foot">Please arrive 10-15 minutes early. Show this slip at the counter.</div>' +
      '</div><script>window.onload=function(){window.print();}<\/script></body></html>',
    );
    w.document.close();
    showToast('Slip ready to print', 'success');
  }

  return (
    <Shell active="dashboard" title="Dashboard">
      <Stack>
      <section className="ap-hero">
        <div className="ap-hero-copy">
          <span className="ap-hero-eyebrow">Today at a glance</span>
          <h1>{greeting}{adminName ? `, ${firstName(adminName)}` : ''}</h1>
          <p>{todayLabel}</p>
        </div>
        <div className="ap-hero-actions">
          <button type="button" className="ap-btn ap-btn--solid" onClick={openAppt}>
            <IconPlus size={18} /> New Appointment
          </button>
          <button type="button" className="ap-btn ap-btn--amber" onClick={openWalkin}>
            <IconWalkins size={18} /> Register Walk-in
          </button>
        </div>
      </section>

      <StatGrid className="ap-stat-grid--seven">
        <StatCard icon={<IconPatients />} label="Patients" value={stats.totalPatients ?? 0} hint="Registered in the clinic" />
        <StatCard icon={<IconDoctors />} label="Doctors" value={stats.totalDoctors ?? 0} hint="Available specialists" tone="blue" />
        <StatCard icon={<IconAppointments />} label="Today" value={stats.todayAppointments ?? 0} hint="Appointments scheduled" tone="violet" />
        <StatCard icon={<IconWalkins />} label="Walk-ins" value={stats.walkinsToday ?? 0} hint="Registered today" tone="amber" />
        <StatCard icon={<IconClock />} label="Pending" value={stats.pendingAppointments ?? 0} hint="Awaiting confirmation" tone="rose" />
        <StatCard icon={<IconCheck />} label="Confirmed" value={stats.confirmedAppointments ?? 0} hint="Ready to serve" tone="teal" />
        <StatCard icon={<IconReports />} label="All appointments" value={stats.totalAppointments ?? 0} hint="Lifetime records" tone="slate" />
      </StatGrid>

      <Card title="Quick actions" subtitle="Jump straight to the counter tasks you use most">
        <div className="ap-quick-grid">
          <a href="admin_clients.php" className="ap-quick">
            <span className="ap-quick-icon"><IconPatients size={18} /></span>
            <span className="ap-quick-body"><strong>Patient directory</strong><small>Search and open profiles</small></span>
            <IconChevronRight size={16} />
          </a>
          <a href="admin_vitals.php" className="ap-quick">
            <span className="ap-quick-icon"><IconVitals size={18} /></span>
            <span className="ap-quick-body"><strong>Record vitals</strong><small>Vaccines and measurements</small></span>
            <IconChevronRight size={16} />
          </a>
          <a href="admin_laboratory.php" className="ap-quick">
            <span className="ap-quick-icon"><IconLab size={18} /></span>
            <span className="ap-quick-body"><strong>Laboratory</strong><small>Requests, results and releases</small></span>
            <IconChevronRight size={16} />
          </a>
          <a href="admin_doctors.php" className="ap-quick">
            <span className="ap-quick-icon"><IconDoctors size={18} /></span>
            <span className="ap-quick-body"><strong>Doctors</strong><small>Profiles and specialties</small></span>
            <IconChevronRight size={16} />
          </a>
          <a href="admin_schedules.php" className="ap-quick">
            <span className="ap-quick-icon"><IconSchedules size={18} /></span>
            <span className="ap-quick-body"><strong>Schedules</strong><small>Weekly availability blocks</small></span>
            <IconChevronRight size={16} />
          </a>
          <a href="admin_walkins.php" className="ap-quick">
            <span className="ap-quick-icon"><IconWalkins size={18} /></span>
            <span className="ap-quick-body"><strong>Walk-in queue</strong><small>Today's counter patients</small></span>
            <IconChevronRight size={16} />
          </a>
          <a href="admin_reports.php" className="ap-quick">
            <span className="ap-quick-icon"><IconReports size={18} /></span>
            <span className="ap-quick-body"><strong>Reports</strong><small>Clinic activity and exports</small></span>
            <IconChevronRight size={16} />
          </a>
        </div>
      </Card>

      <Card
        title="Recent appointments"
        subtitle={`${recent.length} most recent booking${recent.length === 1 ? '' : 's'}`}
        actions={<a className="ap-link" href="admin_appointments.php">View all <IconChevronRight size={14} /></a>}
      >
        <div className="table-scroll">
          <table>
            <thead><tr><th>#</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
            <tbody>
              {recent.length === 0 ? (
                <tr><td colSpan={8} className="empty-state-row">No recent appointments.</td></tr>
              ) : recent.map((a) => {
                const hist = historyByAppt[a.id] || [];
                return (
                  <tr key={a.id}>
                    <td className="ap-id">#{a.id}</td>
                    <td><strong>{a.patient_name}</strong></td>
                    <td>Dr. {a.doctor_name}<br /><small>{a.specialty}</small></td>
                    <td>{fmtDate(a.appointment_date)}</td>
                    <td>{fmtTime(a.appointment_time)}</td>
                    <td>
                      <span className={`badge badge-${a.status}`}>{a.status.charAt(0).toUpperCase() + a.status.slice(1)}</span>
                      {(a.reschedule_count || hist.length > 0) ? (
                        <span className="resched-tip" style={{ marginTop: 4 }}>
                          <span className="resched-badge">&#10227; Rescheduled{(a.reschedule_count || 0) > 1 ? ` x${a.reschedule_count}` : ''}</span>
                          <span className="resched-pop">
                            <span className="resched-pop-title">Reschedule History</span>
                            {hist.length > 0 ? hist.map((h, i) => (
                              <span key={i} className="resched-pop-item">
                                <span className="resched-change">
                                  <span className="resched-old">{fmtDate(h.old_date)} · {fmtTime(h.old_time)}</span>
                                  &nbsp;&rarr;&nbsp;
                                  <span className="resched-new">{fmtDate(h.new_date)} {fmtTime(h.new_time)}</span>
                                </span>
                                <span className="resched-pop-time">rescheduled on {fmtDateTime(h.rescheduled_at)}</span>
                              </span>
                            )) : (
                              <>
                                <span className="resched-pop-title">Rescheduled</span>
                                <span className="resched-pop-time">{a.last_rescheduled_at ? `Last rescheduled on ${fmtDateTime(a.last_rescheduled_at)}` : 'This appointment was rescheduled'}</span>
                              </>
                            )}
                          </span>
                        </span>
                      ) : null}
                    </td>
                    <td>
                      <span className={`badge ${a.payment_status === 'paid' ? 'badge-active' : 'badge-inactive'}`}>
                        {a.payment_status === 'paid' ? 'Paid' : 'Unpaid'}
                      </span>
                    </td>
                    <td>
                      <div className="ap-row-actions">
                        <Select className="action-select" value={a.status} onChange={(e) => updateStatus(a.id, e.target.value)}>
                          {['pending', 'confirmed', 'completed', 'cancelled'].map((o) => (
                            <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>
                          ))}
                        </Select>
                        <Select className="action-select" value={a.payment_status} onChange={(e) => updatePayment(a.id, e.target.value)}>
                          <option value="pending">Unpaid</option>
                          <option value="paid">Paid</option>
                        </Select>
                        <button type="button" className="btn-slip" title="Print appointment slip" onClick={() => printSlip(a)}>
                          <IconPrint size={16} /> Print slip
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>

      <Card
        title="Today's walk-ins"
        subtitle={walkinsByDoctor.length ? `${todayWalkins.length} patient${todayWalkins.length === 1 ? '' : 's'} in the queue` : undefined}
        actions={<a className="ap-link" href="admin_walkins.php">View all <IconChevronRight size={14} /></a>}
      >
        {walkinsByDoctor.length === 0 ? (
          <EmptyState
            icon={<IconWalkins size={20} />}
            title="No walk-ins yet"
            hint="Register a counter patient to start today's queue."
          />
        ) : walkinsByDoctor.map((g) => (
          <div className="ap-group" key={g.doctor}>
            <h3 className="ap-group-title">
              Dr. {g.doctor} {g.specialty ? <small>({g.specialty})</small> : null}
            </h3>
            <div className="table-scroll">
              <table>
                <thead><tr><th>Queue</th><th>Patient</th><th>Type</th><th>Arrival</th><th>Test / Procedure</th><th>Status</th></tr></thead>
                <tbody>
                  {g.rows.map((w, i) => (
                    <tr key={w.id}>
                      <td><span className="ap-queue">#{w.queue ?? i + 1}</span></td>
                      <td><strong>{w.patient_name}</strong></td>
                      <td><span className="badge badge-urgent">{w.patient_type ? w.patient_type.charAt(0).toUpperCase() + w.patient_type.slice(1) : '—'}</span></td>
                      <td>{fmtTime(w.arrival_time)}</td>
                      <td>{w.test_procedure || '—'}</td>
                      <td>
                        <span className={`badge ${walkinBadgeClass(w.status)}`}>
                          {w.status === 'in_service' ? 'In Service' : w.status.charAt(0).toUpperCase() + w.status.slice(1)}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ))}
      </Card>
      </Stack>

      <Modal open={apptOpen} onClose={() => setApptOpen(false)}>
        <ModalHead title="New appointment" hint="Book a slot for a registered patient." />
        <label className="form-label">Patient *</label>
        <Select className="form-dropdown form-input" searchable value={apptForm.patient_id} onChange={(e) => setApptForm({ ...apptForm, patient_id: e.target.value })}>
          <option value="">Select patient...</option>
          {patientsList.map((p) => (
            <option key={p.id} value={p.id}>{p.name}{p.age !== null && p.age !== undefined ? ` (${p.age} yrs)` : ''}</option>
          ))}
        </Select>

        <label className="form-label">Doctor *</label>
        <Select className="form-dropdown form-input" value={apptForm.doctor_id} onChange={(e) => setApptForm({ ...apptForm, doctor_id: e.target.value, time: '' })}>
          <option value="">Select doctor...</option>
          {doctors.map((doc) => (
            <option key={doc.id} value={doc.id}>Dr. {doc.name} ({doc.specialty})</option>
          ))}
        </Select>

        <div className="form-grid">
          <div className="form-group">
            <label>Date *</label>
            <input type="date" className="form-input" min={todayISO()} value={apptForm.date} onChange={(e) => setApptForm({ ...apptForm, date: e.target.value, time: '' })} />
          </div>
          <div className="form-group">
            <label>Time *</label>
            <Select className="form-dropdown form-input" value={apptForm.time} disabled={!apptForm.doctor_id || !apptForm.date} onChange={(e) => setApptForm({ ...apptForm, time: e.target.value })}>
              <option value="">{slotLoading ? 'Loading...' : (!apptForm.doctor_id || !apptForm.date) ? 'Select doctor & date first' : slots.length === 0 ? 'No slots available' : 'Select time...'}</option>
              {slots.map((s) => <option key={s} value={s}>{fmtTime(s)}</option>)}
            </Select>
          </div>
        </div>
        <p className="form-hint">Pick a doctor to see available dates &amp; times. Duration is 1 hour / visit.</p>

        <p className="form-hint" style={{ fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.06em', marginTop: '1rem' }}>Vitals (optional)</p>
        <div className="form-grid">
          <div className="form-group">
            <label>Weight (kg)</label>
            <input type="number" className="form-input" step="0.1" min="0" placeholder="e.g. 58.5" value={apptForm.weight_kg} onChange={(e) => setApptForm({ ...apptForm, weight_kg: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Height (cm)</label>
            <input type="number" className="form-input" step="0.1" min="0" placeholder="e.g. 160.0" value={apptForm.height_cm} onChange={(e) => setApptForm({ ...apptForm, height_cm: e.target.value })} />
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Blood Pressure</label>
            <input type="text" className="form-input" placeholder="e.g. 110/70" value={apptForm.blood_pressure} onChange={(e) => setApptForm({ ...apptForm, blood_pressure: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Heart Rate (bpm)</label>
            <input type="number" className="form-input" min="0" max="250" placeholder="e.g. 78" value={apptForm.heart_rate} onChange={(e) => setApptForm({ ...apptForm, heart_rate: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Fundal Height (cm)</label>
            <input type="number" className="form-input" step="0.1" min="0" max="50" placeholder="e.g. 24.0" value={apptForm.fundal_height} onChange={(e) => setApptForm({ ...apptForm, fundal_height: e.target.value })} />
          </div>
        </div>
        <label className="form-label">Vitals Notes</label>
        <textarea className="form-input" rows={2} placeholder="Optional notes (e.g. patient condition)" value={apptForm.vitals_notes} onChange={(e) => setApptForm({ ...apptForm, vitals_notes: e.target.value })} />
        <p className="form-hint">Leave blank to record vitals later from the appointment&rsquo;s Vitals button.</p>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setApptOpen(false)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={bookAppointment}>Book Appointment</button>
        </div>
      </Modal>

      <Modal open={walkinOpen} onClose={() => setWalkinOpen(false)}>
        <ModalHead title="Register walk-in client" hint="Add a counter patient to today's queue." />
        <div className="form-grid">
          <div className="form-group">
            <label>Specialty</label>
            <Select className="form-dropdown form-input" value={walkinForm.specialty} onChange={(e) => setWalkinForm({ ...walkinForm, specialty: e.target.value, doctor_id: '', test_procedure: '', other: '' })}>
              <option value="">All specialties</option>
              {specialties.map((s) => <option key={s} value={s}>{s}</option>)}
            </Select>
          </div>
          <div className="form-group">
            <label>Doctor *</label>
            <Select className="form-dropdown form-input" value={walkinForm.doctor_id} onChange={(e) => setWalkinForm({ ...walkinForm, doctor_id: e.target.value, test_procedure: '', other: '' })}>
              <option value="">Select Doctor</option>
              {walkinDoctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name} ({doc.specialty})</option>)}
            </Select>
            {availableDoctorIds !== null && walkinDoctors.length === 0 && (
              <p className="form-hint">
                No doctors are available today{walkinForm.specialty ? ` for ${walkinForm.specialty}` : ''}. Add an active schedule for today or free up a doctor's capacity.
              </p>
            )}
          </div>
          <div className="form-group">
            <label>Test / Procedure</label>
            <Select className="form-dropdown form-input" value={walkinForm.test_procedure} disabled={!walkinForm.doctor_id} onChange={(e) => setWalkinForm({ ...walkinForm, test_procedure: e.target.value, other: e.target.value === OTHER_PROC ? walkinForm.other : '' })}>
              <option value="" disabled>{walkinForm.doctor_id ? 'Select Test / Procedure' : 'Select a doctor first'}</option>
              {doctorProcs.map((p) => <option key={p} value={p}>{p}</option>)}
            </Select>
            {walkinForm.test_procedure === OTHER_PROC && (
              <input type="text" className="form-input" style={{ marginTop: '.55rem' }} placeholder="Specify test / procedure..." value={walkinForm.other} onChange={(e) => setWalkinForm({ ...walkinForm, other: e.target.value })} />
            )}
          </div>
          <div className="form-group">
            <label>Arrival Time</label>
            <Select className="form-dropdown form-input" value={walkinForm.arrival_time} onChange={(e) => setWalkinForm({ ...walkinForm, arrival_time: e.target.value })}>
              {arrival.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </Select>
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Full Name *</label>
            <input type="text" className="form-input" placeholder="e.g. Juan Dela Cruz" value={walkinForm.name} onChange={(e) => setWalkinForm({ ...walkinForm, name: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Contact Number *</label>
            <input type="text" className="form-input" maxLength={11} placeholder="e.g. 09171234567" value={walkinForm.contact} onChange={(e) => setWalkinForm({ ...walkinForm, contact: e.target.value })} />
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Gender *</label>
            <Select className="form-dropdown form-input" value={walkinForm.gender} onChange={(e) => setWalkinForm({ ...walkinForm, gender: e.target.value })}>
              <option value="">Select Gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
            </Select>
          </div>
          <div className="form-group">
            <label>Address</label>
            <input type="text" className="form-input" placeholder="e.g. 123 Sampaguita St, Manila" value={walkinForm.address} onChange={(e) => setWalkinForm({ ...walkinForm, address: e.target.value })} />
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Patient Type</label>
            <Select className="form-dropdown form-input" value={walkinForm.patient_type} onChange={(e) => setWalkinForm({ ...walkinForm, patient_type: e.target.value, age: '' })}>
              <option value="">Select Type</option>
              <option value="infant">Infant</option>
              <option value="child">Child</option>
              <option value="adolescent">Adolescent</option>
              <option value="adult">Adult</option>
              <option value="elderly">Elderly</option>
            </Select>
          </div>
          <div className="form-group">
            <label>Age</label>
            <input type="number" className="form-input" min={walkinBand?.min ?? 0} max={walkinBand?.max ?? 150} placeholder="e.g. 32" value={walkinForm.age} onChange={(e) => setWalkinForm({ ...walkinForm, age: e.target.value })} />
            <small className="age-band-hint" style={{ display: 'block', marginTop: '.35rem', fontSize: '.78rem', color: '#6b7280' }}>
              {walkinBand ? `Allowed: ${walkinBand.label}.` : ''}
            </small>
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Weight (kg)</label>
            <input type="number" className="form-input" step="0.1" min="0" placeholder="e.g. 58.5" value={walkinForm.weight_kg} onChange={(e) => setWalkinForm({ ...walkinForm, weight_kg: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Height (cm)</label>
            <input type="number" className="form-input" step="0.1" min="0" placeholder="e.g. 160.0" value={walkinForm.height_cm} onChange={(e) => setWalkinForm({ ...walkinForm, height_cm: e.target.value })} />
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Blood Pressure *</label>
            <input type="text" className="form-input" placeholder="e.g. 110/70" value={walkinForm.blood_pressure} onChange={(e) => setWalkinForm({ ...walkinForm, blood_pressure: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Heart Rate (bpm) *</label>
            <input type="number" className="form-input" min="0" max="250" placeholder="e.g. 78" value={walkinForm.heart_rate} onChange={(e) => setWalkinForm({ ...walkinForm, heart_rate: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Fundal Height (cm)</label>
            <input type="number" className="form-input" step="0.1" min="0" max="50" placeholder="e.g. 24.0" value={walkinForm.fundal_height} onChange={(e) => setWalkinForm({ ...walkinForm, fundal_height: e.target.value })} />
          </div>
        </div>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setWalkinOpen(false)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={registerWalkin}>Register Client</button>
        </div>
      </Modal>
    </Shell>
  );
}

function walkinBadgeClass(status: string): string {
  if (status === 'served') return 'badge-completed';
  if (status === 'in_service') return 'badge-confirmed';
  if (status === 'cancelled') return 'badge-cancelled';
  return 'badge-pending';
}

function escapeHtml(s: string): string {
  return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string));
}
