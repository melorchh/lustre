import Shell from '../Shell';
import { getInitial, fmtDate, fmtTime } from '../api';
import type { AppointmentRow } from '../types';

export default function AdminDashboard() {
  const d = getInitial().data as {
    stats?: Record<string, number>;
    recentAppointments?: AppointmentRow[];
    todayAppointments?: AppointmentRow[];
    weeklyCounts?: { label: string; count: number }[];
    doctorsOnDuty?: { name: string; specialty: string; appointments: number }[];
    topServices?: { service: string; count: number }[];
    walkinStats?: Record<string, number>;
    name?: string;
  };
  const stats = d.stats || {};
  const weekly = d.weeklyCounts || [];
  const maxWeekly = Math.max(1, ...weekly.map((w) => w.count));

  return (
    <Shell active="dashboard" title="Dashboard">
      <div className="content-card welcome-card">
        <h2>Welcome back, {d.name || 'Admin'}</h2>
        <p>Here is what is happening at LustreMDC today, {fmtDate(new Date().toISOString())}.</p>
      </div>

      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.totalAppointments ?? 0}</div><div className="stat-label">Total Appointments</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.todayAppointments ?? 0}</div><div className="stat-label">Today</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.pendingAppointments ?? 0}</div><div className="stat-label">Pending</div></div>
        <div className="stat-card" style={{ borderTopColor: '#8b5cf6' }}><div className="stat-number" style={{ color: '#8b5cf6' }}>{stats.totalPatients ?? 0}</div><div className="stat-label">Patients</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.totalDoctors ?? 0}</div><div className="stat-label">Doctors</div></div>
        <div className="stat-card" style={{ borderTopColor: '#ef4444' }}><div className="stat-number" style={{ color: '#ef4444' }}>{stats.pendingLabs ?? 0}</div><div className="stat-label">Pending Labs</div></div>
      </div>

      <div className="dashboard-grid">
        <div className="content-card">
          <h2>Weekly Appointments</h2>
          <div className="chart-bars">
            {weekly.map((w, i) => (
              <div className="chart-bar-col" key={i}>
                <div className="chart-bar" style={{ height: `${(w.count / maxWeekly) * 100}%` }} title={`${w.count}`}></div>
                <div className="chart-bar-label">{w.label}</div>
              </div>
            ))}
          </div>
        </div>

        <div className="content-card">
          <h2>Doctors on Duty</h2>
          <ul className="list-plain">
            {(d.doctorsOnDuty || []).map((doc, i) => (
              <li className="list-row" key={i}>
                <div className="avatar avatar--sm">{doc.name.charAt(0)}</div>
                <div className="list-row-body"><strong>Dr. {doc.name}</strong><span>{doc.specialty}</span></div>
                <span className="pill">{doc.appointments} appts</span>
              </li>
            ))}
            {(d.doctorsOnDuty || []).length === 0 && <li className="empty-note">No doctors scheduled today.</li>}
          </ul>
        </div>
      </div>

      <div className="content-card">
        <h2>Today's Appointments</h2>
        <div className="table-scroll"><table>
          <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th></tr></thead>
          <tbody>
            {(d.todayAppointments || []).length === 0 ? (
              <tr><td colSpan={4} className="empty-state-row">No appointments today.</td></tr>
            ) : (d.todayAppointments || []).map((a) => (
              <tr key={a.id}>
                <td>{fmtTime(a.appointment_time)}</td>
                <td>{a.patient_name}</td>
                <td>Dr. {a.doctor_name}</td>
                <td><span className={`badge badge-${a.status}`}>{a.status}</span></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>

      <div className="dashboard-grid">
        <div className="content-card">
          <h2>Top Services</h2>
          <ul className="list-plain">
            {(d.topServices || []).map((s, i) => (
              <li className="list-row" key={i}><div className="list-row-body"><strong>{s.service}</strong></div><span className="pill">{s.count}</span></li>
            ))}
            {(d.topServices || []).length === 0 && <li className="empty-note">No data.</li>}
          </ul>
        </div>
        <div className="content-card">
          <h2>Walk-in Summary</h2>
          <div className="stats-grid stats-grid--tight">
            <div className="stat-card"><div className="stat-number">{d.walkinStats?.today ?? 0}</div><div className="stat-label">Today</div></div>
            <div className="stat-card"><div className="stat-number">{d.walkinStats?.pending ?? 0}</div><div className="stat-label">Waiting</div></div>
            <div className="stat-card"><div className="stat-number">{d.walkinStats?.completed ?? 0}</div><div className="stat-label">Served</div></div>
          </div>
        </div>
      </div>

      <div className="content-card">
        <h2>Recent Appointments</h2>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
            {(d.recentAppointments || []).map((a) => (
              <tr key={a.id}>
                <td>#{a.id}</td><td>{a.patient_name}</td><td>Dr. {a.doctor_name}</td>
                <td>{fmtDate(a.appointment_date)} {fmtTime(a.appointment_time)}</td>
                <td><span className={`badge badge-${a.status}`}>{a.status}</span></td>
              </tr>
            ))}
            {(d.recentAppointments || []).length === 0 && <tr><td colSpan={5} className="empty-state-row">No recent appointments.</td></tr>}
          </tbody>
        </table></div>
      </div>
    </Shell>
  );
}
