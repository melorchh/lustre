import { Fragment, useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate, fmtTime } from '../api';
import type { PatientRow } from '../types';

interface PatientAppt {
  patient_id: number;
  id: number;
  unique_code?: string;
  appointment_date: string;
  appointment_time: string;
  service_requested?: string;
  status: string;
  doctor_name?: string;
}

export default function AdminClients() {
  const d = getInitial().data as {
    patients?: PatientRow[];
    appointments?: PatientAppt[];
    stats?: Record<string, number>;
  };
  const patients = d.patients || [];
  const appointments = d.appointments || [];
  const [q, setQ] = useState('');
  const [qAddr, setQAddr] = useState('');
  const [qAge, setQAge] = useState('');
  const [type, setType] = useState('');
  const [expanded, setExpanded] = useState<number | null>(null);
  const stats = d.stats || {};

  const apptsByPatient = useMemo(() => {
    const map: Record<number, PatientAppt[]> = {};
    for (const a of appointments) (map[a.patient_id] ||= []).push(a);
    return map;
  }, [appointments]);

  const filtered = useMemo(() => patients.filter((p) => {
    const hay = `${p.name} ${p.email} ${p.unique_code}`.toLowerCase();
    if (q && !hay.includes(q.toLowerCase())) return false;
    if (qAddr && !String(p.address || '').toLowerCase().includes(qAddr.toLowerCase())) return false;
    if (qAge && !String(p.age ?? '').includes(qAge)) return false;
    if (type && (p.patient_type || '') !== type) return false;
    return true;
  }), [patients, q, qAddr, qAge, type]);

  return (
    <Shell active="clients" title="Patients">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? patients.length}</div><div className="stat-label">Total Patients</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{stats.active ?? 0}</div><div className="stat-label">Recently Active</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.walkins ?? 0}</div><div className="stat-label">Walk-ins</div></div>
      </div>

      <div className="content-card">
        <h2>Patient Directory</h2>
        <div className="apt-toolbar">
          <input type="search" className="apt-search" placeholder="Search name, email or ID..." value={q} onChange={(e) => setQ(e.target.value)} />
          <input type="search" className="apt-search" placeholder="Search address..." value={qAddr} onChange={(e) => setQAddr(e.target.value)} />
          <input type="search" className="apt-search apt-search--age" placeholder="Age..." value={qAge} onChange={(e) => setQAge(e.target.value)} />
          <select className="apt-select" value={type} onChange={(e) => setType(e.target.value)}>
            <option value="">All Types</option>
            <option value="infant">Infant</option>
            <option value="child">Child</option>
            <option value="adolescent">Adolescent</option>
            <option value="adult">Adult</option>
            <option value="elderly">Elderly</option>
          </select>
        </div>
        <div className="table-scroll"><table id="patientsTable">
          <thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Type</th><th>Age</th><th>Appointments</th><th>Last Visit</th><th></th></tr></thead>
          <tbody>
            {filtered.length === 0 ? (
              <tr><td colSpan={8} className="empty-state-row">No patients found.</td></tr>
            ) : filtered.map((p) => {
              const appts = apptsByPatient[p.id] || [];
              const open = expanded === p.id;
              return (
                <Fragment key={p.id}>
                  <tr className={open ? 'is-open' : ''} style={{ cursor: 'pointer' }} onClick={() => setExpanded(open ? null : p.id)}>
                    <td>#{p.id}</td>
                    <td><strong>{p.name}</strong><br /><small style={{ color: '#64748b' }}>{p.unique_code}</small></td>
                    <td>{p.email}<br /><small style={{ color: '#64748b' }}>{p.contact}</small></td>
                    <td>{p.patient_type ? <span className="pill">{p.patient_type.replace(/_/g, ' ')}</span> : '—'}</td>
                    <td>{p.age ?? '—'}</td>
                    <td>{p.appointment_count ?? appts.length}</td>
                    <td>{fmtDate(p.last_appointment)}</td>
                    <td onClick={(e) => e.stopPropagation()}>
                      <div className="row-actions">
                        <button type="button" className="btn-secondary btn-xs" onClick={() => setExpanded(open ? null : p.id)}>{open ? 'Hide' : 'Details'}</button>
                        <a className="btn-secondary btn-xs" href={`client_profile.php?id=${p.id}`}>View</a>
                      </div>
                    </td>
                  </tr>
                  {open && (
                    <tr className="client-detail">
                      <td colSpan={8}>
                        <div className="client-detail-grid">
                          <div><span>Email</span><strong>{p.email || '—'}</strong></div>
                          <div><span>Contact</span><strong>{p.contact || '—'}</strong></div>
                          <div><span>Address</span><strong>{p.address || '—'}</strong></div>
                          <div><span>Gender</span><strong>{p.gender || '—'}</strong></div>
                          <div><span>Type</span><strong>{p.patient_type || '—'}</strong></div>
                          <div><span>Age</span><strong>{p.age ?? '—'}</strong></div>
                          <div><span>Weight</span><strong>{p.weight_kg != null ? `${p.weight_kg} kg` : '—'}</strong></div>
                          <div><span>Height</span><strong>{p.height_cm != null ? `${p.height_cm} cm` : '—'}</strong></div>
                          <div><span>Registered</span><strong>{fmtDate(p.created_at)}</strong></div>
                          <div><span>Source</span><strong>{p.is_walk_in ? 'Walk-in' : 'Registered'}</strong></div>
                        </div>

                        <h3 style={{ margin: '1rem 0 .5rem' }}>Appointment Archive</h3>
                        {appts.length === 0 ? (
                          <p className="empty-note" style={{ margin: 0 }}>No appointments on record.</p>
                        ) : (
                          <div className="table-scroll">
                            <table>
                              <thead><tr><th>#</th><th>Date</th><th>Time</th><th>Doctor</th><th>Service / Test</th><th>Status</th></tr></thead>
                              <tbody>
                                {appts.map((a) => (
                                  <tr key={a.id}>
                                    <td>#{a.id}</td>
                                    <td>{fmtDate(a.appointment_date)}</td>
                                    <td>{fmtTime(a.appointment_time)}</td>
                                    <td>{a.doctor_name ? `Dr. ${a.doctor_name}` : '—'}</td>
                                    <td>{a.service_requested || '—'}</td>
                                    <td><span className={`badge badge-${a.status}`}>{a.status}</span></td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        )}

                        <div className="form-actions" style={{ justifyContent: 'flex-start', marginTop: '.85rem' }}>
                          <a className="btn-primary btn-xs" href={`client_profile.php?id=${p.id}`}>View full profile</a>
                        </div>
                      </td>
                    </tr>
                  )}
                </Fragment>
              );
            })}
          </tbody>
        </table></div>
      </div>
    </Shell>
  );
}
