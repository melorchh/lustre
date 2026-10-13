import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { useScrollLock } from '../hooks';

interface TourStep {
  /** CSS selector, or 'nav' for the matchMedia-picked sidebar/bottom-nav */
  target: string | null;
  title: string;
  msg: string;
}

const STEPS: TourStep[] = [
  { target: '[data-tour="hero"]', title: 'Welcome to your care hub', msg: 'This is your dashboard — everything about your care at LustreMDC lives here after you register.' },
  { target: '[data-tour="stats"]', title: 'Your health at a glance', msg: 'Keep an eye on appointments, lab tests, vaccines, and health records — all counted here.' },
  { target: '[data-tour="next"]', title: 'Upcoming appointment', msg: 'Your next visit appears here. Ready now? Use “Book new” to schedule with a specialist.' },
  { target: '[data-tour="quick"]', title: 'Quick actions', msg: 'Book an appointment, open your records, request lab tests, or track vitals & vaccines — one tap away.' },
  { target: 'nav', title: 'Find your way around', msg: 'Use the menu to jump between sections, open your profile, switch theme, and log out.' },
  { target: null, title: "You're all set!", msg: 'That is everything you need to get started. Book your first appointment and take charge of your health.' },
];

const GAP = 14;
const CARD_W = 340;

function resolveSelector(step: TourStep): string | null {
  if (!step.target) return null;
  if (step.target === 'nav') {
    return window.matchMedia('(min-width: 769px)').matches ? '.sidebar' : '.bottom-nav';
  }
  return step.target;
}

function isMobile(): boolean {
  return window.matchMedia('(max-width: 768px)').matches;
}

interface RingRect { top: number; left: number; width: number; height: number }
type CardPos = { top: number; left: number } | null;

function rectOf(el: Element): RingRect {
  const r = el.getBoundingClientRect();
  return {
    top: Math.max(0, r.top),
    left: Math.max(0, r.left),
    width: Math.min(r.width, window.innerWidth),
    height: Math.min(r.height, window.innerHeight),
  };
}

interface TourProps {
  active: boolean;
  onDone: () => void;
}

export default function Tour({ active, onDone }: TourProps) {
  const [index, setIndex] = useState(0);
  const [ring, setRing] = useState<RingRect | null>(null);
  const [pos, setPos] = useState<CardPos>(null);
  const [mobile, setMobile] = useState(() => isMobile());
  const [locked, setLocked] = useState(false);
  const cardRef = useRef<HTMLDivElement>(null);
  const nextBtnRef = useRef<HTMLButtonElement>(null);

  const finish = useCallback(() => {
    setLocked(false);
    onDone();
  }, [onDone]);

  useScrollLock(locked);

  const update = useCallback(() => {
    const step = STEPS[index];
    setMobile(isMobile());
    const sel = resolveSelector(step);
    const el = sel ? document.querySelector(sel) : null;
    if (!el) {
      setRing((prev) => (prev === null ? prev : null));
      setPos((prev) => (prev === null ? prev : null));
      return;
    }
    const r = rectOf(el);
    setRing((prev) =>
      prev && prev.top === r.top && prev.left === r.left && prev.width === r.width && prev.height === r.height
        ? prev
        : r
    );
    if (isMobile()) {
      setPos((prev) => (prev === null ? prev : null)); // sheet via CSS
      return;
    }
    const ch = cardRef.current?.offsetHeight ?? 190;
    const cw = Math.min(CARD_W, window.innerWidth - 24);
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    let top: number;
    if (r.top + r.height + GAP + ch <= vh - 8) top = r.top + r.height + GAP;
    else if (r.top - GAP - ch >= 8) top = r.top - GAP - ch;
    else top = Math.max(12, (vh - ch) / 2);
    let left = r.left + r.width / 2 - cw / 2;
    left = Math.max(12, Math.min(left, vw - cw - 12));
    setPos((prev) => (prev && prev.top === top && prev.left === left ? prev : { top, left }));
  }, [index]);

  // New step: scroll the target into view, then lock; focus the primary button.
  useEffect(() => {
    if (!active) return;
    const sel = resolveSelector(STEPS[index]);
    const el = sel ? document.querySelector(sel) : null;
    if (el) el.scrollIntoView({ block: 'center', behavior: 'auto' });
    update();
    const timer = window.setTimeout(() => setLocked(true), 250);
    const focusTimer = window.setTimeout(() => nextBtnRef.current?.focus(), 60);
    return () => {
      window.clearTimeout(timer);
      window.clearTimeout(focusTimer);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [active, index]);

  // Keep ring/card in sync while anything scrolls or resizes.
  useEffect(() => {
    if (!active) return;
    const onMove = () => update();
    window.addEventListener('scroll', onMove, { passive: true });
    window.addEventListener('resize', onMove, { passive: true });
    return () => {
      window.removeEventListener('scroll', onMove);
      window.removeEventListener('resize', onMove);
    };
  }, [active, update]);

  // Reset when (re)activated.
  useEffect(() => {
    if (active) {
      setIndex(0);
      setLocked(false);
    }
  }, [active]);

  useEffect(() => {
    if (!active) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') finish();
      else if (e.key === 'ArrowRight') {
        if (index < STEPS.length - 1) setIndex(index + 1);
        else finish();
      } else if (e.key === 'ArrowLeft' && index > 0) {
        setIndex(index - 1);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [active, index, finish]);

  // Re-measure after the card paints (its height drives the flip decision).
  useLayoutEffect(() => {
    if (active) update();
  }, [active, index, mobile, update]);

  if (!active) return null;

  const step = STEPS[index];
  const last = index === STEPS.length - 1;
  const centered = !step.target || (!ring && !mobile);

  const next = () => {
    if (last) {
      onDone();
      window.location.href = 'index.php';
    } else {
      setIndex(index + 1);
    }
  };

  return (
    <div className="tour-root" role="dialog" aria-modal="true" aria-label={step.title}>
      <div className="tour-scrim" aria-hidden="true" />
      {ring && (
        <div
          className="tour-ring"
          style={{ top: ring.top, left: ring.left, width: ring.width, height: ring.height }}
          aria-hidden="true"
        />
      )}
      <div
        ref={cardRef}
        className={`tour-card ${mobile ? 'tour-card--sheet' : centered ? 'tour-card--center' : ''}`}
        style={
          !mobile && pos
            ? { top: pos.top, left: pos.left, width: Math.min(CARD_W, window.innerWidth - 24) }
            : undefined
        }
      >
        <div className="tour-step-label">{`${index + 1} / ${STEPS.length}`}</div>
        <h3 className="tour-title">{step.title}</h3>
        <p className="tour-msg">{step.msg}</p>
        <div className="tour-controls">
          <div className="tour-dots" aria-hidden="true">
            {STEPS.map((_, i) => (
              <span key={i} className={`tour-dot ${i === index ? 'active' : ''}`} />
            ))}
          </div>
          <div className="tour-btns">
            <button type="button" className="tour-btn" onClick={finish}>
              {"Skip"}
            </button>
            {index > 0 && (
              <button type="button" className="tour-btn" onClick={() => setIndex(index - 1)}>
                {"Back"}
              </button>
            )}
            <button
              type="button"
              ref={nextBtnRef}
              className="tour-btn tour-btn--primary"
              onClick={next}
            >
              {last ? "Book my first appointment" : "Next"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
