import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, fmtDate } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';

interface BreakdownRow {
  bucket: string;
  label: string;
  appts: number;
  patients: number;
  completed: number;
  cancelled: number;
  paid: number;
  revenue: number;
}

interface DoctorOption {
  id: number;
  name: string;
  specialty?: string;
}

interface ReportsBundle {
  range?: { from: string; to: string };
  filters?: {
    period?: string;
    group?: string;
    status?: string;
    payment?: string;
    doctor_id?: number;
  };
  summary?: Record<string, number>;
  metrics?: Record<string, number>;
  breakdown?: BreakdownRow[];
  byDay?: BreakdownRow[];
  doctors?: DoctorOption[];
}

interface FilterState {
  period: string;
  from: string;
  to: string;
  group: string;
  status: string;
  payment: string;
  doctor_id: string;
}

const PRESETS = [
  { key: '', label: 'All' },
  { key: 'today', label: 'Today' },
  { key: 'week', label: 'This Week' },
  { key: 'month', label: 'This Month' },
  { key: 'year', label: 'This Year' },
];

const GROUP_LABELS: Record<string, string> = {
  date: 'Daily',
  week: 'Weekly',
  month: 'Monthly',
};

const CHART_FIELDS = [
  { key: 'patients', label: 'Patients', color: '#0ea5e9' },
  { key: 'appts', label: 'Appointments', color: '#8b5cf6' },
  { key: 'paid', label: 'Paid', color: '#f59e0b' },
  { key: 'revenue', label: 'Revenue', color: '#16a34a' },
] as const;

type ChartField = (typeof CHART_FIELDS)[number]['key'];

function niceMax(v: number): number {
  if (v <= 5) return 5;
  const mag = Math.pow(10, Math.floor(Math.log10(v)));
  const norm = v / mag;
  const nice = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10;
  return nice * mag;
}

function fmtMoney(v: number): string {
  return `\u20b1${Number(v).toLocaleString('en-US')}`;
}

function fmtMoneyShort(v: number): string {
  if (v >= 1000000) return `\u20b1${(v / 1000000).toFixed(1)}M`;
  if (v >= 1000) return `\u20b1${(v / 1000).toFixed(0)}k`;
  return `\u20b1${v}`;
}

/** Inline SVG line chart (keeps the old Chart.js look without the CDN). */
function ReportChart({ rows, field }: { rows: BreakdownRow[]; field: ChartField }) {
  if (rows.length === 0) {
    return <div className="chart-empty">No data to display for the selected filters.</div>;
  }

  const W = 960;
  const H = 340;
  const padL = 66;
  const padR = 20;
  const padT = 18;
  const padB = 48;
  const plotW = W - padL - padR;
  const plotH = H - padT - padB;
  const meta = CHART_FIELDS.find((f) => f.key === field) || CHART_FIELDS[0];
  const isMoney = field === 'revenue';
  const values = rows.map((r) => Number(r[field] || 0));
  const top = niceMax(Math.max(1, ...values));
  const xAt = (i: number) => (rows.length === 1 ? padL + plotW / 2 : padL + (i * plotW) / (rows.length - 1));
  const yAt = (v: number) => padT + plotH - (v / top) * plotH;
  const points = values.map((v, i) => `${xAt(i)},${yAt(v)}`).join(' ');
  const baseline = padT + plotH;
  const tickStep = Math.ceil(rows.length / 9);

  return (
    <svg className="report-chart" viewBox={`0 0 ${W} ${H}`} role="img" aria-label={`${meta.label} chart`}>
      {[0, 1, 2, 3, 4].map((i) => {
        const v = (top * i) / 4;
        const y = yAt(v);
        return (
          <g key={i}>
            <line x1={padL} y1={y} x2={W - padR} y2={y} stroke="var(--gray-100, #e2e8f0)" strokeWidth={1} />
            <text x={padL - 9} y={y + 3.5} textAnchor="end" fontSize={10} fill="var(--gray-600, #64748b)">
              {isMoney ? fmtMoneyShort(v) : Math.round(v)}
            </text>
          </g>
        );
      })}

      <polygon
        points={`${xAt(0)},${baseline} ${points} ${xAt(rows.length - 1)},${baseline}`}
        fill={meta.color}
        fillOpacity={0.12}
      />
      <polyline
        points={points}
        fill="none"
        stroke={meta.color}
        strokeWidth={2.5}
        strokeLinejoin="round"
        strokeLinecap="round"
      />

      {rows.map((r, i) => (
        <g key={`${r.bucket}-${i}`}>
          <circle cx={xAt(i)} cy={yAt(values[i])} r={4} fill={meta.color} stroke="#fff" strokeWidth={1.5}>
            <title>{`${r.label}: ${isMoney ? fmtMoney(values[i]) : values[i]}`}</title>
          </circle>
          {i % tickStep === 0 || i === rows.length - 1 ? (
            <text x={xAt(i)} y={H - 16} textAnchor="middle" fontSize={10} fill="var(--gray-600, #64748b)">
              {r.label}
            </text>
          ) : null}
        </g>
      ))}
    </svg>
  );
}

