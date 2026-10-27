import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate } from '../api';
import { StatCard, StatGrid } from '../ui';
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
      <StatGrid>
        <StatCard label="My Patients" value={stats.total ?? patients.length} />
        <StatCard label="Active" value={stats.active ?? 0} tone="teal" />
      </StatGrid>

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
                <td><strong>{p.name}</strong><br /><small>{p.unique_code}</small></td>
                <td>{p.email}<br /><small>{p.contact}</small></td>
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
