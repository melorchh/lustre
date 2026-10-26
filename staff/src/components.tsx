import {
  Fragment,
  Children,
  isValidElement,
  useCallback,
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type KeyboardEvent as ReactKeyboardEvent,
  type ReactNode,
  type SelectHTMLAttributes,
} from 'react';
import { createPortal } from 'react-dom';

/**
 * Admin dropdown.
 *
 * A native <select> can be themed while closed, but the list it opens is drawn
 * by the OS/browser, so it always looks "default". This component keeps the
 * familiar <select>-like API (children <option>s, value, onChange with
 * `e.target.value`) and renders its own listbox instead.
 */
export interface SelectChangeEvent {
  type: 'change';
  target: { value: string; name?: string };
  currentTarget: { value: string; name?: string };
}

type SelectPropsBase = Omit<
  SelectHTMLAttributes<HTMLSelectElement>,
  'onChange' | 'children' | 'multiple' | 'size' | 'defaultValue'
>;

export interface SelectProps extends SelectPropsBase {
  onChange?: (e: SelectChangeEvent) => void;
  children?: ReactNode;
}

interface ParsedOption {
  value: string;
  label: ReactNode;
  text: string;
  disabled: boolean;
}

function textOf(node: ReactNode): string {
  if (node === null || node === undefined || typeof node === 'boolean') return '';
  if (typeof node === 'string' || typeof node === 'number') return String(node);
  if (Array.isArray(node)) return node.map(textOf).join('');
  if (isValidElement<{ children?: ReactNode }>(node)) return textOf(node.props.children);
  return '';
}

function parseOptions(children: ReactNode): ParsedOption[] {
  const out: ParsedOption[] = [];
  const walk = (node: ReactNode) => {
    Children.forEach(node, (child) => {
      if (!isValidElement<{ children?: ReactNode }>(child)) return;
      if (child.type === 'option') {
        const props = child.props as { value?: unknown; disabled?: boolean; children?: ReactNode };
        const text = textOf(props.children).trim();
        out.push({
          value: props.value !== undefined ? String(props.value) : text,
          label: props.children,
          text,
          disabled: Boolean(props.disabled),
        });
      } else if (child.type === 'optgroup' || child.type === Fragment) {
        walk((child.props as { children?: ReactNode }).children);
      }
    });
  };
  walk(children);
  return out;
}

const CARET = (
  <svg className="ap-select-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d="m6 9 6 6 6-6" />
  </svg>
);

const CHECK = (
  <svg className="ap-select-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d="m20 6-11 11-5-5" />
  </svg>
);

