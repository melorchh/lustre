import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal } from '../components';
import type { LabTestRow } from '../types';

interface OptionRow {
  id: number;
  name: string;
  email?: string;
  specialty?: string;
}

const EMPTY_REQUEST = {
  patient_id: '',
  doctor_id: '',
  priority: 'normal',
  scheduled_date: '',
  notes: '',
  other: '',
};

const EMPTY_RESULT = { status: 'pending', result: '', notes: '' };

export default function AdminLaboratory() {
  const d = getInitial().data as {
    tests?: LabTestRow[];
    patients?: OptionRow[];
    doctors?: OptionRow[];
    testTypes?: string[];
    stats?: Record<string, number>;
  };
  const { showToast } = useToast();
  const [tests, setTests] = useState<LabTestRow[]>(d.tests || []);
  const patients = d.patients || [];
  const doctors = d.doctors || [];
  const testTypes = d.testTypes || [];
  const [stats, setStats] = useState<Record<string, number>>(d.stats || {});

  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');

  const [req, setReq] = useState({ ...EMPTY_REQUEST });
  const [types, setTypes] = useState<string[]>([]);

  const [resultFor, setResultFor] = useState<LabTestRow | null>(null);
  const [resultForm, setResultForm] = useState({ ...EMPTY_RESULT });
  const [viewFor, setViewFor] = useState<LabTestRow | null>(null);

  const today = new Date().toISOString().slice(0, 10);

  const filtered = useMemo(() => tests.filter((t) => {
    if (q && !`${t.patient_name} ${t.doctor_name} ${t.test_type}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status && t.status !== status) return false;
    if (priority && t.priority !== priority) return false;
    return true;
  }), [tests, q, status, priority]);

  async function refresh() {
    try {
      const json = await fetchData<{
        data?: {
          tests?: LabTestRow[];
          stats?: Record<string, number>;
        };
      }>('api_admin_data.php', { view: 'laboratory' });
      if (json?.data) {
        setTests(json.data.tests || []);
        setStats(json.data.stats || {});
      }
    } catch {
      /* keep current */
    }
  }

  function toggleType(t: string) {
    setTypes((prev) => (prev.includes(t) ? prev.filter((x) => x !== t) : [...prev, t]));
  }

  async function submitRequest() {
    if (!req.patient_id) { showToast('Please select a patient', 'error'); return; }
    if (!req.doctor_id) { showToast('Please select a requesting doctor', 'error'); return; }
    if (types.length === 0) { showToast('Please select at least one test type', 'error'); return; }
    let chosen = [...types];
    if (chosen.includes('Other')) {
      const custom = req.other.trim();
      if (!custom) { showToast('Please enter the custom test name', 'error'); return; }
      chosen = chosen.filter((t) => t !== 'Other');
      chosen.push(custom);
    }

    const fd = new FormData();
    fd.append('patient_id', req.patient_id);
    fd.append('doctor_id', req.doctor_id);
    fd.append('priority', req.priority);
    if (req.scheduled_date) fd.append('scheduled_date', req.scheduled_date);
    fd.append('notes', req.notes);
    chosen.forEach((t) => fd.append('test_type[]', t));

    showLoading();
    try {
      const res = await postAction('lab_action.php', fd, { action: 'add' });
      if (isSuccess(res)) {
        showToast(chosen.length > 1 ? `${chosen.length} lab tests requested!` : 'Lab test requested!', 'success');
        setReq({ ...EMPTY_REQUEST });
        setTypes([]);
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function updateStatus(id: number, newStatus: string) {
    showLoading();
    try {
      const res = await postAction('lab_action.php', { lab_id: id, status: newStatus }, { action: 'update_status' });
      if (isSuccess(res)) {
        setTests((prev) => prev.map((t) => (t.id === id ? { ...t, status: newStatus } : t)));
        showToast('Status updated!', 'success');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  function openResult(t: LabTestRow) {
    setResultForm({ status: t.status, result: t.result || '', notes: t.notes || '' });
    setResultFor(t);
  }

  async function saveResult() {
    if (!resultFor) return;
    showLoading();
    try {
      const res = await postAction('lab_action.php', {
        lab_id: resultFor.id,
        status: resultForm.status,
        result: resultForm.result,
        notes: resultForm.notes,
      }, { action: 'save_result' });
      if (isSuccess(res)) {
        const id = resultFor.id;
        setTests((prev) => prev.map((t) => (t.id === id
          ? { ...t, status: resultForm.status, result: resultForm.result, notes: resultForm.notes }
          : t)));
        showToast('Result saved!', 'success');
        setResultFor(null);
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function removeTest(t: LabTestRow) {
    if (!confirm(`Delete the ${t.test_type} record for ${t.patient_name}? This cannot be undone.`)) return;
    showLoading();
    try {
      const res = await postAction('lab_action.php', { lab_id: t.id }, { action: 'delete' });
      if (isSuccess(res)) {
        setTests((prev) => prev.filter((x) => x.id !== t.id));
        showToast('Deleted!', 'success');
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  return (
    <Shell active="laboratory" title="Laboratory">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? tests.length}</div><div className="stat-label">Total Requests</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.pending ?? 0}</div><div className="stat-label">Pending</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.in_progress ?? 0}</div><div className="stat-label">In Progress</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.completed ?? 0}</div><div className="stat-label">Completed</div></div>
      </div>

      <div className="content-card">
        <h2>Request New Lab Test</h2>
        <div className="form-grid">
          <div className="form-group">
            <label>Patient</label>
            <select className="form-dropdown" value={req.patient_id} onChange={(e) => setReq({ ...req, patient_id: e.target.value })}>
              <option value="">Select Patient</option>
              {patients.map((p) => <option key={p.id} value={p.id}>{p.name}{p.email ? ` — ${p.email}` : ''}</option>)}
            </select>
          </div>
          <div className="form-group">
            <label>Requesting Doctor</label>
            <select className="form-dropdown" value={req.doctor_id} onChange={(e) => setReq({ ...req, doctor_id: e.target.value })}>
              <option value="">Select Doctor</option>
              {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name} — {doc.specialty}</option>)}
            </select>
          </div>
          <div className="form-group" style={{ gridColumn: '1 / -1' }}>
            <label>Test Type <span style={{ fontWeight: 400, textTransform: 'none' }}>(select one or more)</span></label>
            <div className="test-type-checks">
              {testTypes.map((t) => (
                <label className={`test-type-check${types.includes(t) ? ' is-checked' : ''}`} key={t}>
                  <input type="checkbox" checked={types.includes(t)} onChange={() => toggleType(t)} />
                  <span>{t}</span>
                </label>
              ))}
            </div>
            {types.includes('Other') && (
              <input
                type="text"
                className="form-input"
                style={{ marginTop: '.55rem' }}
                placeholder="Enter custom test name"
                autoComplete="off"
                value={req.other}
                onChange={(e) => setReq({ ...req, other: e.target.value })}
              />
            )}
          </div>
          <div className="form-group">
            <label>Priority</label>
            <select className="form-dropdown" value={req.priority} onChange={(e) => setReq({ ...req, priority: e.target.value })}>
              <option value="normal">Normal</option>
              <option value="urgent">Urgent</option>
              <option value="stat">STAT (Immediate)</option>
            </select>
          </div>
          <div className="form-group">
            <label>Scheduled Date</label>
            <input type="date" className="form-input" min={today} value={req.scheduled_date} onChange={(e) => setReq({ ...req, scheduled_date: e.target.value })} />
          </div>
        </div>
        <div className="form-group">
          <label>Clinical Notes / Reason for Test</label>
          <textarea className="form-input" rows={3} placeholder="Enter clinical notes, symptoms, or reason for requesting this test..." value={req.notes} onChange={(e) => setReq({ ...req, notes: e.target.value })} />
        </div>
        <div className="form-actions" style={{ justifyContent: 'flex-start' }}>
          <button type="button" className="btn-primary" onClick={submitRequest}>+ Submit Lab Request</button>
        </div>
      </div>

      <div className="content-card">
        <h2>All Lab Tests</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search by patient, doctor, or test type..." value={q} onChange={(e) => setQ(e.target.value)} />
          <select className="apt-select" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
          </select>
          <select className="apt-select" value={priority} onChange={(e) => setPriority(e.target.value)}>
            <option value="">All Priorities</option>
            <option value="normal">Normal</option>
            <option value="urgent">Urgent</option>
            <option value="stat">STAT</option>
          </select>
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Test Type</th><th>Priority</th><th>Scheduled</th><th>Status</th><th>Result</th><th>Actions</th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={9} className="empty-state-row">No lab tests found. Use the form above to add one.</td></tr>
            ) : filtered.map((t) => (
              <tr key={t.id}>
                <td>#{t.id}</td>
                <td><strong>{t.patient_name}</strong><br /><small style={{ color: '#64748b' }}>{t.patient_email}</small></td>
                <td>Dr. {t.doctor_name}<br /><small style={{ color: '#64748b' }}>{t.specialty}</small></td>
                <td><strong>{t.test_type}</strong></td>
                <td><span className={`badge badge-${t.priority}`}>{String(t.priority || '').toUpperCase()}</span></td>
                <td>
                  {t.scheduled_date ? fmtDate(t.scheduled_date) : '—'}
                  <br /><small style={{ color: '#94a3b8' }}>Req: {fmtDate(t.created_at)}</small>
                </td>
                <td>
                  <select className="action-select" value={t.status} onChange={(e) => updateStatus(t.id, e.target.value)}>
                    {['pending', 'processing', 'completed'].map((o) => <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
                  </select>
                </td>
                <td>
                  {t.result
                    ? <button type="button" className="btn-view btn-xs" onClick={() => setViewFor(t)}>View</button>
                    : <span style={{ color: '#94a3b8', fontSize: '.82rem' }}>No result</span>}
                </td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <div className="row-actions">
                    <button className="btn-edit btn-xs" onClick={() => openResult(t)}>Result</button>
                    <button className="btn-delete btn-xs" onClick={() => removeTest(t)}>Delete</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>

      <Modal open={!!resultFor} onClose={() => setResultFor(null)}>
        <h2>Enter Lab Result</h2>
        {resultFor && (
          <div style={{ margin: '-.35rem 0 1rem', fontSize: '.92rem', color: '#64748b' }}>
            <strong style={{ color: '#0f172a' }}>{resultFor.test_type}</strong> &middot; {resultFor.patient_name} &middot; Dr. {resultFor.doctor_name}
          </div>
        )}
        <div className="form-group">
          <label>Status</label>
          <select className="action-select" style={{ maxWidth: 200 }} value={resultForm.status} onChange={(e) => setResultForm({ ...resultForm, status: e.target.value })}>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div className="form-group">
          <label>Lab Result / Findings</label>
          <textarea className="form-input" rows={6} placeholder="Enter detailed lab results, findings, and interpretation here..." value={resultForm.result} onChange={(e) => setResultForm({ ...resultForm, result: e.target.value })} />
        </div>
        <div className="form-group">
          <label>Clinical Notes</label>
          <textarea className="form-input" rows={3} placeholder="Additional notes or recommendations..." value={resultForm.notes} onChange={(e) => setResultForm({ ...resultForm, notes: e.target.value })} />
        </div>
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setResultFor(null)}>Cancel</button>
          <button className="btn-primary" onClick={saveResult}>Save Result</button>
        </div>
      </Modal>

      <Modal open={!!viewFor} onClose={() => setViewFor(null)}>
        <h2>Lab Result</h2>
        {viewFor && (
          <>
            <div className="client-detail-grid" style={{ marginBottom: '1rem' }}>
              <div><span>Patient</span><strong>{viewFor.patient_name}</strong></div>
              <div><span>Doctor</span><strong>Dr. {viewFor.doctor_name}</strong></div>
              <div><span>Test</span><strong>{viewFor.test_type}</strong></div>
              <div><span>Scheduled</span><strong>{viewFor.scheduled_date ? fmtDate(viewFor.scheduled_date) : '—'}</strong></div>
              <div><span>Status</span><strong style={{ textTransform: 'capitalize' }}>{viewFor.status}</strong></div>
              {viewFor.scheduled_time ? <div><span>Time</span><strong>{fmtTime(viewFor.scheduled_time)}</strong></div> : null}
            </div>
            <label className="form-label">Findings / Result</label>
            <div className="content-card" style={{ boxShadow: 'none', border: '1px solid #e2e8f0', marginBottom: '1rem', whiteSpace: 'pre-wrap' }}>
              {viewFor.result}
            </div>
            {viewFor.notes ? (
              <>
                <label className="form-label">Clinical Notes</label>
                <div className="content-card" style={{ boxShadow: 'none', border: '1px solid #e2e8f0', whiteSpace: 'pre-wrap' }}>
                  {viewFor.notes}
                </div>
              </>
            ) : null}
          </>
        )}
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setViewFor(null)}>Close</button>
        </div>
      </Modal>
    </Shell>
  );
}
