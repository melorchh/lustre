import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, fmtDate } from '../api';

interface ReportRow {
  date?: string;
  month?: string;
  doctor_name?: string;
  specialty?: string;
  service_requested?: string;
  count: number;
}

export default function AdminReports() {
  const d = getInitial().data as {
    range?: { from: string; to: string };
    summary?: Record<string, number>;
    byDay?: ReportRow[];
    byDoctor?: ReportRow[];
    byService?: ReportRow[];
    byStatus?: ReportRow[];
    byPayment?: ReportRow[];
  };
  const [from, setFrom] = useState(d.range?.from || '');
  const [to, setTo] = useState(d.range?.to || '');
  const [report, setReport] = useState(d);

  function reload() {
    const params = new URLSearchParams({ view: 'reports' });
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    fetch(`api_admin_data.php?${params.toString()}`, { headers: { 'X-Requested-With': 'LustreStaff' }, cache: 'no-store' })
      .then((r) => r.json())
      .then((json) => { if (json?.data) setReport(json.data); })
      .catch(() => {});
  }

  function exportCsv(rows: ReportRow[], cols: string[], filename: string) {
    const header = cols.join(',');
    const body = rows.map((r) => cols.map((c) => `"${String((r as unknown as Record<string, unknown>)[c] ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([header + '\n' + body], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  const s = report.summary || {};

  return (
    <Shell active="reports" title="Reports">
      <div className="content-card">
        <div className="card-head">
          <h2>Report Filters</h2>
        </div>
        <div className="apt-toolbar">
          <label className="inline-label">From</label>
          <input type="date" className="apt-select" value={from} onChange={(e) => setFrom(e.target.value)} />
          <label className="inline-label">To</label>
          <input type="date" className="apt-select" value={to} onChange={(e) => setTo(e.target.value)} />
          <button className="btn-primary" onClick={reload}>Generate</button>
        </div>
      </div>

      <div className="stats-grid">
        <div className="stat-card"><div className="stat-number">{s.total ?? 0}</div><div className="stat-label">Appointments</div></div>
        <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{s.completed ?? 0}</div><div className="stat-label">Completed</div></div>
        <div className="stat-card" style={{ borderTopColor: '#ef4444' }}><div className="stat-number" style={{ color: '#ef4444' }}>{s.cancelled ?? 0}</div><div className="stat-label">Cancelled</div></div>
        <div className="stat-card" style={{ borderTopColor: '#8b5cf6' }}><div className="stat-number" style={{ color: '#8b5cf6' }}>{s.patients ?? 0}</div><div className="stat-label">Unique Patients</div></div>
      </div>

      <ReportTable title="Appointments by Day" rows={report.byDay || []} cols={['date', 'count']} headers={['Date', 'Count']}
        onExport={() => exportCsv(report.byDay || [], ['date', 'count'], 'appointments_by_day.csv')} renderDate />
      <ReportTable title="Appointments by Doctor" rows={report.byDoctor || []} cols={['doctor_name', 'specialty', 'count']} headers={['Doctor', 'Specialty', 'Count']}
        onExport={() => exportCsv(report.byDoctor || [], ['doctor_name', 'specialty', 'count'], 'appointments_by_doctor.csv')} />
      <ReportTable title="Most Requested Services" rows={report.byService || []} cols={['service_requested', 'count']} headers={['Service', 'Count']}
        onExport={() => exportCsv(report.byService || [], ['service_requested', 'count'], 'services.csv')} />
      <ReportTable title="Status Breakdown" rows={report.byStatus || []} cols={['month', 'count']} headers={['Status', 'Count']}
        onExport={() => exportCsv(report.byStatus || [], ['month', 'count'], 'status.csv')} />
      <ReportTable title="Payment Breakdown" rows={report.byPayment || []} cols={['month', 'count']} headers={['Payment', 'Count']}
        onExport={() => exportCsv(report.byPayment || [], ['month', 'count'], 'payment.csv')} />
    </Shell>
  );
}

function ReportTable({
  title, rows, cols, headers, onExport, renderDate,
}: {
  title: string;
  rows: ReportRow[];
  cols: string[];
  headers: string[];
  onExport: () => void;
  renderDate?: boolean;
}) {
  return (
    <div className="content-card">
      <div className="card-head">
        <h2>{title}</h2>
        <button className="btn-secondary btn-xs" onClick={onExport}>Export CSV</button>
      </div>
      <div className="table-scroll"><table>
        <thead><tr>{headers.map((h) => <th key={h}>{h}</th>)}</tr></thead>
        <tbody>
          {rows.length === 0 ? (
            <tr><td colSpan={headers.length} className="empty-state-row">No data.</td></tr>
          ) : rows.map((r, i) => (
            <tr key={i}>
              {cols.map((c) => {
                const v = (r as unknown as Record<string, unknown>)[c];
                return <td key={c}>{renderDate && c === 'date' ? fmtDate(String(v)) : String(v ?? '—')}</td>;
              })}
            </tr>
          ))}
        </tbody>
      </table></div>
    </div>
  );
}
