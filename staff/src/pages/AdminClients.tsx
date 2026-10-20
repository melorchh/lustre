import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate } from '../api';
import type { PatientRow } from '../types';

export default function AdminClients() {
  const d = getInitial().data as { patients?: PatientRow[]; stats?: Record<string, number> };
  const [patients] = useState<PatientRow[]>(d.patients || []);
  const [q, setQ] = useState('');
  const [type, setType] = useState('');
  const stats = d.stats || {};

  const filtered = useMemo(() => patients.filter((p) => {
    if (q && !(`${p.name} ${p.email} ${p.unique_code}`.toLowerCase().includes(q.toLowerCase()))) return false;
    if (type && (p.patient_type || '') !== type) return false;
    return true;
  }), [patients, q, type]);

  return (
    <Shell active="clients" title="Patients">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? patients.length}</div><div className="stat-label">Total Patients</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.active ?? 0}</div><div className="stat-label">Recently Active</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.walkins ?? 0}</div><div className="stat-label">Walk-ins</div></div>
      </div>

      <div className="content-card">
        <h2>Patient Directory</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search name, email or ID..." value={q} onChange={(e) => setQ(e.target.value)} />
          <select className="apt-select" value={type} onChange={(e) => setType(e.target.value)}>
            <option value="">All Types</option>
            <option value="regular">Regular</option>
            <option value="pregnant">Pregnant</option>
            <option value="walk_in">Walk-in</option>
            <option value="senior">Senior</option>
          </select>
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Type</th><th>Age</th><th>Appointments</th><th>Last Visit</th><th></th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={8} className="empty-state-row">No patients found.</td></tr>
            ) : filtered.map((p) => (
              <tr key={p.id}>
                <td>#{p.id}</td>
                <td><strong>{p.name}</strong><br /><small style={{ color: '#64748b' }}>{p.unique_code}</small></td>
                <td>{p.email}<br /><small style={{ color: '#64748b' }}>{p.contact}</small></td>
                <td>{p.patient_type ? <span className="pill">{p.patient_type.replace(/_/g, ' ')}</span> : '—'}</td>
                <td>{p.age ?? '—'}</td>
                <td>{p.appointment_count ?? 0}</td>
                <td>{fmtDate(p.last_appointment)}</td>
                <td><a className="btn-secondary btn-xs" href={`client_profile.php?id=${p.id}`}>View</a></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>
    </Shell>
  );
}
