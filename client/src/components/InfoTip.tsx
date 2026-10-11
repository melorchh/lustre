import { useEffect, useRef, useState } from 'react';

interface InfoTipProps {
  /** Text shown inside the popover. */
  text: string;
  /** Accessible label for the icon button (defaults to `text`). */
  label?: string;
}

function canHover(): boolean {
  return typeof window !== 'undefined' && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
}

export default function InfoTip({ text, label }: InfoTipProps) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLSpanElement>(null);
  const [hoverable] = useState(canHover);

  useEffect(() => {
    if (!open) return;
    const onDocDown = (e: MouseEvent | TouchEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onDocDown);
    document.addEventListener('touchstart', onDocDown, { passive: true });
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDocDown);
      document.removeEventListener('touchstart', onDocDown);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  return (
    <span
      className="info-tip"
      ref={rootRef}
      onMouseEnter={hoverable ? () => setOpen(true) : undefined}
      onMouseLeave={hoverable ? () => setOpen(false) : undefined}
    >
      <button
        type="button"
        className={`info-tip-btn ${open ? 'is-open' : ''}`}
        aria-label={label ?? text}
        aria-expanded={open}
        onClick={() => {
          if (!hoverable) setOpen((v) => !v);
        }}
        onFocus={hoverable ? () => setOpen(true) : undefined}
        onBlur={hoverable ? () => setOpen(false) : undefined}
      >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="9.5" />
          <path d="M12 11v5" />
          <path d="M12 7.5h.01" />
        </svg>
      </button>
      {open && (
        <span className="info-tip-pop" role="tooltip">
          {text}
        </span>
      )}
    </span>
  );
}
