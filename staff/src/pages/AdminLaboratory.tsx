import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal, Select } from '../components';
import DatePicker from '../DatePicker';
import { Card, EmptyState, Field, FilterBar, ModalHead, Stack, StatCard, StatGrid } from '../ui';
import { IconCheck, IconClock, IconLab, IconSearch } from '../icons';
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

  const hasFilters = Boolean(q || status || priority);

  function resetFilters() {
    setQ('');
    setStatus('');
    setPriority('');
  }

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
      <Stack>
        <StatGrid>
          <StatCard icon={<IconLab />} label="Total requests" value={stats.total ?? tests.length} hint="All lab records" />
          <StatCard icon={<IconClock />} label="Pending" value={stats.pending ?? 0} hint="Awaiting processing" tone="amber" />
          <StatCard icon={<IconLab />} label="In progress" value={stats.in_progress ?? 0} hint="Being examined" tone="blue" />
          <StatCard icon={<IconCheck />} label="Completed" value={stats.completed ?? 0} hint="Results released" tone="slate" />
        </StatGrid>

        <Card
          title="Request a lab test"
          subtitle="Pick a patient and requesting doctor, then select one or more tests."
        >
          <form
            className="ap-form"
            onSubmit={(e) => {
              e.preventDefault();
              submitRequest();
            }}
          >
            <div className="form-grid">
              <Field label="Patient">
                <Select className="form-input" searchable value={req.patient_id} onChange={(e) => setReq({ ...req, patient_id: e.target.value })}>
                  <option value="">Select patient</option>
                  {patients.map((p) => <option key={p.id} value={p.id}>{p.name}{p.email ? ` — ${p.email}` : ''}</option>)}
                </Select>
              </Field>
              <Field label="Requesting doctor">
                <Select className="form-input" value={req.doctor_id} onChange={(e) => setReq({ ...req, doctor_id: e.target.value })}>
                  <option value="">Select doctor</option>
                  {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name} — {doc.specialty}</option>)}
                </Select>
              </Field>
            </div>

            <Field label="Test type">
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
                  className="form-input ap-mt"
                  placeholder="Enter custom test name"
                  autoComplete="off"
                  value={req.other}
                  onChange={(e) => setReq({ ...req, other: e.target.value })}
                />
              )}
            </Field>

            <div className="form-grid ap-mt">
              <Field label="Priority">
                <Select className="form-input" value={req.priority} onChange={(e) => setReq({ ...req, priority: e.target.value })}>
                  <option value="normal">Normal</option>
                  <option value="urgent">Urgent</option>
                  <option value="stat">STAT (immediate)</option>
                </Select>
              </Field>
              <Field label="Scheduled date" className="ap-field--date">
                <DatePicker min={today} value={req.scheduled_date} onChange={(scheduled_date) => setReq({ ...req, scheduled_date })} />
              </Field>
            </div>

            <Field label="Clinical notes / reason" className="ap-mt">
              <textarea className="form-input" rows={3} placeholder="Symptoms, history, or reason for requesting this test..." value={req.notes} onChange={(e) => setReq({ ...req, notes: e.target.value })} />
            </Field>

            <div className="form-actions ap-actions-start">
              <button type="submit" className="btn-primary">Submit lab request</button>
            </div>
          </form>
        </Card>

        <Card
          title="All lab tests"
          subtitle={`${filtered.length} of ${tests.length} request${tests.length === 1 ? '' : 's'}`}
          actions={hasFilters ? <button type="button" className="ap-btn-text" onClick={resetFilters}>Reset filters</button> : undefined}
        >
          <FilterBar>
            <Field label="Search" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Patient, doctor or test type..." value={q} onChange={(e) => setQ(e.target.value)} />
              </div>
            </Field>
            <Field label="Status">
              <Select className="apt-select" value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
              </Select>
            </Field>
            <Field label="Priority">
              <Select className="apt-select" value={priority} onChange={(e) => setPriority(e.target.value)}>
                <option value="">All priorities</option>
                <option value="normal">Normal</option>
                <option value="urgent">Urgent</option>
                <option value="stat">STAT</option>
              </Select>
            </Field>
          </FilterBar>

          <div className="table-scroll"><table>
            <thead><tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Test type</th><th>Priority</th><th>Scheduled</th><th>Status</th><th>Result</th><th>Actions</th></tr></thead>
            <tbody>
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={9}>
                    <EmptyState
                      icon={<IconLab size={20} />}
                      title="No lab tests"
                      hint={hasFilters ? 'Try a different search, status or priority.' : 'Use the form above to request the first test.'}
                    />
                  </td>
                </tr>
              ) : filtered.map((t) => (
                <tr key={t.id}>
                  <td className="ap-id">#{t.id}</td>
                  <td>
                    <span className="ap-strong">{t.patient_name}</span>
                    <small className="ap-sub">{t.patient_email || 'No email on file'}</small>
                  </td>
                  <td>
                    <span>Dr. {t.doctor_name}</span>
                    <small className="ap-sub">{t.specialty}</small>
                  </td>
                  <td><span className="ap-strong">{t.test_type}</span></td>
                  <td><span className={`badge badge-${t.priority}`}>{String(t.priority || '').toUpperCase()}</span></td>
                  <td>
                    <span>{t.scheduled_date ? fmtDate(t.scheduled_date) : '—'}</span>
                    <small className="ap-sub">Requested {fmtDate(t.created_at)}</small>
                  </td>
                  <td>
                    <div className="ap-row-actions ap-row-actions--status">
                      <Select className="action-select" value={t.status} onChange={(e) => updateStatus(t.id, e.target.value)}>
                        {['pending', 'processing', 'completed'].map((o) => <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
                      </Select>
                    </div>
                  </td>
                  <td>
                    {t.result
                      ? <button type="button" className="btn-view btn-xs" onClick={() => setViewFor(t)}>View</button>
                      : <span className="ap-none">No result</span>}
                  </td>
                  <td>
                    <div className="ap-row-actions ap-row-actions--inline">
                      <button type="button" className="btn-edit btn-xs" onClick={() => openResult(t)}>Result</button>
                      <button type="button" className="btn-delete btn-xs" onClick={() => removeTest(t)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div>
        </Card>
      </Stack>

      <Modal open={!!resultFor} onClose={() => setResultFor(null)}>
        <ModalHead
          title="Enter lab result"
          hint={resultFor ? `${resultFor.test_type} · ${resultFor.patient_name} · Dr. ${resultFor.doctor_name}` : undefined}
        />
        <Field label="Status">
          <Select className="form-input" value={resultForm.status} onChange={(e) => setResultForm({ ...resultForm, status: e.target.value })}>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </Select>
        </Field>
        <Field label="Lab result / findings" className="ap-mt">
          <textarea className="form-input" rows={6} placeholder="Detailed results, findings and interpretation..." value={resultForm.result} onChange={(e) => setResultForm({ ...resultForm, result: e.target.value })} />
        </Field>
        <Field label="Clinical notes" className="ap-mt">
          <textarea className="form-input" rows={3} placeholder="Additional notes or recommendations..." value={resultForm.notes} onChange={(e) => setResultForm({ ...resultForm, notes: e.target.value })} />
        </Field>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setResultFor(null)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={saveResult}>Save result</button>
        </div>
      </Modal>

      <Modal open={!!viewFor} onClose={() => setViewFor(null)}>
        <ModalHead
          title="Lab result"
          hint={viewFor ? `${viewFor.test_type} · ${viewFor.patient_name}` : undefined}
        />
        {viewFor && (
          <>
            <div className="client-detail-grid ap-detail-grid">
              <div><span>Patient</span><strong>{viewFor.patient_name}</strong></div>
              <div><span>Doctor</span><strong>Dr. {viewFor.doctor_name}</strong></div>
              <div><span>Test</span><strong>{viewFor.test_type}</strong></div>
              <div><span>Scheduled</span><strong>{viewFor.scheduled_date ? fmtDate(viewFor.scheduled_date) : '—'}</strong></div>
              <div><span>Status</span><strong style={{ textTransform: 'capitalize' }}>{viewFor.status}</strong></div>
              {viewFor.scheduled_time ? <div><span>Time</span><strong>{fmtTime(viewFor.scheduled_time)}</strong></div> : null}
            </div>
            <label className="form-label">Findings / result</label>
            <div className="ap-result-body">{viewFor.result}</div>
            {viewFor.notes ? (
              <>
                <label className="form-label ap-mt">Clinical notes</label>
                <div className="ap-result-body">{viewFor.notes}</div>
              </>
            ) : null}
          </>
        )}
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setViewFor(null)}>Close</button>
        </div>
      </Modal>
    </Shell>
  );
}