export function Select({
  value,
  onChange,
  children,
  disabled,
  id,
  name,
  ...rest
}: SelectProps) {
  const options = useMemo(() => parseOptions(children), [children]);
  const current = value === undefined || value === null ? '' : String(value);
  const selectedIndex = options.findIndex((o) => o.value === current);
  const selected = selectedIndex >= 0 ? options[selectedIndex] : undefined;

  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const [pos, setPos] = useState<
    { left: number; minWidth: number; maxHeight: number; top?: number; bottom?: number } | null
  >(null);

  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const typeahead = useRef({ text: '', at: 0 });
  const listId = useId();

  const close = useCallback((refocus: boolean) => {
    setOpen(false);
    if (refocus) window.requestAnimationFrame(() => triggerRef.current?.focus());
  }, []);

  const place = useCallback(() => {
    const el = triggerRef.current;
    if (!el) return;
    const r = el.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const gap = 6;
    const below = vh - r.bottom - gap - 10;
    const above = r.top - gap - 10;
    const estimate = Math.min(308, options.length * 37 + 10);
    const up = estimate > below && above > below;
    const width = Math.max(180, Math.min(r.width, 460, vw - 16));
    const left = Math.max(8, Math.min(r.left, vw - width - 8));
    setPos({
      left,
      minWidth: width,
      maxHeight: Math.max(120, Math.min(308, up ? above : below)),
      ...(up ? { bottom: vh - r.top + gap } : { top: r.bottom + gap }),
    });
  }, [options.length]);

  const openMenu = useCallback(() => {
    if (disabled || options.length === 0) return;
    const startIndex =
      selectedIndex >= 0 && !options[selectedIndex].disabled
        ? selectedIndex
        : options.findIndex((o) => !o.disabled);
    setActive(startIndex);
    place();
    setOpen(true);
  }, [disabled, options, place, selectedIndex]);

  // Track the trigger while the list is open (page scroll, resizing, scrollable tables).
  useEffect(() => {
    if (!open) return;
    place();
    const onMove = () => place();
    window.addEventListener('scroll', onMove, true);
    window.addEventListener('resize', onMove);
    return () => {
      window.removeEventListener('scroll', onMove, true);
      window.removeEventListener('resize', onMove);
    };
  }, [open, place]);

  // Close on outside pointer down.
  useEffect(() => {
    if (!open) return;
    const onDown = (e: PointerEvent) => {
      const target = e.target as Node;
      if (rootRef.current?.contains(target) || menuRef.current?.contains(target)) return;
      setOpen(false);
    };
    document.addEventListener('pointerdown', onDown, true);
    return () => document.removeEventListener('pointerdown', onDown, true);
  }, [open]);

  useEffect(() => {
    if (open && disabled) setOpen(false);
  }, [disabled, open]);

  // Keep the popup inside the viewport once its real width is known.
  useLayoutEffect(() => {
    if (!open) return;
    const el = menuRef.current;
    if (!el) return;
    const r = el.getBoundingClientRect();
    if (r.right > window.innerWidth - 8 || r.left < 8) {
      const left = Math.max(8, Math.min(r.left, window.innerWidth - 8 - r.width));
      setPos((p) => (p && Math.abs(p.left - left) > 0.5 ? { ...p, left } : p));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  useEffect(() => {
    if (!open || active < 0) return;
    const el = menuRef.current?.querySelector<HTMLElement>(`[data-index="${active}"]`);
    el?.scrollIntoView({ block: 'nearest' });
  }, [open, active]);

  const commit = (opt: ParsedOption) => {
    close(true);
    if (opt.disabled || opt.value === current || !onChange) return;
    onChange({
      type: 'change',
      target: { value: opt.value, name },
      currentTarget: { value: opt.value, name },
    });
  };

  const moveActive = (dir: 1 | -1) => {
    if (options.length === 0) return;
    setActive((prev) => {
      let idx = prev;
      for (let n = 0; n < options.length; n += 1) {
        idx = (idx + dir + options.length) % options.length;
        if (!options[idx].disabled) break;
      }
      return idx;
    });
  };

  const onKeyDown = (e: ReactKeyboardEvent<HTMLButtonElement>) => {
    if (disabled) return;
    if (!open) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openMenu();
      }
      return;
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      close(true);
      return;
    }
    if (e.key === 'Tab') {
      close(false);
      return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      moveActive(e.key === 'ArrowDown' ? 1 : -1);
      return;
    }
    if (e.key === 'Home' || e.key === 'End') {
      e.preventDefault();
      const enabled = options.reduce<number[]>((acc, o, i) => {
        if (!o.disabled) acc.push(i);
        return acc;
      }, []);
      if (enabled.length > 0) setActive(e.key === 'Home' ? enabled[0] : enabled[enabled.length - 1]);
      return;
    }
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      if (active >= 0 && options[active]) commit(options[active]);
      return;
    }
    if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && /\S/.test(e.key)) {
      const now = Date.now();
      const prefix =
        now - typeahead.current.at < 700
          ? typeahead.current.text + e.key.toLowerCase()
          : e.key.toLowerCase();
      typeahead.current = { text: prefix, at: now };
      const hit = options.findIndex((o) => !o.disabled && o.text.toLowerCase().startsWith(prefix));
      if (hit >= 0) setActive(hit);
    }
  };

  const isPlaceholder = !selected || selected.value === '';
  const portalTarget =
    typeof document === 'undefined' ? null : document.querySelector('.admin-portal') ?? document.body;

  const menuStyle: CSSProperties = pos
    ? {
        left: pos.left,
        minWidth: pos.minWidth,
        maxHeight: pos.maxHeight,
        ...(pos.top !== undefined ? { top: pos.top } : { bottom: pos.bottom }),
      }
    : { visibility: 'hidden' };

  return (
    <div ref={rootRef} className="ap-select">
      <button
        ref={triggerRef}
        type="button"
        id={id}
        className={`ap-select-trigger${isPlaceholder ? ' is-placeholder' : ''}`}
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={open ? listId : undefined}
        aria-activedescendant={open && active >= 0 ? `${listId}-opt-${active}` : undefined}
        aria-label={rest['aria-label']}
        title={rest.title}
        onClick={() => (open ? close(false) : openMenu())}
        onKeyDown={onKeyDown}
      >
        <span className="ap-select-value">{selected ? selected.label : null}</span>
        {CARET}
      </button>
      {open && portalTarget
        ? createPortal(
            <div
              ref={menuRef}
              id={listId}
              className="ap-select-menu"
              role="listbox"
              aria-label={rest['aria-label']}
              style={menuStyle}
            >
              {options.map((o, i) => (
                <div
                  key={`${o.value}-${i}`}
                  id={`${listId}-opt-${i}`}
                  data-index={i}
                  role="option"
                  aria-selected={i === selectedIndex}
                  aria-disabled={o.disabled || undefined}
                  className={
                    'ap-select-option' +
                    (i === active ? ' is-active' : '') +
                    (i === selectedIndex ? (o.value === '' ? ' is-selected-placeholder' : ' is-selected') : '') +
                    (o.disabled ? ' is-disabled' : '')
                  }
                  onPointerEnter={() => {
                    if (!o.disabled) setActive(i);
                  }}
                  onPointerDown={(e) => e.preventDefault()}
                  onClick={() => commit(o)}
                >
                  <span className="ap-select-option-label">{o.label}</span>
                  {i === selectedIndex ? CHECK : null}
                </div>
              ))}
            </div>,
            portalTarget,
          )
        : null}
    </div>
  );
}

/** Simple controlled modal matching .modal-overlay/.modal-box markup. */
export function Modal({
  open,
  onClose,
  children,
  className = '',
}: {
  open: boolean;
  onClose: () => void;
  children: React.ReactNode;
  className?: string;
}) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (open) document.body.style.overflow = 'hidden';
    else document.body.style.overflow = '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [open]);

  if (!open) return null;
  return (
    <div
      ref={ref}
      className={`modal-overlay active`}
      onClick={(e) => {
        if (e.target === ref.current) onClose();
      }}
    >
      <div className={`modal-box ${className}`}>
        <button className="modal-close-btn" onClick={onClose}>&times;</button>
        {children}
      </div>
    </div>
  );
}

/** Reusable data-loading hook for a page bundle. */
export function useBundle<T>(loader: () => Promise<T>, deps: unknown[]) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    loader()
      .then((d) => {
        if (alive) setData(d);
      })
      .catch((e) => {
        if (alive) setError(e instanceof Error ? e.message : String(e));
      })
      .finally(() => {
        if (alive) setLoading(false);
      });
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  return { data, error, loading, setData };
}
