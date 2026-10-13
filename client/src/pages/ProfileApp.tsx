import { useRef, useState } from 'react';
import { getInitialData, initials, formatStamp } from '../api';
import Shell from '../shell';
import Hero from '../components/Hero';
import { SvgUser, SvgHeart, SvgCheck } from '../components/icons';
import { useToast } from '../hooks';

function fmtNum(v: number | null | undefined, digits = 1): string {
  if (v === null || v === undefined) return '';
  return Number(v).toFixed(digits);
}

function bmi(weight: number | null, height: number | null): number | null {
  if (!weight || !height) return null;
  const m = height / 100;
  if (m <= 0) return null;
  return weight / (m * m);
}

function bmiLabel(b: number): { label: string; cls: string } {
  if (b < 18.5) return { label: "Underweight", cls: 'metric-badge--blue' };
  if (b < 25) return { label: "Normal", cls: 'metric-badge--teal' };
  if (b < 30) return { label: "Overweight", cls: 'metric-badge--amber' };
  return { label: "Obese", cls: 'metric-badge--red' };
}

export default function ProfileApp() {
  const data = getInitialData();
  const patient = data.patient;
  const { showToast } = useToast();

  const [info, setInfo] = useState({
    name: patient?.name ?? '',
    email: patient?.email ?? '',
    contact: patient?.contact ?? '',
    address: patient?.address ?? '',
    age: patient?.age === null || patient?.age === undefined ? '' : String(patient.age),
  });

  const [metrics, setMetrics] = useState({
    height: patient?.height_cm === null || patient?.height_cm === undefined ? '' : fmtNum(patient.height_cm),
    weight: patient?.weight_kg === null || patient?.weight_kg === undefined ? '' : fmtNum(patient.weight_kg),
  });

  const [savingInfo, setSavingInfo] = useState(false);
  const [savingMetrics, setSavingMetrics] = useState(false);
  const [editingInfo, setEditingInfo] = useState(false);
  const [editingMetrics, setEditingMetrics] = useState(false);
  const [donePopup, setDonePopup] = useState<{ title: string; msg: string } | null>(null);

  const infoSnapshot = useRef({ ...info });
  const metricsSnapshot = useRef({ ...metrics });

  const startEditInfo = () => {
    infoSnapshot.current = { ...info };
    setEditingInfo(true);
  };
  const cancelEditInfo = () => {
    setInfo({ ...infoSnapshot.current });
    setEditingInfo(false);
  };
  const startEditMetrics = () => {
    metricsSnapshot.current = { ...metrics };
    setEditingMetrics(true);
  };
  const cancelEditMetrics = () => {
    setMetrics({ ...metricsSnapshot.current });
    setEditingMetrics(false);
  };

  const saveInfo = async () => {
    if (!info.name.trim() || !info.email.trim() || !info.contact.trim()) {
      showToast("Name, email and contact are required.", 'error');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(info.email.trim())) {
      showToast("Please enter a valid email address.", 'error');
      return;
    }
    setSavingInfo(true);
    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('name', info.name.trim());
    fd.append('email', info.email.trim());
    fd.append('contact', info.contact.trim());
    fd.append('address', info.address.trim());
    fd.append('age', info.age.trim());
    try {
      const res = await fetch('patient_profile_action.php', { method: 'POST', body: fd });
      const text = await res.text();
      if (text.trim() === 'success') {
        setEditingInfo(false);
        setDonePopup({ title: "Profile updated.", msg: "Your personal information has been updated successfully." });
      } else {
        showToast(text.trim().replace(/^error:\s*/i, '') || "Failed to update profile.", 'error');
      }
    } catch {
      showToast("Network error. Please try again.", 'error');
    } finally {
      setSavingInfo(false);
    }
  };

  const saveMetrics = async () => {
    const h = metrics.height.trim();
    const w = metrics.weight.trim();
    if (h && (Number(h) <= 0 || Number(h) > 300)) {
      showToast("Please enter a valid height (30–300 cm).", 'error');
      return;
    }
    if (w && (Number(w) <= 0 || Number(w) > 500)) {
      showToast("Please enter a valid weight (1–500 kg).", 'error');
      return;
    }
    setSavingMetrics(true);
    const fd = new FormData();
    fd.append('action', 'update_metrics');
    fd.append('height_cm', h);
    fd.append('weight_kg', w);
    try {
      const res = await fetch('patient_profile_action.php', { method: 'POST', body: fd });
      const text = await res.text();
      if (text.trim() === 'success') {
        setEditingMetrics(false);
        setDonePopup({ title: "Measurements updated.", msg: "Your measurements have been updated successfully." });
      } else {
        showToast(text.trim().replace(/^error:\s*/i, '') || "Failed to update measurements.", 'error');
      }
    } catch {
      showToast("Network error. Please try again.", 'error');
    } finally {
      setSavingMetrics(false);
    }
  };

  const bmiVal = bmi(Number(metrics.weight) || null, Number(metrics.height) || null);
  const bmiInfo = bmiVal ? bmiLabel(bmiVal) : null;

  return (
    <Shell page="profile" title={"My Profile"} patientName={data.patientName}>
      <main className="page-content">
        <Hero
          tag={"Account"}
          title={"My Profile"}
          sub={"View and update your personal information and health measurements."}
        />

        <div className="profile-summary">
          <div className="profile-avatar">{initials(patient?.name || data.patientName)}</div>
          <div className="profile-summary-info">
            <h2>{patient?.name || data.patientName}</h2>
            <p>{patient?.email || '—'}</p>
            <span className="profile-member-since">
              {patient?.created_at ? `Member since ${formatStamp(patient.created_at)}` : ''}
            </span>
          </div>
        </div>

        <section className="profile-card">
          <div className="section-hdr">
            <h2>{"Personal Information"}</h2>
            <p>{"Keep your contact details up to date so we can reach you easily."}</p>
          </div>

          <div className="profile-form-grid">
            <div className="field-block">
              <label className="field-label" htmlFor="pf-name">{"Full Name"}</label>
              <input
                id="pf-name"
                className="text-input"
                type="text"
                value={info.name}
                onChange={(e) => setInfo({ ...info, name: e.target.value })}
                placeholder={"Your full name"}
                disabled={!editingInfo}
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-email">{"Email Address"}</label>
              <input
                id="pf-email"
                className="text-input"
                type="email"
                value={info.email}
                onChange={(e) => setInfo({ ...info, email: e.target.value })}
                placeholder="you@example.com"
                disabled={!editingInfo}
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-contact">{"Contact Number"}</label>
              <input
                id="pf-contact"
                className="text-input"
                type="tel"
                value={info.contact}
                onChange={(e) => setInfo({ ...info, contact: e.target.value })}
                placeholder="e.g. 0917 123 4567"
                disabled={!editingInfo}
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-age">{"Age"}</label>
              <input
                id="pf-age"
                className="text-input"
                type="number"
                min={0}
                max={150}
                value={info.age}
                onChange={(e) => setInfo({ ...info, age: e.target.value })}
                placeholder={"Age"}
                disabled={!editingInfo}
              />
            </div>

            <div className="field-block field-block--full">
              <label className="field-label" htmlFor="pf-address">{"Address"}</label>
              <input
                id="pf-address"
                className="text-input"
                type="text"
                value={info.address}
                onChange={(e) => setInfo({ ...info, address: e.target.value })}
                placeholder={"Your home address"}
                disabled={!editingInfo}
              />
            </div>
          </div>

          {!editingInfo ? (
            <div className="profile-actions">
              <button type="button" className="submit-btn" onClick={startEditInfo}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                {"Edit"}
              </button>
            </div>
          ) : (
            <div className="profile-actions">
              <button type="button" className="submit-btn" disabled={savingInfo} onClick={saveInfo}>
                {savingInfo ? <span className="btn-spinner" aria-hidden="true" /> : <SvgUser size={18} />}
                {"Save Changes"}
              </button>
              <button type="button" className="btn-outline" disabled={savingInfo} onClick={cancelEditInfo}>
                {"Cancel"}
              </button>
            </div>
          )}
        </section>

        <section className="profile-card">
          <div className="section-hdr">
            <h2>{"Health Measurements"}</h2>
            <p>{"Update your current height and weight. This helps us track your health."}</p>
          </div>

          <div className="profile-form-grid">
            <div className="field-block">
              <label className="field-label" htmlFor="pf-height">{"Height (cm)"}</label>
              <input
                id="pf-height"
                className="text-input"
                type="number"
                inputMode="decimal"
                step="any"
                min={30}
                max={300}
                value={metrics.height}
                onChange={(e) => setMetrics({ ...metrics, height: e.target.value })}
                placeholder="e.g. 165"
                disabled={!editingMetrics}
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-weight">{"Weight (kg)"}</label>
              <input
                id="pf-weight"
                className="text-input"
                type="number"
                inputMode="decimal"
                step="any"
                min={1}
                max={500}
                value={metrics.weight}
                onChange={(e) => setMetrics({ ...metrics, weight: e.target.value })}
                placeholder="e.g. 58.5"
                disabled={!editingMetrics}
              />
            </div>
          </div>

          {bmiVal !== null && bmiInfo ? (
            <div className="bmi-card">
              <div className="bmi-icon">
                <SvgHeart size={20} />
              </div>
              <div className="bmi-info">
                <span className="bmi-label">{"Your BMI"}</span>
                <strong>{bmiVal.toFixed(1)}</strong>
                <span className={`metric-badge ${bmiInfo.cls}`}>{bmiInfo.label}</span>
              </div>
            </div>
          ) : (
            <p className="bmi-hint">{"Enter both height and weight to see your BMI."}</p>
          )}

          {!editingMetrics ? (
            <div className="profile-actions">
              <button type="button" className="submit-btn" onClick={startEditMetrics}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                {"Edit"}
              </button>
            </div>
          ) : (
            <div className="profile-actions">
              <button type="button" className="submit-btn" disabled={savingMetrics} onClick={saveMetrics}>
                {savingMetrics ? <span className="btn-spinner" aria-hidden="true" /> : <SvgCheck size={18} />}
                {"Save Changes"}
              </button>
              <button type="button" className="btn-outline" disabled={savingMetrics} onClick={cancelEditMetrics}>
                {"Cancel"}
              </button>
            </div>
          )}
        </section>
      </main>

      {donePopup && (
        <div className="modal-alert" onClick={() => setDonePopup(null)}>
          <div
            className="modal-alert-card"
            role="alertdialog"
            aria-modal="true"
            onClick={(e) => e.stopPropagation()}
          >
            <span className="modal-alert-icon modal-alert-icon--ok" aria-hidden="true">
              <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20 6 9 17l-5-5" />
              </svg>
            </span>
            <h3 className="modal-alert-title">{donePopup.title}</h3>
            <p className="modal-alert-msg">{donePopup.msg}</p>
            <button className="btn-confirm" onClick={() => setDonePopup(null)}>
              {"OK, got it"}
            </button>
          </div>
        </div>
      )}
    </Shell>
  );
}