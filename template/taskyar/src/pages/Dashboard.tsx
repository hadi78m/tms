import { useMemo, useState } from "react";
import { cn } from "@/utils/cn";
import { useStore } from "@/lib/store";
import { fa, faNum, fmtDate, faDuration, leftover } from "@/lib/fa";
import { ACTIVITIES, MONTHLY, WEEKLY } from "@/lib/data";
import {
  CURRENT_USER,
  MEMBERS,
  PROJECTS,
  PRIORITY_META,
  SLA_TONE,
  STATUS_META,
  STATUS_ORDER,
  memberOf,
  projectOf,
  slaOf,
  type SlaTone,
} from "@/lib/types";
import { AreaTrend, BarPairs, Donut, HBars, Ribbon } from "@/components/charts";
import {
  Avatar,
  Badge,
  Chip,
  Counter,
  Panel,
  ProgressBar,
  Reveal,
  Ring,
  Segmented,
  Sparkline,
} from "@/components/ui";
import {
  IconAlert,
  IconArrowLeft,
  IconBoard,
  IconCheck,
  IconChecklist,
  IconClock,
  IconFlame,
  IconGauge,
  IconLayers,
  IconShield,
  IconSpark,
  IconTrend,
  IconUsers,
} from "@/components/icons";

const SLA_KEYS: SlaTone[] = ["ok", "warn", "risk", "breach"];

export default function Dashboard() {
  const { tasks, route, setTab, navigate } = useStore();
  const tab = route.tab ?? "overview";

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <Segmented
          value={tab}
          onChange={setTab}
          options={[
            { value: "overview", label: "نمای کلی", icon: <IconGauge className="h-4 w-4" /> },
            { value: "sla", label: "گزارش SLA", icon: <IconShield className="h-4 w-4" /> },
            { value: "team", label: "کارکرد تیم", icon: <IconUsers className="h-4 w-4" /> },
          ]}
        />
        <div className="flex flex-wrap items-center gap-2 text-[11px] font-bold text-ink-500">
          <span className="inline-flex items-center gap-1.5 rounded-full border border-milk-300 bg-milk-50 px-3 py-1.5">
            <i className="dot-live h-1.5 w-1.5 rounded-full bg-leaf-400" />
            پایش زنده — {faNum(tasks.length)} تسک در سامانه
          </span>
          <button
            onClick={() => navigate({ page: "board", tab: "kanban", mine: true })}
            className="inline-flex items-center gap-1.5 rounded-full bg-ink-900 px-3.5 py-1.5 text-milk-50 transition hover:bg-brand-700"
          >
            رفتن به بورد من
            <IconArrowLeft className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      {tab === "overview" && <Overview />}
      {tab === "sla" && <SlaReport />}
      {tab === "team" && <TeamReport />}
    </div>
  );
}

/* ============================== helpers ============================== */
function MiniStat({ label, value, hex }: { label: string; value: string; hex: string }) {
  return (
    <div className="rounded-xl border border-milk-200 bg-white/70 px-3 py-2.5">
      <p className="text-[10.5px] font-bold text-ink-400">{label}</p>
      <p className="font-num mt-0.5 text-lg font-bold tabular" style={{ color: hex }}>
        {value}
      </p>
    </div>
  );
}

function KpiTile({
  title,
  value,
  hint,
  hex,
  icon,
  trend,
  onClick,
}: {
  title: string;
  value: number;
  hint: string;
  hex: string;
  icon: React.ReactNode;
  trend: number[];
  onClick?: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className="lift group relative flex-1 overflow-hidden rounded-[20px] border border-milk-300 bg-milk-50 p-4 text-start shadow-soft hover:shadow-lift"
    >
      <span className="absolute inset-y-0 start-0 w-1.5 transition-all duration-300 group-hover:w-2.5" style={{ background: hex }} />
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="text-[11.5px] font-bold text-ink-500">{title}</p>
          <p className="font-num mt-1 text-[34px] font-bold leading-none tabular text-ink-900">
            <Counter value={value} />
          </p>
          <p className="mt-1.5 text-[10.5px] font-semibold text-ink-400">{hint}</p>
        </div>
        <span
          className="grid h-10 w-10 shrink-0 place-items-center rounded-xl transition-transform duration-300 group-hover:-rotate-6 group-hover:scale-110"
          style={{ background: `${hex}1c`, color: hex }}
        >
          {icon}
        </span>
      </div>
      <div className="mt-2 opacity-80">
        <Sparkline values={trend} hex={hex} />
      </div>
    </button>
  );
}

