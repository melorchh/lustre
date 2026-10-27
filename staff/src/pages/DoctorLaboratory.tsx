import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Select } from '../components';
import { StatCard, StatGrid } from '../ui';
import type { LabTestRow } from '../types';

export default function DoctorLaboratory() {
  const d = getInitial().data as { tests?: LabTestRow[]; stats?: Record<string, number> };
  const { showToast } = useToast();
  const [tests, setTests] = useState<LabTestRow[]>(d.tests || []);
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const stats = d.stats || {};

  const filtered = useMemo(() => tests.filter((t) => {
    if (q && !`${t.patient_name} ${t.test_type}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status && t.status !== status) return false;
    return true;
  }), [tests, q, status]);

  async function saveResult(id: number, result: string) {
    showLoading();
    try {
      const res = await postAction('doctor_lab_action.php', { lab_id: id, result, status: 'completed' }, { action: 'save_result' });
      if (isSuccess(res)) { setTests((p) => p.map((t) => (t.id === id ? { ...t, result, status: 'completed' } : t))); showToast('Result saved', 'success'); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  return (
    <Shell active="laboratory" title="Laboratory">
      <StatGrid>
        <StatCard label="Total" value={stats.total ?? tests.length} />
        <StatCard label="Pending" value={stats.pending ?? 0} tone="amber" />
        <StatCard label="Completed" value={stats.completed ?? 0} tone="teal" />
      </StatGrid>

      <div className="content-card">
        <h2>Lab Tests</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search patient or test..." value={q} onChange={(e) => setQ(e.target.value)} />
          <Select className="apt-select" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
          </Select>
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>ID</th><th>Patient</th><th>Test</th><th>Priority</th><th>Scheduled</th><th>Status</th><th>Result</th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={7} className="empty-state-row">No lab tests.</td></tr>
            ) : filtered.map((t) => (
              <tr key={t.id}>
                <td>#{t.id}</td>
                <td><strong>{t.patient_name}</strong></td>
                <td>{t.test_type}</td>
                <td><span className={`badge badge-${t.priority === 'urgent' ? 'pending' : 'confirmed'}`}>{t.priority}</span></td>
                <td>{t.scheduled_date ? `${fmtDate(t.scheduled_date)} ${t.scheduled_time ? fmtTime(t.scheduled_time) : ''}` : '—'}</td>
                <td><span className={`badge badge-${t.status === 'completed' ? 'completed' : 'pending'}`}>{t.status}</span></td>
                <td><ResultInline value={t.result || ''} onSave={(v) => saveResult(t.id, v)} /></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>
    </Shell>
  );
}

function ResultInline({ value, onSave }: { value: string; onSave: (v: string) => void }) {
  const [editing, setEditing] = useState(false);
  const [text, setText] = useState(value);
  if (!editing) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
        <span>{value ? value : <span className="ap-none">None</span>}</span>
        <button className="btn-secondary btn-xs" onClick={() => { setText(value); setEditing(true); }}>{value ? 'Edit' : 'Add'}</button>
      </div>
    );
  }
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      <textarea className="form-input" rows={2} value={text} onChange={(e) => setText(e.target.value)} />
      <div style={{ display: 'flex', gap: 6 }}>
        <button className="btn-primary btn-xs" onClick={() => { onSave(text); setEditing(false); }}>Save</button>
        <button className="btn-secondary btn-xs" onClick={() => setEditing(false)}>Cancel</button>
      </div>
    </div>
  );
}
