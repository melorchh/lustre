import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, postAction } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Card, Stack } from '../ui';

function PasswordField({
  label,
  value,
  onChange,
  autoComplete,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  autoComplete: string;
}) {
  return (
    <>
      <label className="form-label">{label}</label>
      <input
        className="form-input"
        type="password"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        autoComplete={autoComplete}
      />
    </>
  );
}

export default function ChangePassword({ portal }: { portal: 'admin' | 'doctor' }) {
  const staff = getInitial().staff;
  const { showToast } = useToast();
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const [mustChange] = useState(!!staff.mustChange);
  const isAdmin = portal === 'admin';

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

  const fields = (
    <>
      <PasswordField label="Current Password" value={current} onChange={setCurrent} autoComplete="current-password" />
      <PasswordField label="New Password" value={next} onChange={setNext} autoComplete="new-password" />
      <PasswordField label="Confirm New Password" value={confirm} onChange={setConfirm} autoComplete="new-password" />
    </>
  );

  if (isAdmin) {
    return (
      <Shell active="change_password" title="Change Password">
        <Stack>
          <Card
            className="ap-narrow"
            title={mustChange ? 'Set a new password' : 'Change password'}
            subtitle={mustChange ? 'For security, you must set a new password before continuing.' : 'Use at least 8 characters.'}
          >
            {fields}
            <div className="form-actions">
              <button className="btn-primary" onClick={submit}>Update Password</button>
            </div>
          </Card>
        </Stack>
      </Shell>
    );
  }

  return (
    <Shell active="change_password" title="Change Password">
      <div className="content-card" style={{ maxWidth: 520 }}>
        <h2>{mustChange ? 'Set a New Password' : 'Change Password'}</h2>
        {mustChange && <p className="form-hint" style={{ marginBottom: '1rem' }}>For security, you must set a new password before continuing.</p>}
        {fields}
        <div className="form-actions">
          <button className="btn-primary" onClick={submit}>Update Password</button>
        </div>
      </div>
    </Shell>
  );
}
