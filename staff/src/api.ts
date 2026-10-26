// Data + action helpers for the staff portals.
//
// GET bundles: api_admin_data.php / api_doctor_data.php (?view=...)
// Actions keep their legacy plain-text contract ("success" / "error: ..."),
// but the four core endpoints also return {ok} JSON when we send the
// X-Lustre-Api header. `postAction` tolerates both shapes and surfaces a
// proper Error on 401 so Shell can bounce to the login page.

import type { InitialData, Staff } from './types';

export function getInitial(): InitialData {
  return window.__LUSTRE__ ?? {
    staff: {} as Staff,
    view: '',
    data: {},
  };
}

/** Fetch a page bundle. `params` typically carries view/date/patient/filters. */
export async function fetchData<T = Record<string, unknown>>(
  endpoint: string,
  params: Record<string, string | number | undefined> = {},
): Promise<T & { ok: boolean }> {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== '') qs.set(k, String(v));
  }
  const res = await fetch(`${endpoint}?${qs.toString()}`, {
    headers: { 'X-Requested-With': 'LustreStaff' },
    cache: 'no-store',
  });
  if (res.status === 401) {
    const data = getInitial();
    window.location.href = data.staff?.loginUrl || 'login.php';
    throw new Error('Unauthorized');
  }
  const json = await res.json();
  return json;
}

export interface ActionOptions {
  /** Append to the URL as ?action=... (lab_action, doctor_lab_action, ...). */
  action?: string;
  query?: Record<string, string | number | undefined>;
}

/**
 * POST to a legacy action endpoint. Returns the raw trimmed body; callers
 * decide success. Sends X-Lustre-Api so upgraded endpoints reply with JSON.
 */
export async function postAction(
  endpoint: string,
  body: Record<string, string | number | undefined | null> | FormData,
  opts: ActionOptions = {},
): Promise<string> {
  const qs = new URLSearchParams();
  if (opts.action) qs.set('action', opts.action);
  if (opts.query) {
    for (const [k, v] of Object.entries(opts.query)) {
      if (v !== undefined && v !== '') qs.set(k, String(v));
    }
  }
  const url = qs.toString() ? `${endpoint}?${qs.toString()}` : endpoint;

  let payload: BodyInit;
  if (body instanceof FormData) {
    payload = body;
  } else {
    const fd = new FormData();
    for (const [k, v] of Object.entries(body)) {
      if (v !== undefined && v !== null) fd.append(k, String(v));
    }
    payload = fd;
  }

  const res = await fetch(url, {
    method: 'POST',
    body: payload,
    headers: { 'X-Lustre-Api': '1' },
  });

  const text = await res.text();

  if (res.status === 401) {
    const data = getInitial();
    window.location.href = data.staff?.loginUrl || 'login.php';
    throw new Error('Unauthorized');
  }

  // Upgraded endpoints may reply with {ok:false, error}. Normalize to text.
  if (text.startsWith('{')) {
    try {
      const json = JSON.parse(text);
      if (json && typeof json.ok === 'boolean') {
        if (json.ok) return 'success';
        throw new Error(json.error || 'Request failed');
      }
    } catch (e) {
      if (e instanceof Error && e.message && !/JSON/.test(e.message)) throw e;
    }
  }
  return text.trim();
}

/** True when a legacy response body means success. */
export function isSuccess(text: string): boolean {
  const t = text.trim().toLowerCase();
  return t === 'success' || t.startsWith('success:');
}

// ---- formatting helpers (shared across pages) ----
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export function fmtDate(iso?: string | null): string {
  if (!iso) return '—';
  const p = String(iso).slice(0, 10).split('-');
  if (p.length < 3) return String(iso);
  return `${MONTHS[parseInt(p[1], 10) - 1] || p[1]} ${parseInt(p[2], 10)}, ${p[0]}`;
}

export function fmtDateTime(iso?: string | null): string {
  if (!iso) return '—';
  const d = new Date(String(iso).replace(' ', 'T'));
  if (isNaN(d.getTime())) return fmtDate(iso);
  let h = d.getHours();
  const m = String(d.getMinutes()).padStart(2, '0');
  const ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${fmtDate(iso)} ${h}:${m} ${ap}`;
}

export function fmtTime(t?: string | null): string {
  if (!t) return '—';
  const parts = String(t).split(':');
  let h = parseInt(parts[0], 10);
  const m = parts[1] || '00';
  if (isNaN(h)) return String(t);
  const ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${h}:${m} ${ap}`;
}

export function fmtDuration(min?: number | null): string {
  const d = Number(min || 0);
  if (d >= 60) return `${Math.floor(d / 60)}h${d % 60 ? ' ' + (d % 60) + 'm' : ''}`;
  return `${d}m`;
}

export function initials(name?: string): string {
  const n = (name || '').trim();
  return n ? n.charAt(0).toUpperCase() : '?';
}

export function firstName(name?: string): string {
  return (name || '').trim().split(/\s+/)[0] || '';
}