/* ============================== OVERVIEW ============================== */
function Overview() {
  const { tasks, stats, now, navigate } = useStore();

  const slices = STATUS_ORDER.map((s) => ({
    label: STATUS_META[s].label,
    value: stats.byStatus[s],
    hex: STATUS_META[s].hex,
  }));

  const urgent = useMemo(
    () =>
      [...tasks]
        .filter((t) => t.status !== "done")
        .map((t) => ({ t, l: leftover(t.due, false, now), sla: slaOf(t, now) }))
        .sort((a, b) => {
          if (a.l.overdue !== b.l.overdue) return a.l.overdue ? -1 : 1;
          const d = PRIORITY_META[b.t.priority].weight - PRIORITY_META[a.t.priority].weight;
          return d !== 0 ? d : new Date(a.t.due).getTime() - new Date(b.t.due).getTime();
        })
        .slice(0, 6),
    [tasks, now],
  );

  const myToday = tasks.filter(
    (t) => t.assignee === CURRENT_USER && t.status !== "done" && leftover(t.due, false, now).days <= 1,
  ).length;

  const deliveryRate = Math.round((stats.done / Math.max(1, tasks.length)) * 100);

  return (
    <>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Reveal className="lg:col-span-5" delay={40}>
          <Panel
            className="h-full"
            title="سلامت کلی پروژه"
            subtitle="میانگین پیشرفت و پایبندی به تعهدات"
            icon={<IconSpark className="h-[18px] w-[18px]" />}
            action={
              <Badge className="bg-leaf-100 text-leaf-700 ring-1 ring-leaf-300">
                <i className="dot-live h-1.5 w-1.5 rounded-full bg-leaf-500" />
                پایدار
              </Badge>
            }
          >
            <div className="flex flex-wrap items-center gap-6">
              <Ring value={stats.compliance} size={148} stroke={15} hex="#1a5cd6" track="#e6eefb">
                <div>
                  <p className="font-num text-3xl font-bold tabular text-ink-900">
                    <Counter value={stats.compliance} suffix="٪" />
                  </p>
                  <p className="text-[10.5px] font-bold text-ink-400">پایبندی SLA</p>
                </div>
              </Ring>
              <div className="min-w-[9rem] flex-1 space-y-3">
                <BarLine label="میانگین پیشرفت تسک‌ها" value={stats.avgProgress} display={`${fa(stats.avgProgress)}٪`} hex="#2f7af0" striped />
                <BarLine label="نرخ تحویل اسپرینت" value={deliveryRate} display={`${fa(deliveryRate)}٪`} hex="#16a157" />
                <BarLine
                  label="بار کاری باقی‌مانده"
                  value={Math.min(100, stats.hoursLeft / 3)}
                  display={faDuration(stats.hoursLeft)}
                  hex="#ef6211"
                />
              </div>
            </div>
            <div className="mt-5">
              <Ribbon slices={slices} />
              <div className="mt-2.5 flex flex-wrap gap-x-4 gap-y-1.5">
                {slices.map((s) => (
                  <span key={s.label} className="inline-flex items-center gap-1.5 text-[11px] font-bold text-ink-500">
                    <i className="h-2 w-2 rounded-[3px]" style={{ background: s.hex }} />
                    {s.label}
                    <span className="font-num tabular text-ink-800">{fa(s.value)}</span>
                  </span>
                ))}
              </div>
            </div>
          </Panel>
        </Reveal>

        <Reveal className="lg:col-span-4" delay={120}>
          <Panel
            className="h-full"
            title="چرخه تحویل شش ماهه"
            subtitle="برنامه در برابر تحویل واقعی"
            icon={<IconTrend className="h-[18px] w-[18px]" />}
          >
            <BarPairs
              data={MONTHLY.map((m) => ({ label: m.label, a: m.planned, b: m.delivered }))}
              keyA="برنامه‌ریزی‌شده"
              keyB="تحویل‌شده"
            />
            <p className="mt-4 rounded-xl border border-sun-200 bg-sun-100/60 px-3 py-2 text-[11px] font-bold leading-5 text-sun-600">
              نرخ تحویل مرداد ۱۰۷٪ برنامه بود؛ شهریور تا امروز ۸۷٪ محقق شده است.
            </p>
          </Panel>
        </Reveal>

        <Reveal className="lg:col-span-3" delay={200}>
          <div className="flex h-full flex-col gap-4">
            <KpiTile
              title="سررسید امروز و فردا"
              value={myToday}
              hint="از تسک‌های اختصاصی من"
              hex="#ef6211"
              icon={<IconClock className="h-[18px] w-[18px]" />}
              trend={[2, 3, 3, 5, 4, 6, 5, Math.max(1, myToday)]}
              onClick={() => navigate({ page: "board", tab: "kanban", mine: true })}
            />
            <KpiTile
              title="تسک‌های بحرانی"
              value={stats.byPriority.critical}
              hint={`${fa(stats.byPriority.high)} تسک با اولویت زیاد`}
              hex="#c94a08"
              icon={<IconFlame className="h-[18px] w-[18px]" />}
              trend={[1, 2, 2, 3, 3, 4, 3, Math.max(1, stats.byPriority.critical)]}
              onClick={() => navigate({ page: "admin", priority: "critical", status: "all" })}
            />
            <KpiTile
              title="تحویل‌شده تا امروز"
              value={stats.done}
              hint={`${fa(stats.open)} تسک باز باقی مانده`}
              hex="#16a157"
              icon={<IconCheck className="h-[18px] w-[18px]" />}
              trend={[9, 12, 8, 14, 11, 16, 13, Math.max(1, stats.done)]}
              onClick={() => navigate({ page: "admin", status: "done" })}
            />
          </div>
        </Reveal>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Reveal className="lg:col-span-5" delay={60}>
          <Panel
            className="h-full"
            title="توزیع وضعیت تسک‌ها"
            subtitle="نشانگر را روی کمان‌ها ببرید"
            icon={<IconLayers className="h-[18px] w-[18px]" />}
          >
            <Donut data={slices} centerTitle="کل تسک‌ها" centerValue={fa(tasks.length)} />
          </Panel>
        </Reveal>

        <Reveal className="lg:col-span-7" delay={140}>
          <Panel
            className="h-full"
            title="روند پایبندی SLA در هشت هفته"
            subtitle="خط‌چین نارنجی = آستانه پذیرش سازمانی (۸۵٪)"
            icon={<IconShield className="h-[18px] w-[18px]" />}
            action={
              <div className="flex items-center gap-2">
                <Badge className="bg-leaf-100 text-leaf-700 ring-1 ring-leaf-300">سبز {fa(stats.slaRows.filter((r) => r.sla.tone === "ok").length)}</Badge>
                <Badge className="bg-sun-100 text-sun-600 ring-1 ring-sun-300">زرد {fa(stats.warn)}</Badge>
                <Badge className="bg-ember-100 text-ember-600 ring-1 ring-ember-300">قرمز {fa(stats.atRisk + stats.breached)}</Badge>
              </div>
            }
          >
            <AreaTrend data={WEEKLY.map((w) => ({ label: w.label, value: w.compliance }))} hex="#1a5cd6" baseline={85} height={210} />
            <div className="mt-4 grid grid-cols-3 gap-3">
              <MiniStat
                label="میانگین پایبندی"
                value={`${fa(Math.round(WEEKLY.reduce((a, w) => a + w.compliance, 0) / WEEKLY.length))}٪`}
                hex="#1a5cd6"
              />
              <MiniStat label="بهترین هفته" value={`${fa(Math.max(...WEEKLY.map((w) => w.compliance)))}٪`} hex="#16a157" />
              <MiniStat label="نقض SLA فعال" value={fa(stats.breached)} hex="#ef6211" />
            </div>
          </Panel>
        </Reveal>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Reveal className="lg:col-span-7" delay={60}>
          <Panel
            className="h-full"
            title="نیازمند اقدام فوری"
            subtitle="مرتب‌شده بر اساس تأخیر و اولویت"
            icon={<IconAlert className="h-[18px] w-[18px]" />}
            accent="#ef6211"
            action={
              <button
                onClick={() => navigate({ page: "admin", status: "all", q: "overdue" })}
                className="text-[11px] font-bold text-brand-600 underline-sweep"
              >
                مشاهده همه
              </button>
            }
          >
            <ul className="space-y-2">
              {urgent.map(({ t, l, sla }) => (
                <li
                  key={t.id}
                  onClick={() => navigate({ page: "admin", q: t.code, status: "all" })}
                  className="group flex cursor-pointer items-center gap-3 rounded-2xl border border-milk-200 bg-white/70 p-3 transition-all duration-300 hover:-translate-y-0.5 hover:border-brand-200 hover:bg-brand-50/50 hover:shadow-soft"
                >
                  <span
                    className="grid h-10 w-10 shrink-0 place-items-center rounded-xl font-num text-[11px] font-bold tabular text-white"
                    style={{ background: `linear-gradient(140deg, ${sla.tone === "breach" ? "#c94a08" : PRIORITY_META[t.priority].hex}, ${projectOf(t.project).hex})` }}
                  >
                    {t.code.replace("TS-", "")}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-extrabold text-ink-900">{t.title}</p>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10.5px] font-bold text-ink-400">
                      <span className="inline-flex items-center gap-1.5">
                        <Avatar name={t.assignee} size="xs" />
                        {t.assignee}
                      </span>
                      <span>سررسید: {fmtDate(t.due)}</span>
                      <span
                        className={cn(
                          "inline-flex items-center gap-1",
                          l.tone === "danger" ? "text-ember-600" : l.tone === "warn" ? "text-sun-600" : "text-leaf-600",
                        )}
                      >
                        <IconClock className="h-3.5 w-3.5" />
                        {l.overdue ? `${l.label} تأخیر` : `${l.label} مانده`}
                      </span>
                    </div>
                    <div className="mt-1.5 flex items-center gap-2">
                      <ProgressBar value={t.progress} hex={SLA_TONE[sla.tone].hex} height={5} className="max-w-[160px]" />
                      <span className="font-num text-[10px] tabular text-ink-400">{fa(t.progress)}٪</span>
                    </div>
                  </div>
                  <Badge className={cn("shrink-0 ring-1", SLA_TONE[sla.tone].chip)}>{SLA_TONE[sla.tone].label}</Badge>
                  <IconArrowLeft className="h-4 w-4 shrink-0 text-ink-300 transition-transform duration-300 group-hover:-translate-x-1 group-hover:text-brand-600" />
                </li>
              ))}
            </ul>
          </Panel>
        </Reveal>

        <Reveal className="lg:col-span-5" delay={140}>
          <Panel className="h-full" title="فعالیت اخیر تیم" subtitle="رویدادهای ثبت‌شده روی تسک‌ها" icon={<IconSpark className="h-[18px] w-[18px]" />}>
            <ol className="relative space-y-4 ps-1">
              <span className="absolute bottom-2 start-[7px] top-2 w-px bg-gradient-to-b from-brand-200 via-sun-200 to-transparent" />
              {ACTIVITIES.map((a) => {
                const tone = a.tone === "note" ? "#527aae" : STATUS_META[a.tone].hex;
                return (
                  <li key={a.id} className="group relative flex gap-3">
                    <span
                      className="relative z-10 mt-1 h-3.5 w-3.5 shrink-0 rounded-full ring-4 ring-milk-50 transition-transform duration-300 group-hover:scale-125"
                      style={{ background: tone }}
                    />
                    <button
                      onClick={() => navigate({ page: "admin", q: a.task, status: "all" })}
                      className="flex-1 rounded-xl px-1 py-0.5 text-start transition hover:bg-white/70"
                    >
                      <p className="text-[12px] font-bold leading-5 text-ink-800">
                        <span className="text-brand-700">{a.who}</span> {a.action}
                      </p>
                      <p className="mt-0.5 flex items-center gap-2 text-[10px] font-semibold text-ink-400">
                        <span className="font-num tabular">{a.task}</span>
                        <span className="h-1 w-1 rounded-full bg-ink-200" />
                        {a.when}
                      </p>
                    </button>
                  </li>
                );
              })}
            </ol>
          </Panel>
        </Reveal>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Reveal className="lg:col-span-7" delay={60}>
          <Panel className="h-full" title="تفکیک پروژه‌ها" subtitle="پیشرفت و تعهد هر پروژه" icon={<IconBoard className="h-[18px] w-[18px]" />}>
            <div className="grid gap-3 sm:grid-cols-2">
              {PROJECTS.map((p) => {
                const list = tasks.filter((t) => t.project === p.name);
                const done = list.filter((t) => t.status === "done").length;
                const prog = list.length ? Math.round(list.reduce((a, t) => a + t.progress, 0) / list.length) : 0;
                const comp = list.length ? Math.round(list.reduce((a, t) => a + slaOf(t, now).compliance, 0) / list.length) : 0;
                return (
                  <button
                    key={p.code}
                    onClick={() => navigate({ page: "board", tab: "kanban", project: p.name, mine: false })}
                    className="lift group relative overflow-hidden rounded-2xl border border-milk-300 bg-white/80 p-4 text-start shadow-soft"
                  >
                    <span className="absolute inset-x-0 top-0 h-1" style={{ background: p.hex }} />
                    <span
                      className="pointer-events-none absolute -end-8 -top-8 h-24 w-24 rounded-full opacity-15 blur-2xl transition-opacity duration-300 group-hover:opacity-35"
                      style={{ background: p.hex }}
                    />
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className="font-num text-[10px] font-bold tabular" style={{ color: p.hex }}>
                          {p.code}
                        </p>
                        <h4 className="mt-1 text-[13.5px] font-extrabold leading-5 text-ink-900">{p.name}</h4>
                        <p className="mt-0.5 text-[10.5px] text-ink-400">کارفرما: {p.client}</p>
                      </div>
                      <Ring value={prog} size={56} stroke={7} hex={p.hex}>
                        <span className="font-num text-[11px] font-bold tabular text-ink-800">{fa(prog)}٪</span>
                      </Ring>
                    </div>
                    <div className="mt-3 flex items-center justify-between text-[10.5px] font-bold text-ink-500">
                      <span>
                        <span className="font-num tabular text-ink-900">{fa(done)}</span> / {fa(list.length)} تسک تحویل‌شده
                      </span>
                      <span className="rounded-full px-2 py-0.5" style={{ background: `${p.hex}18`, color: p.hex }}>
                        پایبندی {fa(comp)}٪
                      </span>
                    </div>
                  </button>
                );
              })}
            </div>
          </Panel>
        </Reveal>

        <Reveal className="lg:col-span-5" delay={140}>
          <Panel className="h-full" title="بار کاری اعضای تیم" subtitle="ساعات تخمینی باقی‌مانده" icon={<IconUsers className="h-[18px] w-[18px]" />}>
            <HBars
              data={stats.workload.map((w) => ({
                label: w.member.name,
                value: w.load,
                hex: w.member.color,
                note: `${fa(w.total)} تسک`,
              }))}
              suffix=" ساعت"
            />
          </Panel>
        </Reveal>
      </div>
    </>
  );
}

