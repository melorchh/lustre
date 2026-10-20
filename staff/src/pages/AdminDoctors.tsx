import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal } from '../components';
import type { DoctorRow } from '../types';

interface DoctorAccount {
  id: number;
  doctor_id: number;
  username: string;
  created_at?: string;
}

export default function AdminDoctors() {
  const d = getInitial().data as {
    doctors?: DoctorRow[];
    accounts?: DoctorAccount[];
    specialties?: string[];
    stats?: Record<string, number>;
  };
  const { showToast } = useToast();
  const [doctors, setDoctors] = useState<DoctorRow[]>(d.doctors || []);
  const [accounts, setAccounts] = useState<DoctorAccount[]>(d.accounts || []);
  const [specialties] = useState<string[]>(d.specialties || []);
  const [q, setQ] = useState('');
  const [addOpen, setAddOpen] = useState(false);
  const [form, setForm] = useState({ name: '', specialty: '', schedule: '', experience: '', test_procedures: '' });
  const [acctFor, setAcctFor] = useState<DoctorRow | null>(null);
  const [acctForm, setAcctForm] = useState({ username: '', password: '' });
  const stats = d.stats || {};

  const accountsByDoctor = useMemo(() => {
    const m: Record<number, DoctorAccount> = {};
    for (const a of accounts) m[a.doctor_id] = a;
    return m;
  }, [accounts]);

  const filtered = doctors.filter((doc) => {
    const hay = `${doc.name} ${doc.specialty}`.toLowerCase();
    return !q || hay.includes(q.toLowerCase());
  });

  async function addDoctor() {
    if (!form.name.trim() || !form.specialty.trim()) { showToast('Name and specialty required', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', { action: 'add', ...form });
      if (isSuccess(res)) {
        showToast('Doctor added', 'success');
        setAddOpen(false);
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function removeDoctor(id: number) {
    if (!confirm('Remove this doctor? This cannot be undone.')) return;
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', { action: 'delete', doctor_id: id });
      if (isSuccess(res)) { setDoctors((p) => p.filter((x) => x.id !== id)); showToast('Doctor removed', 'success'); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function saveAccount() {
    if (!acctFor || !acctForm.username.trim() || !acctForm.password.trim()) { showToast('Username and password required', 'error'); return; }
    showLoading();
    try {
      const existing = accountsByDoctor[acctFor.id];
      const res = existing
        ? await postAction('admin_doctor_actions.php', { action: 'reset_account', account_id: existing.id, password: acctForm.password })
        : await postAction('admin_doctor_actions.php', { action: 'add_account', doctor_id: acctFor.id, username: acctForm.username, password: acctForm.password });
      if (isSuccess(res)) {
        showToast('Account saved', 'success');
        setAcctForm({ username: '', password: '' });
        setAcctFor(null);
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  function refresh() {
    fetch('api_admin_data.php?view=doctors', { headers: { 'X-Requested-With': 'LustreStaff' }, cache: 'no-store' })
      .then((r) => r.json())
      .then((json) => {
        if (json?.data) {
          setDoctors(json.data.doctors || []);
          setAccounts(json.data.accounts || []);
        }
      })
      .catch(() => {});
  }

  return (
    <Shell active="doctors" title="Doctors">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? doctors.length}</div><div className="stat-label">Total Doctors</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.withAccounts ?? accounts.length}</div><div className="stat-label">With Accounts</div></div>
      </div>

      <div className="content-card">
        <div className="card-head">
          <h2>Doctor Directory</h2>
          <button className="btn-primary" onClick={() => setAddOpen(true)}>+ Add Doctor</button>
        </div>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search name or specialty..." value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Name</th><th>Specialty</th><th>Schedule</th><th>Experience</th><th>Account</th><th>Actions</th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={7} className="empty-state-row">No doctors found.</td></tr>
            ) : filtered.map((doc) => {
              const acct = accountsByDoctor[doc.id];
              return (
                <tr key={doc.id}>
                  <td>#{doc.id}</td>
                  <td><strong>Dr. {doc.name}</strong></td>
                  <td>{doc.specialty}</td>
                  <td>{doc.schedule || '—'}</td>
                  <td>{doc.experience ? `${doc.experience} yrs` : '—'}</td>
                  <td>{acct ? <span className="badge badge-completed">{acct.username}</span> : <span className="badge badge-pending">None</span>}</td>
                  <td>
                    <div className="row-actions">
                      <button className="btn-secondary btn-xs" onClick={() => { setAcctFor(doc); setAcctForm({ username: acct?.username || '', password: '' }); }}>{acct ? 'Reset' : 'Create'} Login</button>
                      <button className="btn-danger btn-xs" onClick={() => removeDoctor(doc.id)}>Delete</button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table></div>
      </div>

      <Modal open={addOpen} onClose={() => setAddOpen(false)}>
        <h2>Add Doctor</h2>
        <label className="form-label">Name</label>
        <input className="form-input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        <label className="form-label">Specialty</label>
        <input className="form-input" list="specialtyList" value={form.specialty} onChange={(e) => setForm({ ...form, specialty: e.target.value })} />
        <datalist id="specialtyList">{specialties.map((s) => <option key={s} value={s} />)}</datalist>
        <label className="form-label">Schedule</label>
        <input className="form-input" placeholder="Mon-Fri 9AM-5PM" value={form.schedule} onChange={(e) => setForm({ ...form, schedule: e.target.value })} />
        <label className="form-label">Years of Experience</label>
        <input className="form-input" type="number" value={form.experience} onChange={(e) => setForm({ ...form, experience: e.target.value })} />
        <label className="form-label">Test Procedures (comma separated)</label>
        <input className="form-input" value={form.test_procedures} onChange={(e) => setForm({ ...form, test_procedures: e.target.value })} />
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setAddOpen(false)}>Cancel</button>
          <button className="btn-primary" onClick={addDoctor}>Save Doctor</button>
        </div>
      </Modal>

      <Modal open={!!acctFor} onClose={() => setAcctFor(null)}>
        <h2>{accountsByDoctor[acctFor?.id ?? -1] ? 'Reset' : 'Create'} Login — Dr. {acctFor?.name}</h2>
        <label className="form-label">Username</label>
        <input className="form-input" value={acctForm.username} onChange={(e) => setAcctForm({ ...acctForm, username: e.target.value })} />
        <label className="form-label">Password</label>
        <input className="form-input" type="password" value={acctForm.password} onChange={(e) => setAcctForm({ ...acctForm, password: e.target.value })} />
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setAcctFor(null)}>Cancel</button>
          <button className="btn-primary" onClick={saveAccount}>Save</button>
        </div>
      </Modal>
    </Shell>
  );
}
