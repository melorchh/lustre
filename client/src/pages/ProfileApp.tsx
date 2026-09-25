import { useEffect, useRef, useState } from 'react';
import { getInitialData, initials, formatStamp } from '../api';
import Shell from '../shell';
import Hero from '../components/Hero';
import { SvgUser, SvgHeart, SvgCheck, SvgCheckCircle } from '../components/icons';
import { useToast } from '../hooks';
import { useLang } from '../lang';

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
  const { t } = useLang();
  if (b < 18.5) return { label: t('prof_underweight'), cls: 'metric-badge--blue' };
  if (b < 25) return { label: t('prof_normal'), cls: 'metric-badge--teal' };
  if (b < 30) return { label: t('prof_overweight'), cls: 'metric-badge--amber' };
  return { label: t('prof_obese'), cls: 'metric-badge--red' };
}

export default function ProfileApp() {
  const data = getInitialData();
  const patient = data.patient;
  const { showToast } = useToast();
  const { t } = useLang();

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
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const successTimer = useRef<number | undefined>(undefined);

  const showSuccess = (msg: string) => {
    window.clearTimeout(successTimer.current);
    setSuccessMsg(msg);
    successTimer.current = window.setTimeout(() => setSuccessMsg(null), 5000);
  };

  const saveInfo = async () => {
    if (!info.name.trim() || !info.email.trim() || !info.contact.trim()) {
      showToast(t('prof_required'), 'error');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(info.email.trim())) {
      showToast(t('prof_email_invalid'), 'error');
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
        showSuccess(t('prof_updated_ok'));
        showToast(t('prof_update_toast'), 'success');
      } else {
        showToast(text.trim().replace(/^error:\s*/i, '') || t('prof_update_failed'), 'error');
      }
    } catch {
      showToast(t('prof_network_err'), 'error');
    } finally {
      setSavingInfo(false);
    }
  };

  const saveMetrics = async () => {
    const h = metrics.height.trim();
    const w = metrics.weight.trim();
    if (h && (Number(h) <= 0 || Number(h) > 300)) {
      showToast(t('prof_height_invalid'), 'error');
      return;
    }
    if (w && (Number(w) <= 0 || Number(w) > 500)) {
      showToast(t('prof_weight_invalid'), 'error');
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
        showSuccess(t('prof_measurements_ok'));
        showToast(t('prof_measurements_toast'), 'success');
      } else {
        showToast(text.trim().replace(/^error:\s*/i, '') || t('prof_measurements_failed'), 'error');
      }
    } catch {
      showToast(t('prof_network_err'), 'error');
    } finally {
      setSavingMetrics(false);
    }
  };

  const bmiVal = bmi(Number(metrics.weight) || null, Number(metrics.height) || null);
  const bmiInfo = bmiVal ? bmiLabel(bmiVal) : null;

  useEffect(() => () => window.clearTimeout(successTimer.current), []);

  return (
    <Shell page="profile" title={t('prof_title')} patientName={data.patientName}>
      <main className="page-content">
        <Hero
          tag={t('prof_tag')}
          title={t('prof_title')}
          sub={t('prof_hero_sub')}
        />

        <div className="profile-summary">
          <div className="profile-avatar">{initials(patient?.name || data.patientName)}</div>
          <div className="profile-summary-info">
            <h2>{patient?.name || data.patientName}</h2>
            <p>{patient?.email || '—'}</p>
            <span className="profile-member-since">
              {patient?.created_at ? t('prof_member_since', { d: formatStamp(patient.created_at) }) : ''}
            </span>
          </div>
        </div>

        {successMsg && (
          <div className="profile-success" role="status">
            <span className="profile-success-icon">
              <SvgCheckCircle size={20} />
            </span>
            <span className="profile-success-msg">{successMsg}</span>
            <button
              type="button"
              className="profile-success-close"
              onClick={() => setSuccessMsg(null)}
              aria-label={t('prof_dismiss')}
            >
              &times;
            </button>
          </div>
        )}

        <section className="profile-card">
          <div className="section-hdr">
            <h2>{t('prof_personal_hdr')}</h2>
            <p>{t('prof_personal_sub')}</p>
          </div>

          <div className="profile-form-grid">
            <div className="field-block">
              <label className="field-label" htmlFor="pf-name">{t('prof_full_name')}</label>
              <input
                id="pf-name"
                className="text-input"
                type="text"
                value={info.name}
                onChange={(e) => setInfo({ ...info, name: e.target.value })}
                placeholder={t('prof_full_name_ph')}
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-email">{t('prof_email')}</label>
              <input
                id="pf-email"
                className="text-input"
                type="email"
                value={info.email}
                onChange={(e) => setInfo({ ...info, email: e.target.value })}
                placeholder="you@example.com"
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-contact">{t('prof_contact')}</label>
              <input
                id="pf-contact"
                className="text-input"
                type="tel"
                value={info.contact}
                onChange={(e) => setInfo({ ...info, contact: e.target.value })}
                placeholder="e.g. 0917 123 4567"
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-age">{t('prof_age')}</label>
              <input
                id="pf-age"
                className="text-input"
                type="number"
                min={0}
                max={150}
                value={info.age}
                onChange={(e) => setInfo({ ...info, age: e.target.value })}
                placeholder={t('prof_age')}
              />
            </div>

            <div className="field-block field-block--full">
              <label className="field-label" htmlFor="pf-address">{t('prof_address')}</label>
              <input
                id="pf-address"
                className="text-input"
                type="text"
                value={info.address}
                onChange={(e) => setInfo({ ...info, address: e.target.value })}
                placeholder={t('prof_address_ph')}
              />
            </div>
          </div>

          <button type="button" className="submit-btn" disabled={savingInfo} onClick={saveInfo}>
            {savingInfo ? <span className="btn-spinner" aria-hidden="true" /> : <SvgUser size={18} />}
            {t('prof_save_info')}
          </button>
        </section>

        <section className="profile-card">
          <div className="section-hdr">
            <h2>{t('prof_health_hdr')}</h2>
            <p>{t('prof_health_sub')}</p>
          </div>

          <div className="profile-form-grid">
            <div className="field-block">
              <label className="field-label" htmlFor="pf-height">{t('prof_height')}</label>
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
              />
            </div>

            <div className="field-block">
              <label className="field-label" htmlFor="pf-weight">{t('prof_weight')}</label>
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
              />
            </div>
          </div>

          {bmiVal !== null && bmiInfo ? (
            <div className="bmi-card">
              <div className="bmi-icon">
                <SvgHeart size={20} />
              </div>
              <div className="bmi-info">
                <span className="bmi-label">{t('prof_bmi')}</span>
                <strong>{bmiVal.toFixed(1)}</strong>
                <span className={`metric-badge ${bmiInfo.cls}`}>{bmiInfo.label}</span>
              </div>
            </div>
          ) : (
            <p className="bmi-hint">{t('prof_bmi_hint')}</p>
          )}

          <button type="button" className="submit-btn" disabled={savingMetrics} onClick={saveMetrics}>
            {savingMetrics ? <span className="btn-spinner" aria-hidden="true" /> : <SvgCheck size={18} />}
            {t('prof_save_measurements')}
          </button>
        </section>
      </main>
    </Shell>
  );
}