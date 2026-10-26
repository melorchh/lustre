import { useEffect, useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime, fmtDateTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal, Select } from '../components';
import { Card, EmptyState, Field, FilterBar, ModalHead, Stack, StatCard, StatGrid } from '../ui';
import {
  IconAppointments, IconCheck, IconClock, IconPatients, IconPrint, IconSearch, IconVitals,
} from '../icons';
import type { AppointmentRow, VitalRow } from '../types';

interface HistoryEntry {
  appointment_id: number;
  old_date: string;
  old_time: string;
  new_date: string;
  new_time: string;
  rescheduled_at: string;
}

const EMPTY_VITALS = {
  visit_date: '',
  weight_kg: '',
  height_cm: '',
  blood_pressure: '',
  heart_rate: '',
  fundal_height: '',
  notes: '',
};

export default function AdminAppointments() {
  const data = getInitial();
  const { showToast } = useToast();
  const [appointments, setAppointments] = useState<AppointmentRow[]>((data.data.appointments as AppointmentRow[]) || []);
  const [history] = useState<HistoryEntry[]>((data.data.rescheduleHistory as HistoryEntry[]) || []);

  const [qName, setQName] = useState('');
  const [qAddr, setQAddr] = useState('');
  const [qAge, setQAge] = useState('');
  const [qStatus, setQStatus] = useState('');
  const [qPriority, setQPriority] = useState('');
  const [resultView, setResultView] = useState<AppointmentRow | null>(null);
  const [vitalsFor, setVitalsFor] = useState<AppointmentRow | null>(null);
  const [vitalsForm, setVitalsForm] = useState({ ...EMPTY_VITALS });
  const [vitalsTouched, setVitalsTouched] = useState(false);

  const stats = (data.data.stats as Record<string, number>) || {};

  // Prefill existing vitals for the selected appointment unless the user
  // already started typing.
  useEffect(() => {
    if (!vitalsFor) return;
    let alive = true;
    fetch(`client_vitals_api.php?appointment_id=${vitalsFor.id}`, {
      headers: { 'X-Requested-With': 'LustreStaff' },
      cache: 'no-store',
    })
      .then((r) => r.json())
      .then((json: { ok?: boolean; vitals?: VitalRow[] }) => {
        if (!alive || !json?.ok || !json.vitals?.length) return;
        if (vitalsTouched) return;
        const v = json.vitals[0];
        setVitalsForm({
          visit_date: String(v.visit_date || '').slice(0, 10),
          weight_kg: v.weight_kg != null ? String(v.weight_kg) : '',
          height_cm: v.height_cm != null ? String(v.height_cm) : '',
          blood_pressure: v.blood_pressure || '',
          heart_rate: v.heart_rate != null ? String(v.heart_rate) : '',
          fundal_height: v.fundal_height != null ? String(v.fundal_height) : '',
          notes: v.notes || '',
        });
      })
      .catch(() => {});
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vitalsFor?.id]);

  function openVitals(a: AppointmentRow) {
    setVitalsTouched(false);
    setVitalsForm({
      ...EMPTY_VITALS,
      visit_date: String(a.appointment_date || '').slice(0, 10),
    });
    setVitalsFor(a);
  }

  async function saveVitals() {
    if (!vitalsFor) return;
    if (!vitalsForm.visit_date) { showToast('Visit date is required', 'error'); return; }
    const hasMeasurement = ['weight_kg', 'height_cm', 'blood_pressure', 'heart_rate', 'fundal_height']
      .some((k) => String((vitalsForm as Record<string, string>)[k] || '').trim() !== '');
    if (!hasMeasurement) { showToast('Enter at least one vital sign measurement', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_vitals_action.php', {
        action: 'save_appointment_vitals',
        patient_id: vitalsFor.patient_id,
        appointment_id: vitalsFor.id,
        ...vitalsForm,
      });
      if (isSuccess(res)) {
        const id = vitalsFor.id;
        setAppointments((prev) => prev.map((a) => (a.id === id ? { ...a, vital_count: Math.max(1, a.vital_count || 0) } : a)));
        showToast('Vitals saved!', 'success');
        setVitalsFor(null);
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  const historyByAppt = useMemo(() => {
    const map: Record<number, HistoryEntry[]> = {};
    for (const h of history) {
      (map[h.appointment_id] ||= []).push(h);
    }
    return map;
  }, [history]);

  const filtered = appointments.filter((a) => {
    const name = (a.patient_name || '').toLowerCase();
    const addr = (a.patient_address || '').toLowerCase();
    const age = String(a.patient_age ?? '');
    const prio = a.is_urgent ? 'urgent' : 'normal';
    if (qName && !name.includes(qName.toLowerCase())) return false;
    if (qAddr && !addr.includes(qAddr.toLowerCase())) return false;
    if (qAge && !age.includes(qAge)) return false;
    if (qStatus && a.status !== qStatus) return false;
    if (qPriority && prio !== qPriority) return false;
    return true;
  });

  const hasFilters = Boolean(qName || qAddr || qAge || qStatus || qPriority);
  const activeCount = appointments.filter((a) => a.status === 'pending' || a.status === 'confirmed').length;

  function resetFilters() {
    setQName('');
    setQAddr('');
    setQAge('');
    setQStatus('');
    setQPriority('');
  }

  async function updateStatus(id: number, status: string) {
    showLoading();
    try {
      const res = await postAction('admin_update_appointment.php', { appointment_id: id, status });
      if (isSuccess(res)) {
        setAppointments((prev) => prev.map((a) => (a.id === id ? { ...a, status } : a)));
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
        setAppointments((prev) => prev.map((a) => (a.id === id ? { ...a, payment_status } : a)));
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
      ['Service', a.service_requested || '—'],
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
    <Shell active="appointments" title="Appointments">
      <Stack>
        <StatGrid>
          <StatCard icon={<IconAppointments />} label="Total appointments" value={stats.total ?? appointments.length} hint="All records" />
          <StatCard icon={<IconClock />} label="Pending" value={stats.pending ?? 0} hint="Awaiting confirmation" tone="amber" />
          <StatCard icon={<IconCheck />} label="Confirmed" value={stats.confirmed ?? 0} hint="Ready to serve" tone="blue" />
          <StatCard icon={<IconPatients />} label="Completed" value={stats.completed ?? 0} hint="Visits finished" tone="slate" />
          <StatCard icon={<IconAppointments />} label="Today" value={stats.today ?? 0} hint="Scheduled today" tone="violet" />
        </StatGrid>

        <Card
          title="All appointments"
          subtitle={`${filtered.length} of ${appointments.length} record${appointments.length === 1 ? '' : 's'}${activeCount ? ` · ${activeCount} active` : ''}`}
          actions={hasFilters ? <button type="button" className="ap-btn-text" onClick={resetFilters}>Reset filters</button> : undefined}
        >
          <FilterBar>
            <Field label="Patient" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Search patient name..." value={qName} onChange={(e) => setQName(e.target.value)} />
              </div>
            </Field>
            <Field label="Address" className="ap-field--grow">
              <input type="search" className="apt-search" placeholder="Search address..." value={qAddr} onChange={(e) => setQAddr(e.target.value)} />
            </Field>
            <Field label="Age" className="ap-field--xs">
              <input type="search" className="apt-search" placeholder="Any" value={qAge} onChange={(e) => setQAge(e.target.value)} />
            </Field>
            <Field label="Status">
              <Select className="apt-select filter-select" value={qStatus} onChange={(e) => setQStatus(e.target.value)}>
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </Select>
            </Field>
            <Field label="Priority">
              <Select className="apt-select filter-select" value={qPriority} onChange={(e) => setQPriority(e.target.value)}>
                <option value="">All priorities</option>
                <option value="urgent">Urgent</option>
                <option value="normal">Normal</option>
              </Select>
            </Field>
          </FilterBar>

          <div className="table-scroll">
            <table id="aptTable">
            <thead>
              <tr>
                <th>ID</th><th>Patient</th><th>Doctor</th><th>Service / Test</th><th>Date</th>
                <th>Time</th><th>Status</th><th>Payment</th><th>Result</th><th>Updated</th>
              </tr>
            </thead>
            <tbody>
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={10}>
                    <EmptyState
                      icon={<IconAppointments size={20} />}
                      title="No matching appointments"
                      hint={hasFilters ? 'Try a different name, status or priority.' : 'Bookings will appear here once patients reserve a slot.'}
                    />
                  </td>
                </tr>
              ) : filtered.map((a) => {
                const hist = historyByAppt[a.id] || [];
                const hasResult = !!(a.result && a.result.length);
                return (
                  <tr key={a.id}>
                    <td className="ap-id">#{a.id}</td>
                    <td>
                      <span className="ap-strong">{a.patient_name}</span>
                      <small className="ap-sub">{a.patient_email || 'No email on file'}</small>
                    </td>
                    <td>
                      <span>Dr. {a.doctor_name}</span>
                      <small className="ap-sub">{a.specialty}</small>
                    </td>
                    <td>
                      {a.service_requested || '—'}
                      {a.is_urgent ? <span className="badge badge-urgent ap-inline-badge">Urgent</span> : null}
                    </td>
                    <td>{fmtDate(a.appointment_date)}</td>
                    <td>{fmtTime(a.appointment_time)}</td>
                    <td>
                      <div className="ap-row-actions ap-row-actions--status">
                        <Select className="action-select" value={a.status} onChange={(e) => updateStatus(a.id, e.target.value)}>
                          {['pending', 'confirmed', 'completed', 'cancelled'].map((o) => <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
                        </Select>
                        {(a.reschedule_count || hist.length > 0) ? (
                          <span className="resched-tip">
                            <span className="resched-badge">&#10227; Rescheduled{(a.reschedule_count || 0) > 1 ? ` x${a.reschedule_count}` : ''}</span>
                            <span className="resched-pop">
                              <span className="resched-pop-title">Reschedule History</span>
                              {hist.map((h, i) => (
                                <span key={i} className="resched-pop-item">
                                  <span className="resched-change">
                                    <span className="resched-old">{fmtDate(h.old_date)} · {fmtTime(h.old_time)}<span className="resched-dur">{fmtDuration(a.appointment_duration)}</span></span>
                                    &rarr;
                                    <span className="resched-new">{fmtDate(h.new_date)} {fmtTime(h.new_time)}</span>
                                  </span>
                                  <span className="resched-pop-time">rescheduled on {fmtDateTime(h.rescheduled_at)}</span>
                                </span>
                              ))}
                            </span>
                          </span>
                        ) : null}
                      </div>
                    </td>
                    <td>
                      <Select className="action-select" value={a.payment_status} onChange={(e) => updatePayment(a.id, e.target.value)}>
                        <option value="pending">Unpaid</option>
                        <option value="paid">Paid</option>
                      </Select>
                    </td>
                    <td>
                      {hasResult ? (
                        <button
                          type="button"
                          className={`badge ${a.status === 'pending' || a.status === 'confirmed' ? 'badge-pending' : 'badge-completed'} ap-badge-btn`}
                          onClick={() => setResultView(a)}
                          title="Open the recorded result"
                        >
                          {a.status === 'pending' || a.status === 'confirmed' ? 'Draft' : 'Ready'}
                        </button>
                      ) : <span className="ap-none">No result</span>}
                    </td>
                    <td>
                      <span className="ap-updated">
                        {fmtDate(a.updated_at)}
                        <small>{fmtTime(a.updated_at)}</small>
                      </span>
                      <div className="ap-row-actions ap-row-actions--inline ap-updated-actions">
                        <button type="button" className="btn-slip" title="Print appointment slip" onClick={() => printSlip(a)}>
                          <IconPrint size={16} /> Slip
                        </button>
                        <button
                          type="button"
                          className="btn-slip"
                          title={(a.vital_count || 0) > 0 ? 'Edit recorded vitals' : 'Record vitals for this appointment'}
                          onClick={() => openVitals(a)}
                        >
                          <IconVitals size={16} /> Vitals
                          {(a.vital_count || 0) > 0 && <span className="vitals-check">&#10003;</span>}
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
      </Stack>

      <Modal open={!!resultView} onClose={() => setResultView(null)}>
        {resultView && (
          <>
            <ModalHead
              title="Appointment result"
              hint={`${resultView.patient_name} · ${resultView.unique_code || `#${resultView.id}`}`}
            />
            <div className="ap-result-meta">
              <span className={`badge ${resultView.status === 'completed' ? 'badge-completed' : 'badge-pending'}`}>
                {resultView.status === 'completed' ? 'Completed' : 'Draft'}
              </span>
              <span className="ap-none">Recorded by Dr. {resultView.doctor_name}</span>
            </div>
            <table className="ap-kv"><tbody>
              <tr><th>Doctor</th><td>Dr. {resultView.doctor_name}</td></tr>
              <tr><th>Specialty</th><td>{resultView.specialty || '—'}</td></tr>
              <tr><th>Service</th><td>{resultView.service_requested || 'General consultation'}</td></tr>
            </tbody></table>
            <div className="ap-result-body">{resultView.result}</div>
            <div className="form-actions">
              <button type="button" className="btn-secondary" onClick={() => setResultView(null)}>Close</button>
            </div>
          </>
        )}
      </Modal>

      <Modal open={!!vitalsFor} onClose={() => setVitalsFor(null)}>
        <ModalHead
          title="Record vitals"
          hint={vitalsFor ? `${vitalsFor.patient_name} · ${vitalsFor.unique_code || `#${vitalsFor.id}`} · ${fmtDate(vitalsFor.appointment_date)}` : undefined}
        />
        <div
          onChange={() => setVitalsTouched(true)}
          onInput={() => setVitalsTouched(true)}
        >
          <div className="form-grid">
            <div className="form-group">
              <label>Visit Date *</label>
              <input type="date" className="form-input" required value={vitalsForm.visit_date} onChange={(e) => setVitalsForm({ ...vitalsForm, visit_date: e.target.value })} />
            </div>
            <div className="form-group">
              <label>Weight (kg)</label>
              <input type="number" className="form-input" step="0.1" min="0" placeholder="e.g. 58.5" value={vitalsForm.weight_kg} onChange={(e) => setVitalsForm({ ...vitalsForm, weight_kg: e.target.value })} />
            </div>
          </div>
          <div className="form-grid">
            <div className="form-group">
              <label>Height (cm)</label>
              <input type="number" className="form-input" step="0.1" min="0" placeholder="e.g. 160.0" value={vitalsForm.height_cm} onChange={(e) => setVitalsForm({ ...vitalsForm, height_cm: e.target.value })} />
            </div>
            <div className="form-group">
              <label>Blood Pressure</label>
              <input type="text" className="form-input" placeholder="e.g. 110/70" value={vitalsForm.blood_pressure} onChange={(e) => setVitalsForm({ ...vitalsForm, blood_pressure: e.target.value })} />
            </div>
          </div>
          <div className="form-grid">
            <div className="form-group">
              <label>Heart Rate (bpm)</label>
              <input type="number" className="form-input" min="0" max="250" placeholder="e.g. 78" value={vitalsForm.heart_rate} onChange={(e) => setVitalsForm({ ...vitalsForm, heart_rate: e.target.value })} />
            </div>
            <div className="form-group">
              <label>Fundal Height (cm)</label>
              <input type="number" className="form-input" step="0.1" min="0" max="50" placeholder="e.g. 24.0" value={vitalsForm.fundal_height} onChange={(e) => setVitalsForm({ ...vitalsForm, fundal_height: e.target.value })} />
            </div>
          </div>
          <div className="form-group">
            <label>Notes</label>
            <textarea className="form-input" rows={2} style={{ minHeight: 74 }} placeholder="Optional notes" value={vitalsForm.notes} onChange={(e) => setVitalsForm({ ...vitalsForm, notes: e.target.value })} />
          </div>
        </div>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setVitalsFor(null)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={saveVitals}>Save Vitals</button>
        </div>
      </Modal>
    </Shell>
  );
}

function escapeHtml(s: string): string {
  return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string));
}

function fmtDuration(min?: number | null): string {
  const d = Number(min || 0);
  if (d >= 60) return `${Math.floor(d / 60)}h${d % 60 ? ' ' + (d % 60) + 'm' : ''}`;
  return `${d}m`;
}

// Kept for potential future reload usage.
void fetchData;