function BarLine({
  label,
  value,
  display,
  hex,
  striped,
}: {
  label: string;
  value: number;
  display: string;
  hex: string;
  striped?: boolean;
}) {
  return (
    <div>
      <div className="mb-1 flex items-baseline justify-between">
        <span className="text-[11px] font-bold text-ink-500">{label}</span>
        <span className="font-num text-[12.5px] font-bold tabular" style={{ color: hex }}>
          {display}
        </span>
      </div>
      <ProgressBar value={value} hex={hex} height={9} striped={striped} />
    </div>
  );
}

/* ============================== SLA REPORT ============================== */
function SlaReport() {
  const { tasks, stats, now, navigate } = useStore();
  const [tone, setTone] = useState<"all" | SlaTone>("all");

  const rows = stats.slaRows
    .filter((r) => tone === "all" || r.sla.tone === tone)
    .sort((a, b) => a.sla.compliance - b.sla.compliance);

  const perProject = PROJECTS.map((p) => {
    const list = tasks.filter((t) => t.project === p.name);
    const comp = list.length ? Math.round(list.reduce((a, t) => a + slaOf(t, now).compliance, 0) / list.length) : 0;
    return { label: p.name, value: comp, hex: p.hex, note: `${fa(list.length)} تسک` };
  });

  const breachedHours = stats.slaRows
    .filter((r) => r.sla.tone === "breach" || r.sla.tone === "risk")
    .reduce((a, r) => a + Math.max(0, -r.sla.left), 0);

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Reveal className="lg:col-span-4">
          <Panel className="h-full" title="شاخص پایبندی" subtitle="میانگین وزنی همه تسک‌ها" icon={<IconShield className="h-[18px] w-[18px]" />}>
            <div className="flex flex-wrap items-center gap-5">
              <Ring
                value={stats.compliance}
                size={132}
                stroke={14}
                hex={stats.compliance > 88 ? "#16a157" : stats.compliance > 75 ? "#f9bb1b" : "#ef6211"}
              >
                <div>
                  <p className="font-num text-2xl font-bold tabular text-ink-900">
                    <Counter value={stats.compliance} suffix="٪" />
                  </p>
                  <p className="text-[10px] font-bold text-ink-400">هدف: ۹۰٪</p>
                </div>
              </Ring>
              <ul className="min-w-[9rem] flex-1 space-y-2">
                {SLA_KEYS.map((k) => {
                  const count = stats.slaRows.filter((r) => r.sla.tone === k).length;
                  return (
                    <li key={k} className="flex items-center gap-2">
                      <i className="h-2.5 w-2.5 rounded-full" style={{ background: SLA_TONE[k].hex }} />
                      <span className="flex-1 text-[11.5px] font-bold text-ink-600">{SLA_TONE[k].label}</span>
                      <span className="font-num text-[12.5px] font-bold tabular text-ink-900">{fa(count)}</span>
                    </li>
                  );
                })}
              </ul>
            </div>
            <div className="mt-4 grid grid-cols-2 gap-3">
              <MiniStat label="ساعت تأخیر تجمعی" value={`${fa(Math.round(breachedHours))} ساعت`} hex="#c94a08" />
              <MiniStat label="سررسید در ۷۲ ساعت" value={fa(stats.dueSoon.length)} hex="#e39c07" />
            </div>
          </Panel>
        </Reveal>

        <Reveal className="lg:col-span-4" delay={90}>
          <Panel className="h-full" title="پایبندی به تفکیک پروژه" subtitle="درصد تحقق برنامه هر پروژه" icon={<IconLayers className="h-[18px] w-[18px]" />}>
            <HBars data={perProject} suffix="٪" />
            <div className="mt-5 rounded-2xl border border-brand-100 bg-brand-50/60 p-3.5">
              <p className="text-[11.5px] font-extrabold text-brand-800">توصیه سیستم</p>
              <p className="mt-1 text-[11px] leading-5 text-ink-600">
                پروژه «زیرساخت و استقرار» به دلیل تسک مسدود TS-1033 بیشترین ریسک نقض SLA را دارد؛ وابستگی تسک TS-1039 نیاز به بازبینی دارد.
              </p>
              <button onClick={() => navigate({ page: "timeline" })} className="mt-2 inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-700 underline-sweep">
                بررسی در گانت
                <IconArrowLeft className="h-3.5 w-3.5" />
              </button>
            </div>
          </Panel>
        </Reveal>

        <Reveal className="lg:col-span-4" delay={170}>
          <Panel className="h-full" title="روند هفتگی تعهدات" subtitle="تحویل در برابر حجم ورودی" icon={<IconTrend className="h-[18px] w-[18px]" />}>
            <BarPairs
              data={WEEKLY.map((w) => ({ label: w.label.replace("هفته ", "ه"), a: w.created, b: w.delivered }))}
              keyA="تسک ورودی"
              keyB="تحویل‌شده"
              colorA="#527aae"
              colorB="#16a157"
              height={170}
            />
            <div className="mt-4">
              <AreaTrend
                data={WEEKLY.map((w) => ({ label: w.label.replace("هفته ", "ه"), value: w.compliance }))}
                hex="#ef6211"
                height={110}
                baseline={85}
              />
            </div>
          </Panel>
        </Reveal>
      </div>

      <Reveal delay={60}>
        <Panel
          title="جدول تعهدات SLA"
          subtitle={`${fa(rows.length)} ردیف — مرتب‌شده بر اساس کمترین پایبندی`}
          icon={<IconChecklist className="h-[18px] w-[18px]" />}
          bodyClass="p-0"
          action={
            <div className="flex flex-wrap gap-1.5">
              <Chip active={tone === "all"} onClick={() => setTone("all")}>
                همه ({fa(tasks.length)})
              </Chip>
              {SLA_KEYS.map((k) => (
                <Chip key={k} active={tone === k} hex={SLA_TONE[k].hex} onClick={() => setTone(k)}>
                  {SLA_TONE[k].label}
                </Chip>
              ))}
            </div>
          }
        >
          <div className="overflow-x-auto">
            <table className="w-full min-w-[880px] border-collapse">
              <thead>
                <tr className="bg-milk-100 text-[10.5px] font-extrabold text-ink-500">
                  {["کد", "عنوان تسک", "مسئول", "پنجره SLA", "پیشرفت واقعی / مورد انتظار", "زمان باقی‌مانده", "وضعیت تعهد"].map((h) => (
                    <th key={h} className="whitespace-nowrap border-b border-milk-300 px-4 py-3 text-start">
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map(({ task, sla }) => {
                  const l = leftover(task.due, task.status === "done", now);
                  return (
                    <tr
                      key={task.id}
                      onClick={() => navigate({ page: "admin", q: task.code, status: "all" })}
                      className="cursor-pointer border-b border-milk-200 bg-white/50 transition-colors last:border-0 hover:bg-sun-100/40"
                    >
                      <td className="whitespace-nowrap px-4 py-3">
                        <span className="font-num text-[11.5px] font-bold tabular text-brand-700">{task.code}</span>
                      </td>
                      <td className="px-4 py-3">
                        <p className="max-w-[240px] truncate text-[12.5px] font-extrabold text-ink-900">{task.title}</p>
                        <p className="text-[10.5px] text-ink-400">{task.project}</p>
                      </td>
                      <td className="whitespace-nowrap px-4 py-3">
                        <span className="inline-flex items-center gap-2">
                          <Avatar name={task.assignee} size="xs" />
                          <span className="text-[11.5px] font-bold text-ink-600">{task.assignee}</span>
                        </span>
                      </td>
                      <td className="whitespace-nowrap px-4 py-3 font-num text-[11.5px] tabular text-ink-600">{fa(task.slaHours)} ساعت</td>
                      <td className="px-4 py-3">
                        <div className="relative w-[150px]">
                          <ProgressBar value={task.progress} hex={SLA_TONE[sla.tone].hex} height={8} />
                          <span
                            className="absolute -top-1 h-[16px] w-[2px] rounded bg-ink-800/70"
                            style={{ insetInlineStart: `${sla.expected}%` }}
                            title={`پیشرفت مورد انتظار: ${fa(sla.expected)}٪`}
                          />
                        </div>
                        <p className="mt-1 font-num text-[10px] tabular text-ink-400">
                          واقعی {fa(task.progress)}٪ / انتظار {fa(sla.expected)}٪
                        </p>
                      </td>
                      <td
                        className={cn(
                          "whitespace-nowrap px-4 py-3 text-[11.5px] font-bold",
                          l.tone === "danger" ? "text-ember-600" : l.tone === "warn" ? "text-sun-600" : "text-leaf-600",
                        )}
                      >
                        {task.status === "done" ? "تحویل شد" : l.overdue ? `${l.label} تأخیر` : l.label}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3">
                        <Badge className={cn("ring-1", SLA_TONE[sla.tone].chip)} dot={SLA_TONE[sla.tone].hex}>
                          {SLA_TONE[sla.tone].label}
                        </Badge>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Panel>
      </Reveal>
    </div>
  );
}

/* ============================== TEAM ============================== */
function TeamReport() {
  const { stats, tasks, route, navigate } = useStore();
  const focus = route.q && MEMBERS.some((m) => m.name === route.q) ? route.q : null;
  const list = focus ? stats.workload.filter((w) => w.member.name === focus) : stats.workload;

  return (
    <div className="space-y-4">
      {focus && (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-sun-300 bg-sun-100/70 px-4 py-3">
          <p className="text-[12.5px] font-extrabold text-sun-600">
            فیلتر روی عضو تیم: {focus} — {memberOf(focus).role}
          </p>
          <button onClick={() => navigate({ page: "dashboard", tab: "team" })} className="text-[11px] font-bold text-ink-600 underline-sweep">
            حذف فیلتر
          </button>
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {list.map((w, i) => {
          const mine = tasks.filter((t) => t.assignee === w.member.name);
          const done = mine.filter((t) => t.status === "done").length;
          const trend = [done - 3, done - 2, done - 2, done - 1, done, done + 1, done, Math.max(0, done)].map((n) => Math.max(0, n));
          return (
            <Reveal key={w.member.name} delay={i * 70}>
              <Panel className="h-full">
                <div className="flex items-start gap-3">
                  <Avatar name={w.member.name} size="lg" online={w.atRisk === 0} />
                  <div className="min-w-0 flex-1">
                    <h4 className="truncate text-[14px] font-extrabold text-ink-900">{w.member.name}</h4>
                    <p className="truncate text-[11px] text-ink-400">{w.member.role}</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      <Badge className="bg-brand-50 text-brand-700 ring-1 ring-brand-200">
                        <span className="font-num tabular">{fa(w.total)}</span> تسک
                      </Badge>
                      <Badge className="bg-leaf-100 text-leaf-700 ring-1 ring-leaf-300">
                        <span className="font-num tabular">{fa(done)}</span> تحویل
                      </Badge>
                      {w.atRisk > 0 && (
                        <Badge className="bg-ember-100 text-ember-600 ring-1 ring-ember-300">
                          <span className="font-num tabular">{fa(w.atRisk)}</span> در ریسک
                        </Badge>
                      )}
                    </div>
                  </div>
                  <Ring value={(done / Math.max(1, w.total)) * 100} size={54} stroke={7} hex={w.member.color}>
                    <span className="font-num text-[10px] font-bold tabular text-ink-700">
                      {fa(Math.round((done / Math.max(1, w.total)) * 100))}٪
                    </span>
                  </Ring>
                </div>
                <div className="mt-4">
                  <div className="mb-1 flex items-baseline justify-between text-[11px] font-bold">
                    <span className="text-ink-500">بار کاری باز</span>
                    <span className="font-num tabular text-ink-800">{fa(w.load)} ساعت</span>
                  </div>
                  <ProgressBar value={Math.min(100, (w.load / 60) * 100)} hex={w.member.color} height={9} striped />
                </div>
                <div className="mt-3 rounded-xl bg-milk-100 p-2">
                  <p className="mb-1 text-[10px] font-bold text-ink-400">روند تحویل</p>
                  <Sparkline values={trend} hex={w.member.color} />
                </div>
              </Panel>
            </Reveal>
          );
        })}
      </div>

      <Reveal delay={80}>
        <Panel title="توزیع تسک‌ها بین اعضا" subtitle="مقایسه حجم کار تحویل‌شده و باز" icon={<IconUsers className="h-[18px] w-[18px]" />}>
          <BarPairs
            data={stats.workload.map((w) => ({ label: w.member.name.split(" ")[0], a: w.done, b: w.total - w.done }))}
            keyA="تحویل‌شده"
            keyB="باز"
            colorA="#16a157"
            colorB="#2f7af0"
            height={180}
          />
        </Panel>
      </Reveal>
    </div>
  );
}
