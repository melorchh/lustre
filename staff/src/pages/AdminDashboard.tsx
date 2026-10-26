import { useEffect, useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime, fmtDateTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal } from '../components';
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

function arrivalOptions(): { value: string; label: string }[] {
  const out: { value: string; label: string }[] = [];
  for (let m = 0; m < 24 * 60; m += 5) {
    const h = Math.floor(m / 60);
    const mm = m % 60;
    const value = `${String(h).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
    out.push({ value, label: fmtTime(value) });
  }
  return out;
}

function nowHM(): string {
  const d = new Date();
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

export default function AdminDashboard() {
  const data = getInitial();
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

  const walkinDoctors = useMemo(
    () => doctors.filter((d) => !walkinForm.specialty || d.specialty === walkinForm.specialty),
    [doctors, walkinForm.specialty],
  );

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
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.totalPatients ?? 0}</div><div className="stat-label">Patients</div></div>
        <div className="stat-card"><div className="stat-number">{stats.totalDoctors ?? 0}</div><div className="stat-label">Doctors</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.pendingAppointments ?? 0}</div><div className="stat-label">Pending</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.confirmedAppointments ?? 0}</div><div className="stat-label">Confirmed</div></div>
        <div className="stat-card" style={{ borderTopColor: '#8b5cf6' }}><div className="stat-number" style={{ color: '#8b5cf6' }}>{stats.todayAppointments ?? 0}</div><div className="stat-label">Today</div></div>
        <div className="stat-card"><div className="stat-number">{stats.totalAppointments ?? 0}</div><div className="stat-label">Total</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.walkinsToday ?? 0}</div><div className="stat-label">Walk-ins Today</div></div>
      </div>

      <div className="content-card">
        <h2>Quick Actions</h2>
        <div className="qa-primary">
          <button type="button" className="qa-btn qa-btn--apt" onClick={openAppt}>
            <span className="qa-btn-icon">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="17" rx="3" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
            </span>
            <span className="qa-btn-body"><strong>New Appointment</strong><small>Book a slot at the counter</small></span>
            <span className="qa-btn-arrow"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14" /><path d="M12 5l7 7-7 7" /></svg></span>
          </button>
          <button type="button" className="qa-btn qa-btn--walkin" onClick={openWalkin}>
            <span className="qa-btn-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg></span>
            <span className="qa-btn-body"><strong>Register Walk-In</strong><small>Add a counter patient</small></span>
            <span className="qa-btn-arrow"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14" /><path d="M12 5l7 7-7 7" /></svg></span>
          </button>
        </div>
        <div className="quick-actions">
          <a href="admin_clients.php" className="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg> View Patients</a>
          <a href="admin_vitals.php" className="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" /></svg> Record Vitals</a>
          <a href="admin_laboratory.php" className="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10 2v6.3L4.5 17a2.5 2.5 0 0 0 2 4h11a2.5 2.5 0 0 0 2-4L14 8.3V2" /><path d="M8.5 2h7" /><path d="M7 15h10" /></svg> New Lab Test</a>
          <a href="admin_doctors.php" className="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><line x1="19" y1="8" x2="19" y2="14" /><line x1="22" y1="11" x2="16" y2="11" /></svg> Manage Doctors</a>
          <a href="admin_schedules.php" className="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="17" rx="3" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg> Schedules</a>
          <a href="admin_walkins.php" className="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l1.6 4.7L18 9.3l-4.4 1.6L12 15.6l-1.6-4.7L6 9.3l4.4-1.6z" /><path d="M5 20h14" /></svg> View Walk-ins</a>
          <a href="admin_reports.php" className="quick-action"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 3v18h18" /><path d="M7 15v-4" /><path d="M12 15V7" /><path d="M17 15v-6" /></svg> View Reports</a>
        </div>
      </div>

      <div className="content-card">
        <h2>Recent Appointments</h2>
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
                    <td style={{ color: '#94a3b8', fontWeight: 700 }}>#{a.id}</td>
                    <td><strong>{a.patient_name}</strong></td>
                    <td>Dr. {a.doctor_name}<br /><small style={{ color: '#64748b' }}>{a.specialty}</small></td>
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
                      <div className="apt-actions" style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 160 }}>
                        <select className="action-select" value={a.status} onChange={(e) => updateStatus(a.id, e.target.value)}>
                          {['pending', 'confirmed', 'completed', 'cancelled'].map((o) => (
                            <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>
                          ))}
                        </select>
                        <select className="action-select" value={a.payment_status} onChange={(e) => updatePayment(a.id, e.target.value)}>
                          <option value="pending">Unpaid</option>
                          <option value="paid">Paid</option>
                        </select>
                        <button type="button" className="btn-slip" title="Print appointment slip" onClick={() => printSlip(a)}>
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 6 2 18 2 18 9" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" rx="1" /></svg> Print Slip
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      <div className="content-card">
        <div className="patients-header">
          <h2 style={{ marginBottom: 0 }}>Today's Walk-ins</h2>
          <a href="admin_walkins.php" className="btn-walkin" style={{ textDecoration: 'none' }}>View All Walk-ins</a>
        </div>
        {walkinsByDoctor.length === 0 ? (
          <div className="empty-state-row" style={{ textAlign: 'center', color: '#94a3b8', padding: '1.5rem 0' }}>No walk-ins registered today yet.</div>
        ) : walkinsByDoctor.map((g) => (
          <div className="walkin-doctor-group" key={g.doctor} style={{ marginTop: 18 }}>
            <h3 style={{ margin: '0 0 8px', color: '#0f172a' }}>
              Dr. {g.doctor} {g.specialty ? <small style={{ color: '#64748b', fontWeight: 400 }}>({g.specialty})</small> : null}
            </h3>
            <div className="table-scroll">
              <table>
                <thead><tr><th>Queue</th><th>Patient</th><th>Type</th><th>Arrival</th><th>Test / Procedure</th><th>Status</th></tr></thead>
                <tbody>
                  {g.rows.map((w, i) => (
                    <tr key={w.id}>
                      <td><span className="badge" style={{ background: '#16a34a', color: '#fff', fontSize: '1rem', fontWeight: 800 }}>#{w.queue ?? i + 1}</span></td>
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
      </div>

      <Modal open={apptOpen} onClose={() => setApptOpen(false)}>
        <h2>New Appointment</h2>
        <label className="form-label">Patient *</label>
        <select className="form-dropdown form-input" value={apptForm.patient_id} onChange={(e) => setApptForm({ ...apptForm, patient_id: e.target.value })}>
          <option value="">Select patient...</option>
          {patientsList.map((p) => (
            <option key={p.id} value={p.id}>{p.name}{p.age !== null && p.age !== undefined ? ` (${p.age} yrs)` : ''}</option>
          ))}
        </select>

        <label className="form-label">Doctor *</label>
        <select className="form-dropdown form-input" value={apptForm.doctor_id} onChange={(e) => setApptForm({ ...apptForm, doctor_id: e.target.value, time: '' })}>
          <option value="">Select doctor...</option>
          {doctors.map((doc) => (
            <option key={doc.id} value={doc.id}>Dr. {doc.name} ({doc.specialty})</option>
          ))}
        </select>

        <div className="form-grid">
          <div className="form-group">
            <label>Date *</label>
            <input type="date" className="form-input" min={todayISO()} value={apptForm.date} onChange={(e) => setApptForm({ ...apptForm, date: e.target.value, time: '' })} />
          </div>
          <div className="form-group">
            <label>Time *</label>
            <select className="form-dropdown form-input" value={apptForm.time} disabled={!apptForm.doctor_id || !apptForm.date} onChange={(e) => setApptForm({ ...apptForm, time: e.target.value })}>
              <option value="">{slotLoading ? 'Loading...' : (!apptForm.doctor_id || !apptForm.date) ? 'Select doctor & date first' : slots.length === 0 ? 'No slots available' : 'Select time...'}</option>
              {slots.map((s) => <option key={s} value={s}>{fmtTime(s)}</option>)}
            </select>
          </div>
        </div>
        <p className="form-hint">Pick a doctor to see available dates &amp; times. Duration is 2 hours / visit.</p>

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
        <h2>Register Walk-In Client</h2>
        <div className="form-grid">
          <div className="form-group">
            <label>Specialty</label>
            <select className="form-dropdown form-input" value={walkinForm.specialty} onChange={(e) => setWalkinForm({ ...walkinForm, specialty: e.target.value, doctor_id: '', test_procedure: '', other: '' })}>
              <option value="">All specialties</option>
              {specialties.map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </div>
          <div className="form-group">
            <label>Doctor *</label>
            <select className="form-dropdown form-input" value={walkinForm.doctor_id} onChange={(e) => setWalkinForm({ ...walkinForm, doctor_id: e.target.value, test_procedure: '', other: '' })}>
              <option value="">Select Doctor</option>
              {walkinDoctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name} ({doc.specialty})</option>)}
            </select>
          </div>
          <div className="form-group">
            <label>Test / Procedure</label>
            <select className="form-dropdown form-input" value={walkinForm.test_procedure} disabled={!walkinForm.doctor_id} onChange={(e) => setWalkinForm({ ...walkinForm, test_procedure: e.target.value, other: e.target.value === OTHER_PROC ? walkinForm.other : '' })}>
              <option value="" disabled>{walkinForm.doctor_id ? 'Select Test / Procedure' : 'Select a doctor first'}</option>
              {doctorProcs.map((p) => <option key={p} value={p}>{p}</option>)}
            </select>
            {walkinForm.test_procedure === OTHER_PROC && (
              <input type="text" className="form-input" style={{ marginTop: '.55rem' }} placeholder="Specify test / procedure..." value={walkinForm.other} onChange={(e) => setWalkinForm({ ...walkinForm, other: e.target.value })} />
            )}
          </div>
          <div className="form-group">
            <label>Arrival Time</label>
            <select className="form-dropdown form-input" value={walkinForm.arrival_time} onChange={(e) => setWalkinForm({ ...walkinForm, arrival_time: e.target.value })}>
              {arrival.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
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
            <select className="form-dropdown form-input" value={walkinForm.gender} onChange={(e) => setWalkinForm({ ...walkinForm, gender: e.target.value })}>
              <option value="">Select Gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
            </select>
          </div>
          <div className="form-group">
            <label>Address</label>
            <input type="text" className="form-input" placeholder="e.g. 123 Sampaguita St, Manila" value={walkinForm.address} onChange={(e) => setWalkinForm({ ...walkinForm, address: e.target.value })} />
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Patient Type</label>
            <select className="form-dropdown form-input" value={walkinForm.patient_type} onChange={(e) => setWalkinForm({ ...walkinForm, patient_type: e.target.value, age: '' })}>
              <option value="">Select Type</option>
              <option value="infant">Infant</option>
              <option value="child">Child</option>
              <option value="adolescent">Adolescent</option>
              <option value="adult">Adult</option>
              <option value="elderly">Elderly</option>
            </select>
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
