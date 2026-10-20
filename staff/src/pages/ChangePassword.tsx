import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';

export default function ChangePassword({ portal }: { portal: 'admin' | 'doctor' }) {
  const staff = getInitial().staff;
  const { showToast } = useToast();
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const [mustChange] = useState(!!staff.mustChange);

  async function submit() {
    if (!current || !next || !confirm) { showToast('All fields are required', 'error'); return; }
    if (next !== confirm) { showToast('New passwords do not match', 'error'); return; }
    if (next.length < 8) { showToast('Password must be at least 8 characters', 'error'); return; }
    showLoading();
    try {
      const res = await postAction('api_auth_password.php', { current_password: current, new_password: next, confirm_password: confirm });
      if (res === 'success' || res.startsWith('{')) {
        let ok = res === 'success';
        if (res.startsWith('{')) { try { ok = JSON.parse(res).ok === true; } catch { /* ignore */ } }
        if (ok) {
          showToast('Password updated successfully', 'success');
          setCurrent(''); setNext(''); setConfirm('');
          const dest = portal === 'doctor' ? 'doctor_dashboard.php' : 'admin_dashboard.php';
          window.setTimeout(() => { window.location.href = dest; }, 1200);
          return;
        }
      }
      showToast(res.replace(/^error:\s*/i, '') || 'Update failed', 'error');
    } catch (e) { showToast(e instanceof Error ? e.message : 'Error', 'error'); }
    finally { hideLoading(); }
  }

  return (
    <Shell active="change_password" title="Change Password">
      <div className="content-card" style={{ maxWidth: 520 }}>
        <h2>{mustChange ? 'Set a New Password' : 'Change Password'}</h2>
        {mustChange && <p className="form-hint" style={{ marginBottom: '1rem' }}>For security, you must set a new password before continuing.</p>}
        <label className="form-label">Current Password</label>
        <input className="form-input" type="password" value={current} onChange={(e) => setCurrent(e.target.value)} autoComplete="current-password" />
        <label className="form-label">New Password</label>
        <input className="form-input" type="password" value={next} onChange={(e) => setNext(e.target.value)} autoComplete="new-password" />
        <label className="form-label">Confirm New Password</label>
        <input className="form-input" type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} autoComplete="new-password" />
        <div className="form-actions">
          <button className="btn-primary" onClick={submit}>Update Password</button>
        </div>
      </div>
    </Shell>
  );
}
