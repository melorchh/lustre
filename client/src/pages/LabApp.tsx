import { useMemo, useState } from 'react';
import type { Doctor } from '../types';
import { getInitialData, scrollToTop } from '../api';
import Shell from '../shell';
import Hero from '../components/Hero';
import DatePicker from '../components/DatePicker';
import Select from '../components/Select';
import { SvgFlask, SvgUser, SvgCalendar } from '../components/icons';
import { useToast } from '../hooks';
import { useLang } from '../lang';

const PRIORITIES = [
  { value: 'normal', label: 'Normal', desc: 'Standard turnaround' },
  { value: 'urgent', label: 'Urgent', desc: 'Expedited processing' },
  { value: 'stat', label: 'STAT', desc: 'Immediate priority' },
];

const OTHER_TEST = 'Other (specify below)';

interface StepBarProps {
  step: 1 | 2 | 3;
}

function StepBar({ step }: StepBarProps) {
  const { t } = useLang();
  const steps = [
    { n: 1, label: t('lab_choose_test') },
    { n: 2, label: t('lab_doctor_notes') },
    { n: 3, label: t('lab_confirm') },
  ];
  return (
    <div className="steps">
      {steps.map((s, i) => {
        const state = step === s.n ? 'active' : step > s.n ? 'done' : '';
        return (
          <div key={s.n} className="steps-group">
            {i > 0 && <div className={`step-line ${state === 'done' ? 'done' : ''}`} />}
            <div className={`step ${state}`}>
              <div className="step-num">{state === 'done' ? '✓' : s.n}</div>
              <span>{s.label}</span>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default function LabApp() {
  const { patientName, doctors = [], categories = {}, patientId } = getInitialData();
  const { showToast } = useToast();
  const { t } = useLang();

  const categoryNames = useMemo(() => Object.keys(categories).filter((c) => c !== 'Other'), [categories]);
  const hasOther = useMemo(() => !!categories['Other'], [categories]);

  const [view, setView] = useState<{ kind: 'categories' | 'tests'; category: string }>({
    kind: 'categories',
    category: '',
  });
  const [selectedTest, setSelectedTest] = useState('');
  const [customTest, setCustomTest] = useState('');
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [doctorId, setDoctorId] = useState('');
  const [scheduledDate, setScheduledDate] = useState('');
  const [notes, setNotes] = useState('');
  const [priority, setPriority] = useState('normal');
  const [submitting, setSubmitting] = useState(false);

  const today = useMemo(() => {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
  }, []);

  const pickCategory = (cat: string) => {
    setView({ kind: 'tests', category: cat });
    setSelectedTest('');
    setCustomTest('');
    scrollToTop();
  };

  const pickTest = (test: string) => {
    if (test === OTHER_TEST) {
      setSelectedTest(OTHER_TEST);
      setCustomTest('');
      return;
    }
    setSelectedTest(test);
    setCustomTest('');
    window.setTimeout(() => goToStep2(test), 260);
  };

  const goToStep2 = (testName: string) => {
    setSelectedTest(testName);
    setStep(2);
    scrollToTop();
  };

  const submit = async () => {
    if (!doctorId) {
      showToast(t('lab_need_doctor'), 'error');
      return;
    }
    if (!patientId) {
      showToast(t('lab_need_login'), 'error');
      return;
    }
    setSubmitting(true);
    const fd = new FormData();
    fd.append('patient_id', String(patientId));
    fd.append('doctor_id', doctorId);
    fd.append('test_type', selectedTest);
    fd.append('priority', priority);
    fd.append('notes', notes);
    fd.append('scheduled_date', scheduledDate);
    try {
      const res = await fetch('patient_lab_action.php', { method: 'POST', body: fd });
      const text = await res.text();
      if (text.trim() === 'success') {
        setStep(3);
        showToast(t('lab_submitted_ok'), 'success');
      } else {
        showToast(text.trim() || t('lab_submit_failed'), 'error');
      }
    } catch (err) {
      showToast(t('lab_network_err'), 'error');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Shell page="lab" title={t('lab_title')} patientName={patientName}>
      <main className="page-content">
        <Hero
          tag={t('lab_tag')}
          title={t('lab_hero_title')}
          sub={t('lab_hero_sub')}
        />

        <StepBar step={step} />

        {step === 3 ? (
          <div className="success-card">
            <div className="success-icon">
              <svg width="30" height="30" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <h3>{t('lab_submitted')}</h3>
            <p>
              {t('lab_submitted_sub')}
            </p>
            <div className="success-summary">
              <div className="row">
                <span><SvgFlask size={18} /></span>
                <span className="lbl">{t('lab_test')}</span>
                <strong>{selectedTest}</strong>
              </div>
              <div className="row">
                <span><SvgUser size={18} /></span>
                <span className="lbl">{t('bm_doctor')}</span>
                <strong>{doctors.find((d) => String(d.id) === doctorId)?.name || '—'}</strong>
              </div>
              {scheduledDate && (
                <div className="row">
                  <span><SvgCalendar size={18} /></span>
                  <span className="lbl">{t('lab_preferred_date_lbl')}</span>
                  <strong>{new Date(`${scheduledDate}T00:00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</strong>
                </div>
              )}
            </div>
            <a href="my_appointments.php" className="btn-primary">
              {t('dash_view_all')}
            </a>
          </div>
        ) : step === 2 ? (
          <div className="lab-step">
            <div className="selected-summary">
              <div>
                <div className="selected-label">{t('lab_selected_test')}</div>
                <div className="selected-test-name">{selectedTest}</div>
              </div>
              <button
                type="button"
                className="clear-btn"
                onClick={() => {
                  setStep(1);
                  setSelectedTest('');
                  setCustomTest('');
                  setView({ kind: 'categories', category: '' });
                  scrollToTop();
                }}
              >
                {t('lab_change')}
              </button>
            </div>

            <div className="section-hdr">
              <h2>{t('lab_req_doctor')}</h2>
              <p>{t('lab_req_doctor_sub')}</p>
            </div>
            <div className="field-block">
              <label className="field-label">{t('bm_doctor')}</label>
              <div className="select-wrap">
                <Select
                  value={doctorId}
                  onChange={(v) => setDoctorId(v)}
                  placeholder={t('lab_select_doctor')}
                  icon={
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M12 5v14M5 12h14" />
                    </svg>
                  }
                  options={[
                    { value: '', label: t('lab_select_doctor') },
                    ...doctors.map((d: Doctor) => ({
                      value: String(d.id),
                      label: `Dr. ${d.name} — ${d.specialty}`,
                    })),
                  ]}
                />
              </div>
            </div>

            <div className="section-hdr">
              <h2>{t('lab_priority')}</h2>
              <p>{t('lab_priority_sub')}</p>
            </div>
            <div className="priority-grid">
              {PRIORITIES.map((p) => (
                <button
                  key={p.value}
                  type="button"
                  className={`priority-opt ${priority === p.value ? 'selected' : ''}`}
                  onClick={() => setPriority(p.value)}
                >
                  <span className="prio-name">{p.label}</span>
                  <span className="prio-desc">{p.desc}</span>
                </button>
              ))}
            </div>

            <div className="section-hdr">
              <h2>{t('lab_preferred_schedule')}</h2>
              <p>{t('lab_preferred_schedule_sub')}</p>
            </div>
            <div className="field-block field-block--date">
              <label className="field-label">{t('lab_preferred_date')}</label>
              <DatePicker value={scheduledDate} onChange={setScheduledDate} min={today} />
            </div>

            <div className="section-hdr">
              <h2>{t('lab_clinical_notes')}</h2>
              <p>{t('lab_clinical_notes_sub')}</p>
            </div>
            <div className="notes-wrap">
              <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder={t('lab_notes_ph')}
                rows={4}
              />
            </div>

            <button type="button" className="submit-btn" disabled={submitting} onClick={submit}>
              {submitting ? (
                <span className="btn-spinner" aria-hidden="true" />
              ) : (
                <>
                  <svg width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                    <polyline points="22 2 15 22 11 13 2 9 22 2" />
                  </svg>
                  {t('lab_submit')}
                </>
              )}
            </button>
          </div>
        ) : view.kind === 'categories' ? (
          <>
            <div className="section-hdr">
              <h2>{t('lab_category_hdr')}</h2>
              <p>{t('lab_category_sub')}</p>
            </div>
            <div className="categories-grid">
              {categoryNames.map((cat, i) => (
                <button
                  key={cat}
                  type="button"
                  className="category-card"
                  style={{ animationDelay: `${i * 50}ms` }}
                  onClick={() => pickCategory(cat)}
                >
                  <span className="cat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M9 3h6l1 7H8L9 3z" />
                      <path d="M8 10l-3 9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1l-3-9" />
                    </svg>
                  </span>
                  <span className="cat-name">{cat}</span>
                  <span className="cat-count">
                    {(categories[cat] ?? []).length} test{(categories[cat] ?? []).length > 1 ? 's' : ''}
                  </span>
                </button>
              ))}
              {hasOther && (
                <button
                  type="button"
                  className="category-card category-card--other"
                  onClick={() => pickCategory('Other')}
                >
                  <span className="cat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                      <path d="M12 5v14M5 12h14" />
                    </svg>
                  </span>
                  <span className="cat-name">{t('lab_other')}</span>
                  <span className="cat-count">{t('lab_custom_test')}</span>
                </button>
              )}
            </div>
          </>
        ) : (
          <>
            <div className="tests-panel-head">
              <button type="button" className="back-to-cats" onClick={() => setView({ kind: 'categories', category: '' })}>
                {t('lab_all_categories')}
              </button>
              <div className="section-hdr">
                <h2>{view.category}</h2>
                <p>{t('lab_select_one')}</p>
              </div>
            </div>
            <div className="tests-list">
              {(categories[view.category] ?? []).map((test: string, i) => (
                <button
                  key={test}
                  type="button"
                  className={`test-chip ${selectedTest === test ? 'selected' : ''}`}
                  style={{ animationDelay: `${i * 40}ms` }}
                  onClick={() => pickTest(test)}
                >
                  {test}
                </button>
              ))}
            </div>
            {selectedTest === OTHER_TEST && (
              <div className="custom-input-wrap visible">
                <input
                  type="text"
                  placeholder={t('lab_custom_ph')}
                  value={customTest}
                  onChange={(e) => setCustomTest(e.target.value)}
                />
                <button
                  type="button"
                  className="proceed-btn"
                  onClick={() => {
                    const val = customTest.trim();
                    if (!val) {
                      showToast(t('lab_enter_test_name'), 'error');
                      return;
                    }
                    goToStep2(val);
                  }}
                >
                  {t('lab_continue_test')}
                </button>
              </div>
            )}
          </>
        )}
      </main>
    </Shell>
  );
}
