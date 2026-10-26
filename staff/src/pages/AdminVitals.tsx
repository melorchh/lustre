import { useMemo, useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, postAction, isSuccess, fmtDate, fmtTime } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Modal } from '../components';
import type { VaccinationRow } from '../types';

interface PatientOption {
  id: number;
  name: string;
  email?: string;
}

interface DoctorOption {
  id: number;
  name: string;
  specialty?: string;
}

const VACCINES = [
  'Tetanus Toxoid (TT)',
  'Diptheria-Tetanus (DT)',
  'Hepatitis B',
  'Influenza',
  'COVID-19',
  'Other',
];

const EMPTY_FORM = {
  vaccine_name: '',
  vaccine_other: '',
  dose_label: 'Dose 1',
  administered_date: '',
  next_due_date: '',
  doctor_id: '',
  appointment_time: '',
  notes: '',
};

export default function AdminVitals() {
  const d = getInitial().data as {
    patients?: PatientOption[];
    doctors?: DoctorOption[];
    selectedId?: number;
    selectedName?: string;
    summary?: { next_due?: string };
    slots?: string[];
    vaccinations?: VaccinationRow[];
    allVaccinations?: VaccinationRow[];
    stats?: Record<string, number>;
  };
  const { showToast } = useToast();

  const patients = d.patients || [];
  const doctors = d.doctors || [];
  const slots = d.slots || [];
  const [selectedId, setSelectedId] = useState<number>(d.selectedId || patients[0]?.id || 0);
  const [selectedName, setSelectedName] = useState<string>(d.selectedName || patients[0]?.name || '');
  const [nextDue, setNextDue] = useState<string>(d.summary?.next_due || 'None scheduled');
  const [vaccinations, setVaccinations] = useState<VaccinationRow[]>(d.vaccinations || []);
  const [stats, setStats] = useState<Record<string, number>>(d.stats || {});
  const [q, setQ] = useState('');

  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ ...EMPTY_FORM });

  const today = new Date().toISOString().slice(0, 10);

  const filtered = useMemo(() => vaccinations.filter((v) =>
    !q || `${v.vaccine_name} ${v.dose_label}`.toLowerCase().includes(q.toLowerCase()),
  ), [vaccinations, q]);

  async function loadPatient(id: number) {
    if (!id) return;
    showLoading();
    try {
      const json = await fetchData<{
        data?: {
          selectedId?: number;
          selectedName?: string;
          summary?: { next_due?: string };
          vaccinations?: VaccinationRow[];
          stats?: Record<string, number>;
        };
      }>('api_admin_data.php', { view: 'vitals', patient: id });
      if (json?.data) {
        setSelectedId(json.data.selectedId || id);
        setSelectedName(json.data.selectedName || patients.find((p) => p.id === id)?.name || '');
        setNextDue(json.data.summary?.next_due || 'None scheduled');
        setVaccinations(json.data.vaccinations || []);
        setStats(json.data.stats || {});
        try {
          window.history.replaceState(null, '', `admin_vitals.php?patient=${id}`);
        } catch {
          /* ignore */
        }
      }
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  function openModal() {
    if (!selectedId) { showToast('Select a patient first', 'error'); return; }
    setForm({ ...EMPTY_FORM, administered_date: today, doctor_id: doctors[0] ? String(doctors[0].id) : '' });
    setModalOpen(true);
  }

  async function saveVaccination() {
    const vaccine = form.vaccine_name === 'Other' ? form.vaccine_other.trim() : form.vaccine_name.trim();
    if (!vaccine) { showToast('Please select a vaccine', 'error'); return; }
    if (!form.administered_date) { showToast('Please set the Administered Date', 'error'); return; }

    showLoading();
    try {
      const res = await postAction('admin_vitals_action.php', {
        action: 'add_vaccination',
        patient_id: selectedId,
        vaccine_name: vaccine,
        dose_label: form.dose_label,
        administered_date: form.administered_date,
        next_due_date: form.next_due_date,
        doctor_id: form.doctor_id,
        appointment_time: form.appointment_time,
        notes: form.notes,
      });

      interface AutoSchedule {
        next_dose?: string;
        date?: string;
        time?: string;
      }
      let ok = false;
      let auto: AutoSchedule | null = null;
      if (res.startsWith('{')) {
        try {
          const json = JSON.parse(res) as { success?: boolean; auto_scheduled?: AutoSchedule | null };
          ok = !!json.success;
          auto = json.auto_scheduled || null;
        } catch {
          ok = false;
        }
      } else {
        ok = isSuccess(res);
      }

      if (ok) {
        setModalOpen(false);
        await loadPatient(selectedId);
        if (auto?.date) {
          showToast(`Vaccination saved! Appointment auto-scheduled for ${auto.next_dose || 'next dose'} on ${fmtDate(auto.date)}.`, 'success');
        } else {
          showToast('Vaccination saved!', 'success');
        }
      } else {
        showToast(res.replace(/^error:\s*/i, ''), 'error');
      }
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  async function deleteVaccination(v: VaccinationRow) {
    if (!confirm(`Delete the ${v.vaccine_name} (${v.dose_label}) record? This cannot be undone.`)) return;
    showLoading();
    try {
      const res = await postAction('admin_vitals_action.php', {
        action: 'delete_vaccination',
        patient_id: selectedId,
        vaccination_id: v.id,
      });
      if (isSuccess(res)) {
        setVaccinations((prev) => prev.filter((x) => x.id !== v.id));
        showToast('Record deleted', 'success');
        loadPatient(selectedId);
      } else showToast(res.replace(/^error:\s*/i, ''), 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error', 'error');
    } finally {
      hideLoading();
    }
  }

  function dueBadge(v: VaccinationRow) {
    if (!v.next_due_date) return <span className="badge badge-done">Complete</span>;
    const diff = (new Date(v.next_due_date).getTime() - new Date(today).getTime()) / 86400000;
    return <span className={`badge ${diff <= 14 ? 'badge-due' : 'badge-done'}`}>{fmtDate(v.next_due_date)}</span>;
  }

  return (
    <Shell active="vitals" title="Vaccines">
      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{stats.total ?? 0}</div><div className="stat-label">Total Records</div></div>
        <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{stats.thisMonth ?? 0}</div><div className="stat-label">This Month</div></div>
        <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{stats.dueSoon ?? 0}</div><div className="stat-label">Due Soon (30 days)</div></div>
      </div>

      {selectedId > 0 && (
        <>
          <div className="patient-picker">
            <label htmlFor="patientSelect">Patient:</label>
            <select
              id="patientSelect"
              className="form-dropdown"
              value={selectedId}
              onChange={(e) => loadPatient(Number(e.target.value))}
            >
              {patients.map((p) => (
                <option key={p.id} value={p.id}>{p.name}{p.email ? ` — ${p.email}` : ''}</option>
              ))}
            </select>
          </div>

          <div className="summary-chips">
            <div className="summary-chip"><label>Patient</label><strong>{selectedName}</strong></div>
            <div className="summary-chip"><label>Next Vaccine Due</label><strong style={{ fontSize: '1rem' }}>{nextDue}</strong></div>
          </div>

          <div className="content-card">
            <div className="section-head">
              <h2>Vaccination Record</h2>
              <button type="button" className="btn-add" onClick={openModal}>+ Add Vaccination</button>
            </div>
            <div className="apt-toolbar">
              <input type="search" className="apt-search" placeholder="Search vaccine or dose..." value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
            <div className="table-scroll"><table>
              <thead><tr><th>Vaccine</th><th>Dose</th><th>Administered</th><th>Next Due</th><th>Notes</th><th>Actions</th></tr></thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr><td colSpan={6} className="empty-state-row">No vaccination records for this patient yet.</td></tr>
                ) : filtered.map((v) => (
                  <tr key={v.id}>
                    <td><strong>{v.vaccine_name}</strong></td>
                    <td><span className="badge badge-normal">{v.dose_label}</span></td>
                    <td><strong>{fmtDate(v.administered_date)}</strong></td>
                    <td>{dueBadge(v)}</td>
                    <td><span className="cell-sub">{v.notes || '—'}</span></td>
                    <td>
                      <button type="button" className="btn-delete btn-xs" onClick={() => deleteVaccination(v)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table></div>
          </div>
        </>
      )}

      <Modal open={modalOpen} onClose={() => setModalOpen(false)}>
        <h2>Add Vaccination</h2>
        <div className="form-grid">
          <div className="form-group">
            <label>Vaccine Name *</label>
            <select className="form-dropdown" value={form.vaccine_name} onChange={(e) => setForm({ ...form, vaccine_name: e.target.value })}>
              <option value="">Select Vaccine</option>
              {VACCINES.map((v) => <option key={v} value={v}>{v}</option>)}
            </select>
          </div>
          <div className="form-group">
            <label>Dose Given</label>
            <select className="form-dropdown" value={form.dose_label} onChange={(e) => setForm({ ...form, dose_label: e.target.value })}>
              {['Dose 1', 'Dose 2', 'Dose 3', 'Dose 4', 'Booster'].map((v) => <option key={v} value={v}>{v}</option>)}
            </select>
          </div>
        </div>
        {form.vaccine_name === 'Other' && (
          <div className="form-group">
            <label>Specify Vaccine</label>
            <input className="form-input" placeholder="Enter vaccine name" value={form.vaccine_other} onChange={(e) => setForm({ ...form, vaccine_other: e.target.value })} />
          </div>
        )}
        <div className="form-grid">
          <div className="form-group">
            <label>Administered Date *</label>
            <input type="date" className="form-input" max={today} value={form.administered_date} onChange={(e) => setForm({ ...form, administered_date: e.target.value })} />
          </div>
          <div className="form-group">
            <label>Next Due Date</label>
            <input type="date" className="form-input" min={today} value={form.next_due_date} onChange={(e) => setForm({ ...form, next_due_date: e.target.value })} />
          </div>
        </div>
        <div className="form-grid">
          <div className="form-group">
            <label>Doctor (for booking)</label>
            <select className="form-dropdown" value={form.doctor_id} onChange={(e) => setForm({ ...form, doctor_id: e.target.value })}>
              {doctors.map((doc) => <option key={doc.id} value={doc.id}>Dr. {doc.name}</option>)}
            </select>
          </div>
          <div className="form-group">
            <label>Preferred Time (booking)</label>
            <select className="form-dropdown" value={form.appointment_time} onChange={(e) => setForm({ ...form, appointment_time: e.target.value })}>
              {slots.map((s) => <option key={s} value={s}>{fmtTime(s)}</option>)}
            </select>
          </div>
        </div>
        <p className="form-hint">
          Set <strong>Dose Given</strong> to the dose administered now. If you also set a <strong>Next Due Date</strong>, the system
          automatically books a pending appointment for the next dose on that date using the chosen doctor and time (the next free
          slot is picked if your preference is taken).
        </p>
        <div className="form-group">
          <label>Notes</label>
          <textarea className="form-input" rows={3} placeholder="Batch, remarks, or next-dose instructions..." value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
        </div>
        <div className="form-actions">
          <button className="btn-secondary" onClick={() => setModalOpen(false)}>Cancel</button>
          <button className="btn-primary" onClick={saveVaccination}>Save Vaccination</button>
        </div>
      </Modal>
    </Shell>
  );
}
