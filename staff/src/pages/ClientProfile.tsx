import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction, isSuccess, fmtDate, fmtTime, fmtDateTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import type { AppointmentRow, LabTestRow, VitalRow } from '../types';

interface PatientInfo {
  id: number;
  unique_code?: string;
  name: string;
  email?: string;
  contact?: string;
  address?: string;
  patient_type?: string;
  age?: number | null;
  gender?: string | null;
  weight_kg?: number | null;
  height_cm?: number | null;
  created_at?: string;
}

interface Props {
  portal: 'admin' | 'doctor';
}

/**
 * Shared patient profile page. `portal` decides the sidebar + which pharmacy/
 * result action endpoints are available (doctor pages can write results).
 */
export default function ClientProfile({ portal }: Props) {
  const data = getInitial();
  const d = data.data as {
    patient?: PatientInfo;
    appointments?: AppointmentRow[];
    vitals?: VitalRow[];
    labs?: LabTestRow[];
    vitalsByAppt?: Record<string, VitalRow[]>;
    lastVisit?: string | null;
  };
  const { showToast } = useToast();
  const patient = d.patient || ({ id: 0, name: 'Unknown' } as PatientInfo);
  const [appointments] = useState<AppointmentRow[]>(d.appointments || []);
  const [vitals] = useState<VitalRow[]>(d.vitals || []);
  const [labs] = useState<LabTestRow[]>(d.labs || []);

  const [tab, setTab] = useState<'appointments' | 'vitals' | 'labs'>('appointments');
  const [latestVital] = useState<VitalRow | null>(
    vitals.length ? vitals[0] : null,
  );

  const totalVisits = appointments.length;
  const lastVisit = d.lastVisit || (appointments[0]?.appointment_date ?? null);

  const bmi = useMemo(() => {
    const w = Number(patient.weight_kg || 0);
    const h = Number(patient.height_cm || 0) / 100;
    if (w > 0 && h > 0) return (w / (h * h)).toFixed(1);
    return null;
  }, [patient.weight_kg, patient.height_cm]);

  const canWriteResults = portal === 'doctor';

  async function saveResult(appt: AppointmentRow, text: string) {
    showLoading();
    try {
      const res = await postAction('doctor_result_action.php', {
        appointment_id: appt.id,
        result: text,
      });
      if (isSuccess(res)) showToast('Result saved', 'success');
      else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  return (
    <Shell active={portal === 'doctor' ? 'clients' : 'clients'} title={`Patient: ${patient.name}`}>
      <div className="profile-header">
        <div className="profile-avatar">{(patient.name || '?').charAt(0).toUpperCase()}</div>
        <div className="profile-info">
          <h1>{patient.name}</h1>
          <p className="profile-code">Patient ID: {patient.unique_code}</p>
          <div className="profile-tags">
            {patient.patient_type && <span className="pill">{patient.patient_type.replace(/_/g, ' ')}</span>}
            {patient.gender && <span className="pill">{patient.gender}</span>}
            {patient.age != null && <span className="pill">{patient.age} yrs</span>}
          </div>
        </div>
        <div className="profile-stats">
          <div className="pstat"><span className="pstat-num">{totalVisits}</span><span className="pstat-label">Visits</span></div>
          <div className="pstat"><span className="pstat-num">{vitals.length}</span><span className="pstat-label">Vitals</span></div>
          <div className="pstat"><span className="pstat-num">{labs.length}</span><span className="pstat-label">Lab Tests</span></div>
        </div>
      </div>

      <div className="profile-grid">
        <div className="content-card">
          <h2>Demographics</h2>
          <table><tbody>
            <tr><th>Email</th><td>{patient.email || '—'}</td></tr>
            <tr><th>Contact</th><td>{patient.contact || '—'}</td></tr>
            <tr><th>Address</th><td>{patient.address || '—'}</td></tr>
            <tr><th>Weight</th><td>{patient.weight_kg ? `${patient.weight_kg} kg` : '—'}</td></tr>
            <tr><th>Height</th><td>{patient.height_cm ? `${patient.height_cm} cm` : '—'}</td></tr>
            <tr><th>BMI</th><td>{bmi || '—'}</td></tr>
            <tr><th>Last Visit</th><td>{fmtDate(lastVisit)}</td></tr>
            <tr><th>Registered</th><td>{fmtDate(patient.created_at)}</td></tr>
          </tbody></table>
        </div>

        <div className="content-card">
          <h2>Latest Vitals</h2>
          {latestVital ? (
            <table><tbody>
              <tr><th>Date</th><td>{fmtDateTime(latestVital.visit_date)}</td></tr>
              <tr><th>Blood Pressure</th><td>{latestVital.blood_pressure || '—'}</td></tr>
              <tr><th>Heart Rate</th><td>{latestVital.heart_rate ? `${latestVital.heart_rate} bpm` : '—'}</td></tr>
              <tr><th>Weight</th><td>{latestVital.weight_kg ? `${latestVital.weight_kg} kg` : '—'}</td></tr>
              <tr><th>Blood Pressure</th><td>{latestVital.blood_pressure || '—'}</td></tr>
            </tbody></table>
          ) : <p className="empty-note">No vitals recorded yet.</p>}
        </div>
      </div>

      <div className="content-card">
        <div className="profile-tabs">
          <button className={`ptab${tab === 'appointments' ? ' active' : ''}`} onClick={() => setTab('appointments')}>Appointments</button>
          <button className={`ptab${tab === 'vitals' ? ' active' : ''}`} onClick={() => setTab('vitals')}>Vitals</button>
          <button className={`ptab${tab === 'labs' ? ' active' : ''}`} onClick={() => setTab('labs')}>Lab Tests</button>
        </div>

        {tab === 'appointments' && (
          <div className="table-scroll"><table>
            <thead><tr><th>Date</th><th>Doctor</th><th>Service</th><th>Status</th><th>Payment</th><th>Result</th></tr></thead>
            <tbody>
              {appointments.length === 0 ? (
                <tr><td colSpan={6} className="empty-state-row">No appointments.</td></tr>
              ) : appointments.map((a) => (
                <tr key={a.id}>
                  <td>{fmtDate(a.appointment_date)}<br /><small>{fmtTime(a.appointment_time)}</small></td>
                  <td>Dr. {a.doctor_name}</td>
                  <td>{a.service_requested || '—'}</td>
                  <td><span className={`badge badge-${a.status}`}>{a.status}</span></td>
                  <td>{a.payment_status === 'paid' ? 'Paid' : 'Unpaid'}</td>
                  <td>
                    {canWriteResults ? (
                      <ResultEditor value={a.result || ''} onSave={(t) => saveResult(a, t)} />
                    ) : (a.result ? <span className="badge badge-completed">Ready</span> : <span style={{ color: '#94a3b8' }}>—</span>)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div>
        )}

        {tab === 'vitals' && (
          <div className="table-scroll"><table>
            <thead><tr><th>Date</th><th>BP</th><th>HR</th><th>Weight</th><th>Height</th><th>Notes</th></tr></thead>
            <tbody>
              {vitals.length === 0 ? (
                <tr><td colSpan={6} className="empty-state-row">No vitals.</td></tr>
              ) : vitals.map((v) => (
                <tr key={v.id}>
                  <td>{fmtDate(v.visit_date)}</td>
                  <td>{v.blood_pressure || '—'}</td>
                  <td>{v.heart_rate ? `${v.heart_rate} bpm` : '—'}</td>
                  <td>{v.weight_kg ? `${v.weight_kg} kg` : '—'}</td>
                  <td>{v.height_cm ? `${v.height_cm} cm` : '—'}</td>
                  <td>{v.notes || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table></div>
        )}

        {tab === 'labs' && (
          <div className="table-scroll"><table>
            <thead><tr><th>Test</th><th>Priority</th><th>Status</th><th>Scheduled</th><th>Result</th></tr></thead>
            <tbody>
              {labs.length === 0 ? (
                <tr><td colSpan={5} className="empty-state-row">No lab tests.</td></tr>
              ) : labs.map((l) => (
                <tr key={l.id}>
                  <td>{l.test_type}</td>
                  <td><span className={`badge badge-${l.priority === 'urgent' ? 'pending' : 'confirmed'}`}>{l.priority}</span></td>
                  <td><span className={`badge badge-${l.status === 'completed' ? 'completed' : 'pending'}`}>{l.status}</span></td>
                  <td>{l.scheduled_date ? fmtDate(l.scheduled_date) : '—'}</td>
                  <td>{l.result || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table></div>
        )}
      </div>
    </Shell>
  );
}

function ResultEditor({ value, onSave }: { value: string; onSave: (t: string) => void }) {
  const [editing, setEditing] = useState(false);
  const [text, setText] = useState(value);
  if (!editing) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
        <span>{value ? <span className="badge badge-completed">Ready</span> : <span style={{ color: '#94a3b8' }}>No result</span>}</span>
        <button className="btn-secondary btn-xs" onClick={() => setEditing(true)}>{value ? 'Edit' : 'Add'}</button>
      </div>
    );
  }
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      <textarea className="form-input" rows={3} value={text} onChange={(e) => setText(e.target.value)} />
      <div style={{ display: 'flex', gap: 6 }}>
        <button className="btn-primary btn-xs" onClick={() => { onSave(text); setEditing(false); }}>Save</button>
        <button className="btn-secondary btn-xs" onClick={() => { setText(value); setEditing(false); }}>Cancel</button>
      </div>
    </div>
  );
}
