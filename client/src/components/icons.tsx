import type { CSSProperties, ReactNode } from 'react';

interface IconProps {
  size?: number;
  className?: string;
  style?: CSSProperties;
}

const base = (children: ReactNode) =>
  function Icon({ size, className, style }: IconProps) {
    return (
      <svg
        className={className}
        width={size ?? 20}
        height={size ?? 20}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
        style={style}
      >
        {children}
      </svg>
    );
  };

export const SvgActivity = base(
  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />,
);
export const SvgView = base(
  <path d="M12 21C12 21 4 14.5 4 9a4 4 0 0 1 8-1 4 4 0 0 1 8 1c0 5.5-8 12-8 12z" />,
);
export const SvgSyringe = base(
  <>
    <path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0L11.5 5.85 9.9 4.25a5.4 5.4 0 0 0-7.65 7.65l1.6 1.6-6.4 6.4a1 1 0 0 0 0 1.41l2.83 2.83a1 1 0 0 0 1.41 0l6.4-6.4 1.6 1.6a5.4 5.4 0 0 0 7.65-7.65l-1.6-1.6 1.27-1.27a5.4 5.4 0 0 0 0-7.65z" />
    <line x1="11" y1="6" x2="15" y2="10" />
  </>,
);
export const SvgCalendar = base(
  <>
    <rect x="3" y="4" width="18" height="18" rx="3" />
    <line x1="16" y1="2" x2="16" y2="6" />
    <line x1="8" y1="2" x2="8" y2="6" />
    <line x1="3" y1="10" x2="21" y2="10" />
  </>,
);
export const SvgClock = base(
  <>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7v5l3 3" />
  </>,
);
export const SvgCheck = base(<path d="M20 6 9 17l-5-5" />);
export const SvgCheckCircle = base(
  <>
    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
    <path d="m9 11 3 3L22 4" />
  </>,
);
export const SvgXCircle = base(
  <>
    <circle cx="12" cy="12" r="10" />
    <path d="m15 9-6 6M9 9l6 6" />
  </>,
);
export const SvgFlag = base(
  <>
    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" />
    <line x1="4" y1="22" x2="4" y2="15" />
  </>,
);
export const SvgFileText = base(
  <>
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
    <path d="M14 2v6h6" />
    <path d="M16 13H8M16 17H8M10 9H8" />
  </>,
);
export const SvgStickyNote = base(
  <>
    <path d="M16 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11l5-5V5a2 2 0 0 0-2-2z" />
    <path d="M16 21v-5h5" />
    <path d="M8 8h8M8 12h5" />
  </>,
);
export const SvgClipboard = base(
  <>
    <rect x="8" y="2" width="8" height="4" rx="1" />
    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
    <path d="M9 12h6M9 16h6" />
  </>,
);
export const SvgFlask = base(
  <>
    <path d="M10 2v6.5L5 18a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9.5V2" />
    <path d="M8.5 2h7" />
    <path d="M7 15h10" />
  </>,
);
export const SvgCreditCard = base(
  <>
    <rect x="1" y="4" width="22" height="16" rx="2" />
    <line x1="1" y1="10" x2="23" y2="10" />
  </>,
);
export const SvgDollarSign = base(
  <>
    <line x1="12" y1="1" x2="12" y2="23" />
    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
  </>,
);
export const SvgUser = base(
  <>
    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
    <circle cx="12" cy="7" r="4" />
  </>,
);
export const SvgArrowRight = base(
  <>
    <path d="M5 12h14" />
    <path d="m13 6 6 6-6 6" />
  </>,
);
export const SvgDownload = base(
  <>
    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
    <polyline points="7 10 12 15 17 10" />
    <line x1="12" y1="15" x2="12" y2="3" />
  </>,
);
export const SvgHeart = base(
  <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z" />
);