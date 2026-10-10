import {
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { cn } from "@/utils/cn";
import { fa, faNum, initials } from "@/lib/fa";
import { memberOf } from "@/lib/types";
import { useStore } from "@/lib/store";
import { IconCheck, IconX, IconAlert, IconSpark } from "./icons";

/* ---------------- scroll reveal ---------------- */
export function Reveal({
  children,
  delay = 0,
  className,
  as: Tag = "div",
}: {
  children: ReactNode;
  delay?: number;
  className?: string;
  as?: "div" | "li" | "section" | "tr";
}) {
  const ref = useRef<HTMLElement | null>(null);
  const [seen, setSeen] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setSeen(true);
          io.disconnect();
        }
      },
      { threshold: 0.08, rootMargin: "0px 0px -60px 0px" },
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);

  return (
    <Tag
      ref={ref as never}
      className={cn("reveal", seen && "in", className)}
      style={{ animationDelay: `${delay}ms` }}
    >
      {children}
    </Tag>
  );
}

/* ---------------- panel ---------------- */
export function Panel({
  title,
  subtitle,
  icon,
  action,
  className,
  bodyClass,
  children,
  accent,
}: {
  title?: ReactNode;
  subtitle?: ReactNode;
  icon?: ReactNode;
  action?: ReactNode;
  className?: string;
  bodyClass?: string;
  children: ReactNode;
  accent?: string;
}) {
  return (
    <section
      className={cn(
        "relative overflow-hidden rounded-[20px] border border-milk-300 bg-milk-50 shadow-soft",
        className,
      )}
    >
      {accent && (
        <span
          className="absolute inset-y-0 start-0 w-1.5"
          style={{ background: accent }}
          aria-hidden
        />
      )}
      {(title || action) && (
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-milk-200/90 bg-gradient-to-l from-milk-50 to-milk-100/60 px-5 py-3.5">
          <div className="flex items-center gap-3">
            {icon && (
              <span className="grid h-9 w-9 place-items-center rounded-xl bg-brand-600/10 text-brand-700 ring-1 ring-brand-200">
                {icon}
              </span>
            )}
            <div>
              <h3 className="text-[15px] font-extrabold text-ink-900">{title}</h3>
              {subtitle && <p className="mt-0.5 text-xs text-ink-500">{subtitle}</p>}
            </div>
          </div>
          {action}
        </header>
      )}
      <div className={cn("p-5", bodyClass)}>{children}</div>
    </section>
  );
}

/* ---------------- badge ---------------- */
export const Badge = ({
  children,
  className,
  dot,
}: {
  children: ReactNode;
  className?: string;
  dot?: string;
}) => (
  <span
    className={cn(
      "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold whitespace-nowrap",
      className,
    )}
  >
    {dot && <i className="h-1.5 w-1.5 rounded-full" style={{ background: dot }} />}
    {children}
  </span>
);

/* ---------------- avatar ---------------- */
export const Avatar = ({
  name,
  size = "md",
  className,
  online,
}: {
  name: string;
  size?: "xs" | "sm" | "md" | "lg";
  className?: string;
  online?: boolean;
}) => {
  const m = memberOf(name);
  const dim = { xs: "h-6 w-6 text-[9px]", sm: "h-8 w-8 text-[11px]", md: "h-10 w-10 text-xs", lg: "h-14 w-14 text-base" }[size];
  return (
    <span className={cn("relative inline-flex shrink-0", className)}>
      <span
        className={cn(
          "grid place-items-center rounded-xl font-extrabold text-white shadow-sm ring-2 ring-white/70",
          dim,
        )}
        style={{ background: `linear-gradient(140deg, ${m.color}, ${m.color}bb)` }}
        title={`${name} — ${m.role}`}
      >
        {initials(name)}
      </span>
      {online !== undefined && (
        <i
          className={cn(
            "absolute -bottom-0.5 -end-0.5 h-3 w-3 rounded-full ring-2 ring-milk-50",
            online ? "bg-leaf-400" : "bg-ink-300",
          )}
        />
      )}
    </span>
  );
};

