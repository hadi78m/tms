import { useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import { AlertTriangle, Timer, CheckCircle2, Layers, ArrowLeft } from "lucide-react";
import { Donut, WeeklyBars, SlaRows, Burndown } from "../components/charts";
import {
  type Task,
  STATUS,
  PRIORITY,
  MEMBERS,
  WEEKLY,
  BURNDOWN,
  BURNDOWN_IDEAL,
  ACTIVITY,
  countdown,
  faNum,
} from "../data";

const H = 3600_000;

/* ── نوار زندهٔ SLA: تختهٔ عملیات ۲۴ ساعت آینده ── */
function Ribbon({ tasks }: { tasks: Task[] }) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);

  const ws = useMemo(() => {
    const b = new Date();
    b.setHours(8, 0, 0, 0);
    let s = b.getTime();
    if (now < s) s -= 24 * H;
    return s;
  }, [Math.floor(now / 600000)]);
  const we = ws + 24 * H;
  const p = (t: number) => Math.min(1, Math.max(0, (t - ws) / (we - ws)));

  const items = tasks
    .map((t) => {
      const late = t.due < now && t.status !== "done";
      const b = t.due <= ws ? ws + 0.5 * H : Math.min(t.due, we);
      const a = Math.max(Math.min(t.start, b - 0.5 * H), ws);
      return { t, a, b, late };
    })
    .filter((x) => x.b > ws && x.a < we)
    .sort((x, y) => x.a - y.a);

  // چیدمان در مسیرها (جلوگیری از هم‌پوشانی)
  const lanes: (typeof items)[] = [];
  items.forEach((it) => {
    const idx = lanes.findIndex((lane) => lane[lane.length - 1].b <= it.a);
    if (idx === -1) lanes.push([it]);
    else lanes[idx].push(it);
  });

  const ticks = Array.from({ length: 13 }, (_, i) => ws + i * 2 * H);

  return (
    <section className="relative -mx-4 overflow-hidden bg-ink-950 px-4 py-6 text-cream-100 md:-mx-8 md:px-8 md:py-7">
      <div className="pointer-events-none absolute inset-0 opacity-[0.16] [background-image:linear-gradient(to_bottom,rgba(143,186,240,.5)_1px,transparent_1px),linear-gradient(to_left,rgba(143,186,240,.35)_1px,transparent_1px)] [background-size:100%_38px,38px_100%]" />
      <div className="relative flex flex-wrap items-end justify-between gap-3">
        <div>
          <div className="micro text-gold-500">SLA TIMELINE · 24H</div>
          <h2 className="mt-1.5 text-xl font-black text-cream-50 md:text-2xl">
            نوار زمان‌بندی SLA امروز
          </h2>
        </div>
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px]">
          {[
            ["عقب‌افتاده", "#E4572E"],
            ["اولویت بحرانی", "#F2B705"],
            ["در مهلت", "#8FBAF0"],
            ["انجام‌شده", "#2FB673"],
          ].map(([l, c]) => (
            <span key={l} className="flex items-center gap-2">
              <span className="h-2.5 w-2.5 rounded-[2px]" style={{ background: c }} />
              {l}
            </span>
          ))}
          <span className="tnum border-s border-ink-300/40 ps-4 text-ink-300">
            {faNum(items.length)} نوار فعال
          </span>
        </div>
      </div>

      <div className="relative mt-5 overflow-x-auto pb-1">
        <div className="relative min-w-[860px]">
          {/* خط‌کش ساعت */}
          <div className="relative mb-2 h-5">
            {ticks.map((t) => (
              <div
                key={t}
                className="absolute top-0 translate-x-1/2"
                style={{ right: `${p(t) * 100}%` }}
              >
                <span className="tnum text-[10px] text-ink-300">
                  {new Date(t).toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" })}
                </span>
                <span className="mx-auto mt-1 block h-2 w-px bg-white/30" />
              </div>
            ))}
          </div>

          {/* مسیرها */}
          <div
            className="relative rounded-xl border border-white/10 bg-white/[0.04] p-2"
            style={{ height: Math.max(3, lanes.length) * 44 + 16 }}
          >
            {lanes.map((lane, li) =>
              lane.map(({ t, a, b, late }) => {
                const cd = countdown(t.due, now);
                const tone =
                  t.status === "done"
                    ? "border-jade-400/60 bg-jade-600/35"
                    : late
                      ? "border-flame-500 bg-flame-500/30"
                      : t.priority === "high"
                        ? "border-gold-500 bg-gold-500/25"
                        : "border-ink-300/50 bg-ink-700/60";
                return (
                  <motion.div
                    key={t.id}
                    initial={{ opacity: 0, x: 26 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ duration: 0.5, delay: li * 0.06 + 0.1 }}
                    className={`absolute flex h-9 items-center gap-2 overflow-hidden rounded-md border px-2.5 ${tone}`}
                    style={{
                      top: li * 44 + 8,
                      right: `${p(a) * 100}%`,
                      width: `${Math.max(0.6, p(b) - p(a)) * 100}%`,
                      minWidth: 96,
                    }}
                    title={`${t.id} — ${t.title}`}
                  >
                    <span className="tnum shrink-0 text-[10px] font-semibold text-gold-300">
                      {t.id.replace("TSK-", "")}
                    </span>
                    <span className="truncate text-[11.5px] font-bold text-cream-50">{t.title}</span>
                    <span
                      className={`tnum ms-auto shrink-0 rounded px-1.5 py-0.5 text-[10px] ${
                        late ? "bg-flame-500 text-white" : "bg-ink-950/70 text-gold-300"
                      }`}
                    >
                      {cd.text}
                    </span>
                  </motion.div>
                );
              }),
            )}

            {/* نشانگر «الان» */}
            <div
              className="absolute inset-y-0 w-px bg-gold-500"
              style={{ right: `${p(now) * 100}%` }}
            >
              <span className="absolute -top-1 right-1/2 translate-x-1/2 rounded bg-gold-500 px-1.5 py-0.5 text-[9px] font-black text-ink-950">
                NOW
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

/* ── کارت آمار ─────────────────────────────────── */
function Kpi({
  label,
  value,
  unit,
  note,
  icon: Icon,
  tone,
  delay,
}: {
  label: string;
  value: string;
  unit?: string;
  note: string;
  icon: typeof Timer;
  tone: "blue" | "green" | "gold" | "flame";
  delay: number;
}) {
  const map = {
    blue: { icon: "text-ink-500 bg-ink-100", bar: "bg-ink-500" },
    green: { icon: "text-jade-600 bg-jade-100", bar: "bg-jade-600" },
    gold: { icon: "text-gold-500 bg-gold-300/30", bar: "bg-gold-500" },
    flame: { icon: "text-flame-500 bg-flame-300/25", bar: "bg-flame-500" },
  } as const;
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay }}
      className="relative overflow-hidden rounded-xl border border-line bg-cream-50 p-4 card-lift"
    >
      <div className="flex items-start justify-between">
        <span className={`grid h-9 w-9 place-items-center rounded-lg ${map[tone].icon}`}>
          <Icon size={17} />
        </span>
        <span className="micro text-sand">{label}</span>
      </div>
      <div className="mt-4 flex items-end gap-1.5">
        <span className="text-[2.6rem] font-black leading-none tracking-tight text-ink-900 tnum">
          {value}
        </span>
        {unit && <span className="mb-1.5 text-xs font-bold text-sand">{unit}</span>}
      </div>
      <p className="mt-2 text-[11.5px] leading-relaxed text-sand-dark">{note}</p>
      <span className={`absolute inset-x-0 bottom-0 h-[3px] ${map[tone].bar}`} />
    </motion.div>
  );
}

