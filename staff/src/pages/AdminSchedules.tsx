import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal, Select } from '../components';
import { Card, EmptyState, Field, FilterBar, FormSection, ModalHead, Stack, StatCard, StatGrid } from '../ui';
import { IconCheck, IconSchedules, IconWalkins } from '../icons';
import type { DoctorRow, ScheduleRow, TimeBlockRow } from '../types';

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

type PendingSchedule = { day_of_week: string; start_time: string; end_time: string };

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
  const [dayForm, setDayForm] = useState({ day_of_week: 'Monday', start_time: '09:00', end_time: '17:00' });
  const [scheduleItems, setScheduleItems] = useState<PendingSchedule[]>([]);
  const [blockOpen, setBlockOpen] = useState(false);
  const [blockForm, setBlockForm] = useState({ doctor_id: '', block_type: 'date', block_date: '', end_date: '', day_of_week: 'Monday', start_time: '09:00', end_time: '17:00', reason: '' });

  const doctorName = (id: number) => doctors.find((x) => x.id === id)?.name || `#${id}`;
  const visibleSchedules = doctorId ? schedules.filter((s) => s.doctor_id === Number(doctorId)) : schedules;
  const visibleBlocks = doctorId ? blocks.filter((b) => b.doctor_id === Number(doctorId)) : blocks;
  const activeSchedules = schedules.filter((s) => s.is_active).length;
  const coveredDoctors = new Set(schedules.map((s) => s.doctor_id)).size;
  const upcomingBlocks = blocks.filter((b) => !b.block_date || b.block_date >= new Date().toISOString().slice(0, 10)).length;

  function addScheduleItem() {
    if (!dayForm.start_time || !dayForm.end_time) {
      showToast('Start time and end time are required', 'error');
      return;
    }
    if (dayForm.end_time <= dayForm.start_time) {
      showToast('End time must be after start time', 'error');
      return;
    }
    setScheduleItems((prev) => {
      const exists = prev.some(
        (item) => item.day_of_week === dayForm.day_of_week && item.start_time === dayForm.start_time && item.end_time === dayForm.end_time,
      );
      if (exists) {
        showToast('Schedule already added', 'error');
        return prev;
      }
      return [...prev, { ...dayForm }];
    });
  }

  function removeScheduleItem(index: number) {
    setScheduleItems((prev) => prev.filter((_, i) => i !== index));
  }

  async function addSchedule() {
    if (!doctorId) { showToast('Select a doctor first', 'error'); return; }
    if (scheduleItems.length === 0 && !(schedForm.start_day && schedForm.end_day && schedForm.start_time && schedForm.end_time)) {
      showToast('Add at least one schedule item', 'error');
      return;
    }
    showLoading();
    try {
      if (scheduleItems.length > 0) {
        for (const item of scheduleItems) {
          const res = await postAction('admin_schedule_action.php', { action: 'add', doctor_id: doctorId, start_day: item.day_of_week, end_day: item.day_of_week, start_time: item.start_time, end_time: item.end_time });
          if (!isSuccess(res)) {
            showToast(res.replace(/^error:\s*/i, ''), 'error');
            hideLoading();
            return;
          }
        }
        setScheduleItems([]);
        showToast('Schedule added', 'success');
        refresh();
      } else {
        const res = await postAction('admin_schedule_action.php', { action: 'add', doctor_id: doctorId, ...schedForm });
        if (isSuccess(res)) { showToast('Schedule added', 'success'); refresh(); }
        else showToast(res.replace(/^error:\s*/i, ''), 'error');
      }
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
      <Stack>
        <StatGrid>
          <StatCard icon={<IconSchedules />} label="Active blocks" value={activeSchedules} hint="Weekly availability rows" />
          <StatCard icon={<IconCheck />} label="Doctors covered" value={coveredDoctors} hint="With at least one schedule" tone="blue" />
          <StatCard icon={<IconWalkins />} label="Time blocks" value={blocks.length} hint={`${upcomingBlocks} upcoming or recurring`} tone="amber" />
        </StatGrid>

        <Card
          title="Weekly schedules"
          subtitle={`${visibleSchedules.length} availability row${visibleSchedules.length === 1 ? '' : 's'}`}
          actions={<button type="button" className="btn-primary" onClick={() => setBlockOpen(true)}>Add time block</button>}
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
                      hint="Pick a doctor below to add their weekly availability."
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
                      <button type="button" className="btn-delete btn-xs" onClick={() => deleteSchedule(s.id)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div>

          {doctorId && (
            <FormSection
              title={`Add schedule for Dr. ${doctorName(Number(doctorId))}`}
              hint="Pick a day and time, add to list, then save"
            >
              <div className="ap-inline-form">
                <Field label="Day">
                  <Select className="form-input" value={dayForm.day_of_week} onChange={(e) => setDayForm({ ...dayForm, day_of_week: e.target.value })}>
                    {DAYS.map((day) => <option key={day} value={day}>{day}</option>)}
                  </Select>
                </Field>
                <Field label="Start time">
                  <input className="form-input" type="time" value={dayForm.start_time} onChange={(e) => setDayForm({ ...dayForm, start_time: e.target.value })} />
                </Field>
                <Field label="End time">
                  <input className="form-input" type="time" value={dayForm.end_time} onChange={(e) => setDayForm({ ...dayForm, end_time: e.target.value })} />
                </Field>
                <div className="ap-inline-form-submit">
                  <button type="button" className="btn-secondary" onClick={addScheduleItem}>Add</button>
                </div>
              </div>
              {scheduleItems.length > 0 && (
                <div style={{ marginTop: 12 }}>
                  <div className="table-scroll" style={{ maxWidth: 640 }}>
                    <table>
                      <thead>
                        <tr>
                          <th>Day</th>
                          <th>Start Time</th>
                          <th>End Time</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {scheduleItems.map((item, idx) => (
                          <tr key={`${item.day_of_week}-${item.start_time}-${item.end_time}-${idx}`}>
                            <td>{item.day_of_week}</td>
                            <td>{fmtTime(item.start_time)}</td>
                            <td>{fmtTime(item.end_time)}</td>
                            <td>
                              <button type="button" className="btn-delete btn-xs" onClick={() => removeScheduleItem(idx)}>Remove</button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  <div style={{ marginTop: 10 }}>
                    <button type="button" className="btn-primary" onClick={addSchedule}>Add schedule</button>
                  </div>
                </div>
              )}
              <div className="ap-inline-form" style={{ marginTop: 16, paddingTop: 16, borderTop: '1px solid var(--ap-border)' }}>
                <Field label="From">
                  <Select className="form-input" value={schedForm.start_day} onChange={(e) => setSchedForm({ ...schedForm, start_day: e.target.value })}>
                    {DAYS.map((day) => <option key={day} value={day}>{day}</option>)}
                  </Select>
                </Field>
                <Field label="To">
                  <Select className="form-input" value={schedForm.end_day} onChange={(e) => setSchedForm({ ...schedForm, end_day: e.target.value })}>
                    {DAYS.map((day) => <option key={day} value={day}>{day}</option>)}
                  </Select>
                </Field>
                <Field label="Start time">
                  <input className="form-input" type="time" value={schedForm.start_time} onChange={(e) => setSchedForm({ ...schedForm, start_time: e.target.value })} />
                </Field>
                <Field label="End time">
                  <input className="form-input" type="time" value={schedForm.end_time} onChange={(e) => setSchedForm({ ...schedForm, end_time: e.target.value })} />
                </Field>
                <div className="ap-inline-form-submit">
                  <button type="button" className="btn-primary" onClick={addSchedule}>Add range</button>
                </div>
              </div>
            </FormSection>
          )}
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
                <input className="form-input" type="date" value={blockForm.block_date} onChange={(e) => setBlockForm({ ...blockForm, block_date: e.target.value })} />
              </Field>
              {blockForm.block_type === 'week' && (
                <Field label="End date">
                  <input className="form-input" type="date" value={blockForm.end_date} onChange={(e) => setBlockForm({ ...blockForm, end_date: e.target.value })} />
                </Field>
              )}
            </>
          )}
          <Field label="Start time">
            <input className="form-input" type="time" value={blockForm.start_time} onChange={(e) => setBlockForm({ ...blockForm, start_time: e.target.value })} />
          </Field>
          <Field label="End time">
            <input className="form-input" type="time" value={blockForm.end_time} onChange={(e) => setBlockForm({ ...blockForm, end_time: e.target.value })} />
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
    </Shell>
  );
}
