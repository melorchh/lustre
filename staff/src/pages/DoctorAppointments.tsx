import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Select } from '../components';
import { StatCard, StatGrid } from '../ui';
import type { AppointmentRow } from '../types';

export default function DoctorAppointments() {
  const d = getInitial().data as { appointments?: AppointmentRow[]; stats?: Record<string, number> };
  const { showToast } = useToast();
  const [appointments, setAppointments] = useState<AppointmentRow[]>(d.appointments || []);
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const stats = d.stats || {};

  const filtered = useMemo(() => appointments.filter((a) => {
    if (q && !`${a.patient_name} ${a.service_requested}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status && a.status !== status) return false;
    return true;
  }), [appointments, q, status]);

  async function updateStatus(id: number, newStatus: string) {
    showLoading();
    try {
      const res = await postAction('admin_update_appointment.php', { appointment_id: id, status: newStatus });
      if (isSuccess(res)) { setAppointments((p) => p.map((a) => (a.id === id ? { ...a, status: newStatus } : a))); showToast('Updated', 'success'); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  const [resultFor, setResultFor] = useState<AppointmentRow | null>(null);
  const [resultText, setResultText] = useState('');

  async function saveResult() {
    if (!resultFor) return;
    showLoading();
    try {
      const res = await postAction('doctor_result_action.php', { appointment_id: resultFor.id, result: resultText });
      if (isSuccess(res)) {
        setAppointments((p) => p.map((a) => (a.id === resultFor.id ? { ...a, result: resultText } : a)));
        showToast('Result saved', 'success');
        setResultFor(null);
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  return (
    <Shell active="appointments" title="Appointments">
      <StatGrid>
        <StatCard label="Total" value={stats.total ?? appointments.length} />
        <StatCard label="Pending" value={stats.pending ?? 0} tone="amber" />
        <StatCard label="Completed" value={stats.completed ?? 0} tone="teal" />
      </StatGrid>

      <div className="content-card">
        <h2>My Appointments</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search patient..." value={q} onChange={(e) => setQ(e.target.value)} />
          <Select className="apt-select" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </Select>
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Patient</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Result</th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={7} className="empty-state-row">No appointments.</td></tr>
            ) : filtered.map((a) => (
              <tr key={a.id}>
                <td>#{a.id}</td>
                <td><strong>{a.patient_name}</strong>{a.is_urgent ? <span className="badge badge-pending" style={{ marginLeft: 6 }}>Urgent</span> : null}</td>
                <td>{a.service_requested || '—'}</td>
                <td>{fmtDate(a.appointment_date)}</td>
                <td>{fmtTime(a.appointment_time)}</td>
                <td>
                  <Select className="action-select" value={a.status} onChange={(e) => updateStatus(a.id, e.target.value)}>
                    {['pending', 'confirmed', 'completed', 'cancelled'].map((o) => <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
                  </Select>
                </td>
                <td>
                  <button className="btn-secondary btn-xs" onClick={() => { setResultFor(a); setResultText(a.result || ''); }}>
                    {a.result ? 'Edit Result' : 'Add Result'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>

      {resultFor && (
        <div className="modal-overlay active" onClick={() => setResultFor(null)}>
          <div className="modal-box" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close-btn" onClick={() => setResultFor(null)}>&times;</button>
            <h2>Result — {resultFor.patient_name}</h2>
            <textarea className="form-input" rows={8} value={resultText} onChange={(e) => setResultText(e.target.value)} placeholder="Enter findings, diagnosis and recommendations..." />
            <div className="form-actions">
              <button className="btn-secondary" onClick={() => setResultFor(null)}>Cancel</button>
              <button className="btn-primary" onClick={saveResult}>Save Result</button>
            </div>
          </div>
        </div>
      )}
    </Shell>
  );
}