function Panel({
  title,
  kicker,
  children,
  aside,
}: {
  title: string;
  kicker: string;
  children: React.ReactNode;
  aside?: React.ReactNode;
}) {
  return (
    <section className="rounded-xl border border-line bg-cream-50 p-5 card-lift">
      <div className="mb-4 flex items-start justify-between gap-3 border-b border-line pb-3">
        <div>
          <div className="micro text-sand">{kicker}</div>
          <h3 className="mt-1 text-base font-black text-ink-900">{title}</h3>
        </div>
        {aside}
      </div>
      {children}
    </section>
  );
}

export default function Dashboard({ tasks, onOpenTask }: { tasks: Task[]; onOpenTask: () => void }) {
  const now = Date.now();
  const total = tasks.length;
  const done = tasks.filter((t) => t.status === "done").length;
  const active = tasks.filter((t) => t.status === "doing" || t.status === "review").length;
  const late = tasks.filter((t) => t.status !== "done" && t.due < now).length;
  const soon = tasks.filter((t) => t.status !== "done" && t.due >= now && t.due - now < 6 * H).length;
  const ratio = total ? done / total : 0;

  const segments = (["todo", "doing", "review", "done"] as const).map((s) => ({
    label: STATUS[s].label,
    value: tasks.filter((t) => t.status === s).length,
    color: { todo: "#8B7F69", doing: "#1E6FD9", review: "#F2B705", done: "#17864F" }[s],
  }));

  return (
    <div className="space-y-6">
      <Ribbon tasks={tasks} />

      <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <Kpi
          label="TOTAL TASKS"
          value={faNum(total)}
          unit="تسک"
          note="در اسپرینت ۱۴ پروژهٔ بازارک"
          icon={Layers}
          tone="blue"
          delay={0}
        />
        <Kpi
          label="COMPLETED"
          value={faNum(done)}
          unit="مورد"
          note={`${faNum(Math.round(ratio * 100))}٪ از مجموعه بسته شده است`}
          icon={CheckCircle2}
          tone="green"
          delay={0.07}
        />
        <Kpi
          label="IN PROGRESS"
          value={faNum(active)}
          unit="در جریان"
          note={`${faNum(soon)} تسک کمتر از ۶ ساعت تا مهلت دارد`}
          icon={Timer}
          tone="gold"
          delay={0.14}
        />
        <Kpi
          label="SLA BREACH"
          value={faNum(late)}
          unit="قضایا"
          note="فراتر از مهلت تعیین‌شده — نیازمند اقدام فوری"
          icon={AlertTriangle}
          tone="flame"
          delay={0.21}
        />
      </div>

      <div className="grid gap-5 xl:grid-cols-3">
        <Panel
          kicker="STATUS SPLIT"
          title="ترکیب وضعیت تسک‌ها"
          aside={
            <span className="tnum rounded-md bg-cream-200 px-2 py-1 text-[11px] text-sand-dark">
              {faNum(total)} رکورد
            </span>
          }
        >
          <Donut
            segments={segments}
            centerValue={`${Math.round(ratio * 100)}%`}
            centerLabel="DONE RATE"
          />
          <div className="mt-4 flex gap-2 border-t border-line pt-4">
            <button
              onClick={onOpenTask}
              className="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-ink-500 py-2 text-[12px] font-bold text-ink-700 transition hover:bg-ink-100"
            >
              مشاهدهٔ بورد
              <ArrowLeft size={14} />
            </button>
          </div>
        </Panel>

        <Panel
          kicker="WEEKLY FLOW"
          title="روند ایجاد و انجام در شش هفته"
          aside={
            <div className="flex items-center gap-3 text-[11px]">
              <span className="flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-sm bg-ink-300" /> ایجادشده
              </span>
              <span className="flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-sm bg-jade-600" /> انجام‌شده
              </span>
            </div>
          }
        >
          <WeeklyBars data={WEEKLY} />
        </Panel>

        <Panel kicker="SPRINT 14" title="روند سوزاندن کار باقی‌مانده">
          <Burndown actual={BURNDOWN} ideal={BURNDOWN_IDEAL} />
          <div className="mt-3 flex items-center justify-between border-t border-line pt-3 text-[11.5px]">
            <span className="text-sand">خط‌چین: مسیر ایده‌آل</span>
            <span className="tnum font-semibold text-flame-500">۱۲ واحد بالاتر از هدف</span>
          </div>
        </Panel>
      </div>

      <div className="grid gap-5 xl:grid-cols-3">
        <Panel kicker="SLA COMPLIANCE" title="پایبندی SLA به تفکیک عضو">
          <SlaRows rows={MEMBERS} />
          <p className="mt-4 border-t border-line pt-3 text-[11.5px] leading-relaxed text-sand">
            خط عمودی، آستانهٔ تعهد ۹۰٪ است. هر عضو زیر این خط، وارد گزارش هفتگی مدیر پروژه می‌شود.
          </p>
        </Panel>

        <Panel kicker="ACTIVITY LOG" title="آخرین رویدادهای پروژه">
          <ul className="relative space-y-4 ps-4 before:absolute before:inset-y-1 before:right-[3px] before:w-px before:bg-line">
            {ACTIVITY.map((a) => (
              <li key={a.what} className="relative">
                <span className="absolute -right-1 top-1.5 h-2 w-2 rounded-full bg-ink-500 ring-2 ring-cream-50" />
                <p className="text-[12.5px] leading-relaxed text-ink-800">
                  <span className="font-black text-ink-900">{a.who}</span> {a.what}
                </p>
                <span className="tnum text-[10.5px] text-sand">{a.when}</span>
              </li>
            ))}
          </ul>
        </Panel>

        <Panel kicker="PRIORITY MIX" title="توزیع اولویت و فشار کاری">
          <ul className="space-y-4">
            {(["high", "med", "low"] as const).map((pr) => {
              const list = tasks.filter((t) => t.priority === pr);
              const pct = total ? Math.round((list.length / total) * 100) : 0;
              return (
                <li key={pr}>
                  <div className="mb-1.5 flex items-center justify-between text-[12.5px]">
                    <span className="flex items-center gap-2 font-bold text-ink-800">
                      <span className={`h-2.5 w-2.5 rounded-sm ${PRIORITY[pr].color}`} />
                      {PRIORITY[pr].label}
                    </span>
                    <span className="tnum text-sand">
                      {faNum(list.length)} · {faNum(pct)}٪
                    </span>
                  </div>
                  <div className="h-3 overflow-hidden rounded-sm bg-cream-200">
                    <motion.div
                      initial={{ width: 0 }}
                      animate={{ width: `${pct}%` }}
                      transition={{ duration: 0.8, ease: [0.22, 1, 0.36, 1] }}
                      className={`h-full ${PRIORITY[pr].color}`}
                    />
                  </div>
                </li>
              );
            })}
          </ul>
          <div className="mt-5 rounded-lg border border-gold-500/50 bg-gold-300/20 p-3">
            <div className="micro text-ink-800">FOCUS</div>
            <p className="mt-1 text-[12px] leading-relaxed text-ink-900">
              {faNum(tasks.filter((t) => t.priority === "high" && t.status !== "done").length)} تسک
              بحرانی باز است؛ تمرکز امروز تیم بک‌اند روی <b>TSK-1049</b> است.
            </p>
          </div>
        </Panel>
      </div>
    </div>
  );
}
