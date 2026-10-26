import type { ReactNode } from 'react';

/**
 * Shared admin design-system primitives.
 *
 * These are intentionally dumb wrappers: every visual decision lives in the
 * `.ap-*` rules at the bottom of staff.css, scoped to `.admin-portal` so the
 * doctor portal (and the remaining PHP admin pages) keep their own styling.
 */

export type Tone = 'green' | 'amber' | 'blue' | 'violet' | 'slate' | 'rose' | 'teal';

export function StatGrid({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`ap-stat-grid ${className}`.trim()}>{children}</div>;
}

export function StatCard({
  icon,
  label,
  value,
  hint,
  tone = 'green',
}: {
  icon?: ReactNode;
  label: string;
  value: ReactNode;
  hint?: ReactNode;
  tone?: Tone;
}) {
  return (
    <div className={`ap-stat ap-stat--${tone}`}>
      {icon ? <span className="ap-stat-icon">{icon}</span> : null}
      <span className="ap-stat-body">
        <span className="ap-stat-value">{value}</span>
        <span className="ap-stat-label">{label}</span>
        {hint ? <span className="ap-stat-hint">{hint}</span> : null}
      </span>
    </div>
  );
}

export function Stack({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`ap-stack ${className}`.trim()}>{children}</div>;
}

export function Card({
  title,
  subtitle,
  actions,
  children,
  className = '',
  id,
}: {
  title?: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
  id?: string;
}) {
  return (
    <section className={`ap-card ${className}`.trim()} id={id}>
      {title || actions ? (
        <header className="ap-card-head">
          <div className="ap-card-heading">
            {title ? <h2>{title}</h2> : null}
            {subtitle ? <p>{subtitle}</p> : null}
          </div>
          {actions ? <div className="ap-card-actions">{actions}</div> : null}
        </header>
      ) : null}
      {children}
    </section>
  );
}

export function FilterBar({ children, actions }: { children: ReactNode; actions?: ReactNode }) {
  return (
    <div className="ap-filterbar">
      <div className="ap-filters">{children}</div>
      {actions ? <div className="ap-filter-actions">{actions}</div> : null}
    </div>
  );
}

export function Field({
  label,
  children,
  className = '',
}: {
  label: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <label className={`ap-field ${className}`.trim()}>
      <span>{label}</span>
      {children}
    </label>
  );
}

export function EmptyState({
  icon,
  title,
  hint,
}: {
  icon?: ReactNode;
  title: ReactNode;
  hint?: ReactNode;
}) {
  return (
    <div className="ap-empty">
      {icon ? <span className="ap-empty-icon">{icon}</span> : null}
      <strong>{title}</strong>
      {hint ? <span>{hint}</span> : null}
    </div>
  );
}

export function ModalHead({ title, hint }: { title: ReactNode; hint?: ReactNode }) {
  return (
    <div className="ap-modal-head">
      <h2>{title}</h2>
      {hint ? <p>{hint}</p> : null}
    </div>
  );
}

export function FormSection({ title, hint, children }: { title: ReactNode; hint?: ReactNode; children: ReactNode }) {
  return (
    <div className="ap-form-section">
      <div className="ap-form-section-head">
        <span>{title}</span>
        {hint ? <small>{hint}</small> : null}
      </div>
      {children}
    </div>
  );
}
