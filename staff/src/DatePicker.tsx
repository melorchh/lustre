import { useLayoutEffect, useRef, useState, type CSSProperties } from 'react';
import { createPortal } from 'react-dom';
import { fmtDate } from './api';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const pad2 = (n: number) => String(n).padStart(2, '0');

type View = { y: number; m: number };
type PopupPos = { top: number; left: number; width: number; up: boolean };

interface DatePickerProps {
  value: string;
  onChange: (value: string) => void;
  min?: string;
  max?: string;
  placeholder?: string;
}

function parseDate(value?: string | null): Date | null {
  if (!value) return null;
  const d = new Date(`${value}T00:00:00`);
  return Number.isNaN(d.getTime()) ? null : d;
}

function viewFor(value: string): View {
  const base = parseDate(value) || new Date();
  return { y: base.getFullYear(), m: base.getMonth() };
}

export default function DatePicker({ value, onChange, min, max, placeholder = 'Select a date' }: DatePickerProps) {
  const [open, setOpen] = useState(false);
  const [view, setView] = useState<View>(() => viewFor(value));
  const [pos, setPos] = useState<PopupPos | null>(null);
  const rootRef = useRef<HTMLDivElement>(null);
  const popupRef = useRef<HTMLDivElement>(null);

  const minDate = parseDate(min);
  const maxDate = parseDate(max);

  useLayoutEffect(() => {
    if (!open) return;
    const root = rootRef.current;
    if (!root) return;

    const position = () => {
      const popup = popupRef.current;
      const r = root.getBoundingClientRect();
      const vh = window.innerHeight || document.documentElement.clientHeight;
      const vw = window.innerWidth || document.documentElement.clientWidth;
      const isMobile = vw <= 480;
      const margin = 8;
      const calW = isMobile
        ? Math.min(320, Math.max(0, vw - margin * 2))
        : Math.min(330, Math.max(295, r.width));
      const naturalHeight =
        popup?.scrollHeight ||
        popup?.firstElementChild?.getBoundingClientRect().height ||
        380;
      const visibleHeight = isMobile ? Math.min(naturalHeight, vh - margin * 2) : naturalHeight;
      const modal = isMobile ? root.closest('.modal-box, .register-sheet, .register-modal') : null;
      const containerRect = modal?.getBoundingClientRect();
      const downRoom = vh - r.bottom;
      const up = !isMobile && downRoom < naturalHeight + 16 && r.top > naturalHeight + 16;
      let top = isMobile
        ? (containerRect
            ? containerRect.top + (containerRect.height - visibleHeight) / 2
            : (vh - visibleHeight) / 2)
        : up
          ? r.top - naturalHeight - 10
          : r.bottom + 10;
      top = Math.max(margin, Math.min(top, vh - visibleHeight - margin));
      let left = isMobile && containerRect
        ? containerRect.left + (containerRect.width - calW) / 2
        : isMobile
          ? (vw - calW) / 2
          : r.left;
      if (left + calW > vw - 8) left = vw - 8 - calW;
      if (left < margin) left = margin;
      setPos({ top, left, width: calW, up });
    };

    setView(viewFor(value));
    position();
    const raf = window.requestAnimationFrame(position);

    const onDocDown = (e: MouseEvent | TouchEvent) => {
      const target = e.target as Node;
      if (rootRef.current?.contains(target)) return;
      if (popupRef.current?.contains(target)) return;
      setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    const onScroll = () => position();

    document.addEventListener('mousedown', onDocDown);
    document.addEventListener('touchstart', onDocDown, { passive: true });
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', onScroll, true);
    window.addEventListener('resize', onScroll);

    return () => {
      window.cancelAnimationFrame(raf);
      document.removeEventListener('mousedown', onDocDown);
      document.removeEventListener('touchstart', onDocDown);
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('scroll', onScroll, true);
      window.removeEventListener('resize', onScroll);
    };
  }, [open, value]);

  const canPrev =
    !minDate || view.y > minDate.getFullYear() || (view.y === minDate.getFullYear() && view.m > minDate.getMonth());
  const canNext =
    !maxDate || view.y < maxDate.getFullYear() || (view.y === maxDate.getFullYear() && view.m < maxDate.getMonth());

  const shift = (dir: 1 | -1) =>
    setView((v) => {
      const m = v.m + dir;
      return m < 0 ? { y: v.y - 1, m: 11 } : m > 11 ? { y: v.y + 1, m: 0 } : { y: v.y, m };
    });

  const monthLabel = new Date(view.y, view.m, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const firstDow = new Date(view.y, view.m, 1).getDay();
  const daysInMonth = new Date(view.y, view.m + 1, 0).getDate();

  const cells: (number | null)[] = [];
  for (let i = 0; i < firstDow; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);

  const now = new Date();
  const todayStr = `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`;

  return (
    <div className="admin-datepicker" ref={rootRef}>
      <button
        type="button"
        className={`admin-dp-trigger ${value ? 'has-value' : 'placeholder'}${open ? ' open' : ''}`}
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="dialog"
        aria-expanded={open}
      >
        <span className="admin-dp-cal">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="3" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
        </span>
        <span className="admin-dp-text">{value ? fmtDate(value) : placeholder}</span>
        <span className="admin-dp-chev">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
          </svg>
        </span>
      </button>

      {open &&
        createPortal(
          <div
            ref={popupRef}
            className={`admin-dp-popup show${pos?.up ? ' up' : ''}`}
            role="dialog"
            style={
              {
                top: pos?.top ?? 0,
                left: pos?.left ?? 0,
                '--dp-w': `${pos?.width ?? 310}px`,
                visibility: pos ? 'visible' : 'hidden',
              } as CSSProperties
            }
          >
            <div className="calendar admin-cal">
              <div className="cal-head">
                <button type="button" className="cal-nav" disabled={!canPrev} onClick={() => shift(-1)} aria-label="Previous month">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                  </svg>
                </button>
                <span className="cal-month">{monthLabel}</span>
                <button type="button" className="cal-nav" disabled={!canNext} onClick={() => shift(1)} aria-label="Next month">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M9 6l6 6-6 6" />
                  </svg>
                </button>
              </div>

              <div className="cal-weekdays">
                {WEEKDAYS.map((w) => (
                  <span key={w}>{w}</span>
                ))}
              </div>

              <div className="cal-days">
                {cells.map((day, i) => {
                  if (day === null) return <span key={`blank-${i}`} className="cal-day cal-day--blank" />;
                  const dateStr = `${view.y}-${pad2(view.m + 1)}-${pad2(day)}`;
                  const dt = new Date(`${dateStr}T00:00:00`);
                  const disabled = (minDate && dt < minDate) || (maxDate && dt > maxDate);
                  if (disabled) {
                    return (
                      <span key={dateStr} className="cal-day cal-day--disabled">
                        <span className="cal-day-num">{day}</span>
                      </span>
                    );
                  }
                  const cls = ['cal-btn'];
                  if (dateStr === value) cls.push('active');
                  if (dateStr === todayStr) cls.push('cal-today');
                  return (
                    <span key={dateStr} className="cal-day">
                      <button
                        type="button"
                        className={cls.join(' ')}
                        onClick={() => {
                          onChange(dateStr);
                          setOpen(false);
                        }}
                      >
                        <span className="cal-day-num">{day}</span>
                      </button>
                    </span>
                  );
                })}
              </div>

              <div className="cal-legend">
                <span className="cal-legend-dot" />
                <span>{value ? `Selected: ${fmtDate(value)}` : 'Pick a date from the calendar'}</span>
                {value && (
                  <button
                    type="button"
                    className="cal-clear"
                    onClick={() => {
                      onChange('');
                      setOpen(false);
                    }}
                  >
                    Clear
                  </button>
                )}
              </div>
            </div>
          </div>,
          document.body,
        )}
    </div>
  );
}
