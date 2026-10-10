import { useMemo, useState } from "react";
import { cn } from "@/utils/cn";
import { fa, faNum } from "@/lib/fa";

/* ------------------------------ donut ------------------------------ */
export interface Slice {
  label: string;
  value: number;
  hex: string;
}

export function Donut({
  data,
  size = 190,
  thickness = 22,
  centerTitle,
  centerValue,
}: {
  data: Slice[];
  size?: number;
  thickness?: number;
  centerTitle?: string;
  centerValue?: string;
}) {
  const [active, setActive] = useState<number | null>(null);
  const total = data.reduce((a, d) => a + d.value, 0) || 1;
  const r = (size - thickness) / 2;
  const c = 2 * Math.PI * r;

  let acc = 0;
  const arcs = data.map((d, i) => {
    const frac = d.value / total;
    const dash = frac * c;
    const arc = {
      ...d,
      i,
      dash,
      gap: c - dash,
      offset: -acc,
      pct: Math.round(frac * 100),
    };
    acc += dash;
    return arc;
  });

  const shown = active !== null ? arcs[active] : null;

  return (
    <div className="flex flex-wrap items-center justify-center gap-6">
      <div className="relative" style={{ width: size, height: size }}>
        <svg width={size} height={size} className="-rotate-90">
          <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="#eef2f9" strokeWidth={thickness} />
          {arcs.map((a) => (
            <circle
              key={a.label}
              cx={size / 2}
              cy={size / 2}
              r={r}
              fill="none"
              stroke={a.hex}
              strokeWidth={active === a.i ? thickness + 7 : thickness}
              strokeDasharray={`${a.dash} ${a.gap}`}
              strokeDashoffset={a.offset}
              strokeLinecap="butt"
              className="cursor-pointer transition-[stroke-width,opacity] duration-300"
              style={{ opacity: active === null || active === a.i ? 1 : 0.35 }}
              onMouseEnter={() => setActive(a.i)}
              onMouseLeave={() => setActive(null)}
            />
          ))}
        </svg>
        <div className="absolute inset-0 grid place-items-center text-center">
          <div>
            <p className="font-num text-3xl font-bold tabular text-ink-900">
              {shown ? fa(shown.value) : (centerValue ?? fa(total))}
            </p>
            <p className="mt-0.5 max-w-[9rem] text-[11px] font-bold text-ink-400">
              {shown ? `${shown.label} — ${fa(shown.pct ?? 0)}٪` : (centerTitle ?? "کل تسک‌ها")}
            </p>
          </div>
        </div>
      </div>
      <ul className="flex-1 space-y-2">
        {arcs.map((a) => (
          <li
            key={a.label}
            onMouseEnter={() => setActive(a.i)}
            onMouseLeave={() => setActive(null)}
            className={cn(
              "flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 transition-all duration-200",
              active === a.i
                ? "border-milk-400 bg-white shadow-soft"
                : "border-transparent bg-milk-100/60",
            )}
          >
            <i className="h-3 w-3 shrink-0 rounded-[4px]" style={{ background: a.hex }} />
            <span className="flex-1 text-xs font-bold text-ink-700">{a.label}</span>
            <span className="font-num text-sm font-bold tabular text-ink-900">{fa(a.value)}</span>
            <span className="w-9 text-end text-[10.5px] font-bold text-ink-400">{fa(a.pct)}٪</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/* --------------------------- grouped bars --------------------------- */
export function BarPairs({
  data,
  keyA,
  keyB,
  colorA = "#1a5cd6",
  colorB = "#f9bb1b",
  height = 190,
}: {
  data: { label: string; a: number; b: number }[];
  keyA: string;
  keyB: string;
  colorA?: string;
  colorB?: string;
  height?: number;
}) {
  const [hover, setHover] = useState<number | null>(null);
  const max = Math.max(...data.flatMap((d) => [d.a, d.b]), 1);
  const grid = [0, 0.25, 0.5, 0.75, 1];

  return (
    <div>
      <div className="mb-3 flex items-center gap-4 text-[11px] font-bold">
        <span className="inline-flex items-center gap-1.5 text-ink-600">
          <i className="h-2.5 w-2.5 rounded-sm" style={{ background: colorA }} /> {keyA}
        </span>
        <span className="inline-flex items-center gap-1.5 text-ink-600">
          <i className="h-2.5 w-2.5 rounded-sm" style={{ background: colorB }} /> {keyB}
        </span>
      </div>
      <div className="relative" style={{ height }}>
        {grid.map((g) => (
          <div
            key={g}
            className="absolute inset-x-0 flex items-center gap-2"
            style={{ bottom: `${g * 100}%` }}
          >
            <span className="w-6 shrink-0 text-end font-num text-[9.5px] tabular text-ink-300">
              {fa(Math.round(max * g))}
            </span>
            <span className="h-px flex-1 bg-ink-100/90" />
          </div>
        ))}
        <div className="absolute inset-0 flex items-end gap-1 ps-8">
          {data.map((d, i) => (
            <div
              key={d.label}
              className="group relative flex h-full flex-1 items-end justify-center gap-1"
              onMouseEnter={() => setHover(i)}
              onMouseLeave={() => setHover(null)}
            >
              {[
                { v: d.a, c: colorA },
                { v: d.b, c: colorB },
              ].map((bar, bi) => (
                <span
                  key={bi}
                  className="bar-grow w-[38%] max-w-[26px] rounded-t-[6px] transition-all duration-300 group-hover:brightness-110"
                  style={{
                    height: `${Math.max(2, (bar.v / max) * 100)}%`,
                    background: `linear-gradient(180deg, ${bar.c}, ${bar.c}b0)`,
                    animationDelay: `${i * 70 + bi * 40}ms`,
                    boxShadow: hover === i ? `0 -6px 18px -6px ${bar.c}` : undefined,
                  }}
                />
              ))}
              {hover === i && (
                <div className="anim-pop pointer-events-none absolute top-0 z-20 w-max rounded-xl border border-milk-300 bg-ink-900 px-3 py-2 text-[11px] font-bold text-milk-50 shadow-lift">
                  <p className="text-sun-300">{d.label}</p>
                  <p className="mt-1">
                    {keyA}: <span className="tabular">{faNum(d.a)}</span>
                  </p>

                  <p>
                    {keyB}: <span className="tabular">{faNum(d.b)}</span>
                  </p>
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
      <div className="mt-2 flex gap-1 ps-8">
        {data.map((d, i) => (
          <span
            key={d.label}
            className={cn(
              "flex-1 text-center text-[10.5px] font-bold transition-colors",
              hover === i ? "text-brand-700" : "text-ink-400",
            )}
          >
            {d.label}
          </span>
        ))}
      </div>
    </div>
  );
}

/* ---------------------------- area trend ---------------------------- */
export function AreaTrend({
  data,
  hex = "#1a5cd6",
  unit = "٪",
  height = 180,
  baseline,
}: {
  data: { label: string; value: number }[];
  hex?: string;
  unit?: string;
  height?: number;
  baseline?: number;
}) {
  const [hover, setHover] = useState<number | null>(null);
  const W = 320;
  const H = 100;
  const values = data.map((d) => d.value);
  const min = Math.min(...values, baseline ?? Infinity) - 4;
  const max = Math.max(...values, baseline ?? -Infinity) + 4;

  // RTL: time flows right -> left
  const pts = useMemo(
    () =>
      data.map((d, i) => {
        const x = W - (i / Math.max(1, data.length - 1)) * W;
        const y = H - ((d.value - min) / Math.max(1, max - min)) * H;
        return { x, y, ...d };
      }),
    [data, min, max],
  );

  const path = pts.map((p, i) => `${i === 0 ? "M" : "L"}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(" ");
  const area = `${path} L${W},${H} L0,${H} Z`;
  const baseY = baseline !== undefined ? H - ((baseline - min) / Math.max(1, max - min)) * H : null;

  return (
    <div
      className="relative"
      style={{ height }}
      onMouseLeave={() => setHover(null)}
      onMouseMove={(e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const rel = (e.clientX - rect.left) / rect.width;
        setHover(Math.max(0, Math.min(data.length - 1, Math.round((1 - rel) * (data.length - 1)))));
      }}
    >
      <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" className="h-full w-full overflow-visible">
        <defs>
          <linearGradient id={`area-${hex.slice(1)}`} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor={hex} stopOpacity="0.34" />
            <stop offset="100%" stopColor={hex} stopOpacity="0.02" />
          </linearGradient>
        </defs>
        {[0.25, 0.5, 0.75].map((g) => (
          <line key={g} x1="0" y1={H * g} x2={W} y2={H * g} stroke="#e4ebf6" strokeWidth="0.6" />
        ))}
        {baseY !== null && (
          <line
            x1="0"
            y1={baseY}
            x2={W}
            y2={baseY}
            stroke="#ef6211"
            strokeWidth="1"
            strokeDasharray="4 3"
            vectorEffect="non-scaling-stroke"
          />
        )}
        <path d={area} fill={`url(#area-${hex.slice(1)})`} />
        <path
          d={path}
          fill="none"
          stroke={hex}
          strokeWidth="2.4"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="draw-line"
          vectorEffect="non-scaling-stroke"
        />
        {pts.map((p, i) => (
          <circle
            key={i}
            cx={p.x}
            cy={p.y}
            r={hover === i ? 4.6 : 2.6}
            fill="#fffdf8"
            stroke={hex}
            strokeWidth="2"
            vectorEffect="non-scaling-stroke"
            className="transition-all duration-200"
          />
        ))}
        {hover !== null && (
          <line
            x1={pts[hover].x}
            y1="0"
            x2={pts[hover].x}
            y2={H}
            stroke="#102642"
            strokeOpacity="0.25"
            strokeWidth="1"
            strokeDasharray="3 3"
            vectorEffect="non-scaling-stroke"
          />
        )}
      </svg>
      {hover !== null && (
        <div
          className="anim-pop pointer-events-none absolute top-0 z-20 -translate-x-1/2 rounded-xl border border-milk-300 bg-ink-900 px-3 py-1.5 text-center text-[11px] font-bold text-milk-50 shadow-lift"
          style={{ left: `${100 - (hover / Math.max(1, data.length - 1)) * 100}%` }}
        >
          <p className="text-sun-300">{data[hover].label}</p>
          <p className="font-num tabular">
            {faNum(data[hover].value)}
            {unit}
          </p>
        </div>
      )}
      <div className="mt-1 flex justify-between px-1 text-[10px] font-bold text-ink-400">
        {data.map((d, i) => (
          <span key={d.label} className={cn(hover === i && "text-brand-700")}>
            {d.label}
          </span>
        ))}
      </div>
    </div>
  );
}

/* --------------------------- horizontal bars --------------------------- */
export function HBars({
  data,
  max,
  suffix = " ساعت",
}: {
  data: { label: string; value: number; hex: string; note?: string }[];
  max?: number;
  suffix?: string;
}) {
  const top = max ?? Math.max(...data.map((d) => d.value), 1);
  return (
    <ul className="space-y-3">
      {data.map((d, i) => (
        <li key={d.label} className="group">
          <div className="mb-1.5 flex items-baseline justify-between gap-2">
            <span className="text-xs font-bold text-ink-700">{d.label}</span>
            <span className="font-num text-[11.5px] font-bold tabular text-ink-500">
              {faNum(d.value)}
              {suffix}
              {d.note && <span className="ms-2 font-sans text-[10px] text-ink-400">{d.note}</span>}
            </span>
          </div>
          <div className="h-2.5 overflow-hidden rounded-full bg-ink-100/70">
            <div
              className="h-full rounded-full transition-all duration-700 ease-out group-hover:brightness-110"
              style={{
                width: `${(d.value / top) * 100}%`,
                background: `linear-gradient(270deg, ${d.hex}, ${d.hex}99)`,
                transitionDelay: `${i * 60}ms`,
              }}
            />
          </div>
        </li>
      ))}
    </ul>
  );
}

/* --------------------------- stacked ribbon --------------------------- */
export function Ribbon({ slices, className }: { slices: Slice[]; className?: string }) {
  const total = slices.reduce((a, s) => a + s.value, 0) || 1;
  return (
    <div className={cn("flex h-3 w-full overflow-hidden rounded-full bg-ink-100", className)}>
      {slices.map((s) => (
        <span
          key={s.label}
          className="h-full transition-all duration-700"
          style={{ width: `${(s.value / total) * 100}%`, background: s.hex }}
          title={`${s.label}: ${fa(s.value)}`}
        />
      ))}
    </div>
  );
}
