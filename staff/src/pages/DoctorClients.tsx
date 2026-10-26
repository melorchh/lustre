import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate } from '../api';
import type { PatientRow } from '../types';

export default function DoctorClients() {
  const d = getInitial().data as { patients?: PatientRow[]; stats?: Record<string, number> };
  const [patients] = useState<PatientRow[]>(d.patients || []);
  const [q, setQ] = useState('');
  const stats = d.stats || {};

  const filtered = useMemo(() => patients.filter((p) =>
    !q || `${p.name} ${p.email} ${p.unique_code}`.toLowerCase().includes(q.toLowerCase()),
  ), [patients, q]);

  return (
    <Shell active="clients" title="Patients">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? patients.length}</div><div className="stat-label">My Patients</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.active ?? 0}</div><div className="stat-label">Active</div></div>
      </div>

      <div className="content-card">
        <h2>Patient List</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search patient..." value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Age</th><th>Visits</th><th>Last Visit</th><th></th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={7} className="empty-state-row">No patients found.</td></tr>
            ) : filtered.map((p) => (
              <tr key={p.id}>
                <td>#{p.id}</td>
                <td><strong>{p.name}</strong><br /><small style={{ color: '#64748b' }}>{p.unique_code}</small></td>
                <td>{p.email}<br /><small style={{ color: '#64748b' }}>{p.contact}</small></td>
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