/* ---------------- progress ---------------- */
export function ProgressBar({
  value,
  hex = "#2f7af0",
  height = 8,
  striped,
  className,
}: {
  value: number;
  hex?: string;
  height?: number;
  striped?: boolean;
  className?: string;
}) {
  const [w, setW] = useState(0);
  useEffect(() => {
    const t = setTimeout(() => setW(Math.max(0, Math.min(100, value))), 60);
    return () => clearTimeout(t);
  }, [value]);
  return (
    <span
      className={cn("block w-full overflow-hidden rounded-full bg-ink-100/80", className)}
      style={{ height }}
    >
      <span
        className="block h-full rounded-full transition-[width] duration-[900ms] ease-out"
        style={{
          width: `${w}%`,
          background: striped
            ? `repeating-linear-gradient(115deg, ${hex}, ${hex} 8px, ${hex}cc 8px, ${hex}cc 16px)`
            : `linear-gradient(90deg, ${hex}cc, ${hex})`,
        }}
      />
    </span>
  );
}

/* ---------------- ring ---------------- */
export function Ring({
  value,
  size = 108,
  stroke = 11,
  hex = "#2f7af0",
  track = "#e6eefb",
  children,
}: {
  value: number;
  size?: number;
  stroke?: number;
  hex?: string;
  track?: string;
  children?: ReactNode;
}) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const [dash, setDash] = useState(0);
  useEffect(() => {
    const t = setTimeout(() => setDash(Math.max(0, Math.min(100, value))), 90);
    return () => clearTimeout(t);
  }, [value]);
  return (
    <span className="relative inline-grid place-items-center" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={track} strokeWidth={stroke} />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          stroke={hex}
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={c - (c * dash) / 100}
          style={{ transition: "stroke-dashoffset 1.2s cubic-bezier(.22,1,.36,1)" }}
        />
      </svg>
      <span className="absolute inset-0 grid place-items-center text-center">{children}</span>
    </span>
  );
}

