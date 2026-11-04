import { useMemo, useState } from 'react';
import type { Doctor } from '../types';
import { getInitialData, scrollToTop } from '../api';
import Shell from '../shell';
import Hero from '../components/Hero';
import DatePicker from '../components/DatePicker';
import Select from '../components/Select';
import { SvgFlask, SvgUser, SvgCalendar } from '../components/icons';
import { useToast } from '../hooks';
const PRIORITIES = [
  { value: 'normal', label: 'Normal', desc: 'Standard turnaround' },
  { value: 'urgent', label: 'Urgent', desc: 'Expedited processing' },
  { value: 'stat', label: 'STAT', desc: 'Immediate priority' },
];

const OTHER_TEST = 'Other (specify below)';

interface LabPackage {
  category: string;
  name: string;
  tests: string;
}

const LAB_PACKAGES: LabPackage[] = [
  { category: 'Routine Chemistry Packages', name: 'CBC + Chem 5', tests: 'FBS, BUN, Creatinine, Uric Acid, Total Cholesterol' },
  { category: 'Routine Chemistry Packages', name: 'CBC + Chem 8 + UA', tests: 'CBC, Chem 8 panel, Urinalysis' },
  { category: 'Routine Chemistry Packages', name: 'CBC + Chem 10 + UA', tests: 'CBC, Chem 10 panel, Urinalysis' },
  { category: 'Routine Chemistry Packages', name: 'CBC + Chem 12 + UA', tests: 'CBC, Chem 12 panel, Urinalysis' },
  { category: 'Routine Chemistry Packages', name: 'FBS + HbA1c', tests: 'Fasting Blood Sugar, Hemoglobin A1c' },
  { category: 'All Time Packages', name: 'Hypertension Package', tests: 'TC, TG, HDL, LDL' },
  { category: 'All Time Packages', name: 'Dehydration Check', tests: 'Na, K, Cl' },
  { category: 'All Time Packages', name: 'Kidney Check', tests: 'Urinalysis, BUN, Creatinine, Uric Acid' },
  { category: 'All Time Packages', name: 'Liver Package', tests: 'SGOT, SGPT, TPA' },
  { category: 'All Time Packages', name: 'Cancer Panel Package', tests: 'CBC, CEA, SGPT, PSA' },
  { category: 'OB-GYN Packages', name: 'Buntis Package 1 Basic', tests: 'CBC, UA, RPR/VDRL, HIV, HBsAg screening, Blood Typing' },
  { category: 'OB-GYN Packages', name: 'Buntis Package 2', tests: 'OGTT 3 samples + Basic Buntis Package' },
  { category: 'OB-GYN Packages', name: 'Buntis Package 3', tests: 'OGTT 4 samples + Basic Buntis Package' },
  { category: 'Annual Physical Exam Packages', name: 'APE Basic 3', tests: 'CBC, UA, FA' },
  { category: 'Annual Physical Exam Packages', name: 'APE Basic 5', tests: 'CBC, UA, FA, HBsAg, Drug Test' },
  { category: 'Annual Physical Exam Packages', name: 'Basic 3 + Drug Test', tests: 'CBC, UA, FA, Drug Test' },
  { category: 'Annual Physical Exam Packages', name: 'Drug Test Only', tests: 'Drug Test' },
];

interface StepBarProps {
  step: 1 | 2 | 3;
}

