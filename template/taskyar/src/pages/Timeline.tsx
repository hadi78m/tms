import { useMemo, useState } from "react";
import { cn } from "@/utils/cn";
import { useStore } from "@/lib/store";
import { fa, fmtDate, daysBetween, isoFrom, leftover, todayISO } from "@/lib/fa";
import {
  PRIORITY_META,
  PROJECTS,
  SLA_TONE,
  STATUS_META,
  memberOf,
  projectOf,
  slaOf,
  type Task,
} from "@/lib/types";
import { Avatar, Badge, Empty, Panel, ProgressBar, Reveal, Chip } from "@/components/ui";
import {
  IconAlert,
  IconCalendar,
  IconClock,
  IconFlag,
  IconGantt,
  IconLayers,
  IconLink,
  IconSpark,
  IconShield,
} from "@/components/icons";

const NAME_W = 236;
const ROW_H = 48;

export default function Timeline() {
  const { tasks, now, navigate, toast } = useStore();
  const [zoom, setZoom] = useState(34);
  const [project, setProject] = useState("all");
  const [showDeps, setShowDeps] = useState(true);
  const [hover, setHover] = useState<Task | null>(null);

  const list = useMemo(
    () =>
      tasks
        .filter((t) => project === "all" || t.project === project)
        .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime()),
    [tasks, project],
  );

  const span = useMemo(() => {
    if (list.length === 0) return { from: todayISO(), to: isoFrom(14), days: 14 };
    const starts = list.map((t) => t.start);
    const dues = list.map((t) => t.due);
    let from = starts.reduce((a, b) => (a < b ? a : b));
    let to = dues.reduce((a, b) => (a > b ? a : b));
    from = isoFrom(Math.min(daysBetween(todayISO(), from), 0) - 2);
    to = isoFrom(Math.max(daysBetween(todayISO(), to), 0) + 3);
    return { from, to, days: Math.max(7, daysBetween(from, to) + 1) };
  }, [list]);

  const width = span.days * zoom;
  const todayOffset = daysBetween(span.from, todayISO()) * zoom;
  const indexOf = (t: Task) => list.findIndex((x) => x.id === t.id);

  const monthTicks = useMemo(() => {
    const out: { label: string; left: number; width: number }[] = [];
    for (let d = 0; d < span.days; d++) {
      const date = new Date(span.from + "T12:00:00");
      date.setDate(date.getDate() + d);
      const label = date.toLocaleDateString("fa-IR", { month: "long" });
      const last = out[out.length - 1];
      if (last && last.label === label) last.width += zoom;
      else out.push({ label, left: d * zoom, width: zoom });
    }
    return out;
  }, [span.from, span.days, zoom]);

  const edges = useMemo(
    () =>
      list.flatMap((t) =>
        t.dependencies
          .map((d) => ({ from: list.find((x) => x.id === d), to: t }))
          .filter((e): e is { from: Task; to: Task } => Boolean(e.from)),
      ),
    [list],
  );

  const blocked = list.filter((t) => t.status === "blocked");
  const overdue = list.filter((t) => leftover(t.due, t.status === "done", now).overdue);

  if (list.length === 0) {
    return <Empty text="تسکی در این بازه زمانی وجود ندارد" hint="فیلتر پروژه را تغییر دهید" />;
  }

  return (
    <div className="space-y-4">
      {/* summary strip */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {[
          { label: "بازه زمان‌بندی", value: `${fa(span.days)} روز`, hint: `${fmtDate(span.from)} تا ${fmtDate(span.to)}`, hex: "#1a5cd6", icon: <IconCalendar className="h-[18px] w-[18px]" /> },
          { label: "تسک روی زمان‌بندی", value: fa(list.length), hint: `${fa(list.filter((t) => t.status === "done").length)} مورد تحویل‌شده`, hex: "#16a157", icon: <IconGantt className="h-[18px] w-[18px]" /> },
          { label: "مسدود", value: fa(blocked.length), hint: blocked.map((b) => b.code).join("، ") || "بدون مورد", hex: "#ef6211", icon: <IconAlert className="h-[18px] w-[18px]" /> },
          { label: "عقب‌افتاده", value: fa(overdue.length), hint: overdue.map((b) => b.code).join("، ") || "بدون مورد", hex: "#c94a08", icon: <IconClock className="h-[18px] w-[18px]" /> },
        ].map((k, i) => (
          <Reveal key={k.label} delay={i * 60}>
            <div className="lift relative overflow-hidden rounded-2xl border border-milk-300 bg-milk-50 p-4 shadow-soft">
              <span className="absolute inset-y-0 start-0 w-1.5" style={{ background: k.hex }} />
              <div className="flex items-center justify-between gap-2">
                <p className="text-[11.5px] font-bold text-ink-500">{k.label}</p>
                <span className="grid h-9 w-9 place-items-center rounded-xl" style={{ background: `${k.hex}1a`, color: k.hex }}>
                  {k.icon}
                </span>
              </div>
              <p className="font-num mt-1.5 text-[26px] font-bold leading-none tabular text-ink-900">{k.value}</p>
              <p className="mt-1 truncate text-[10px] font-semibold text-ink-400">{k.hint}</p>
            </div>
          </Reveal>
        ))}
      </div>

      <Panel
        bodyClass="p-0"
        title="نمای گانت اسپرینت‌ها"
        subtitle="میله‌ها بر اساس تاریخ شروع و سررسید؛ بخش تیره نشان‌دهنده پیشرفت است"
        icon={<IconGantt className="h-[18px] w-[18px]" />}
        accent="#2f7af0"
        action={
          <div className="flex flex-wrap items-center gap-1.5">
            <Chip active={showDeps} hex="#1a5cd6" onClick={() => setShowDeps((v) => !v)}>
              <span className="inline-flex items-center gap-1.5">
                <IconLink className="h-3.5 w-3.5" />
                وابستگی‌ها
              </span>
            </Chip>
            {[
              { v: 22, l: "فشرده" },
              { v: 34, l: "عادی" },
              { v: 52, l: "باز" },
            ].map((z) => (
              <button
                key={z.v}
                onClick={() => setZoom(z.v)}
                className={cn(
                  "rounded-lg px-2.5 py-1.5 text-[10.5px] font-bold transition",
                  zoom === z.v ? "bg-brand-600 text-white shadow-soft" : "bg-milk-100 text-ink-500 hover:bg-milk-200",
                )}
              >
                {z.l}
              </button>
            ))}
            <button
              onClick={() => toast("نمای گانت برای اشتراک‌گذاری آماده شد", "ok")}
              className="rounded-lg bg-ink-900 px-3 py-1.5 text-[10.5px] font-bold text-sun-300 transition hover:bg-brand-700"
            >
              اشتراک زمان‌بندی
            </button>
          </div>
        }
      >
        <div className="flex flex-wrap items-center gap-2 border-b border-milk-200 bg-milk-100/60 p-3.5">
          <span className="text-[11px] font-extrabold text-ink-500">پروژه:</span>
          <Chip active={project === "all"} onClick={() => setProject("all")}>
            همه
          </Chip>
          {PROJECTS.map((p) => (
            <Chip key={p.code} active={project === p.name} hex={p.hex} onClick={() => setProject(p.name)}>
              {p.name}
            </Chip>
          ))}
          <span className="ms-auto flex flex-wrap items-center gap-3 text-[10px] font-bold text-ink-500">
            {STATUS_ORDER_SHORT.map((s) => (
              <span key={s} className="inline-flex items-center gap-1.5">
                <i className="h-2.5 w-2.5 rounded-sm" style={{ background: STATUS_META[s].hex }} />
                {STATUS_META[s].label}
              </span>
            ))}
          </span>
        </div>

        <div className="overflow-x-auto">
          <div style={{ width: NAME_W + width }} className="relative">
            {/* header row */}
            <div className="sticky top-0 z-20 flex border-b border-milk-300 bg-milk-50">
              <div className="shrink-0 border-e border-milk-300 px-4 py-2.5" style={{ width: NAME_W }}>
                <p className="text-[11.5px] font-extrabold text-ink-700">تسک / مسئول</p>
              </div>
              <div className="relative flex-1" style={{ width }}>
                <div className="flex">
                  {monthTicks.map((m, i) => (
                    <div
                      key={i}
                      className="shrink-0 border-e border-milk-200 py-2.5 text-center text-[10.5px] font-extrabold text-ink-500"
                      style={{ width: m.width }}
                    >
                      {m.label}
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* body */}
            <div className="relative">
              {/* vertical grid */}
              <div className="pointer-events-none absolute inset-y-0" style={{ insetInlineStart: NAME_W, width }}>
                {Array.from({ length: Math.ceil(span.days / 7) + 1 }).map((_, i) => (
                  <span key={i} className="absolute inset-y-0 w-px bg-milk-300/80" style={{ insetInlineStart: i * 7 * zoom }} />
                ))}
                {/* today */}
                {todayOffset >= 0 && todayOffset <= width && (
                  <span className="absolute inset-y-0 z-10 w-[2px] bg-ember-500/80" style={{ insetInlineStart: todayOffset }}>
                    <span className="absolute -top-0.5 start-1/2 -translate-x-1/2 whitespace-nowrap rounded-b-md bg-ember-500 px-1.5 py-0.5 text-[9px] font-bold text-white">
                      امروز
                    </span>
                  </span>
                )}
              </div>

              {/* dependency curves */}
              {showDeps && (
                <svg
                  className="pointer-events-none absolute inset-y-0 z-10"
                  style={{ insetInlineStart: NAME_W, width, height: list.length * ROW_H }}
                >
                  <defs>
                    <marker id="arrow" markerWidth="7" markerHeight="7" refX="5.5" refY="3.5" orient="auto">
                      <path d="M0,0 L7,3.5 L0,7 z" fill="#c94a08" />
                    </marker>
                  </defs>
                  {edges.map(({ from, to }) => {
                    // RTL: bars grow right -> left, so physical x is mirrored
                    const x1 = width - daysBetween(span.from, from.due) * zoom - zoom;
                    const y1 = indexOf(from) * ROW_H + ROW_H / 2;
                    const x2 = width - daysBetween(span.from, to.start) * zoom;
                    const y2 = indexOf(to) * ROW_H + ROW_H / 2;
                    const risky = from.status === "blocked" || leftover(from.due, from.status === "done", now).overdue;
                    const midX = (x1 + x2) / 2;
                    return (
                      <path
                        key={`${from.id}-${to.id}`}
                        d={`M${x1},${y1} C${midX},${y1} ${midX},${y2} ${x2},${y2}`}
                        fill="none"
                        stroke={risky ? "#c94a08" : "#93c0fd"}
                        strokeWidth={risky ? 1.8 : 1.3}
                        strokeDasharray={risky ? "5 4" : undefined}
                        markerEnd="url(#arrow)"
                        opacity={0.9}
                      />
                    );
                  })}
                </svg>
              )}

              {/* rows */}
              {list.map((t, i) => {
                const left = daysBetween(span.from, t.start) * zoom;
                const barW = Math.max(zoom * 0.8, (daysBetween(t.start, t.due) + 1) * zoom);
                const proj = projectOf(t.project);
                const sla = slaOf(t, now);
                const isHover = hover?.id === t.id;
                return (
                  <div key={t.id} className="relative flex border-b border-milk-200/80" style={{ height: ROW_H }}>
                    {/* name cell */}
                    <button
                      onClick={() => navigate({ page: "admin", q: t.code, status: "all" })}
                      className={cn(
                        "flex shrink-0 items-center gap-2 border-e border-milk-300 px-3 text-start transition-colors",
                        i % 2 ? "bg-milk-100/50" : "bg-milk-50",
                        "hover:bg-brand-50",
                      )}
                      style={{ width: NAME_W }}
                    >
                      <Avatar name={t.assignee} size="xs" />
                      <span className="min-w-0 flex-1">
                        <span className="flex items-center gap-1.5">
                          <span className="font-num text-[9.5px] font-bold tabular" style={{ color: proj.hex }}>
                            {t.code}
                          </span>
                          <i className="h-1.5 w-1.5 rounded-full" style={{ background: PRIORITY_META[t.priority].hex }} />
                        </span>
                        <span className="block truncate text-[11.5px] font-extrabold text-ink-800">{t.title}</span>
                      </span>
                    </button>

                    {/* bar cell */}
                    <div className="relative flex-1" style={{ width }}>
                      {i % 2 === 1 && <span className="absolute inset-0 bg-milk-100/40" />}
                      <div
                        onMouseEnter={() => setHover(t)}
                        onMouseLeave={() => setHover((h) => (h?.id === t.id ? null : h))}
                        onClick={() => navigate({ page: "board", tab: "kanban", q: t.code })}
                        className={cn(
                          "group absolute top-1/2 flex h-7 -translate-y-1/2 cursor-pointer items-center overflow-hidden rounded-lg border transition-all duration-300",
                          isHover && "z-20 h-8 shadow-lift",
                        )}
                        style={{
                          insetInlineStart: left,
                          width: barW,
                          background: t.status === "done" ? "#d9f6e4" : `${STATUS_META[t.status].hex}26`,
                          borderColor: isHover ? proj.hex : `${STATUS_META[t.status].hex}66`,
                          borderStyle: t.status === "blocked" ? "dashed" : "solid",
                        }}
                      >
                        <span
                          className="absolute inset-y-0 start-0 rounded-e-lg transition-all duration-700"
                          style={{
                            width: `${t.progress}%`,
                            background: `linear-gradient(270deg, ${STATUS_META[t.status].hex}, ${STATUS_META[t.status].hex}b3)`,
                          }}
                        />
                        <span className="relative z-10 flex w-full items-center gap-1.5 px-2">
                          <span className="truncate text-[10px] font-extrabold text-ink-800">{t.title}</span>
                          <span className="ms-auto shrink-0 font-num text-[9.5px] font-bold tabular text-ink-600">{fa(t.progress)}٪</span>
                        </span>
                        {/* due milestone */}
                        <span
                          className="absolute -end-1.5 top-1/2 z-20 h-3 w-3 -translate-y-1/2 rotate-45 border-2 border-white"
                          style={{ background: SLA_TONE[sla.tone].hex }}
                        />
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        {/* hover inspector */}
        <div className="border-t border-milk-200 bg-milk-100/60 p-4">
          {hover ? (
            <div className="flex flex-wrap items-center gap-4">
              <span className="grid h-11 w-11 place-items-center rounded-xl font-num text-[12px] font-bold tabular text-white" style={{ background: projectOf(hover.project).hex }}>
                {hover.code.replace("TS-", "")}
              </span>
              <div className="min-w-[12rem] flex-1">
                <p className="text-[13px] font-extrabold text-ink-900">{hover.title}</p>
                <p className="mt-0.5 text-[10.5px] font-semibold text-ink-500">
                  {hover.project} — {hover.assignee} ({memberOf(hover.assignee).role})
                </p>
              </div>
              <Inspector label="شروع" value={fmtDate(hover.start)} />
              <Inspector label="سررسید" value={fmtDate(hover.due)} />
              <Inspector label="مدت" value={`${fa(daysBetween(hover.start, hover.due) + 1)} روز`} />
              <Inspector
                label="زمان مانده"
                value={hover.status === "done" ? "تحویل شد" : leftover(hover.due, false, now).overdue ? `${leftover(hover.due, false, now).label} تأخیر` : leftover(hover.due, false, now).label}
                tone={leftover(hover.due, hover.status === "done", now).tone}
              />
              <div className="w-40">
                <div className="mb-1 flex items-baseline justify-between text-[10px] font-bold text-ink-500">
                  <span>پیشرفت</span>
                  <span className="font-num tabular">{fa(hover.progress)}٪</span>
                </div>
                <ProgressBar value={hover.progress} hex={SLA_TONE[slaOf(hover, now).tone].hex} height={7} />
              </div>
              <div className="flex items-center gap-1.5">
                <Badge className={STATUS_META[hover.status].chip} dot={STATUS_META[hover.status].hex}>
                  {STATUS_META[hover.status].label}
                </Badge>
                <Badge className={cn("ring-1", SLA_TONE[slaOf(hover, now).tone].chip)}>{SLA_TONE[slaOf(hover, now).tone].label}</Badge>
              </div>
            </div>
          ) : (
            <div className="flex flex-wrap items-center gap-4 text-[11px] font-bold text-ink-400">
              <span className="inline-flex items-center gap-2">
                <IconSpark className="h-4 w-4 text-sun-500" />
                نشانگر را روی میله‌ها ببرید تا جزئیات زمان‌بندی نمایش داده شود
              </span>
              <span className="inline-flex items-center gap-2">
                <IconFlag className="h-4 w-4 text-ember-500" />
                لوزی انتهای میله = نقطه سررسید (رنگ = وضعیت SLA)
              </span>
              <span className="inline-flex items-center gap-2">
                <IconShield className="h-4 w-4 text-brand-500" />
                کلیک روی میله، تسک را در بورد کاربر باز می‌کند
              </span>
              <span className="inline-flex items-center gap-2">
                <IconLayers className="h-4 w-4 text-leaf-500" />
                {fa(edges.length)} وابستگی روی نمودار ترسیم شده است
              </span>
            </div>
          )}
        </div>
      </Panel>
    </div>
  );
}

const STATUS_ORDER_SHORT = ["todo", "doing", "review", "blocked", "done"] as const;

const Inspector = ({ label, value, tone }: { label: string; value: string; tone?: "danger" | "warn" | "ok" }) => (
  <div className="rounded-xl border border-milk-300 bg-white px-3 py-2">
    <p className="text-[9.5px] font-bold text-ink-400">{label}</p>
    <p
      className={cn(
        "mt-0.5 text-[11.5px] font-extrabold",
        tone === "danger" ? "text-ember-600" : tone === "warn" ? "text-sun-600" : "text-ink-800",
      )}
    >
      {value}
    </p>
  </div>
);
