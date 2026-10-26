import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal } from '../components';
import type { DoctorRow, ScheduleRow, TimeBlockRow } from '../types';

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

export default function AdminSchedules() {
  const d = getInitial().data as {
    doctors?: DoctorRow[];
    schedules?: ScheduleRow[];
    blocks?: TimeBlockRow[];
  };
  const { showToast } = useToast();
  const [doctors] = useState<DoctorRow[]>(d.doctors || []);
  const [schedules, setSchedules] = useState<ScheduleRow[]>(d.schedules || []);
  const [blocks, setBlocks] = useState<TimeBlockRow[]>(d.blocks || []);
  const [doctorId, setDoctorId] = useState<string>('');
  const [schedForm, setSchedForm] = useState({ start_day: 'Monday', end_day: 'Monday', start_time: '09:00', end_time: '17:00' });
  const [blockOpen, setBlockOpen] = useState(false);
  const [blockForm, setBlockForm] = useState({ doctor_id: '', block_type: 'date', block_date: '', end_date: '', day_of_week: 'Monday', start_time: '09:00', end_time: '17:00', reason: '' });

  const doctorName = (id: number) => doctors.find((x) => x.id === id)?.name || `#${id}`;
  const visibleSchedules = doctorId ? schedules.filter((s) => s.doctor_id === Number(doctorId)) : schedules;
  const visibleBlocks = doctorId ? blocks.filter((b) => b.doctor_id === Number(doctorId)) : blocks;

  async function addSchedule() {
    if (!doctorId) { showToast('Select a doctor first', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { action: 'add', doctor_id: doctorId, ...schedForm });
      if (isSuccess(res)) { showToast('Schedule added', 'success'); refresh(); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function toggleSchedule(s: ScheduleRow) {
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { action: 'toggle', schedule_id: s.id, is_active: s.is_active ? 0 : 1 });
      if (isSuccess(res)) { setSchedules((p) => p.map((x) => (x.id === s.id ? { ...x, is_active: s.is_active ? 0 : 1 } : x))); showToast('Updated', 'success'); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function deleteSchedule(id: number) {
    if (!confirm('Delete this schedule?')) return;
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { action: 'delete', schedule_id: id });
      if (isSuccess(res)) { setSchedules((p) => p.filter((x) => x.id !== id)); showToast('Deleted', 'success'); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function addBlock() {
    if (!blockForm.doctor_id) { showToast('Select a doctor', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { action: 'add_block', ...blockForm });
      if (isSuccess(res)) { showToast('Time block added', 'success'); setBlockOpen(false); setBlockForm({ doctor_id: '', block_type: 'date', block_date: '', end_date: '', day_of_week: 'Monday', start_time: '09:00', end_time: '17:00', reason: '' }); refresh(); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function deleteBlock(id: number) {
    if (!confirm('Remove this time block?')) return;
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { action: 'delete_block', block_id: id });
      if (isSuccess(res)) { setBlocks((p) => p.filter((x) => x.id !== id)); showToast('Removed', 'success'); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  function refresh() {
    fetch('api_admin_data.php?view=schedules', { headers: { 'X-Requested-With': 'LustreStaff' }, cache: 'no-store' })
      .then((r) => r.json())
      .then((json) => {
        if (json?.data) { setSchedules(json.data.schedules || []); setBlocks(json.data.blocks || []); }
      })
      .catch(() => {});
  }

  return (
    <Shell active="schedules" title="Schedules">
      <div className="content-card">
        <div className="card-head">
          <h2>Doctor Schedules</h2>
          <button className="btn-primary" onClick={() => setBlockOpen(true)}>+ Add Time Block</button>
        </div>
        <div className="apt-toolbar">
          <select className="apt-select" value={doctorId} onChange={(e) => setDoctorId(e.target.value)}>
            <option value="">All Doctors</option>
            {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name}</option>)}
          </select>
        </div>
        <div className="table-scroll"><table>
          <thead><tr><th>Doctor</th><th>Day</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            {visibleSchedules.length === 0 ? (
              <tr><td colSpan={6} className="empty-state-row">No schedules.</td></tr>
            ) : visibleSchedules.map((s) => (
              <tr key={s.id}>
                <td>Dr. {doctorName(s.doctor_id)}</td>
                <td>{s.day_of_week}</td>
                <td>{fmtTime(s.start_time)}</td>
                <td>{fmtTime(s.end_time)}</td>
                <td><span className={`badge badge-${s.is_active ? 'completed' : 'cancelled'}`}>{s.is_active ? 'Active' : 'Inactive'}</span></td>
                <td>
                  <div className="row-actions">
                    <button className="btn-secondary btn-xs" onClick={() => toggleSchedule(s)}>{s.is_active ? 'Disable' : 'Enable'}</button>
                    <button className="btn-danger btn-xs" onClick={() => deleteSchedule(s.id)}>Delete</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table></div>

        {doctorId && (
          <div className="subform">
            <h3>Add Schedule for Dr. {doctorName(Number(doctorId))}</h3>
            <div className="subform-row">
              <select className="form-dropdown" value={schedForm.start_day} onChange={(e) => setSchedForm({ ...schedForm, start_day: e.target.value })}>
                {DAYS.map((day) => <option key={day} value={day}>From {day}</option>)}
              </select>
              <select className="form-dropdown" value={schedForm.end_day} onChange={(e) => setSchedForm({ ...schedForm, end_day: e.target.value })}>
                {DAYS.map((day) => <option key={day} value={day}>To {day}</option>)}
              </select>
              <input className="form-input" type="time" value={schedForm.start_time} onChange={(e) => setSchedForm({ ...schedForm, start_time: e.target.value })} />
              <input className="form-input" type="time" value={schedForm.end_time} onChange={(e) => setSchedForm({ ...schedForm, end_time: e.target.value })} />
              <button className="btn-primary" onClick={addSchedule}>Add</button>
            </div>
          </div>
        )}
      </div>

      <div className="content-card">
        <h2>Time Blocks &amp; Leaves</h2>
        <div className="table-scroll"><table>
          <thead><tr><th>Doctor</th><th>Type</th><th>Date</th><th>Start</th><th>End</th><th>Reason</th><th></th></tr></thead>
          <tbody>
            {visibleBlocks.length === 0 ? (
              <tr><td colSpan={7} className="empty-state-row">No time blocks.</td></tr>
            ) : visibleBlocks.map((b) => (
              <tr key={b.id}>
                <td>Dr. {doctorName(b.doctor_id)}</td>
                <td><span className="pill">{b.block_type}</span></td>
                <td>{b.block_date || b.day_of_week || '—'}{b.end_date ? ` → ${b.end_date}` : ''}</td>
                <td>{fmtTime(b.start_time)}</td>
                <td>{fmtTime(b.end_time)}</td>
                <td>{b.reason || '—'}</td>
                <td><button className="btn-danger btn-xs" onClick={() => deleteBlock(b.id)}>Remove</button></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>

      <Modal open={blockOpen} onClose={() => setBlockOpen(false)}>
        <h2>Add Time Block</h2>
        <label className="form-label">Doctor</label>
        <select className="form-dropdown form-input" value={blockForm.doctor_id} onChange={(e) => setBlockForm({ ...blockForm, doctor_id: e.target.value })}>
          <option value="">Select doctor</option>
          {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name}</option>)}
        </select>
        <label className="form-label">Type</label>
        <select className="form-dropdown form-input" value={blockForm.block_type} onChange={(e) => setBlockForm({ ...blockForm, block_type: e.target.value })}>
          <option value="date">Specific Date</option>
          <option value="weekly">Weekly (recurring day)</option>
          <option value="week">Date Range</option>
        </select>
        {blockForm.block_type === 'weekly' ? (
          <>
            <label className="form-label">Day of Week</label>
            <select className="form-dropdown form-input" value={blockForm.day_of_week} onChange={(e) => setBlockForm({ ...blockForm, day_of_week: e.target.value })}>
              {DAYS.map((day) => <option key={day} value={day}>{day}</option>)}
            </select>
          </>
        ) : (
          <>
            <label className="form-label">{blockForm.block_type === 'week' ? 'Start Date' : 'Date'}</label>
            <input className="form-input" type="date" value={blockForm.block_date} onChange={(e) => setBlockForm({ ...blockForm, block_date: e.target.value })} />
            {blockForm.block_type === 'week' && (
              <>
                <label className="form-label">End Date</label>
                <input className="form-input" type="date" value={blockForm.end_date} onChange={(e) => setBlockForm({ ...blockForm, end_date: e.target.value })} />
              </>
            )}
          </>
        )}
        <label className="form-label">Start</label>
        <input className="form-input" type="time" value={blockForm.start_time} onChange={(e) => setBlockForm({ ...blockForm, start_time: e.target.value })} />
        <label className="form-label">End</label>
        <input className="form-input" type="time" value={blockForm.end_time} onChange={(e) => setBlockForm({ ...blockForm, end_time: e.target.value })} />
        <label className="form-label">Reason</label>
        <input className="form-input" value={blockForm.reason} onChange={(e) => setBlockForm({ ...blockForm, reason: e.target.value })} />
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setBlockOpen(false)}>Cancel</button>
          <button className="btn-primary" onClick={addBlock}>Add Block</button>
        </div>
      </Modal>
    </Shell>
  );
}
