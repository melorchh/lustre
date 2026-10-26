import { Fragment, useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate, fmtTime } from '../api';
import { Select } from '../components';
import { Card, EmptyState, Field, FilterBar, Stack, StatCard, StatGrid } from '../ui';
import { IconChevronRight, IconPatients, IconSearch, IconWalkins } from '../icons';
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

  const hasFilters = Boolean(q || qAddr || qAge || type);

  function resetFilters() {
    setQ('');
    setQAddr('');
    setQAge('');
    setType('');
  }

  return (
    <Shell active="clients" title="Patients">
      <Stack>
        <StatGrid>
          <StatCard
            icon={<IconPatients />}
            label="Total patients"
            value={stats.total ?? patients.length}
            hint="Registered in the clinic"
          />
          <StatCard
            icon={<IconSearch />}
            label="Recently active"
            value={stats.active ?? 0}
            hint="Visited in the last 30 days"
          />
          <StatCard
            icon={<IconWalkins />}
            label="Walk-ins"
            value={stats.walkins ?? 0}
            hint="Registered at the counter"
            tone="amber"
          />
        </StatGrid>

        <Card
          title="Patient directory"
          subtitle={`${filtered.length} of ${patients.length} patient${patients.length === 1 ? '' : 's'}`}
          actions={hasFilters ? <button type="button" className="ap-btn-text" onClick={resetFilters}>Reset filters</button> : undefined}
        >
          <FilterBar>
            <Field label="Search" className="ap-field--grow">
              <div className="ap-input-icon">
                <IconSearch size={16} />
                <input
                  type="search"
                  className="apt-search"
                  placeholder="Name, email or patient ID..."
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                />
              </div>
            </Field>
            <Field label="Address" className="ap-field--grow">
              <input
                type="search"
                className="apt-search"
                placeholder="Barangay, city or street..."
                value={qAddr}
                onChange={(e) => setQAddr(e.target.value)}
              />
            </Field>
            <Field label="Age" className="ap-field--xs">
              <input
                type="search"
                className="apt-search"
                placeholder="Any"
                value={qAge}
                onChange={(e) => setQAge(e.target.value)}
              />
            </Field>
            <Field label="Patient type">
              <Select className="apt-select" value={type} onChange={(e) => setType(e.target.value)}>
                <option value="">All types</option>
                <option value="infant">Infant</option>
                <option value="child">Child</option>
                <option value="adolescent">Adolescent</option>
                <option value="adult">Adult</option>
                <option value="elderly">Elderly</option>
              </Select>
            </Field>
          </FilterBar>

          <div className="table-scroll">
            <table id="patientsTable">
              <thead>
                <tr><th>ID</th><th>Patient</th><th>Contact</th><th>Type</th><th>Age</th><th>Visits</th><th>Last visit</th><th aria-label="Actions"></th></tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr>
                    <td colSpan={8}>
                      <EmptyState
                        icon={<IconPatients size={20} />}
                        title="No matching patients"
                        hint={hasFilters ? 'Try a different name, address or type.' : 'Patients will appear here once they register.'}
                      />
                    </td>
                  </tr>
                ) : filtered.map((p) => {
                  const appts = apptsByPatient[p.id] || [];
                  const open = expanded === p.id;
                  return (
                    <Fragment key={p.id}>
                      <tr
                        className={`ap-row${open ? ' is-open' : ''}`}
                        onClick={() => setExpanded(open ? null : p.id)}
                      >
                        <td className="ap-id">#{p.id}</td>
                        <td>
                          <span className="ap-strong">{p.name}</span>
                          <small className="ap-sub">{p.unique_code}</small>
                        </td>
                        <td>
                          <span>{p.email || '—'}</span>
                          <small className="ap-sub">{p.contact || 'No contact number'}</small>
                        </td>
                        <td>{p.patient_type ? <span className="pill">{p.patient_type.replace(/_/g, ' ')}</span> : '—'}</td>
                        <td>{p.age ?? '—'}</td>
                        <td><span className="ap-count-badge">{p.appointment_count ?? appts.length}</span></td>
                        <td>{p.last_appointment ? fmtDate(p.last_appointment) : '—'}</td>
                        <td onClick={(e) => e.stopPropagation()}>
                          <div className="ap-row-actions ap-row-actions--inline">
                            <a className="ap-icon-btn" href={`client_profile.php?id=${p.id}`} title="Open full profile" aria-label={`Open ${p.name}'s profile`}>
                              <IconChevronRight size={17} />
                            </a>
                          </div>
                        </td>
                      </tr>
                      {open && (
                        <tr className="client-detail">
                          <td colSpan={8}>
                            <div className="ap-detail">
                              <div className="ap-detail-head">
                                <div>
                                  <h3>Patient details</h3>
                                  <p>Full record and appointment archive for {p.name}.</p>
                                </div>
                                <a className="btn-primary btn-xs" href={`client_profile.php?id=${p.id}`}>View full profile</a>
                              </div>

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

                              <h3 className="ap-subsection-title">Appointment archive</h3>
                              {appts.length === 0 ? (
                                <p className="empty-note" style={{ margin: 0 }}>No appointments on record.</p>
                              ) : (
                                <div className="table-scroll">
                                  <table>
                                    <thead><tr><th>#</th><th>Date</th><th>Time</th><th>Doctor</th><th>Service / test</th><th>Status</th></tr></thead>
                                    <tbody>
                                      {appts.map((a) => (
                                        <tr key={a.id}>
                                          <td className="ap-id">#{a.id}</td>
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
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Card>
      </Stack>
    </Shell>
  );
}