export default function AdminReports() {
  const d = getInitial().data as ReportsBundle;
  const { showToast } = useToast();
  const [report, setReport] = useState<ReportsBundle>(d);
  const [doctors, setDoctors] = useState<DoctorOption[]>(d.doctors || []);
  const [filters, setFilters] = useState<FilterState>({
    period: d.filters?.period || '',
    from: d.range?.from || '',
    to: d.range?.to || '',
    group: d.filters?.group || 'date',
    status: d.filters?.status || '',
    payment: d.filters?.payment || '',
    doctor_id: d.filters?.doctor_id ? String(d.filters.doctor_id) : '',
  });
  const [chartField, setChartField] = useState<ChartField>('patients');

  const summary = report.summary || {};
  const rows = report.breakdown || report.byDay || [];
  const metrics = report.metrics || {
    appointments: summary.total || 0,
    patients: summary.patients || 0,
    completed: summary.completed || 0,
    cancelled: summary.cancelled || 0,
    paid: Math.round((summary.revenue || 0) / 500),
    revenue: summary.revenue || 0,
  };
  const groupLabel = GROUP_LABELS[filters.group] || 'Daily';

  const generated = new Date().toLocaleString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });

  const periodLabel = (() => {
    if (filters.from && filters.to) return `${fmtDate(filters.from)} \u2013 ${fmtDate(filters.to)}`;
    if (filters.from) return `From ${fmtDate(filters.from)}`;
    if (filters.to) return `Up to ${fmtDate(filters.to)}`;
    return 'All time';
  })();

  async function apply(overrides: Partial<FilterState> = {}) {
    const next = { ...filters, ...overrides };
    setFilters(next);
    showLoading();
    try {
      const json = await fetchData<{ data?: ReportsBundle }>('api_admin_data.php', {
        view: 'reports',
        period: next.period,
        from: next.from,
        to: next.to,
        group: next.group,
        status: next.status,
        payment: next.payment,
        doctor_id: next.doctor_id || undefined,
      });
      if (json?.data) {
        setReport(json.data);
        if (json.data.doctors) setDoctors(json.data.doctors);
        const range = json.data.range;
        if (range) {
          setFilters((prev) => ({ ...prev, from: range.from || '', to: range.to || '' }));
        }
      }
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Failed to load report', 'error');
    } finally {
      hideLoading();
    }
  }

  function setField<K extends keyof FilterState>(key: K, value: FilterState[K]) {
    setFilters((prev) => ({ ...prev, [key]: value }));
  }

  function printReport() {
    const sheet = document.getElementById('report-sheet');
    if (!sheet) return;
    const w = window.open('', '_blank', 'width=1000,height=760');
    if (!w) {
      showToast('Popup blocked \u2014 allow popups to print', 'error');
      return;
    }
    const css = `
      *{box-sizing:border-box}
      body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;padding:24px;margin:0;background:#fff}
      h2{margin:0 0 8px;font-size:20px}
      h3{font-size:13px;color:#15803d;letter-spacing:.06em;text-transform:uppercase;margin:18px 0 8px}
      .report-meta{display:flex;flex-wrap:wrap;gap:4px 22px;color:#475569;font-size:12px;margin-bottom:14px}
      .stats-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin:10px 0 4px}
      .stat-card{border:1px solid #e2e8f0;border-top:3px solid #16a34a;border-radius:9px;padding:9px 10px}
      .stat-number{font-size:20px;font-weight:800;line-height:1.2}
      .stat-label{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
      .chart-switch{display:none !important}
      .report-chart{width:100%;height:auto;max-height:300px}
      .table-scroll{overflow:visible !important}
      table{width:100%;border-collapse:collapse;font-size:11px}
      th,td{border:1px solid #e2e8f0;padding:6px 7px;text-align:left}
      th{background:#f8fafc;color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
      tfoot td{font-weight:700;background:#f8fafc}
      .report-note{font-size:10px;color:#94a3b8;margin-top:12px;padding-top:9px;border-top:1px solid #e2e8f0}
      .empty-state-row{text-align:center;color:#94a3b8}
      .chart-empty{border:1px dashed #e2e8f0;border-radius:8px;padding:24px;text-align:center;color:#94a3b8}
    `;
    w.document.write(
      `<!DOCTYPE html><html><head><title>Patient &amp; Appointment Report</title><style>${css}</style></head>` +
      `<body>${sheet.innerHTML}<script>window.onload=function(){window.print();};<\/script></body></html>`,
    );
    w.document.close();
    showToast('Report ready to print', 'success');
  }

  const totals = rows.reduce(
    (acc, r) => ({
      appts: acc.appts + Number(r.appts || 0),
      patients: acc.patients + Number(r.patients || 0),
      completed: acc.completed + Number(r.completed || 0),
      cancelled: acc.cancelled + Number(r.cancelled || 0),
      paid: acc.paid + Number(r.paid || 0),
      revenue: acc.revenue + Number(r.paid || 0) * 500,
    }),
    { appts: 0, patients: 0, completed: 0, cancelled: 0, paid: 0, revenue: 0 },
  );

  return (
    <Shell active="reports" title="Reports">
      <div className="content-card report-filters">
        <div className="report-toolbar">
          <div className="report-presets">
            {PRESETS.map((p) => (
              <button
                key={p.key || 'all'}
                type="button"
                className={filters.period === p.key ? 'on' : ''}
                onClick={() => apply({ period: p.key, from: '', to: '' })}
              >
                {p.label}
              </button>
            ))}
          </div>

          <div className="field">
            <label>From</label>
            <input
              type="date"
              className="form-input"
              value={filters.from}
              onChange={(e) => setField('from', e.target.value)}
              onInput={() => filters.period && setField('period', '')}
            />
          </div>
          <div className="field">
            <label>To</label>
            <input
              type="date"
              className="form-input"
              value={filters.to}
              onChange={(e) => setField('to', e.target.value)}
              onInput={() => filters.period && setField('period', '')}
            />
          </div>
          <div className="field">
            <label>Group By</label>
            <select className="form-dropdown" value={filters.group} onChange={(e) => apply({ group: e.target.value, period: filters.period })}>
              <option value="date">Daily</option>
              <option value="week">Weekly</option>
              <option value="month">Monthly</option>
            </select>
          </div>
          <div className="field">
            <label>Status</label>
            <select className="form-dropdown" value={filters.status} onChange={(e) => setField('status', e.target.value)}>
              <option value="">All Statuses</option>
              {['pending', 'confirmed', 'completed', 'cancelled'].map((s) => (
                <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
              ))}
            </select>
          </div>
          <div className="field">
            <label>Payment</label>
            <select className="form-dropdown" value={filters.payment} onChange={(e) => setField('payment', e.target.value)}>
              <option value="">All Payments</option>
              <option value="paid">Paid</option>
              <option value="pending">Unpaid</option>
            </select>
          </div>
          <div className="field">
            <label>Doctor</label>
            <select className="form-dropdown" value={filters.doctor_id} onChange={(e) => setField('doctor_id', e.target.value)}>
              <option value="">All Doctors</option>
              {doctors.map((doc) => (
                <option key={doc.id} value={doc.id}>
                  {doc.name}{doc.specialty ? ` (${doc.specialty})` : ''}
                </option>
              ))}
            </select>
          </div>
          <div className="field">
            <button type="button" className="btn-primary" onClick={() => apply()}>Generate Report</button>
          </div>
          <div className="field">
            <button type="button" className="btn-secondary" onClick={printReport}>Print / PDF</button>
          </div>
        </div>
      </div>

      <div className="content-card report-sheet" id="report-sheet">
        <h2>Patient &amp; Appointment Report</h2>
        <div className="report-meta">
          <span>Period: <strong>{periodLabel}</strong></span>
          <span>Grouped: <strong>{filters.group.charAt(0).toUpperCase() + filters.group.slice(1)}</strong></span>
          <span>Generated: <strong>{generated}</strong></span>
        </div>

        <div className="stats-grid">
          <div className="stat-card"><div className="stat-number">{metrics.appointments ?? 0}</div><div className="stat-label">Appointments</div></div>
          <div className="stat-card" style={{ borderTopColor: '#8b5cf6' }}><div className="stat-number" style={{ color: '#8b5cf6' }}>{metrics.patients ?? 0}</div><div className="stat-label">Unique Patients</div></div>
          <div className="stat-card" style={{ borderTopColor: '#10b981' }}><div className="stat-number" style={{ color: '#10b981' }}>{metrics.completed ?? 0}</div><div className="stat-label">Completed</div></div>
          <div className="stat-card" style={{ borderTopColor: '#ef4444' }}><div className="stat-number" style={{ color: '#ef4444' }}>{metrics.cancelled ?? 0}</div><div className="stat-label">Cancelled</div></div>
          <div className="stat-card" style={{ borderTopColor: '#f59e0b' }}><div className="stat-number" style={{ color: '#f59e0b' }}>{metrics.paid ?? 0}</div><div className="stat-label">Paid Visits</div></div>
          <div className="stat-card" style={{ borderTopColor: '#0ea5e9' }}><div className="stat-number" style={{ color: '#0ea5e9' }}>{fmtMoney(metrics.revenue ?? 0)}</div><div className="stat-label">Est. Revenue</div></div>
        </div>

        <h3 className="report-section">{groupLabel} Patient Trend</h3>
        <div className="chart-switch">
          <span className="chart-switch-label">Show:</span>
          {CHART_FIELDS.map((f) => (
            <button
              key={f.key}
              type="button"
              className={`chart-chip${chartField === f.key ? ' active' : ''}`}
              onClick={() => setChartField(f.key)}
            >
              {f.label}
            </button>
          ))}
        </div>
        <ReportChart rows={rows} field={chartField} />

        <h3 className="report-section">{groupLabel} Breakdown</h3>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>{groupLabel}</th>
                <th>Appointments</th>
                <th>Unique Patients</th>
                <th>Completed</th>
                <th>Cancelled</th>
                <th>Paid</th>
                <th>Est. Revenue</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr><td colSpan={7} className="empty-state-row">No records match the selected filters.</td></tr>
              ) : rows.map((r) => (
                <tr key={r.bucket}>
                  <td><strong>{r.label}</strong></td>
                  <td>{r.appts}</td>
                  <td>{r.patients}</td>
                  <td>{r.completed}</td>
                  <td>{r.cancelled}</td>
                  <td>{r.paid}</td>
                  <td>{fmtMoney(Number(r.paid || 0) * 500)}</td>
                </tr>
              ))}
            </tbody>
            {rows.length > 0 && (
              <tfoot>
                <tr>
                  <td>Total</td>
                  <td>{totals.appts}</td>
                  <td>{totals.patients}</td>
                  <td>{totals.completed}</td>
                  <td>{totals.cancelled}</td>
                  <td>{totals.paid}</td>
                  <td>{fmtMoney(totals.revenue)}</td>
                </tr>
              </tfoot>
            )}
          </table>
        </div>

        <div className="report-note">
          * Estimated revenue = paid visits &times; {'\u20b1'}500. No billing amount is stored in the appointment record.
        </div>
      </div>
    </Shell>
  );
}
