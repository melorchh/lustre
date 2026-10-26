import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal } from '../components';
import type { DoctorRow, WalkinRow } from '../types';

export default function AdminWalkins() {
  const d = getInitial().data as {
    walkins?: WalkinRow[];
    doctors?: DoctorRow[];
    tests?: string[];
    stats?: Record<string, number>;
  };
  const { showToast } = useToast();
  const [walkins, setWalkins] = useState<WalkinRow[]>(d.walkins || []);
  const doctors = d.doctors || [];
  const tests = d.tests || [];
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [addOpen, setAddOpen] = useState(false);
  const [form, setForm] = useState({
    name: '', contact: '', email: '', address: '', patient_type: '', age: '', gender: '',
    weight_kg: '', height_cm: '', test_procedure: '', doctor_id: '',
  });
  const stats = d.stats || {};

  const filtered = useMemo(() => walkins.filter((w) => {
    if (q && !`${w.patient_name} ${w.contact}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status && w.status !== status) return false;
    return true;
  }), [walkins, q, status]);

  async function addWalkin() {
    if (!form.name.trim()) { showToast('Patient name required', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_walkin_action.php', { action: 'add_walkin', ...form });
      if (isSuccess(res)) { showToast('Walk-in registered', 'success'); setAddOpen(false); refresh(); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  function refresh() {
    fetch('api_admin_data.php?view=walkins', { headers: { 'X-Requested-With': 'LustreStaff' }, cache: 'no-store' })
      .then((r) => r.json())
      .then((json) => { if (json?.data) setWalkins(json.data.walkins || []); })
      .catch(() => {});
  }

  return (
    <Shell active="walkins" title="Walk-ins">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.today ?? 0}</div><div className="stat-label">Today</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.waiting ?? 0}</div><div className="stat-label">Waiting</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.in_progress ?? 0}</div><div className="stat-label">In Progress</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.completed ?? 0}</div><div className="stat-label">Completed</div></div>
      </div>

      <div className="content-card">
        <div className="card-head">
          <h2>Walk-in Queue</h2>
          <button className="btn-primary" onClick={() => setAddOpen(true)}>+ Register Walk-in</button>
        </div>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search patient..." value={q} onChange={(e) => setQ(e.target.value)} />
          <select className="apt-select" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All Statuses</option>
            <option value="waiting">Waiting</option>
            <option value="in-progress">In Progress</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>#</th><th>Patient</th><th>Type</th><th>Procedure</th><th>Doctor</th><th>Arrived</th><th>Status</th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={7} className="empty-state-row">No walk-ins.</td></tr>
            ) : filtered.map((w) => (
              <tr key={w.id}>
                <td>{w.queue ?? `#${w.id}`}</td>
                <td><strong>{w.patient_name}</strong><br /><small style={{ color: '#64748b' }}>{w.contact}</small></td>
                <td>{w.patient_type ? <span className="pill">{w.patient_type.replace(/_/g, ' ')}</span> : '—'}</td>
                <td>{w.test_procedure || '—'}</td>
                <td>{w.doctor_name ? `Dr. ${w.doctor_name}` : '—'}</td>
                <td>{w.arrival_time ? fmtTime(w.arrival_time) : fmtDate(w.arrival_date)}</td>
                <td><span className={`badge badge-${w.status === 'served' ? 'completed' : w.status === 'in_service' ? 'confirmed' : 'pending'}`}>{w.status.replace(/_/g, ' ')}</span></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>

      <Modal open={addOpen} onClose={() => setAddOpen(false)}>
        <h2>Register Walk-in</h2>
        <label className="form-label">Patient Name</label>
        <input className="form-input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        <label className="form-label">Contact</label>
        <input className="form-input" value={form.contact} onChange={(e) => setForm({ ...form, contact: e.target.value })} />
        <label className="form-label">Email</label>
        <input className="form-input" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        <label className="form-label">Address</label>
        <input className="form-input" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
        <label className="form-label">Patient Type</label>
        <select className="form-dropdown form-input" value={form.patient_type} onChange={(e) => setForm({ ...form, patient_type: e.target.value })}>
          <option value="">Select type</option>
          <option value="infant">Infant (1-2)</option>
          <option value="child">Child (3-12)</option>
          <option value="adolescent">Adolescent (13-17)</option>
          <option value="adult">Adult (18-59)</option>
          <option value="elderly">Elderly (60+)</option>
        </select>
        <label className="form-label">Age</label>
        <input className="form-input" type="number" value={form.age} onChange={(e) => setForm({ ...form, age: e.target.value })} />
        <label className="form-label">Gender</label>
        <select className="form-dropdown form-input" value={form.gender} onChange={(e) => setForm({ ...form, gender: e.target.value })}>
          <option value="">Select gender</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
        </select>
        <label className="form-label">Weight (kg)</label>
        <input className="form-input" type="number" value={form.weight_kg} onChange={(e) => setForm({ ...form, weight_kg: e.target.value })} />
        <label className="form-label">Height (cm)</label>
        <input className="form-input" type="number" value={form.height_cm} onChange={(e) => setForm({ ...form, height_cm: e.target.value })} />
        <label className="form-label">Test / Procedure</label>
        <input className="form-input" list="testList" value={form.test_procedure} onChange={(e) => setForm({ ...form, test_procedure: e.target.value })} />
        <datalist id="testList">{tests.map((t) => <option key={t} value={t} />)}</datalist>
        <label className="form-label">Assign Doctor</label>
        <select className="form-dropdown form-input" value={form.doctor_id} onChange={(e) => setForm({ ...form, doctor_id: e.target.value })}>
          <option value="">Select doctor</option>
          {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name} — {doc.specialty}</option>)}
        </select>
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setAddOpen(false)}>Cancel</button>
          <button className="btn-primary" onClick={addWalkin}>Register</button>
        </div>
      </Modal>
    </Shell>
  );
}
