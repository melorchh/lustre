import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { formatLongDate } from '../api';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const pad2 = (n: number) => String(n).padStart(2, '0');

type View = { y: number; m: number };

interface DatePickerProps {
  value: string;
  onChange: (v: string) => void;
  min?: string;
  max?: string;
  placeholder?: string;
}

export default function DatePicker({ value, onChange, min, max, placeholder = 'Select a date' }: DatePickerProps) {
  const [open, setOpen] = useState(false);
  const [flip, setFlip] = useState(false);
  const [rect, setRect] = useState<{ top: number; bottom: number; left: number; width: number } | null>(null);
  const rootRef = useRef<HTMLDivElement>(null);

  const minDate = min ? new Date(`${min}T00:00:00`) : null;
  const maxDate = max ? new Date(`${max}T00:00:00`) : null;
  const selected = value ? new Date(`${value}T00:00:00`) : null;

  const [view, setView] = useState<View>(() => {
    const base = selected && !Number.isNaN(selected.getTime()) ? selected : new Date();
    return { y: base.getFullYear(), m: base.getMonth() };
  });

  useLayoutEffect(() => {
    if (!open || !rootRef.current) return;

    const position = () => {
      if (!rootRef.current) return;
      const el = rootRef.current.getBoundingClientRect();
      const vh = window.innerHeight || document.documentElement.clientHeight;
      const gap = 8;
      const popH = 400;
      const downRoom = vh - el.bottom - gap;
      const upRoom = el.top - gap;
      const nflip = downRoom < popH && upRoom > downRoom;
      setFlip(nflip);
      setRect({ top: el.bottom, bottom: el.top, left: el.left, width: el.width });
      if (el.bottom < -80 || el.top > vh + 80) setOpen(false);
    };

    position();
    const base = selected && !Number.isNaN(selected.getTime()) ? selected : new Date();
    setView({ y: base.getFullYear(), m: base.getMonth() });

    const onDown = (e: MouseEvent | TouchEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) {
        const port = document.getElementById('dp-portal');
        if (port && !port.contains(e.target as Node)) setOpen(false);
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    const onScroll = () => position();
    document.addEventListener('mousedown', onDown);
    document.addEventListener('touchstart', onDown, { passive: true });
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', onScroll, true);
    window.addEventListener('resize', onScroll);
    const raf = window.setTimeout(position, 30);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('touchstart', onDown);
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('scroll', onScroll, true);
      window.removeEventListener('resize', onScroll);
      window.clearTimeout(raf);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

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

  const t = new Date();
  const todayStr = `${t.getFullYear()}-${pad2(t.getMonth() + 1)}-${pad2(t.getDate())}`;

  const vw = window.innerWidth || document.documentElement.clientWidth;
  const vh = window.innerHeight || document.documentElement.clientHeight;
  const popW = Math.min(320, Math.max(295, rect ? rect.width : 320));
  const left = rect ? Math.max(8, Math.min(rect.left, vw - 8 - popW)) : 8;
  const POP_H = 400;
  const openDown = !flip && rect;
  const openUp = flip && rect;
  const topVal = openUp && rect && rect.bottom - POP_H - 8 < 8 ? 8 : openDown ? rect.top + 8 : 'auto';
  const bottomVal = openUp ? (topVal === 8 ? vh - (8 + POP_H) - 8 : vh - rect.bottom + 8) : 'auto';

  const calendarNode = (
    <div className="calendar">
      <div className="cal-head">
        <button type="button" className="cal-nav" disabled={!canPrev} onClick={() => shift(-1)} aria-label="Previous month">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        </button>
        <span className="cal-month">{monthLabel}</span>
        <button type="button" className="cal-nav" disabled={!canNext} onClick={() => shift(1)} aria-label="Next month">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </div>

      <div className="cal-weekdays">
        {WEEKDAYS.map((w) => (
          <span key={w}>{w}</span>
        ))}
      </div>

      <div className="cal-days">
        {cells.map((day, i) => {
          if (day === null) return <span key={`bl-${i}`} className="cal-day cal-day--blank" />;
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
        <span>{value ? `Selected: ${formatLongDate(value)}` : 'Pick a date from the calendar'}</span>
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
  );

  useEffect(() => {
    return () => setOpen(false);
  }, []);

  return (
    <div className="datepicker" ref={rootRef}>
      <button
        type="button"
        className={`datepicker-trigger ${value ? 'has-value' : ''} ${open ? 'open' : ''}`}
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="dialog"
        aria-expanded={open}
      >
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <rect x="3" y="4" width="18" height="18" rx="3" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        <span className={`datepicker-text ${value ? '' : 'placeholder'}`}>{value ? formatLongDate(value) : placeholder}</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className={`datepicker-chev ${open ? 'open' : ''}`} aria-hidden="true">
          <path d="m6 9 6 6 6-6" />
        </svg>
      </button>

      {open &&
        rect &&
        createPortal(
          <div
            id="dp-portal"
            className={`datepicker-popup ${flip ? 'datepicker-popup--up' : ''}`}
            role="dialog"
            aria-modal="false"
            style={{
              position: 'fixed',
              top: topVal,
              bottom: bottomVal,
              left,
              width: popW,
              margin: 0,
            }}
          >
            {calendarNode}
          </div>,
          document.body,
        )}
    </div>
  );
}