/* ---------------- animated counter ---------------- */
export function Counter({
  value,
  suffix,
  className,
  duration = 1000,
}: {
  value: number;
  suffix?: string;
  className?: string;
  duration?: number;
}) {
  const [n, setN] = useState(0);
  useEffect(() => {
    let raf = 0;
    const start = performance.now();
    const from = 0;
    const tick = (t: number) => {
      const p = Math.min(1, (t - start) / duration);
      const eased = 1 - Math.pow(1 - p, 3);
      setN(from + (value - from) * eased);
      if (p < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [value, duration]);
  return (
    <span className={cn("tabular", className)}>
      {faNum(n)}
      {suffix && <span className="ms-0.5">{suffix}</span>}
    </span>
  );
}

/* ---------------- segmented control ---------------- */
export function Segmented<T extends string>({
  options,
  value,
  onChange,
  className,
}: {
  options: { value: T; label: string; icon?: ReactNode }[];
  value: T;
  onChange: (v: T) => void;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "inline-flex items-center gap-1 rounded-2xl border border-milk-300 bg-milk-100 p-1 shadow-inner-line",
        className,
      )}
    >
      {options.map((o) => {
        const active = o.value === value;
        return (
          <button
            key={o.value}
            type="button"
            onClick={() => onChange(o.value)}
            className={cn(
              "inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition-all duration-300",
              active
                ? "bg-brand-600 text-white shadow-lift"
                : "text-ink-500 hover:bg-white hover:text-ink-800",
            )}
          >
            {o.icon}
            {o.label}
          </button>
        );
      })}
    </div>
  );
}

/* ---------------- modal ---------------- */
export function Modal({
  open,
  onClose,
  title,
  subtitle,
  children,
  footer,
  wide,
}: {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  subtitle?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  wide?: boolean;
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [open, onClose]);

  if (!open) return null;
  return (
    <div className="fixed inset-0 z-[90] grid place-items-center p-4">
      <button
        aria-label="بستن"
        onClick={onClose}
        className="absolute inset-0 cursor-default bg-ink-950/55 backdrop-blur-[3px]"
      />
      <div
        role="dialog"
        aria-modal="true"
        className={cn(
          "anim-pop relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-[24px] border border-milk-300 bg-milk-50 shadow-lift",
          wide ? "max-w-4xl" : "max-w-2xl",
        )}
      >
        <header className="flex items-start justify-between gap-4 border-b border-milk-200 bg-gradient-to-l from-brand-50 via-milk-50 to-sun-100/50 px-6 py-4">
          <div>
            <h3 className="font-display text-2xl leading-tight text-ink-900">{title}</h3>
            {subtitle && <p className="mt-1 text-xs text-ink-500">{subtitle}</p>}
          </div>
          <button
            onClick={onClose}
            className="grid h-9 w-9 place-items-center rounded-xl border border-milk-300 bg-white text-ink-500 transition hover:rotate-90 hover:border-ember-300 hover:text-ember-500"
            aria-label="بستن پنجره"
          >
            <IconX className="h-4 w-4" />
          </button>
        </header>
        <div className="overflow-y-auto px-6 py-5">{children}</div>
        {footer && (
          <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-milk-200 bg-milk-100 px-6 py-4">
            {footer}
          </footer>
        )}
      </div>
    </div>
  );
}

/* ---------------- toaster ---------------- */
const TOAST_TONE = {
  ok: { bg: "bg-leaf-500", icon: <IconCheck className="h-4 w-4" /> },
  info: { bg: "bg-brand-600", icon: <IconSpark className="h-4 w-4" /> },
  warn: { bg: "bg-sun-400", icon: <IconAlert className="h-4 w-4" /> },
  danger: { bg: "bg-ember-500", icon: <IconAlert className="h-4 w-4" /> },
} as const;

export function Toaster() {
  const { toasts, dropToast } = useStore();
  return (
    <div className="pointer-events-none fixed bottom-5 left-5 z-[120] flex w-[min(21rem,calc(100vw-2.5rem))] flex-col gap-2.5">
      {toasts.map((t) => {
        const tone = TOAST_TONE[t.tone];
        return (
          <div
            key={t.id}
            className="anim-toast pointer-events-auto flex items-start gap-3 overflow-hidden rounded-2xl border border-milk-300 bg-milk-50/97 p-3 shadow-lift backdrop-blur"
          >
            <span className={cn("grid h-8 w-8 shrink-0 place-items-center rounded-xl text-white", tone.bg)}>
              {tone.icon}
            </span>
            <p className="flex-1 pt-1 text-[12.5px] font-semibold leading-5 text-ink-800">{t.text}</p>
            <button
              onClick={() => dropToast(t.id)}
              className="mt-0.5 text-ink-300 transition hover:text-ember-500"
              aria-label="بستن اعلان"
            >
              <IconX className="h-3.5 w-3.5" />
            </button>
          </div>
        );
      })}
    </div>
  );
}

/* ---------------- misc ---------------- */
export const Sparkline = ({
  values,
  hex = "#2f7af0",
  className,
}: {
  values: number[];
  hex?: string;
  className?: string;
}) => {
  const max = Math.max(...values, 1);
  const min = Math.min(...values, 0);
  // RTL: time flows right -> left
  const pts = values.map((v, i) => {
    const x = 100 - (i / Math.max(1, values.length - 1)) * 100;
    const y = 30 - ((v - min) / Math.max(1, max - min)) * 26 - 2;
    return `${x},${y}`;
  });
  return (
    <svg viewBox="0 0 100 30" preserveAspectRatio="none" className={cn("h-8 w-full", className)}>
      <polyline
        points={`0,30 ${pts.join(" ")} 100,30`}
        fill={hex}
        opacity={0.13}
        stroke="none"
      />
      <polyline
        points={pts.join(" ")}
        fill="none"
        stroke={hex}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
        className="draw-line"
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  );
};

export const Chip = ({
  children,
  active,
  onClick,
  hex,
}: {
  children: ReactNode;
  active?: boolean;
  onClick?: () => void;
  hex?: string;
}) => (
  <button
    type="button"
    onClick={onClick}
    style={active && hex ? { borderColor: hex, color: hex, background: `${hex}14` } : undefined}
    className={cn(
      "rounded-full border px-3 py-1.5 text-[11.5px] font-bold transition-all duration-200",
      active
        ? "border-transparent bg-ink-900 text-milk-50 shadow-soft"
        : "border-milk-300 bg-white text-ink-500 hover:-translate-y-0.5 hover:border-brand-300 hover:text-brand-700",
    )}
  >
    {children}
  </button>
);

export const KeyVal = ({ label, children }: { label: string; children: ReactNode }) => (
  <div className="rounded-xl border border-milk-200 bg-white/70 px-3 py-2">
    <p className="text-[10.5px] font-bold text-ink-400">{label}</p>
    <div className="mt-0.5 text-[12.5px] font-extrabold text-ink-800">{children}</div>
  </div>
);

export const Empty = ({ text, hint }: { text: string; hint?: string }) => (
  <div className="grid place-items-center rounded-2xl border border-dashed border-milk-400 bg-milk-100/60 px-6 py-12 text-center">
    <span className="grid h-12 w-12 place-items-center rounded-2xl bg-white text-ink-300 shadow-soft">
      <IconAlert className="h-5 w-5" />
    </span>
    <p className="mt-3 text-sm font-bold text-ink-700">{text}</p>
    {hint && <p className="mt-1 text-xs text-ink-400">{hint}</p>}
  </div>
);

export const faLabel = (n: number) => fa(n);
