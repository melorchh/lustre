import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate, fmtTime } from '../api';
import type { AppointmentRow, WalkinRow } from '../types';

interface ArchivedAppt extends AppointmentRow {
  archived_at?: string;
}

export default function AdminHistory() {
  const d = getInitial().data as {
    appointments?: ArchivedAppt[];
    walkins?: WalkinRow[];
    stats?: Record<string, number>;
  };
  const [tab, setTab] = useState<'appointments' | 'walkins'>('appointments');
  const [q, setQ] = useState('');
  const appointments = d.appointments || [];
  const walkins = d.walkins || [];
  const stats = d.stats || {};

  const fAppts = useMemo(() => appointments.filter((a) =>
    !q || `${a.patient_name} ${a.doctor_name}`.toLowerCase().includes(q.toLowerCase()),
  ), [appointments, q]);
  const fWalkins = useMemo(() => walkins.filter((w) =>
    !q || `${w.patient_name} ${w.test_procedure}`.toLowerCase().includes(q.toLowerCase()),
  ), [walkins, q]);

  return (
    <Shell active="history" title="Archives">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.appointments ?? appointments.length}</div><div className="stat-label">Archived Appointments</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.walkins ?? walkins.length}</div><div className="stat-label">Archived Walk-ins</div></div>
      </div>

      <div className="content-card">
        <div className="profile-tabs">
          <button className={`ptab${tab === 'appointments' ? ' active' : ''}`} onClick={() => setTab('appointments')}>Appointments</button>
          <button className={`ptab${tab === 'walkins' ? ' active' : ''}`} onClick={() => setTab('walkins')}>Walk-ins</button>
        </div>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search..." value={q} onChange={(e) => setQ(e.target.value)} />
        </div>

        {tab === 'appointments' ? (
          <div className="table-scroll"><table>
            <thead><tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Service</th><th>Date</th><th>Status</th><th>Archived</th></tr></thead>
            <tbody>
              {fAppts.length === 0 ? (
                <tr><td colSpan={7} className="empty-state-row">No archived appointments.</td></tr>
              ) : fAppts.map((a) => (
                <tr key={a.id}>
                  <td>#{a.id}</td>
                  <td>{a.patient_name}</td>
                  <td>Dr. {a.doctor_name}</td>
                  <td>{a.service_requested || '—'}</td>
                  <td>{fmtDate(a.appointment_date)} {fmtTime(a.appointment_time)}</td>
                  <td><span className={`badge badge-${a.status}`}>{a.status}</span></td>
                  <td>{fmtDate(a.archived_at || a.updated_at)}</td>
                </tr>
              ))}
            </tbody>
          </table></div>
        ) : (
          <div className="table-scroll"><table>
            <thead><tr><th>ID</th><th>Patient</th><th>Procedure</th><th>Doctor</th><th>Arrived</th><th>Status</th></tr></thead>
            <tbody>
              {fWalkins.length === 0 ? (
                <tr><td colSpan={6} className="empty-state-row">No archived walk-ins.</td></tr>
              ) : fWalkins.map((w) => (
                <tr key={w.id}>
                  <td>#{w.id}</td>
                  <td>{w.patient_name}</td>
                  <td>{w.test_procedure || '—'}</td>
                  <td>{w.doctor_name ? `Dr. ${w.doctor_name}` : '—'}</td>
                  <td>{fmtDate(w.arrival_date)} {fmtTime(w.arrival_time)}</td>
                  <td><span className={`badge badge-${w.status === 'completed' ? 'completed' : 'pending'}`}>{w.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table></div>
        )}
      </div>
    </Shell>
  );
}
