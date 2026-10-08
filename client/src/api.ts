import type { InitialData, MedicalDate } from './types';

export function getInitialData(): InitialData {
  return window.__Lustre__ ?? { patientName: 'Patient', doctors: [] };
}

export async function fetchAvailableDates(doctorId: number): Promise<MedicalDate[]> {
  const res = await fetch(`get_available_dates.php?doctor_id=${doctorId}&days=31&t=${Date.now()}`);
  if (!res.ok) throw new Error('Failed to load available dates');
  const data = await res.json();
  return Array.isArray(data) ? data : [];
}

export async function fetchAvailableTimes(doctorId: number, date: string): Promise<string[]> {
  const res = await fetch(
    `get_available_times.php?doctor_id=${doctorId}&date=${encodeURIComponent(date)}&t=${Date.now()}`,
  );
  if (!res.ok) throw new Error('Failed to load available times');
  const data = await res.json();
  return Array.isArray(data) ? data : [];
}

export async function bookAppointment(doctorId: number, date: string, time: string, service?: string): Promise<number> {
  const body = new URLSearchParams({ doctor_id: String(doctorId), date, time });
  if (service) body.set('service', service);
  const res = await fetch('book_appointment.php', { method: 'POST', body });
  const text = await res.text();
  try {
    const data = JSON.parse(text);
    if (data && data.success) return Number(data.appointment_id) || 0;
  } catch {
    /* not JSON — treat as plain error text below */
  }
  throw new Error(text.trim().replace(/^error:\s*/i, '') || 'Booking failed. Please try again.');
}

export async function rescheduleAppointment(
  appointmentId: number,
  doctorId: number,
  date: string,
  time: string,
): Promise<number> {
  const body = new URLSearchParams({
    appointment_id: String(appointmentId),
    doctor_id: String(doctorId),
    date,
    time,
  });
  const res = await fetch('reschedule_appointment.php', { method: 'POST', body });
  const text = await res.text();
  try {
    const data = JSON.parse(text);
    if (data && data.success) return Number(data.appointment_id) || 0;
  } catch {
    /* not JSON — treat as plain error text below */
  }
  throw new Error(text.trim().replace(/^error:\s*/i, '') || 'Rescheduling failed. Please try again.');
}

export function formatTime12h(t: string): string {
  const parts = t.split(':');
  const h = parseInt(parts[0], 10);
  const m = parts[1];
  const suffix = h >= 12 ? 'PM' : 'AM';
  const hour = h % 12 === 0 ? 12 : h % 12;
  return `${hour}:${m} ${suffix}`;
}

export function parseDateParts(dateStr: string): { month: string; day: string; weekday: string } {
  const d = new Date(`${dateStr}T00:00:00`);
  return {
    month: d.toLocaleDateString('en-US', { month: 'short' }),
    day: String(d.getDate()),
    weekday: d.toLocaleDateString('en-US', { weekday: 'short' }),
  };
}

export function initials(name: string): string {
  const clean = (name || '').trim();
  if (!clean) return '?';
  const parts = clean.split(/\s+/);
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export function formatLongDate(dateStr: string): string {
  if (!dateStr) return '';
  const d = new Date(`${dateStr}T00:00:00`);
  if (Number.isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

export function formatStamp(stamp: string): string {
  if (!stamp) return '';
  const d = new Date(stamp.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return stamp;
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

export function scrollToTop(smooth = true): void {
  if (window.matchMedia('(min-width: 769px)').matches) {
    const scroller = document.querySelector<HTMLElement>('.main .page-content');
    if (scroller) {
      scroller.scrollTo({ top: 0, behavior: smooth ? 'smooth' : 'auto' });
      return;
    }
  }
  window.scrollTo({ top: 0, behavior: smooth ? 'smooth' : 'auto' });
}
