import { useState } from 'react';
import Shell from '../Shell';
import { getInitial, fetchData, fmtDate } from '../api';
import { showLoading, hideLoading } from '../loading';
import { useToast } from '../hooks';
import { Select } from '../components';
import DatePicker from '../DatePicker';
import { Card, EmptyState, Field, FilterBar, Stack, StatCard, StatGrid } from '../ui';
import { IconAppointments, IconCheck, IconPatients, IconPrint, IconReports, IconVitals } from '../icons';

interface BreakdownRow {
  bucket: string;
  label: string;
  appts: number;
  patients: number;
  completed: number;
  cancelled: number;
  paid: number;
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
] as const;

type ChartField = (typeof CHART_FIELDS)[number]['key'];

function niceMax(v: number): number {
  if (v <= 5) return 5;
  const mag = Math.pow(10, Math.floor(Math.log10(v)));
  const norm = v / mag;
  const nice = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10;
  return nice * mag;
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
              {Math.round(v)}
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
            <title>{`${r.label}: ${values[i]}`}</title>
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
    paid: summary.paid || 0,
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

  async function generateReport() {
    await apply();
    window.setTimeout(printReport, 150);
  }

  function printReport() {
    const sheet = document.getElementById('report-sheet');
    if (!sheet) return;
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(frame);
    const w = frame.contentWindow;
    if (!w) {
      frame.remove();
      showToast('Unable to prepare the report for printing', 'error');
      return;
    }
    w.document.open();
    const css = `
      *{box-sizing:border-box}
      body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;padding:24px;margin:0;background:#fff}
      h2{margin:0 0 8px;font-size:20px}
      h3{font-size:13px;color:#15803d;letter-spacing:.06em;text-transform:uppercase;margin:18px 0 8px}
      .ap-card-heading p{font-size:12px;color:#475569;margin:2px 0 12px}
      .ap-card-actions{display:none !important}
      .report-meta{display:flex;flex-wrap:wrap;gap:4px 22px;color:#475569;font-size:12px;margin-bottom:14px}
      .ap-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:10px 0 4px}
      .ap-stat{display:flex;gap:8px;border:1px solid #e2e8f0;border-radius:9px;padding:9px 10px}
      .ap-stat-icon,.ap-stat-hint{display:none}
      .ap-stat-value{font-size:20px;font-weight:800;line-height:1.2}
      .ap-stat-label{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
      .chart-switch{display:none !important}
      .report-chart{width:100%;height:auto;max-height:300px}
      .table-scroll{overflow:visible !important}
      table{width:100%;border-collapse:collapse;font-size:11px}
      th,td{border:1px solid #e2e8f0;padding:6px 7px;text-align:left}
      th{background:#f8fafc;color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
      tfoot td{font-weight:700;background:#f8fafc}
      .report-note{font-size:10px;color:#94a3b8;margin-top:12px;padding-top:9px;border-top:1px solid #e2e8f0}
      .ap-empty{text-align:center;color:#94a3b8;padding:18px}
      .chart-empty{border:1px dashed #e2e8f0;border-radius:8px;padding:24px;text-align:center;color:#94a3b8}
    `;
    w.document.write(
      `<!DOCTYPE html><html><head><title>Patient &amp; Appointment Report</title><style>${css}</style></head>` +
      `<body>${sheet.innerHTML}<script>window.onload=function(){window.print();};<\/script></body></html>`,
    );
    w.document.close();
    window.setTimeout(() => frame.remove(), 60000);
    showToast('Report ready to print', 'success');
  }

  const totals = rows.reduce(
    (acc, r) => ({
      appts: acc.appts + Number(r.appts || 0),
      patients: acc.patients + Number(r.patients || 0),
      completed: acc.completed + Number(r.completed || 0),
      cancelled: acc.cancelled + Number(r.cancelled || 0),
      paid: acc.paid + Number(r.paid || 0),
    }),
    { appts: 0, patients: 0, completed: 0, cancelled: 0, paid: 0 },
  );

  return (
    <Shell active="reports" title="Reports">
      <Stack>
        <Card
          title="Report filters"
          subtitle={`Showing ${periodLabel.toLowerCase()} · grouped ${groupLabel.toLowerCase()}`}
          actions={
            <button type="button" className="btn-primary" onClick={generateReport}>
              <IconPrint size={16} /> Generate report
            </button>
          }
        >
          <FilterBar>
            <Field label="Quick range">
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
            </Field>
            <Field label="From" className="ap-field--date">
              <DatePicker
                value={filters.from}
                onChange={(v) => {
                  setField('from', v);
                  if (filters.period) setField('period', '');
                }}
              />
            </Field>
            <Field label="To" className="ap-field--date">
              <DatePicker
                value={filters.to}
                onChange={(v) => {
                  setField('to', v);
                  if (filters.period) setField('period', '');
                }}
              />
            </Field>
            <Field label="Group by" className="ap-field--select">
              <Select className="apt-select" value={filters.group} onChange={(e) => apply({ group: e.target.value, period: filters.period })}>
                <option value="date">Daily</option>
                <option value="week">Weekly</option>
                <option value="month">Monthly</option>
              </Select>
            </Field>
            <Field label="Status" className="ap-field--select">
              <Select className="apt-select" value={filters.status} onChange={(e) => setField('status', e.target.value)}>
                <option value="">All statuses</option>
                {['pending', 'confirmed', 'completed', 'cancelled'].map((s) => (
                  <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                ))}
              </Select>
            </Field>
            <Field label="Payment" className="ap-field--select">
              <Select className="apt-select" value={filters.payment} onChange={(e) => setField('payment', e.target.value)}>
                <option value="">All payments</option>
                <option value="paid">Paid</option>
                <option value="pending">Unpaid</option>
              </Select>
            </Field>
            <Field label="Doctor" className="ap-field--select">
              <Select className="apt-select" value={filters.doctor_id} onChange={(e) => setField('doctor_id', e.target.value)}>
                <option value="">All doctors</option>
                {doctors.map((doc) => (
                  <option key={doc.id} value={doc.id}>
                    {doc.name}{doc.specialty ? ` (${doc.specialty})` : ''}
                  </option>
                ))}
              </Select>
            </Field>
          </FilterBar>
        </Card>

        <Card
          id="report-sheet"
          className="report-sheet"
          title="Patient & appointment report"
          subtitle={`${periodLabel} · ${groupLabel} breakdown · generated ${generated}`}
        >
          <StatGrid>
            <StatCard icon={<IconAppointments />} label="Appointments" value={metrics.appointments ?? 0} hint="Booked in range" />
            <StatCard icon={<IconPatients />} label="Unique patients" value={metrics.patients ?? 0} hint="Distinct patients seen" tone="blue" />
            <StatCard icon={<IconCheck />} label="Completed" value={metrics.completed ?? 0} hint="Finished visits" tone="slate" />
            <StatCard icon={<IconAppointments />} label="Cancelled" value={metrics.cancelled ?? 0} hint="Did not push through" tone="rose" />
            <StatCard icon={<IconVitals />} label="Paid visits" value={metrics.paid ?? 0} hint="Settled at the counter" tone="amber" />
          </StatGrid>

          <div className="report-meta">
            <span>Period: <strong>{periodLabel}</strong></span>
            <span>Grouped: <strong>{groupLabel}</strong></span>
            <span>Generated: <strong>{generated}</strong></span>
          </div>

          <h3 className="report-section">{groupLabel} patient trend</h3>
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

          <h3 className="report-section">{groupLabel} breakdown</h3>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>{groupLabel}</th>
                  <th>Appointments</th>
                  <th>Unique patients</th>
                  <th>Completed</th>
                  <th>Cancelled</th>
                  <th>Paid</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={6}>
                      <EmptyState
                        icon={<IconReports size={20} />}
                        title="No records match these filters"
                        hint="Widen the date range or clear the status, payment or doctor filters."
                      />
                    </td>
                  </tr>
                ) : rows.map((r) => (
                  <tr key={r.bucket}>
                    <td><span className="ap-strong">{r.label}</span></td>
                    <td>{r.appts}</td>
                    <td>{r.patients}</td>
                    <td>{r.completed}</td>
                    <td>{r.cancelled}</td>
                    <td>{r.paid}</td>
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
                  </tr>
                </tfoot>
              )}
            </table>
          </div>

        </Card>
      </Stack>
    </Shell>
  );
}
