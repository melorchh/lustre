import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import type { WalkinRow } from '../types';

const STATUSES = ['waiting', 'in_service', 'served', 'cancelled'];

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
  const today = new Date().toISOString().slice(0, 10);
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
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? 0}</div><div className="stat-label">Total</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.waiting ?? 0}</div><div className="stat-label">Waiting</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.in_progress ?? 0}</div><div className="stat-label">In Service</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.completed ?? 0}</div><div className="stat-label">Served</div></div>
        <div className="stat-card" style={{ borderTopColor: '#ef4444' }}><div className="stat-number" style={{ color: '#ef4444' }}>{stats.cancelled ?? 0}</div><div className="stat-label">Cancelled</div></div>
      </div>

      <div className="content-card">
        <div className="card-head">
          <div>
            <h2 style={{ marginBottom: 4 }}>Walk-ins Queue</h2>
            <small style={{ color: '#64748b' }}>Dynamic queue number per doctor for the selected date.</small>
          </div>
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: '.6rem', flexWrap: 'wrap' }}>
            <div>
              <label className="form-label" style={{ marginTop: 0 }}>Date</label>
              <input
                type="date"
                className="form-input"
                style={{ width: 180, marginBottom: 0 }}
                max={today}
                value={date}
                onChange={(e) => loadDate(e.target.value || today)}
              />
            </div>
          </div>
        </div>

        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search patient, contact or procedure..." value={q} onChange={(e) => setQ(e.target.value)} />
          <select className="apt-select" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All Statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
          </select>
        </div>

        {groups.length === 0 ? (
          <div className="empty-state-row" style={{ textAlign: 'center', color: '#94a3b8', padding: '1.5rem 0' }}>
            No walk-ins for {fmtDate(date)}.
          </div>
        ) : groups.map((g) => (
          <div className="walkin-doctor-group" key={g.doctor} style={{ marginTop: 18 }}>
            <h3 style={{ margin: '0 0 8px', color: '#0f172a' }}>
              Dr. {g.doctor} {g.specialty ? <small style={{ color: '#64748b', fontWeight: 400 }}>({g.specialty})</small> : null}
            </h3>
            <div className="table-scroll"><table>
              <thead><tr><th>Queue</th><th>Patient</th><th>Type</th><th>Arrival</th><th>Test / Procedure</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                {g.rows.map((w) => (
                  <tr key={w.id}>
                    <td><span className="badge" style={{ background: '#16a34a', color: '#fff', fontSize: '1rem', fontWeight: 800 }}>#{w.queue ?? w.id}</span></td>
                    <td><strong>{w.patient_name}</strong><br /><small style={{ color: '#64748b' }}>{w.contact}</small></td>
                    <td>{w.patient_type ? <span className="pill">{w.patient_type.replace(/_/g, ' ')}</span> : '—'}</td>
                    <td>{fmtTime(w.arrival_time)}</td>
                    <td>{w.test_procedure || '—'}</td>
                    <td><span className={`badge ${badgeClass(w.status)}`}>{statusLabel(w.status)}</span></td>
                    <td>
                      <select className="action-select" value={w.status} onChange={(e) => updateStatus(w.id, e.target.value)}>
                        {STATUSES.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                      </select>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table></div>
          </div>
        ))}
      </div>
    </Shell>
  );
}
