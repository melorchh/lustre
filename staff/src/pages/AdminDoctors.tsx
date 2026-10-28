import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal, Select } from '../components';
import { Card, EmptyState, Field, FilterBar, FormSection, ModalHead, Stack, StatCard, StatGrid } from '../ui';
import { IconDoctors, IconLab, IconSearch } from '../icons';
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

type PendingDelete =
  | { kind: 'doctor'; doctor: DoctorRow }
  | { kind: 'account'; account: DoctorAccount };

const EMPTY_FORM = { name: '', specialty: '', schedule: '', experience: '', test_procedures: '', daily_limit: '' };
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
          className="form-input"
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
      <small className="ap-sub">
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
  const [pendingDelete, setPendingDelete] = useState<PendingDelete | null>(null);
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
    setChips(['General Consultation', 'Follow Up Consultation', 'Diagnostic Procedure', '(Other) - specify below']);
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
      daily_limit: doc.daily_limit ? String(doc.daily_limit) : '',
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
        daily_limit: form.daily_limit,
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
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', { action: 'delete', doctor_id: doc.id });
      if (isSuccess(res)) {
        setDoctors((p) => p.filter((x) => x.id !== doc.id));
        setPendingDelete(null);
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
    showLoading();
    try {
      const res = await postAction('admin_doctor_actions.php', { action: 'delete_account', account_id: acct.id });
      if (isSuccess(res)) {
        setAccounts((p) => p.filter((x) => x.id !== acct.id));
        setPendingDelete(null);
        showToast('Account deleted', 'success');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function confirmDelete() {
    if (!pendingDelete) return;
    if (pendingDelete.kind === 'doctor') await removeDoctor(pendingDelete.doctor);
    else await removeAccount(pendingDelete.account);
  }

  const deleteHint = pendingDelete
    ? pendingDelete.kind === 'doctor'
      ? `Dr. ${pendingDelete.doctor.name} will be removed from the directory. This action cannot be undone.`
      : `This removes the portal login for Dr. ${pendingDelete.account.doctor_name || pendingDelete.account.doctor_id}. The doctor profile is kept.`
    : undefined;

  return (
    <Shell active="doctors" title="Doctors">
      <Stack>
        <StatGrid>
          <StatCard icon={<IconDoctors />} label="Total doctors" value={stats.total ?? doctors.length} hint="Active specialists" />
          <StatCard icon={<IconDoctors />} label="Portal accounts" value={stats.withAccounts ?? accounts.length} hint="Can sign in" tone="blue" />
          <StatCard icon={<IconLab />} label="Specialties" value={specialties.length} hint="Covered by the clinic" tone="violet" />
        </StatGrid>

        <Card
          title="Doctor directory"
          subtitle={`${filtered.length} of ${doctors.length} specialist${doctors.length === 1 ? '' : 's'}`}
          actions={<button type="button" className="btn-add" onClick={openAdd}>Add doctor</button>}
        >
          <FilterBar>
            <Field label="Search" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Search name or specialty..." value={q} onChange={(e) => setQ(e.target.value)} />
              </div>
            </Field>
          </FilterBar>
          <div className="table-scroll"><table>
            <thead><tr><th>ID</th><th>Name</th><th>Specialty</th><th>Schedule</th><th>Experience</th><th>Tests / procedures</th><th>Appointments</th><th>Account</th><th>Actions</th></tr></thead>
            <tbody>
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={9}>
                    <EmptyState
                      icon={<IconDoctors size={20} />}
                      title="No doctors found"
                      hint={q ? 'Try a different name or specialty.' : 'Add the first doctor to start booking appointments.'}
                    />
                  </td>
                </tr>
              ) : filtered.map((doc) => {
                const acct = accountsByDoctor[doc.id];
                const procs = String(doc.test_procedures || '')
                  .split('\n')
                  .map((l) => l.trim())
                  .filter(Boolean);
                return (
                  <tr key={doc.id}>
                    <td className="ap-id">#{doc.id}</td>
                    <td><span className="ap-strong">Dr. {doc.name}</span></td>
                    <td>{doc.specialty}</td>
                    <td>{doc.schedule || '—'}</td>
                    <td>{doc.experience ? `${doc.experience} yrs` : '—'}</td>
                    <td>
                      {procs.length === 0 ? <span className="ap-none">—</span> : procs.map((p) => <span className="proc-chip" key={p}>{p}</span>)}
                    </td>
                    <td><span className="ap-count-badge">{doc.appointment_count ?? 0}</span></td>
                    <td>{acct ? <code className="pill">{acct.username}</code> : <span className="badge badge-pending">None</span>}</td>
                    <td>
                      <div className="ap-row-actions ap-row-actions--inline">
                        <button type="button" className="btn-edit btn-xs" onClick={() => openEdit(doc)}>Edit</button>
                        <button type="button" className="btn-delete btn-xs" onClick={() => setPendingDelete({ kind: 'doctor', doctor: doc })}>Delete</button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table></div>
        </Card>

        <Card
          title="Doctor login accounts"
          subtitle="Let doctors sign into their portal to enter lab results and set availability."
        >
          <FormSection title="Create new account" hint="Username and a password of at least 8 characters">
            <div className="form-grid">
              <Field label="Doctor">
                <Select className="form-input" value={acctCreate.doctor_id} onChange={(e) => setAcctCreate({ ...acctCreate, doctor_id: e.target.value })}>
                  <option value="">Select doctor</option>
                  {doctors.map((doc) => (
                    <option key={doc.id} value={doc.id}>Dr. {doc.name} — {doc.specialty}</option>
                  ))}
                </Select>
              </Field>
              <Field label="Username">
                <input type="text" className="form-input" maxLength={30} placeholder="e.g. dr_dela_cruz" value={acctCreate.username} onChange={(e) => setAcctCreate({ ...acctCreate, username: e.target.value })} />
              </Field>
              <Field label="Password">
                <input type="password" className="form-input" placeholder="At least 8 characters" value={acctCreate.password} onChange={(e) => setAcctCreate({ ...acctCreate, password: e.target.value })} />
              </Field>
              <div className="ap-form-submit">
                <button type="button" className="btn-primary" onClick={createAccount}>Create account</button>
              </div>
            </div>
          </FormSection>

          <div className="table-scroll ap-mt"><table>
            <thead><tr><th>Doctor</th><th>Specialty</th><th>Username</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              {accounts.length === 0 ? (
                <tr>
                  <td colSpan={5}>
                    <EmptyState
                      icon={<IconDoctors size={20} />}
                      title="No doctor accounts yet"
                      hint="Create one above to give a doctor portal access."
                    />
                  </td>
                </tr>
              ) : accounts.map((ac) => (
                <tr key={ac.id}>
                  <td><span className="ap-strong">Dr. {ac.doctor_name}</span></td>
                  <td>{ac.specialty || '—'}</td>
                  <td><code className="pill">{ac.username}</code></td>
                  <td>{fmtDate(ac.created_at)}</td>
                  <td>
                    <div className="ap-row-actions ap-row-actions--inline">
                      <button type="button" className="btn-edit btn-xs" onClick={() => { setResetFor(ac); setResetPassword(''); }}>Reset password</button>
                      <button type="button" className="btn-delete btn-xs" onClick={() => setPendingDelete({ kind: 'account', account: ac })}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div>
        </Card>
      </Stack>

      <Modal open={formOpen} onClose={() => setFormOpen(false)}>
        <ModalHead
          title={editing ? 'Edit doctor' : 'Add doctor'}
          hint={editing ? `Update the profile for Dr. ${editing.name}.` : 'Create a profile so the doctor can receive appointments.'}
        />
        <div className="form-grid">
          <Field label="Full name">
            <input className="form-input" placeholder="e.g. Juan dela Cruz" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </Field>
          <Field label="Specialty">
            <input className="form-input" list="specialtyList" placeholder="e.g. Obstetrics & Gynecology" value={form.specialty} onChange={(e) => setForm({ ...form, specialty: e.target.value })} />
            <datalist id="specialtyList">{specialties.map((s) => <option key={s} value={s} />)}</datalist>
          </Field>
          <Field label="Tests / procedures" className="ap-field--full">
            <ChipInput chips={chips} onChange={setChips} />
          </Field>
          <Field label="Schedule">
            <input className="form-input" placeholder="e.g. Mon-Fri: 9AM-5PM" value={form.schedule} onChange={(e) => setForm({ ...form, schedule: e.target.value })} />
          </Field>
          <Field label="Years of experience">
            <input className="form-input" type="number" min={0} max={60} placeholder="e.g. 10" value={form.experience} onChange={(e) => setForm({ ...form, experience: e.target.value })} />
          </Field>
          <Field label="Daily patient limit" className="ap-field--full">
            <input className="form-input" type="number" min={0} max={200} placeholder="e.g. 12" value={form.daily_limit} onChange={(e) => setForm({ ...form, daily_limit: e.target.value })} />
            <small className="ap-sub">Leave blank or 0 to derive the limit from this doctor&rsquo;s schedules (one patient per 1-hour visit).</small>
          </Field>
        </div>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setFormOpen(false)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={saveDoctor}>{editing ? 'Update doctor' : 'Save doctor'}</button>
        </div>
      </Modal>

      <Modal open={!!resetFor} onClose={() => setResetFor(null)}>
        <ModalHead
          title="Reset password"
          hint={resetFor ? `Set a new password for Dr. ${resetFor.doctor_name} (${resetFor.username}).` : undefined}
        />
        <Field label="New password">
          <input type="password" className="form-input" placeholder="At least 8 characters" value={resetPassword} onChange={(e) => setResetPassword(e.target.value)} />
        </Field>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setResetFor(null)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={saveReset}>Save new password</button>
        </div>
      </Modal>

      <Modal open={!!pendingDelete} onClose={() => setPendingDelete(null)} className="ap-confirm-modal">
        <span className="ap-confirm-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M3 6h18" />
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
          </svg>
        </span>
        <ModalHead
          title={pendingDelete?.kind === 'account' ? 'Delete login account?' : 'Delete doctor?'}
          hint={deleteHint}
        />
        <div className="form-actions ap-confirm-actions">
          <button type="button" className="btn-secondary" onClick={() => setPendingDelete(null)}>Cancel</button>
          <button type="button" className="btn-danger" onClick={confirmDelete}>Yes, delete</button>
        </div>
      </Modal>
    </Shell>
  );
}
