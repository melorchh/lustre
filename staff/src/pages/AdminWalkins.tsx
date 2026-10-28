import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Select } from '../components';
import DatePicker from '../DatePicker';
import { Card, EmptyState, Field, FilterBar, Stack, StatCard, StatGrid } from '../ui';
import { IconCheck, IconClock, IconSearch, IconWalkins } from '../icons';
import type { WalkinRow } from '../types';

const STATUSES = ['waiting', 'in_service', 'served', 'cancelled'];

function todayISO(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function statusLabel(s: string): string {
  if (s === 'in_service') return 'In Service';
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '—';
}

function badgeClass(s: string): string {
  if (s === 'served') return 'badge-completed';
  if (s === 'in_service') return 'badge-confirmed';
  if (s === 'cancelled') return 'badge-cancelled';
  return 'badge-pending';
}

export default function AdminWalkins() {
  const d = getInitial().data as {
    date?: string;
    walkins?: WalkinRow[];
    stats?: Record<string, number>;
  };
  const { showToast } = useToast();
  const today = todayISO();
  const [date, setDate] = useState<string>(d.date || today);
  const [walkins, setWalkins] = useState<WalkinRow[]>(d.walkins || []);
  const [stats, setStats] = useState<Record<string, number>>(d.stats || {});
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');

  const filtered = useMemo(() => walkins.filter((w) => {
    if (q && !`${w.patient_name} ${w.contact} ${w.test_procedure}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status && w.status !== status) return false;
    return true;
  }), [walkins, q, status]);

  const groups = useMemo(() => {
    const out: { doctor: string; specialty: string; rows: WalkinRow[] }[] = [];
    for (const w of filtered) {
      const name = w.doctor_name || 'Unassigned';
      let g = out.find((x) => x.doctor === name);
      if (!g) {
        g = { doctor: name, specialty: w.specialty || '', rows: [] };
        out.push(g);
      }
      g.rows.push(w);
    }
    return out;
  }, [filtered]);

  async function loadDate(next: string) {
    setDate(next);
    showLoading();
    try {
      const json = await fetchData<{ data?: { walkins?: WalkinRow[]; stats?: Record<string, number> } }>(
        'api_admin_data.php',
        { view: 'walkins', date: next },
      );
      if (json?.data) {
        setWalkins(json.data.walkins || []);
        setStats(json.data.stats || {});
      }
      try {
        window.history.replaceState(null, '', `admin_walkins.php?date=${next}`);
      } catch {
        /* ignore */
      }
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function updateStatus(id: number, next: string) {
    showLoading();
    try {
      const res = await postAction('admin_walkin_action.php', {
        action: 'update_status',
        walkin_id: id,
        status: next,
      });
      if (isSuccess(res)) {
        setWalkins((prev) => prev.map((w) => (w.id === id ? { ...w, status: next } : w)));
        showToast('Status updated!', 'success');
        loadDate(date);
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  return (
    <Shell active="walkins" title="Walk-ins">
      <Stack>
        <StatGrid>
          <StatCard icon={<IconWalkins />} label="Total" value={stats.total ?? walkins.length} hint="Queue entries" />
          <StatCard icon={<IconClock />} label="Waiting" value={stats.waiting ?? 0} hint="Not yet called" tone="amber" />
          <StatCard icon={<IconWalkins />} label="In service" value={stats.in_progress ?? 0} hint="Currently with a doctor" tone="blue" />
          <StatCard icon={<IconCheck />} label="Served" value={stats.completed ?? 0} hint="Completed today" tone="green" />
          <StatCard icon={<IconClock />} label="Cancelled" value={stats.cancelled ?? 0} hint="Did not push through" tone="rose" />
        </StatGrid>

        <Card
          title="Walk-in queue"
          subtitle={`${filtered.length} entr${filtered.length === 1 ? 'y' : 'ies'} for ${fmtDate(date)} · one queue per doctor`}
          actions={
            <Field label="Queue date" className="ap-field--date">
              <DatePicker value={date} max={today} onChange={(next) => loadDate(next || today)} />
            </Field>
          }
        >
          <FilterBar>
            <Field label="Search" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input type="search" className="apt-search" placeholder="Patient, contact or procedure..." value={q} onChange={(e) => setQ(e.target.value)} />
              </div>
            </Field>
            <Field label="Status">
              <Select className="apt-select" value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="">All statuses</option>
                {STATUSES.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
              </Select>
            </Field>
          </FilterBar>

          {groups.length === 0 ? (
            <EmptyState
              icon={<IconWalkins size={20} />}
              title={`No walk-ins for ${fmtDate(date)}`}
              hint="Pick another date or register a walk-in from the dashboard."
            />
          ) : groups.map((g) => (
            <div className="ap-group" key={g.doctor}>
              <h3 className="ap-group-title">
                Dr. {g.doctor} {g.specialty ? <small>({g.specialty})</small> : null}
              </h3>
              <div className="table-scroll"><table>
                <thead><tr><th>Queue</th><th>Patient</th><th>Type</th><th>Arrival</th><th>Test / procedure</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                  {g.rows.map((w) => (
                    <tr key={w.id}>
                      <td><span className="ap-queue">#{w.queue ?? w.id}</span></td>
                      <td>
                        <span className="ap-strong">{w.patient_name}</span>
                        <small className="ap-sub">{w.contact || 'No contact number'}</small>
                      </td>
                      <td>{w.patient_type ? <span className="pill">{w.patient_type.replace(/_/g, ' ')}</span> : '—'}</td>
                      <td>{fmtTime(w.arrival_time)}</td>
                      <td>{w.test_procedure || '—'}</td>
                      <td><span className={`badge ${badgeClass(w.status)}`}>{statusLabel(w.status)}</span></td>
                      <td>
                        <div className="ap-row-actions ap-row-actions--status">
                          <Select className="action-select" value={w.status} onChange={(e) => updateStatus(w.id, e.target.value)}>
                            {STATUSES.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                          </Select>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table></div>
            </div>
          ))}
        </Card>
      </Stack>
    </Shell>
  );
}
