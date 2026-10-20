import Shell from '../Shell';
import { getInitial, fmtDate, fmtTime } from '../api';
import type { AppointmentRow, LabTestRow } from '../types';

export default function DoctorDashboard() {
  const d = getInitial().data as {
    stats?: Record<string, number>;
    todayAppointments?: AppointmentRow[];
    upcomingAppointments?: AppointmentRow[];
    recentLabs?: LabTestRow[];
    scheduleToday?: { start_time: string; end_time: string }[];
  };
  const stats = d.stats || {};

  return (
    <Shell active="dashboard" title="Dashboard">
      <div className="content-card welcome-card">
        <h2>Welcome, Dr. {getInitial().staff.name}</h2>
        <p>Your schedule and tasks for {fmtDate(new Date().toISOString())}.</p>
      </div>

      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.todayAppointments ?? 0}</div><div className="stat-label">Today's Appointments</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.pending ?? 0}</div><div className="stat-label">Pending</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.completed ?? 0}</div><div className="stat-label">Completed</div></div>
        <div className="stat-card" style={{ borderTopColor: '#8b5cf6' }}><div className="stat-number" style={{ color: '#8b5cf6' }}>{stats.pendingLabs ?? 0}</div><div className="stat-label">Pending Labs</div></div>
      </div>

      <div className="dashboard-grid">
        <div className="content-card">
          <h2>Today's Schedule</h2>
          {(d.scheduleToday || []).length === 0 ? (
            <p className="empty-note">No schedule blocks today.</p>
          ) : (
            <ul className="list-plain">
              {(d.scheduleToday || []).map((s, i) => (
                <li className="list-row" key={i}><div className="list-row-body"><strong>{fmtTime(s.start_time)} – {fmtTime(s.end_time)}</strong></div></li>
              ))}
            </ul>
          )}
        </div>

        <div className="content-card">
          <h2>Today's Appointments</h2>
          <ul className="list-plain">
            {(d.todayAppointments || []).length === 0 ? (
              <li className="empty-note">No appointments today.</li>
            ) : (d.todayAppointments || []).map((a) => (
              <li className="list-row" key={a.id}>
                <div className="list-row-body"><strong>{a.patient_name}</strong><span>{fmtTime(a.appointment_time)} · {a.service_requested || 'Consultation'}</span></div>
                <span className={`badge badge-${a.status}`}>{a.status}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="dashboard-grid">
        <div className="content-card">
          <h2>Upcoming Appointments</h2>
          <div className="table-scroll"><table>
            <thead><tr><th>Date</th><th>Patient</th><th>Service</th><th>Status</th></tr></thead>
            <tbody>
              {(d.upcomingAppointments || []).length === 0 ? (
                <tr><td colSpan={4} className="empty-state-row">Nothing upcoming.</td></tr>
              ) : (d.upcomingAppointments || []).map((a) => (
                <tr key={a.id}>
                  <td>{fmtDate(a.appointment_date)} {fmtTime(a.appointment_time)}</td>
                  <td>{a.patient_name}</td>
                  <td>{a.service_requested || '—'}</td>
                  <td><span className={`badge badge-${a.status}`}>{a.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table></div>
        </div>

        <div className="content-card">
          <h2>Recent Lab Tests</h2>
          <ul className="list-plain">
            {(d.recentLabs || []).length === 0 ? (
              <li className="empty-note">No recent labs.</li>
            ) : (d.recentLabs || []).map((l) => (
              <li className="list-row" key={l.id}>
                <div className="list-row-body"><strong>{l.patient_name}</strong><span>{l.test_type}</span></div>
                <span className={`badge badge-${l.status === 'completed' ? 'completed' : 'pending'}`}>{l.status}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </Shell>
  );
}
