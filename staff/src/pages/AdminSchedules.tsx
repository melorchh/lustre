import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal, Select } from '../components';
import DatePicker from '../DatePicker';
import TimePicker from '../TimePicker';
import { Card, EmptyState, Field, FilterBar, ModalHead, Stack, StatCard, StatGrid } from '../ui';
import { IconCheck, IconSchedules, IconWalkins } from '../icons';
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
  const [availOpen, setAvailOpen] = useState(false);
  const [availDoctorId, setAvailDoctorId] = useState<string>('');
  const [pendingDelete, setPendingDelete] = useState<ScheduleRow | null>(null);
  const [blockOpen, setBlockOpen] = useState(false);
  const [blockForm, setBlockForm] = useState({ doctor_id: '', block_type: 'date', block_date: '', end_date: '', day_of_week: 'Monday', start_time: '09:00', end_time: '17:00', reason: '' });

  const doctorName = (id: number) => doctors.find((x) => x.id === id)?.name || `#${id}`;
  const visibleSchedules = doctorId ? schedules.filter((s) => s.doctor_id === Number(doctorId)) : schedules;
  const visibleBlocks = doctorId ? blocks.filter((b) => b.doctor_id === Number(doctorId)) : blocks;
  const activeSchedules = schedules.filter((s) => s.is_active).length;
  const coveredDoctors = new Set(schedules.map((s) => s.doctor_id)).size;
  const upcomingBlocks = blocks.filter((b) => !b.block_date || b.block_date >= new Date().toISOString().slice(0, 10)).length;

  function openAvail() {
    setAvailDoctorId(doctorId);
    setSchedForm({ start_day: 'Monday', end_day: 'Monday', start_time: '09:00', end_time: '17:00' });
    setAvailOpen(true);
  }

  async function addSchedule() {
    if (!availDoctorId) { showToast('Select a doctor first', 'error'); return; }
    if (!schedForm.start_day || !schedForm.end_day || !schedForm.start_time || !schedForm.end_time) {
      showToast('All fields are required', 'error');
      return;
    }
    if (DAYS.indexOf(schedForm.end_day) < DAYS.indexOf(schedForm.start_day)) {
      showToast('End day must be on or after start day', 'error');
      return;
    }
    if (schedForm.end_time <= schedForm.start_time) {
      showToast('End time must be after start time', 'error');
      return;
    }
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { doctor_id: availDoctorId, ...schedForm }, { action: 'add' });
      if (isSuccess(res)) {
        showToast('Availability saved', 'success');
        setAvailOpen(false);
        refresh();
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function toggleSchedule(s: ScheduleRow) {
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { schedule_id: s.id, is_active: s.is_active ? 0 : 1 }, { action: 'toggle' });
      if (isSuccess(res)) { setSchedules((p) => p.map((x) => (x.id === s.id ? { ...x, is_active: s.is_active ? 0 : 1 } : x))); showToast('Updated', 'success'); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function deleteSchedule() {
    if (!pendingDelete) return;
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { schedule_id: pendingDelete.id }, { action: 'delete' });
      if (isSuccess(res)) {
        setSchedules((p) => p.filter((x) => x.id !== pendingDelete.id));
        setPendingDelete(null);
        showToast('Deleted', 'success');
      }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function addBlock() {
    if (!blockForm.doctor_id) { showToast('Select a doctor', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', blockForm, { action: 'add_block' });
      if (isSuccess(res)) { showToast('Time block added', 'success'); setBlockOpen(false); setBlockForm({ doctor_id: '', block_type: 'date', block_date: '', end_date: '', day_of_week: 'Monday', start_time: '09:00', end_time: '17:00', reason: '' }); refresh(); }
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  async function deleteBlock(id: number) {
    if (!confirm('Remove this time block?')) return;
    showLoading();
    try {
      const res = await postAction('admin_schedule_action.php', { block_id: id }, { action: 'delete_block' });
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
      <Stack>
        <StatGrid>
          <StatCard icon={<IconSchedules />} label="Active blocks" value={activeSchedules} hint="Weekly availability rows" />
          <StatCard icon={<IconCheck />} label="Doctors covered" value={coveredDoctors} hint="With at least one schedule" tone="blue" />
          <StatCard icon={<IconWalkins />} label="Time blocks" value={blocks.length} hint={`${upcomingBlocks} upcoming or recurring`} tone="amber" />
        </StatGrid>

        <Card
          title="Weekly schedules"
          subtitle={`${visibleSchedules.length} availability row${visibleSchedules.length === 1 ? '' : 's'}`}
          actions={
            <div className="ap-row-actions ap-row-actions--inline">
              <button type="button" className="btn-secondary" onClick={() => setBlockOpen(true)}>Add time block</button>
              <button type="button" className="btn-primary" onClick={openAvail}>Add available time</button>
            </div>
          }
        >
          <FilterBar>
            <Field label="Doctor">
              <Select className="apt-select" value={doctorId} onChange={(e) => setDoctorId(e.target.value)}>
                <option value="">All doctors</option>
                {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name}</option>)}
              </Select>
            </Field>
          </FilterBar>

          <div className="table-scroll"><table>
            <thead><tr><th>Doctor</th><th>Day</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              {visibleSchedules.length === 0 ? (
                <tr>
                  <td colSpan={6}>
                    <EmptyState
                      icon={<IconSchedules size={20} />}
                      title="No schedules"
                      hint="Use Add available time to create the first schedule."
                    />
                  </td>
                </tr>
              ) : visibleSchedules.map((s) => (
                <tr key={s.id}>
                  <td><span className="ap-strong">Dr. {doctorName(s.doctor_id)}</span></td>
                  <td>{s.day_of_week}</td>
                  <td>{fmtTime(s.start_time)}</td>
                  <td>{fmtTime(s.end_time)}</td>
                  <td><span className={`badge badge-${s.is_active ? 'completed' : 'cancelled'}`}>{s.is_active ? 'Active' : 'Inactive'}</span></td>
                  <td>
                    <div className="ap-row-actions ap-row-actions--inline">
                      <button type="button" className="btn-edit btn-xs" onClick={() => toggleSchedule(s)}>{s.is_active ? 'Disable' : 'Enable'}</button>
                      <button type="button" className="btn-delete btn-xs" onClick={() => setPendingDelete(s)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div>

        </Card>

        <Card
          title="Time blocks &amp; leaves"
          subtitle={`${visibleBlocks.length} block${visibleBlocks.length === 1 ? '' : 's'}${doctorId ? ` for Dr. ${doctorName(Number(doctorId))}` : ''}`}
        >
          <div className="table-scroll"><table>
            <thead><tr><th>Doctor</th><th>Type</th><th>Date</th><th>Start</th><th>End</th><th>Reason</th><th></th></tr></thead>
            <tbody>
              {visibleBlocks.length === 0 ? (
                <tr>
                  <td colSpan={7}>
                    <EmptyState
                      icon={<IconSchedules size={20} />}
                      title="No time blocks"
                      hint="Add a block to mark leaves, holidays or unavailable hours."
                    />
                  </td>
                </tr>
              ) : visibleBlocks.map((b) => (
                <tr key={b.id}>
                  <td><span className="ap-strong">Dr. {doctorName(b.doctor_id)}</span></td>
                  <td><span className="pill">{b.block_type}</span></td>
                  <td>{b.block_date || b.day_of_week || '—'}{b.end_date ? ` → ${b.end_date}` : ''}</td>
                  <td>{fmtTime(b.start_time)}</td>
                  <td>{fmtTime(b.end_time)}</td>
                  <td>{b.reason || <span className="ap-none">—</span>}</td>
                  <td>
                    <div className="ap-row-actions ap-row-actions--inline">
                      <button type="button" className="btn-delete btn-xs" onClick={() => deleteBlock(b.id)}>Remove</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div>
        </Card>
      </Stack>

      <Modal open={availOpen} onClose={() => setAvailOpen(false)}>
        <ModalHead
          title="Add available time"
          hint="Choose a day range and time. Reusing an existing start time updates that block."
        />
        <div className="form-grid">
          <Field label="Doctor" className="ap-field--full">
            <Select className="form-input" value={availDoctorId} onChange={(e) => setAvailDoctorId(e.target.value)}>
              <option value="">Select doctor</option>
              {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name}</option>)}
            </Select>
          </Field>
          <Field label="From (day)">
            <Select className="form-input" value={schedForm.start_day} onChange={(e) => setSchedForm({ ...schedForm, start_day: e.target.value })}>
              {DAYS.map((day) => <option key={day} value={day}>{day}</option>)}
            </Select>
          </Field>
          <Field label="To (day)">
            <Select className="form-input" value={schedForm.end_day} onChange={(e) => setSchedForm({ ...schedForm, end_day: e.target.value })}>
              {DAYS.map((day) => <option key={day} value={day}>{day}</option>)}
            </Select>
          </Field>
          <Field label="Start time">
            <TimePicker value={schedForm.start_time} onChange={(start_time) => setSchedForm({ ...schedForm, start_time })} />
          </Field>
          <Field label="End time">
            <TimePicker value={schedForm.end_time} onChange={(end_time) => setSchedForm({ ...schedForm, end_time })} />
          </Field>
        </div>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setAvailOpen(false)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={addSchedule}>Add availability</button>
        </div>
      </Modal>

      <Modal open={blockOpen} onClose={() => setBlockOpen(false)}>
        <ModalHead
          title="Add time block"
          hint="Block out leaves, holidays or hours the doctor cannot take appointments."
        />
        <div className="form-grid">
          <Field label="Doctor">
            <Select className="form-input" value={blockForm.doctor_id} onChange={(e) => setBlockForm({ ...blockForm, doctor_id: e.target.value })}>
              <option value="">Select doctor</option>
              {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name}</option>)}
            </Select>
          </Field>
          <Field label="Type">
            <Select className="form-input" value={blockForm.block_type} onChange={(e) => setBlockForm({ ...blockForm, block_type: e.target.value })}>
              <option value="date">Specific date</option>
              <option value="weekly">Weekly (recurring day)</option>
              <option value="week">Date range</option>
            </Select>
          </Field>
          {blockForm.block_type === 'weekly' ? (
            <Field label="Day of week">
              <Select className="form-input" value={blockForm.day_of_week} onChange={(e) => setBlockForm({ ...blockForm, day_of_week: e.target.value })}>
                {DAYS.map((day) => <option key={day} value={day}>{day}</option>)}
              </Select>
            </Field>
          ) : (
            <>
              <Field label={blockForm.block_type === 'week' ? 'Start date' : 'Date'}>
                <DatePicker value={blockForm.block_date} onChange={(block_date) => setBlockForm({ ...blockForm, block_date })} />
              </Field>
              {blockForm.block_type === 'week' && (
                <Field label="End date">
                  <DatePicker value={blockForm.end_date} onChange={(end_date) => setBlockForm({ ...blockForm, end_date })} />
                </Field>
              )}
            </>
          )}
          <Field label="Start time">
            <TimePicker value={blockForm.start_time} onChange={(start_time) => setBlockForm({ ...blockForm, start_time })} />
          </Field>
          <Field label="End time">
            <TimePicker value={blockForm.end_time} onChange={(end_time) => setBlockForm({ ...blockForm, end_time })} />
          </Field>
          <Field label="Reason" className="ap-field--full">
            <input className="form-input" placeholder="e.g. Conference, holiday, personal leave" value={blockForm.reason} onChange={(e) => setBlockForm({ ...blockForm, reason: e.target.value })} />
          </Field>
        </div>
        <div className="form-actions">
          <button type="button" className="btn-secondary" onClick={() => setBlockOpen(false)}>Cancel</button>
          <button type="button" className="btn-primary" onClick={addBlock}>Add block</button>
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
          title="Delete schedule?"
          hint={pendingDelete
            ? `Remove Dr. ${doctorName(pendingDelete.doctor_id)}'s ${pendingDelete.day_of_week} availability (${fmtTime(pendingDelete.start_time)}–${fmtTime(pendingDelete.end_time)})?`
            : undefined}
        />
        <div className="form-actions ap-confirm-actions">
          <button type="button" className="btn-secondary" onClick={() => setPendingDelete(null)}>Cancel</button>
          <button type="button" className="btn-danger" onClick={deleteSchedule}>Yes, delete</button>
        </div>
      </Modal>
    </Shell>
  );
}