function StepBar({ step }: StepBarProps) {
  const steps = [
    { n: 1, label: "Choose Test" },
    { n: 2, label: "Doctor & Notes" },
    { n: 3, label: "Confirm" },
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

  const categoryNames = useMemo(() => Object.keys(categories).filter((c) => c !== 'Other'), [categories]);
  const hasOther = useMemo(() => !!categories['Other'], [categories]);

  const [view, setView] = useState<{ kind: 'categories' | 'tests'; category: string }>({
    kind: 'categories',
    category: '',
  });
  const [selectedTests, setSelectedTests] = useState<string[]>([]);
  const [selectedPackage, setSelectedPackage] = useState<LabPackage | null>(null);
  const [customTest, setCustomTest] = useState('');
  const [customInputOpen, setCustomInputOpen] = useState(false);
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
    setCustomTest('');
    setCustomInputOpen(false);
    scrollToTop();
  };

  const toggleTest = (test: string) => {
    setSelectedPackage(null);
    setSelectedTests((current) => current.includes(test)
      ? current.filter((item) => item !== test)
      : [...current, test]);
  };

  const addCustomTest = () => {
    const value = customTest.trim();
    if (!value) {
      showToast("Please enter the test name.", 'error');
      return;
    }
    if (selectedTests.some((test) => test.toLowerCase() === value.toLowerCase())) {
      showToast("That test is already selected.", 'error');
      return;
    }
    setSelectedPackage(null);
    setSelectedTests((current) => [...current, value]);
    setCustomInputOpen(false);
    setCustomTest('');
  };

  const pickPackage = (labPackage: LabPackage) => {
    setSelectedTests([]);
    setSelectedPackage(labPackage);
    setStep(2);
    scrollToTop();
  };

  const continueWithTests = () => {
    if (selectedTests.length === 0) {
      showToast("Select at least one test or choose a package.", 'error');
      return;
    }
    setSelectedPackage(null);
    setStep(2);
    scrollToTop();
  };

  const selectedOrder = selectedPackage
    ? `${selectedPackage.name} (${selectedPackage.tests})`
    : selectedTests.join(', ');

  const startOver = () => {
    setStep(1);
    setSelectedTests([]);
    setSelectedPackage(null);
    setCustomTest('');
    setCustomInputOpen(false);
    setView({ kind: 'categories', category: '' });
    scrollToTop();
  };

  const openCustomTest = () => {
    setSelectedPackage(null);
    setCustomInputOpen(true);
    setCustomTest('');
  };

  const pickIndividualTest = (test: string) => {
    if (test === OTHER_TEST) {
      openCustomTest();
      setCustomTest('');
      return;
    }
    toggleTest(test);
  };

  const submit = async () => {
    if (!doctorId) {
      showToast("Please select a requesting doctor.", 'error');
      return;
    }
    if (!patientId) {
      showToast("Please login again.", 'error');
      return;
    }
    setSubmitting(true);
    const fd = new FormData();
    fd.append('patient_id', String(patientId));
    fd.append('doctor_id', doctorId);
    if (selectedPackage) {
      fd.append('test_type[]', selectedOrder);
    } else {
      selectedTests.forEach((test) => fd.append('test_type[]', test));
    }
    fd.append('priority', priority);
    fd.append('notes', notes);
    fd.append('scheduled_date', scheduledDate);
    try {
      const res = await fetch('patient_lab_action.php', { method: 'POST', body: fd });
      const text = await res.text();
      if (text.trim() === 'success') {
        setStep(3);
        showToast("Lab request submitted.", 'success');
      } else {
        showToast(text.trim() || "Submission failed. Please try again.", 'error');
      }
    } catch (err) {
      showToast("Network error. Please try again.", 'error');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Shell page="lab" title={"Laboratory Tests"} patientName={patientName}>
      <main className="page-content">
        <Hero
          tag={"Lab Request"}
          title={"Request a Lab Test"}
          sub={"Choose from our available laboratory services and submit a request. Our staff will confirm your schedule."}
        />

        <StepBar step={step} />

        {step === 3 ? (
          <div className="success-card">
            <div className="success-icon">
              <svg width="30" height="30" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <h3>{"Request Submitted!"}</h3>
            <p>
              {"Your lab test request has been received. Our staff will review it and contact you to confirm the schedule."}
            </p>
            <div className="success-summary">
              <div className="row">
                <span><SvgFlask size={18} /></span>
                <span className="lbl">{"Test"}</span>
                <strong>{selectedOrder}</strong>
              </div>
              <div className="row">
                <span><SvgUser size={18} /></span>
                <span className="lbl">{"Doctor"}</span>
                <strong>{doctors.find((d) => String(d.id) === doctorId)?.name || '—'}</strong>
              </div>
              {scheduledDate && (
                <div className="row">
                  <span><SvgCalendar size={18} /></span>
                  <span className="lbl">{"Preferred date"}</span>
                  <strong>{new Date(`${scheduledDate}T00:00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</strong>
                </div>
              )}
            </div>
            <a href="my_appointments.php" className="btn-primary">
              {"View all records"}
            </a>
          </div>
        ) : step === 2 ? (
          <div className="lab-step">
            <div className="selected-summary">
              <div>
                <div className="selected-label">{selectedPackage ? 'Selected Package' : 'Selected Tests'}</div>
                <div className="selected-test-name">{selectedPackage ? selectedPackage.name : `${selectedTests.length} test${selectedTests.length === 1 ? '' : 's'} selected`}</div>
                {selectedPackage ? (
                  <div className="selected-test-details">
                    <span>{selectedPackage.tests}</span>
                  </div>
                ) : (
                  <div className="selected-test-details">{selectedTests.join(', ')}</div>
                )}
              </div>
              <button
                type="button"
                className="clear-btn"
                onClick={() => {
                  startOver();
                }}
              >
                {"Change"}
              </button>
            </div>

            <div className="section-hdr">
              <h2>{"Requesting Doctor"}</h2>
              <p>{"Select the doctor who is ordering this lab test."}</p>
            </div>
            <div className="field-block">
              <label className="field-label">{"Doctor"}</label>
              <div className="select-wrap">
                <Select
                  value={doctorId}
                  onChange={(v) => setDoctorId(v)}
                  placeholder={"Select a doctor"}
                  icon={
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M12 5v14M5 12h14" />
                    </svg>
                  }
                  options={[
                    { value: '', label: "Select a doctor" },
                    ...doctors.map((d: Doctor) => ({
                      value: String(d.id),
                      label: `Dr. ${d.name} — ${d.specialty}`,
                    })),
                  ]}
                />
              </div>
            </div>

            <div className="section-hdr">
              <h2>{"Priority"}</h2>
              <p>{"How urgent is this request?"}</p>
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
              <h2>{"Preferred Schedule"}</h2>
              <p>{"Optional — let us know your preferred date."}</p>
            </div>
            <div className="field-block field-block--date">
              <label className="field-label">{"Preferred Date (optional)"}</label>
              <DatePicker value={scheduledDate} onChange={setScheduledDate} min={today} />
            </div>

            <div className="section-hdr">
              <h2>{"Clinical Notes"}</h2>
              <p>{"Describe your symptoms or reason for requesting this test."}</p>
            </div>
            <div className="notes-wrap">
              <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder={"e.g. Feeling dizzy, doctor recommended CBC..."}
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
                  {"Submit Lab Request"}
                </>
              )}
            </button>
          </div>
        ) : view.kind === 'categories' ? (
          <>
            <section className="lab-packages">
              <div className="section-hdr">
                <h2>Laboratory Packages</h2>
                <p>Choose a package, or select multiple individual tests below.</p>
              </div>
              {Array.from(new Set(LAB_PACKAGES.map((labPackage) => labPackage.category))).map((group) => (
                <div className="lab-package-group" key={group}>
                  <h3>{group}</h3>
                  <div className="lab-packages-grid">
                    {LAB_PACKAGES.filter((labPackage) => labPackage.category === group).map((labPackage) => (
                      <button
                        key={labPackage.name}
                        type="button"
                        className="lab-package-card"
                        onClick={() => pickPackage(labPackage)}
                      >
                        <span className="lab-package-card-top">
                          <strong>{labPackage.name}</strong>
                        </span>
                        <span className="lab-package-card-tests">{labPackage.tests}</span>
                        <span className="lab-package-card-action">Choose package <span aria-hidden="true">→</span></span>
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </section>

            <section className="lab-individual-tests">
              <div className="section-hdr">
                <h2>Choose Individual Tests</h2>
                <p>Select as many tests as you need. You can choose from multiple categories.</p>
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
                    <span className="cat-name">Other</span>
                    <span className="cat-count">Custom test</span>
                  </button>
                )}
              </div>
              {selectedTests.length > 0 && (
                <div className="lab-selection-bar">
                  <span><strong>{selectedTests.length}</strong> individual test{selectedTests.length === 1 ? '' : 's'} selected</span>
                  <button type="button" className="submit-btn" onClick={continueWithTests}>Continue with selected tests <span aria-hidden="true">→</span></button>
                </div>
              )}
            </section>
          </>
        ) : (
          <>
            <div className="tests-panel-head">
              <button type="button" className="back-to-cats" onClick={() => setView({ kind: 'categories', category: '' })}>
                {"← All Categories"}
              </button>
              <div className="section-hdr">
                <h2>{view.category}</h2>
                <p>{"Select multiple tests, including tests in other categories. Your selections will be kept."}</p>
              </div>
            </div>
            <div className="tests-list">
              {(categories[view.category] ?? []).map((test: string, i) => (
                <button
                  key={test}
                  type="button"
                  className={`test-chip ${selectedTests.includes(test) ? 'selected' : ''}`}
                  aria-pressed={selectedTests.includes(test)}
                  style={{ animationDelay: `${i * 40}ms` }}
                  onClick={() => pickIndividualTest(test)}
                >
                  {test}
                </button>
              ))}
            </div>
            {customInputOpen && (
              <div className="custom-input-wrap visible">
                <input
                  type="text"
                  placeholder={"Enter the name of the test..."}
                  value={customTest}
                  onChange={(e) => setCustomTest(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addCustomTest(); } }}
                />
                <button
                  type="button"
                  className="proceed-btn"
                  onClick={addCustomTest}
                >
                  Add test
                </button>
              </div>
            )}
            <div className="lab-selection-bar">
              <span><strong>{selectedTests.length}</strong> individual test{selectedTests.length === 1 ? '' : 's'} selected</span>
              <button type="button" className="submit-btn" disabled={selectedTests.length === 0} onClick={continueWithTests}>Continue with selected tests <span aria-hidden="true">→</span></button>
            </div>
          </>
        )}
      </main>
    </Shell>
  );
}
