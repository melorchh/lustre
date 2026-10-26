import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal } from '../components';
import type { DoctorRow } from '../types';

interface DoctorAccount {
  id: number;
  account_id?: number;
  doctor_id: number;
  doctor_name?: string;
  specialty?: string;
  username: string;
  created_at?: string;
}

const EMPTY_FORM = { name: '', specialty: '', schedule: '', experience: '', test_procedures: '' };
const EMPTY_ACCOUNT = { doctor_id: '', username: '', password: '' };

/** Chip-style input for a doctor's newline-separated test_procedures list. */
function ChipInput({ chips, onChange }: { chips: string[]; onChange: (next: string[]) => void }) {
  const [text, setText] = useState('');

  function add() {
    const v = text.trim();
    if (!v) return;
    if (!chips.includes(v)) onChange([...chips, v]);
    setText('');
  }

  return (
    <div>
      <div className="tp-input-box">
        <input
          type="text"
          placeholder="Type a test / procedure..."
          value={text}
          autoComplete="off"
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              add();
            }
          }}
        />
        <button type="button" className="btn-secondary btn-xs" onClick={add}>Add</button>
      </div>
      <div className="proc-chip-list">
        {chips.map((c) => (
          <span className="proc-chip proc-chip--x" key={c}>
            {c}
            <button
              type="button"
              className="proc-chip-x"
              title={`Remove ${c}`}
              onClick={() => onChange(chips.filter((x) => x !== c))}
            >
              &times;
            </button>
          </span>
        ))}
      </div>
      <small style={{ color: '#64748b', fontSize: '.78rem', display: 'block', marginTop: '.35rem' }}>
        Type one and press Enter or Add. Each shows above and can be removed with &times;.
      </small>
    </div>
  );
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
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<DoctorRow | null>(null);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [chips, setChips] = useState<string[]>([]);
  const [acctCreate, setAcctCreate] = useState({ ...EMPTY_ACCOUNT });
  const [resetFor, setResetFor] = useState<DoctorAccount | null>(null);
  const [resetPassword, setResetPassword] = useState('');
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

  async function refresh() {
    try {
      const json = await fetchData<{ data?: { doctors?: DoctorRow[]; accounts?: DoctorAccount[] } }>('api_admin_data.php', { view: 'doctors' });
      if (json?.data) {
        setDoctors(json.data.doctors || []);
        setAccounts(json.data.accounts || []);
      }
    } catch {
      /* keep current */
    }
  }

  function openAdd() {
    setEditing(null);
    setForm({ ...EMPTY_FORM });
    setChips([]);
    setFormOpen(true);
  }

  function openEdit(doc: DoctorRow) {
    setEditing(doc);
    setForm({
      name: doc.name,
      specialty: doc.specialty,
      schedule: doc.schedule || '',
      experience: doc.experience != null ? String(doc.experience) : '',
      test_procedures: doc.test_procedures || '',
    });
    setChips(
      String(doc.test_procedures || '')
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean),
    );
    setFormOpen(true);
  }

  async function saveDoctor() {
    if (!form.name.trim() || !form.specialty.trim() || !form.schedule.trim()) {
      showToast('Name, specialty and schedule are required', 'error');
      return;
    }
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', {
        action: editing ? 'edit' : 'add',
        doctor_id: editing?.id,
        name: form.name,
        specialty: form.specialty,
        schedule: form.schedule,
        experience: form.experience,
        test_procedures: chips.join('\n'),
      });
      if (isSuccess(res)) {
        showToast(editing ? 'Doctor updated' : 'Doctor added', 'success');
        setFormOpen(false);
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function removeDoctor(doc: DoctorRow) {
    if (!confirm(`Delete Dr. ${doc.name}? This cannot be undone.`)) return;
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', { action: 'delete', doctor_id: doc.id });
      if (isSuccess(res)) {
        setDoctors((p) => p.filter((x) => x.id !== doc.id));
        showToast('Doctor removed', 'success');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function createAccount() {
    if (!acctCreate.doctor_id) { showToast('Please select a doctor', 'error'); return; }
    if (!/^[a-zA-Z0-9_]{3,30}$/.test(acctCreate.username.trim())) {
      showToast('Username must be 3-30 characters (letters, numbers, underscore only)', 'error');
      return;
    }
    if (acctCreate.password.length < 8) { showToast('Password must be at least 8 characters', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', {
        action: 'add_account',
        doctor_id: acctCreate.doctor_id,
        username: acctCreate.username,
        password: acctCreate.password,
      });
      if (isSuccess(res)) {
        showToast('Doctor account created', 'success');
        setAcctCreate({ ...EMPTY_ACCOUNT });
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function saveReset() {
    if (!resetFor) return;
    if (resetPassword.length < 8) { showToast('Password must be at least 8 characters', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', {
        action: 'reset_account',
        account_id: resetFor.id,
        password: resetPassword,
      });
      if (isSuccess(res)) {
        showToast('Password reset', 'success');
        setResetFor(null);
        setResetPassword('');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function removeAccount(acct: DoctorAccount) {
    if (!confirm(`Delete the login account for Dr. ${acct.doctor_name || acct.doctor_id}? The doctor profile is kept.`)) return;
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', { action: 'delete_account', account_id: acct.id });
      if (isSuccess(res)) {
        setAccounts((p) => p.filter((x) => x.id !== acct.id));
        showToast('Account deleted', 'success');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
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
          <button className="btn-add" onClick={openAdd}>+ Add Doctor</button>
        </div>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search name or specialty..." value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Name</th><th>Specialty</th><th>Schedule</th><th>Experience</th><th>Tests / Procedures</th><th>Appointments</th><th>Account</th><th>Actions</th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={9} className="empty-state-row">No doctors found.</td></tr>
            ) : filtered.map((doc) => {
              const acct = accountsByDoctor[doc.id];
              const procs = String(doc.test_procedures || '')
                .split('\n')
                .map((l) => l.trim())
                .filter(Boolean);
              return (
                <tr key={doc.id}>
                  <td>#{doc.id}</td>
                  <td><strong>Dr. {doc.name}</strong></td>
                  <td>{doc.specialty}</td>
                  <td>{doc.schedule || '—'}</td>
                  <td>{doc.experience ? `${doc.experience} yrs` : '—'}</td>
                  <td>
                    {procs.length === 0 ? '—' : procs.map((p) => <span className="proc-chip" key={p}>{p}</span>)}
                  </td>
                  <td><strong style={{ color: '#16a34a' }}>{doc.appointment_count ?? 0}</strong></td>
                  <td>{acct ? <code className="pill">{acct.username}</code> : <span className="badge badge-pending">None</span>}</td>
                  <td>
                    <div className="row-actions">
                      <button className="btn-edit btn-xs" onClick={() => openEdit(doc)}>Edit</button>
                      <button className="btn-delete btn-xs" onClick={() => removeDoctor(doc)}>Delete</button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table></div>
      </div>

      <div className="content-card">
        <h2>Doctor Login Accounts</h2>
        <p style={{ color: '#64748b', margin: '0 0 1.25rem', fontSize: '.93rem' }}>
          Doctor accounts let doctors log into their own portal to enter lab results and set their availability.
        </p>

        <div className="subform" style={{ border: '1px solid #e2e8f0', borderRadius: 14, padding: '1.1rem 1.25rem', marginBottom: '1.6rem' }}>
          <h3>Create New Account</h3>
          <div className="form-grid">
            <div className="form-group">
              <label>Doctor</label>
              <select className="form-dropdown" value={acctCreate.doctor_id} onChange={(e) => setAcctCreate({ ...acctCreate, doctor_id: e.target.value })}>
                <option value="">Select Doctor</option>
                {doctors.map((doc) => (
                  <option key={doc.id} value={doc.id}>Dr. {doc.name} — {doc.specialty}</option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Username</label>
              <input type="text" className="form-input" maxLength={30} placeholder="e.g. dr_dela_cruz" value={acctCreate.username} onChange={(e) => setAcctCreate({ ...acctCreate, username: e.target.value })} />
            </div>
            <div className="form-group">
              <label>Password</label>
              <input type="password" className="form-input" placeholder="At least 8 characters" value={acctCreate.password} onChange={(e) => setAcctCreate({ ...acctCreate, password: e.target.value })} />
            </div>
            <div className="form-group" style={{ display: 'flex', alignItems: 'flex-end' }}>
              <button type="button" className="btn-primary" onClick={createAccount}>+ Create Account</button>
            </div>
          </div>
        </div>

        <div className="table-scroll"><table>
          <thead><tr><th>Doctor</th><th>Specialty</th><th>Username</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>
            {accounts.length === 0 ? (
              <tr><td colSpan={5} className="empty-state-row">No doctor accounts yet. Create one above.</td></tr>
            ) : accounts.map((ac) => (
              <tr key={ac.id}>
                <td><strong>Dr. {ac.doctor_name}</strong></td>
                <td>{ac.specialty || '—'}</td>
                <td><code className="pill">{ac.username}</code></td>
                <td>{fmtDate(ac.created_at)}</td>
                <td>
                  <div className="row-actions">
                    <button className="btn-edit btn-xs" onClick={() => { setResetFor(ac); setResetPassword(''); }}>Reset Password</button>
                    <button className="btn-delete btn-xs" onClick={() => removeAccount(ac)}>Delete</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>

      <Modal open={formOpen} onClose={() => setFormOpen(false)}>
        <h2>{editing ? 'Edit Doctor' : 'Add New Doctor'}</h2>
        <div className="form-grid">
          <div className="form-group">
            <label>Full Name</label>
            <input className="form-input" placeholder="e.g. Juan dela Cruz" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Specialty</label>
            <input className="form-input" list="specialtyList" placeholder="e.g. Obstetrics & Gynecology" value={form.specialty} onChange={(e) => setForm({ ...form, specialty: e.target.value })} />
            <datalist id="specialtyList">{specialties.map((s) => <option key={s} value={s} />)}</datalist>
          </div>
          <div className="form-group" style={{ gridColumn: '1 / -1' }}>
            <label>Tests / Procedures</label>
            <ChipInput chips={chips} onChange={setChips} />
          </div>
          <div className="form-group">
            <label>Schedule</label>
            <input className="form-input" placeholder="e.g. Mon-Fri: 9AM-5PM" value={form.schedule} onChange={(e) => setForm({ ...form, schedule: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Years of Experience</label>
            <input className="form-input" type="number" min={0} max={60} placeholder="e.g. 10" value={form.experience} onChange={(e) => setForm({ ...form, experience: e.target.value })} />
          </div>
        </div>
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setFormOpen(false)}>Cancel</button>
          <button className="btn-primary" onClick={saveDoctor}>{editing ? 'Update Doctor' : 'Save Doctor'}</button>
        </div>
      </Modal>

      <Modal open={!!resetFor} onClose={() => setResetFor(null)}>
        <h2>Reset Password</h2>
        <p style={{ color: '#64748b', marginBottom: '1rem', fontSize: '.95rem' }}>
          Set a new password for <strong>Dr. {resetFor?.doctor_name}</strong> ({resetFor?.username}).
        </p>
        <div className="form-group">
          <label>New Password</label>
          <input type="password" className="form-input" placeholder="At least 8 characters" value={resetPassword} onChange={(e) => setResetPassword(e.target.value)} />
        </div>
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setResetFor(null)}>Cancel</button>
          <button className="btn-primary" onClick={saveReset}>Save New Password</button>
        </div>
      </Modal>
    </Shell>
  );
}
