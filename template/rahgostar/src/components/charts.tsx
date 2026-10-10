import { motion } from "framer-motion";
import { faNum } from "../data";

/* ── دونات وضعیت‌ها ─────────────────────────────── */
export function Donut({
  segments,
  centerValue,
  centerLabel,
}: {
  segments: { label: string; value: number; color: string }[];
  centerValue: string;
  centerLabel: string;
}) {
  const total = segments.reduce((s, x) => s + x.value, 0) || 1;
  const R = 74;
  const C = 2 * Math.PI * R;
  let acc = 0;

  return (
    <div className="flex flex-wrap items-center gap-6">
      <div className="relative h-[190px] w-[190px] shrink-0">
        <svg viewBox="0 0 190 190" className="h-full w-full -rotate-90">
          <circle cx="95" cy="95" r={R} fill="none" stroke="#F0E7D3" strokeWidth="26" />
          {segments.map((s) => {
            const len = (s.value / total) * C;
            const off = acc;
            acc += len;
            return (
              <motion.circle
                key={s.label}
                cx="95"
                cy="95"
                r={R}
                fill="none"
                stroke={s.color}
                strokeWidth="26"
                strokeLinecap="butt"
                strokeDasharray={`${len - 3} ${C - len + 3}`}
                strokeDashoffset={-off}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.1 }}
              />
            );
          })}
        </svg>
        <div className="absolute inset-0 grid place-content-center text-center">
          <div className="text-[2.4rem] font-black leading-none text-ink-900 tnum">
            {centerValue}
          </div>
          <div className="micro mt-1 text-sand">{centerLabel}</div>
        </div>
      </div>

      <ul className="min-w-[150px] flex-1 space-y-2.5">
        {segments.map((s) => (
          <li key={s.label} className="flex items-center gap-2.5 text-[13px]">
            <span className="h-3 w-3 shrink-0 rounded-[3px]" style={{ background: s.color }} />
            <span className="flex-1 text-ink-800">{s.label}</span>
            <span className="tnum text-xs text-sand">{faNum(s.value)}</span>
            <span className="tnum w-10 text-left text-[11px] text-sand">
              {Math.round((s.value / total) * 100)}%
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/* ── نمودار میله‌ای هفتگی ───────────────────────── */
export function WeeklyBars({
  data,
}: {
  data: { label: string; created: number; done: number }[];
}) {
  const W = 460;
  const H = 210;
  const pad = 30;
  const max = 28;
  const step = (W - pad) / data.length;

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="w-full">
      {[0, 7, 14, 21, 28].map((v) => {
        const y = H - 34 - (v / max) * (H - 60);
        return (
          <g key={v}>
            <line x1={pad} x2={W - 6} y1={y} y2={y} stroke="#E2D6BE" strokeWidth="1" />
            <text x={pad - 6} y={y + 3} textAnchor="end" fontSize="8" fill="#8B7F69" fontFamily="IBM Plex Mono">
              {v}
            </text>
          </g>
        );
      })}
      {data.map((d, i) => {
        const x = pad + i * step;
        const hC = (d.created / max) * (H - 60);
        const hD = (d.done / max) * (H - 60);
        return (
          <g key={d.label}>
            <motion.rect
              x={x + step * 0.14}
              width={step * 0.28}
              y={H - 34 - hC}
              height={hC}
              rx="2"
              fill="#8FBAF0"
              initial={{ scaleY: 0 }}
              animate={{ scaleY: 1 }}
              style={{ transformOrigin: `0px ${H - 34}px` }}
              transition={{ duration: 0.5, delay: i * 0.05 }}
            />
            <motion.rect
              x={x + step * 0.46}
              width={step * 0.28}
              y={H - 34 - hD}
              height={hD}
              rx="2"
              fill="#17864F"
              initial={{ scaleY: 0 }}
              animate={{ scaleY: 1 }}
              style={{ transformOrigin: `0px ${H - 34}px` }}
              transition={{ duration: 0.5, delay: 0.1 + i * 0.05 }}
            />
            <text
              x={x + step * 0.42}
              y={H - 16}
              textAnchor="middle"
              fontSize="9"
              fill="#8B7F69"
              fontFamily="Vazirmatn"
            >
              {d.label}
            </text>
          </g>
        );
      })}
    </svg>
  );
}

/* ── نوار SLA هر عضو ────────────────────────────── */
export function SlaRows({
  rows,
}: {
  rows: { name: string; role: string; sla: number; load: number }[];
}) {
  return (
    <ul className="space-y-4">
      {rows.map((r, i) => {
        const breach = r.sla < 80;
        return (
          <li key={r.name} className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5">
            <div className="flex items-center gap-2">
              <span className="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-ink-900 text-[10px] font-black text-cream-50">
                {r.name.slice(0, 1)}
              </span>
              <span className="truncate text-[13px] font-bold text-ink-800">{r.name}</span>
              <span className="hidden truncate text-[11px] text-sand sm:inline">· {r.role}</span>
            </div>
            <span
              className={`tnum text-sm font-semibold ${breach ? "text-flame-500" : "text-jade-600"}`}
            >
              {r.sla}%
            </span>
            <div className="col-span-2 flex items-center gap-3">
              <div className="relative h-2.5 flex-1 overflow-hidden rounded-full bg-cream-200">
                <motion.div
                  initial={{ width: 0 }}
                  animate={{ width: `${r.sla}%` }}
                  transition={{ duration: 0.8, delay: i * 0.07, ease: [0.22, 1, 0.36, 1] }}
                  className={`h-full rounded-full ${
                    breach
                      ? "bg-gradient-to-l from-flame-500 to-flame-300"
                      : "bg-gradient-to-l from-jade-600 to-jade-400"
                  }`}
                />
                <span className="absolute inset-y-[-3px] right-[90%] w-px bg-ink-900/70" />
              </div>
              <span className="tnum w-16 text-[10px] text-sand">بار {r.load}%</span>
            </div>
          </li>
        );
      })}
    </ul>
  );
}

/* ── منحنی سوزاندن اسپرینت ─────────────────────── */
export function Burndown({ actual, ideal }: { actual: number[]; ideal: number[] }) {
  const W = 460;
  const H = 200;
  const max = 100;
  const px = (i: number, n: number) => 24 + (i * (W - 44)) / (n - 1);
  const py = (v: number) => H - 34 - (v / max) * (H - 58);
  const line = (arr: number[]) => arr.map((v, i) => `${px(i, arr.length)},${py(v)}`).join(" ");

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="w-full">
      <defs>
        <linearGradient id="bd" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#1E6FD9" stopOpacity="0.28" />
          <stop offset="100%" stopColor="#1E6FD9" stopOpacity="0" />
        </linearGradient>
      </defs>
      {[0, 25, 50, 75, 100].map((v) => (
        <g key={v}>
          <line x1="24" x2={W - 20} y1={py(v)} y2={py(v)} stroke="#E2D6BE" />
          <text x="18" y={py(v) + 3} textAnchor="end" fontSize="8" fill="#8B7F69" fontFamily="IBM Plex Mono">
            {v}
          </text>
        </g>
      ))}
      <polyline
        points={line(ideal)}
        fill="none"
        stroke="#8B7F69"
        strokeWidth="1.5"
        strokeDasharray="5 5"
      />
      <motion.polygon
        points={`${line(actual)} ${px(actual.length - 1, actual.length)},${py(0)} ${px(0, actual.length)},${py(0)}`}
        fill="url(#bd)"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.7 }}
      />
      <motion.polyline
        points={line(actual)}
        fill="none"
        stroke="#123E73"
        strokeWidth="2.5"
        strokeLinejoin="round"
        initial={{ pathLength: 0 }}
        animate={{ pathLength: 1 }}
        transition={{ duration: 1, ease: "easeInOut" }}
      />
      {actual.map((v, i) => (
        <circle
          key={i}
          cx={px(i, actual.length)}
          cy={py(v)}
          r="3.5"
          fill={i === actual.length - 1 ? "#E4572E" : "#F2B705"}
          stroke="#FCF9F2"
          strokeWidth="1.5"
        />
      ))}
      {actual.map((_, i) => (
        <text
          key={i}
          x={px(i, actual.length)}
          y={H - 14}
          textAnchor="middle"
          fontSize="8.5"
          fill="#8B7F69"
          fontFamily="IBM Plex Mono"
        >
          D{i + 1}
        </text>
      ))}
    </svg>
  );
}
