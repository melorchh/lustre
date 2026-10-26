// Smoke test: render each built staff entry into a jsdom document with an
// injected window.__LUSTRE__ payload and assert it produces DOM without error.
import { JSDOM } from 'jsdom';
import { pathToFileURL } from 'node:url';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';

const DIST = path.resolve('C:/Users/User-PC/Documents/GitHub/lustre/staff/dist/assets');

function payloadFor(entry) {
  const role = entry.startsWith('doctor') ? 'doctor' : 'admin';
  const staff = {
    role, id: 1, accountId: 1, name: role === 'doctor' ? 'Jane Doe' : 'Admin User',
    username: 'test', mustChange: false, loginUrl: `${role}_login.php`,
    logoutUrl: `${role}_logout.php`, changePasswordUrl: `${role}_change_password.php`,
  };
  const data = {
    stats: { total: 3, pending: 1, confirmed: 1, completed: 1, today: 1, patients: 5, doctors: 2,
      totalPatients: 5, totalDoctors: 2, totalAppointments: 3, todayAppointments: 1,
      pendingAppointments: 1, pendingLabs: 1, in_progress: 1, thisMonth: 1, dueSoon: 1,
      active: 1, appointments: 2, walkins: 2 },
    appointments: [{ id: 1, patient_id: 1, appointment_date: '2026-01-01', appointment_time: '09:00',
      status: 'pending', payment_status: 'pending', patient_name: 'John', doctor_name: 'Jane',
      specialty: 'OB', service_requested: 'Consult', result: '', updated_at: '2026-01-01 09:00:00' }],
    patients: [{ id: 1, name: 'John', email: 'j@x.com', contact: '123', unique_code: 'U1',
      patient_type: 'regular', age: 30, appointment_count: 2, last_appointment: '2026-01-01' }],
    doctors: [{ id: 1, name: 'Jane', specialty: 'OB', schedule: 'Mon-Fri', experience: 5, appointment_count: 2 }],
    accounts: [], specialties: ['OB'],
    schedules: [{ id: 1, doctor_id: 1, day_of_week: 'Monday', start_time: '09:00', end_time: '17:00', is_active: 1 }],
    blocks: [], timeBlocks: [],
    tests: [{ id: 1, patient_id: 1, doctor_id: 1, test_type: 'CBC', priority: 'normal',
      status: 'pending', patient_name: 'John', doctor_name: 'Jane', result: '' }],
    labs: [], vaccinations: [{ id: 1, vaccine_name: 'BCG', dose_label: 'Dose 1',
      administered_date: '2026-01-01', next_due_date: '2026-02-01', patient_name: 'John' }],
    walkins: [{ id: 1, arrival_date: '2026-01-01', arrival_time: '09:00', status: 'waiting',
      patient_name: 'John', patient_type: 'regular', contact: '123', queue: 1 }],
    walkinStats: { today: 1, pending: 1, completed: 0 },
    recentAppointments: [{ id: 1, patient_name: 'John', doctor_name: 'Jane', appointment_date: '2026-01-01', appointment_time: '09:00', status: 'pending' }],
    todayAppointments: [], upcomingAppointments: [], recentLabs: [], scheduleToday: [],
    doctorsOnDuty: [{ name: 'Jane', specialty: 'OB', appointments: 1 }],
    topServices: [{ service: 'Consult', count: 1 }], weeklyCounts: [{ label: 'Mon', count: 1 }],
    summary: { total: 1, completed: 1, cancelled: 0, patients: 1, revenue: 500 },
    byDay: [{ date: '2026-01-01', count: 1 }], byDoctor: [], byService: [], byStatus: [], byPayment: [],
    range: { from: '2026-01-01', to: '2026-01-31' },
    rescheduleHistory: [], lastVisit: '2026-01-01',
    patient: { id: 1, name: 'John', unique_code: 'U1', email: 'j@x.com', contact: '123',
      address: 'x', patient_type: 'regular', age: 30, gender: 'male', weight_kg: 60, height_cm: 170 },
    vitals: [{ id: 1, visit_date: '2026-01-01', blood_pressure: '120/80', heart_rate: 70 }],
  };
  return { staff, view: 'test', data };
}

const entries = readdirSync(DIST).filter((f) => /^(admin|doctor|client)_.*\.js$/.test(f));
let pass = 0, fail = 0;

for (const entry of entries) {
  const dom = new JSDOM('<!DOCTYPE html><html><body><div id="root"></div></body></html>', {
    url: 'https://example.test/', pretendToBeVisual: true, runScripts: 'outside-only',
  });
  const { window } = dom;
  window.__LUSTRE__ = payloadFor(entry);
  // minimal browser globals the app touches
  window.matchMedia = window.matchMedia || (() => ({ matches: false, addListener() {}, removeListener() {} }));
  window.fetch = async () => ({ status: 200, json: async () => ({ ok: true, data: {} }), text: async () => '' });
  global.window = window;
  global.document = window.document;
  try { Object.defineProperty(global, 'navigator', { value: window.navigator, configurable: true }); } catch {}
  global.CustomEvent = window.CustomEvent;
  global.HTMLElement = window.HTMLElement;
  global.requestAnimationFrame = window.requestAnimationFrame || ((cb) => setTimeout(cb, 0));

  const url = pathToFileURL(path.join(DIST, entry)).href;
  try {
    await import(url);
    // allow microtasks/effects to flush
    await new Promise((r) => setTimeout(r, 80));
    const html = window.document.getElementById('root').innerHTML;
    if (html && html.length > 40) {
      pass++;
      console.log(`PASS ${entry} (${html.length} bytes)`);
    } else {
      fail++;
      console.log(`FAIL ${entry} rendered empty`);
    }
  } catch (e) {
    fail++;
    console.log(`FAIL ${entry}: ${e && e.stack ? e.stack.split('\n').slice(0, 3).join(' | ') : e}`);
  }
}
console.log(`\n${pass}/${entries.length} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
