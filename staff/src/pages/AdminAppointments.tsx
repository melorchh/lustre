import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime, fmtDateTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import type { AppointmentRow } from '../types';

interface HistoryEntry {
  appointment_id: number;
  old_date: string;
  old_time: string;
  new_date: string;
  new_time: string;
  rescheduled_at: string;
}

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

  const stats = (data.data.stats as Record<string, number>) || {};

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
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? 0}</div><div className="stat-label">Total</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.pending ?? 0}</div><div className="stat-label">Pending</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.confirmed ?? 0}</div><div className="stat-label">Confirmed</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.completed ?? 0}</div><div className="stat-label">Completed</div></div>
        <div className="stat-card" style={{ borderTopColor: '#8b5cf6' }}><div className="stat-number" style={{ color: '#8b5cf6' }}>{stats.today ?? 0}</div><div className="stat-label">Today</div></div>
      </div>

      <div className="content-card">
        <h2>All Appointments</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search patient name..." value={qName} onChange={(e) => setQName(e.target.value)} />
          <input type="search" className="apt-search" placeholder="Search address..." value={qAddr} onChange={(e) => setQAddr(e.target.value)} />
          <input type="search" className="apt-search apt-search--age" placeholder="Age..." value={qAge} onChange={(e) => setQAge(e.target.value)} />
          <select className="apt-select filter-select" value={qStatus} onChange={(e) => setQStatus(e.target.value)}>
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <select className="apt-select filter-select" value={qPriority} onChange={(e) => setQPriority(e.target.value)}>
            <option value="">All Priorities</option>
            <option value="urgent">Urgent</option>
            <option value="normal">Normal</option>
          </select>
        </div>

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
                <tr><td colSpan={10} className="empty-state-row"><div className="empty-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="17" rx="3" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg></div>No appointments found.</td></tr>
              ) : filtered.map((a) => {
                const hist = historyByAppt[a.id] || [];
                const hasResult = !!(a.result && a.result.length);
                return (
                  <tr key={a.id}>
                    <td>#{a.id}</td>
                    <td><strong>{a.patient_name}</strong><br /><small style={{ color: '#64748b' }}>{a.patient_email}</small></td>
                    <td>Dr. {a.doctor_name}<br /><small style={{ color: '#64748b' }}>{a.specialty}</small></td>
                    <td>{a.service_requested || '—'}</td>
                    <td>{fmtDate(a.appointment_date)}</td>
                    <td>{fmtTime(a.appointment_time)}</td>
                    <td>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                        <select className="action-select" value={a.status} onChange={(e) => updateStatus(a.id, e.target.value)}>
                          {['pending', 'confirmed', 'completed', 'cancelled'].map((o) => <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
                        </select>
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
                      <select className="action-select" value={a.payment_status} onChange={(e) => updatePayment(a.id, e.target.value)}>
                        <option value="pending">Unpaid</option>
                        <option value="paid">Paid</option>
                      </select>
                    </td>
                    <td>
                      {hasResult ? (
                        a.status === 'pending' || a.status === 'confirmed'
                          ? <span className="badge" style={{ background: '#fef3c7', color: '#92400e', cursor: 'pointer' }} onClick={() => setResultView(a)}>Draft</span>
                          : <span className="badge badge-completed" style={{ cursor: 'pointer' }} onClick={() => setResultView(a)}>Ready</span>
                      ) : <span style={{ color: '#94a3b8', fontSize: '.82rem' }}>No result</span>}
                    </td>
                    <td>
                      {fmtDate(a.updated_at)}<br /><small style={{ color: '#94a3b8' }}>{fmtTime(a.updated_at)}</small>
                      <div style={{ marginTop: 6, display: 'flex', flexDirection: 'column', gap: 6 }}>
                        <button type="button" className="btn-slip" title="Print appointment slip" onClick={() => printSlip(a)}>
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 6 2 18 2 18 9" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" rx="1" /></svg> Slip
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

      {resultView && (
        <div className="modal-overlay active" onClick={() => setResultView(null)}>
          <div className="modal-box" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close-btn" onClick={() => setResultView(null)}>&times;</button>
            <h2>Appointment Result</h2>
            <div style={{ margin: '-.35rem 0 1rem', fontSize: '.92rem', color: 'var(--gray-600)' }}>
              <strong>{resultView.patient_name}</strong> · {resultView.unique_code} ·{' '}
              <span className={`badge ${resultView.status === 'completed' ? 'badge-completed' : 'badge-pending'}`}>
                {resultView.status === 'completed' ? 'Completed' : 'Draft'}
              </span>
            </div>
            <div className="table-scroll"><table><tbody>
              <tr><th style={{ width: 140 }}>Doctor</th><td>Dr. {resultView.doctor_name} ({resultView.specialty})</td></tr>
              <tr><th>Service</th><td>{resultView.service_requested || 'General consultation'}</td></tr>
            </tbody></table></div>
            <div className="result-view-body" style={{ marginTop: '1rem', whiteSpace: 'pre-wrap' }}>{resultView.result}</div>
            <div className="form-actions" style={{ marginTop: '1.1rem' }}>
              <button type="button" className="btn-secondary" onClick={() => setResultView(null)}>Close</button>
            </div>
          </div>
        </div>
      )}
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
