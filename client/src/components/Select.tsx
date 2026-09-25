import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

interface Option {
  value: string;
  label: string;
}

interface SelectProps {
  value: string;
  onChange: (v: string) => void;
  options: Option[];
  placeholder?: string;
  icon?: React.ReactNode;
}

export default function Select({ value, onChange, options, placeholder = 'Select', icon }: SelectProps) {
  const [open, setOpen] = useState(false);
  const [flip, setFlip] = useState(false);
  const [rect, setRect] = useState<{ top: number; bottom: number; left: number; width: number } | null>(null);
  const rootRef = useRef<HTMLDivElement>(null);

  const selected = options.find((o) => o.value === value);
  const label = selected ? selected.label : placeholder;

  const POP_H = Math.min(280, options.length * 48 + 16);

  useLayoutEffect(() => {
    if (!open || !rootRef.current) return;

    const position = () => {
      if (!rootRef.current) return;
      const el = rootRef.current.getBoundingClientRect();
      const vh = window.innerHeight || document.documentElement.clientHeight;
      const gap = 8;
      const downRoom = vh - el.bottom - gap;
      const upRoom = el.top - gap;
      const nflip = downRoom < POP_H && upRoom > downRoom;
      setFlip(nflip);
      setRect({ top: el.bottom, bottom: el.top, left: el.left, width: el.width });
      if (el.bottom < -80 || el.top > vh + 80) setOpen(false);
    };

    position();
    const raf = window.setTimeout(position, 30);

    const onDown = (e: MouseEvent | TouchEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) {
        const port = document.getElementById('fd-portal');
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

  useEffect(() => () => setOpen(false), []);

  const vw = window.innerWidth || document.documentElement.clientWidth;
  const popW = Math.min(340, Math.max(220, rect ? rect.width : 260));

  return (
    <div className="fd-select" ref={rootRef}>
      <button
        type="button"
        className={`fd-select-trigger ${value ? 'has-value' : ''} ${open ? 'open' : ''}`}
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        {icon && <span className="fd-select-icon">{icon}</span>}
        <span className={`fd-select-text ${value ? '' : 'placeholder'}`}>{label}</span>
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          strokeLinecap="round"
          strokeLinejoin="round"
          className={`fd-select-chev ${open ? 'open' : ''}`}
          aria-hidden="true"
        >
          <path d="m6 9 6 6 6-6" />
        </svg>
      </button>

      {open &&
        rect &&
        createPortal(
          <div
            id="fd-portal"
            className={`fd-select-popup ${flip ? 'fd-select-popup--up' : ''}`}
            role="listbox"
            style={{
              position: 'fixed',
              top: flip ? Math.max(8, rect.bottom - POP_H - 8) : rect.top + 8,
              left: Math.max(8, Math.min(rect.left, vw - 8 - popW)),
              width: popW,
              margin: 0,
              maxHeight: 280,
            }}
          >
            <div className="fd-select-menu">
              {options.map((o) => (
                <button
                  key={o.value}
                  type="button"
                  role="option"
                  aria-selected={o.value === value}
                  className={`fd-opt ${o.value === value ? 'sel' : ''}`}
                  onClick={() => {
                    onChange(o.value);
                    setOpen(false);
                  }}
                >
                  {o.label}
                </button>
              ))}
            </div>
          </div>,
          document.body,
        )}
    </div>
  );
}
