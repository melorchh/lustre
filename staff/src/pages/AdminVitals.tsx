import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate } from '../api';
import type { VaccinationRow } from '../types';

export default function AdminVitals() {
  const d = getInitial().data as {
    vaccinations?: VaccinationRow[];
    stats?: Record<string, number>;
  };
  const [vaccinations] = useState<VaccinationRow[]>(d.vaccinations || []);
  const [q, setQ] = useState('');
  const stats = d.stats || {};

  const filtered = useMemo(() => vaccinations.filter((v) =>
    !q || `${v.patient_name} ${v.vaccine_name}`.toLowerCase().includes(q.toLowerCase()),
  ), [vaccinations, q]);

  const today = new Date().toISOString().slice(0, 10);

  return (
    <Shell active="vitals" title="Vaccines">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? vaccinations.length}</div><div className="stat-label">Total Records</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.thisMonth ?? 0}</div><div className="stat-label">This Month</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.dueSoon ?? 0}</div><div className="stat-label">Due Soon</div></div>
      </div>

      <div className="content-card">
        <h2>Vaccination Records</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search patient or vaccine..." value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Patient</th><th>Vaccine</th><th>Dose</th><th>Administered</th><th>Next Due</th><th>Notes</th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={7} className="empty-state-row">No vaccination records.</td></tr>
            ) : filtered.map((v) => {
              const due = v.next_due_date ? v.next_due_date.slice(0, 10) : '';
              const overdue = due && due < today;
              return (
                <tr key={v.id}>
                  <td>#{v.id}</td>
                  <td><strong>{v.patient_name}</strong></td>
                  <td>{v.vaccine_name}</td>
                  <td><span className="pill">{v.dose_label}</span></td>
                  <td>{fmtDate(v.administered_date)}</td>
                  <td>{v.next_due_date ? <span className={`badge badge-${overdue ? 'cancelled' : 'completed'}`}>{fmtDate(v.next_due_date)}</span> : '—'}</td>
                  <td>{v.notes || '—'}</td>
                </tr>
              );
            })}
          </tbody>
        </table></div>
      </div>
    </Shell>
  );
}
