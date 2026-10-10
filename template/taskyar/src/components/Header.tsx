import { useEffect, useMemo, useRef, useState } from "react";
import { cn } from "@/utils/cn";
import { useStore } from "@/lib/store";
import { PAGE_HERO } from "@/lib/data";
import { fa, fmtDateLong, fmtTime, leftover } from "@/lib/fa";
import { NOTIFICATIONS } from "@/lib/data";
import { STATUS_META, type Page } from "@/lib/types";
import {
  IconBell,
  IconMenu,
  IconPlus,
  IconSearch,
  IconDownload,
  IconClock,
  IconX,
  IconArrowLeft,
} from "./icons";
import { Avatar, Badge } from "./ui";

const TITLES: Record<Page, { title: string; desc: string; crumb: string }> = {
  dashboard: {
    title: "داشبورد پایش پروژه",
    desc: "نمای زنده از وضعیت تسک‌ها، تعهدات SLA و بهره‌وری تیم",
    crumb: "داشبورد",
  },
  admin: {
    title: "پنل مدیریت تسک‌ها",
    desc: "تعریف تسک، زیرگروه تصویری، وابستگی، زمان‌بندی و تخصیص منابع",
    crumb: "مدیریت",
  },
  board: {
    title: "بورد کاری من",
    desc: "جابجایی تسک‌ها بین ستون‌ها، یادداشت چسبنده و به‌روزرسانی وضعیت",
    crumb: "بورد کاربر",
  },
  timeline: {
    title: "زمان‌بندی و گانت",
    desc: "نمای تقویمی اسپرینت‌ها همراه با وابستگی‌های بحرانی",
    crumb: "زمان‌بندی",
  },
};

export default function Header() {
  const { route, navigate, tasks, stats, setSidebarOpen, toast } = useStore();
  const hero = PAGE_HERO[route.page] ?? PAGE_HERO.dashboard;
  const meta = TITLES[route.page];

  const [clock, setClock] = useState(() => new Date());
  const [q, setQ] = useState("");
  const [bell, setBell] = useState(false);
  const [read, setRead] = useState<string[]>([]);
  const boxRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const t = setInterval(() => setClock(new Date()), 1000);
    return () => clearInterval(t);
  }, []);

  useEffect(() => {
    const onDoc = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setBell(false);
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  const suggestions = useMemo(() => {
    const term = q.trim();
    if (term.length < 2) return [];
    return tasks
      .filter(
        (t) =>
          t.title.includes(term) ||
          t.code.toLowerCase().includes(term.toLowerCase()) ||
          t.assignee.includes(term) ||
          t.tags.some((tag) => tag.includes(term)),
      )
      .slice(0, 5);
  }, [q, tasks]);

  const unread = NOTIFICATIONS.filter((n) => !read.includes(n.id)).length;

  return (
    <header className="relative isolate overflow-hidden rounded-b-[28px] border-b border-ink-800/40 text-milk-50">
      {/* real photographic backdrop */}
      <div className="absolute inset-0 -z-20 overflow-hidden bg-ink-900">
        <img
          key={hero.src}
          src={hero.src}
          alt={hero.alt}
          className="kenburns h-full w-full object-cover opacity-[0.92]"
          loading="eager"
        />
      </div>
      <div className="absolute inset-0 -z-10 bg-gradient-to-l from-ink-950/96 via-ink-950/80 to-brand-900/35" />
      <div className="absolute inset-0 -z-10 opacity-60 grid-light" />
      <div className="pointer-events-none absolute -top-16 end-10 -z-10 h-56 w-56 rounded-full bg-sun-400/20 blur-3xl anim-float" />
      <div className="pointer-events-none absolute -bottom-10 start-1/3 -z-10 h-40 w-64 rounded-full bg-leaf-500/15 blur-3xl" />

      {/* top bar */}
      <div className="flex flex-wrap items-center gap-3 border-b border-white/10 px-4 py-3 sm:px-6">
        <button
          onClick={() => setSidebarOpen(true)}
          className="grid h-10 w-10 place-items-center rounded-xl border border-white/15 bg-white/10 text-milk-100 transition hover:bg-white/20 xl:hidden"
          aria-label="باز کردن منو"
        >
          <IconMenu className="h-5 w-5" />
        </button>

        <nav className="flex items-center gap-2 text-[11.5px] font-semibold text-milk-200/75">
          <span className="text-brand-200">تسک‌یار</span>
          <IconArrowLeft className="h-3.5 w-3.5 opacity-60" />
          <span>{meta.crumb}</span>
          {route.project && (
            <>
              <IconArrowLeft className="h-3.5 w-3.5 opacity-60" />
              <span className="text-sun-300">{route.project}</span>
            </>
          )}
        </nav>

        <div className="ms-auto flex items-center gap-2">
          {/* search */}
          <div className="relative">
            <div className="flex items-center gap-2 rounded-xl border border-white/15 bg-ink-950/45 px-3 py-2 backdrop-blur transition-all duration-300 focus-within:border-sun-300/70 focus-within:bg-ink-950/70">
              <IconSearch className="h-4 w-4 text-brand-200" />
              <input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" && q.trim()) navigate({ page: "admin", q: q.trim(), status: "all" });
                }}
                placeholder="جستجوی تسک، کد یا عضو…"
                className="w-32 bg-transparent text-[12px] font-semibold text-milk-50 outline-none placeholder:text-milk-200/50 sm:w-56"
              />
              {q && (
                <button onClick={() => setQ("")} className="text-milk-200/60 hover:text-ember-300" aria-label="پاک کردن">
                  <IconX className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
            {suggestions.length > 0 && (
              <div className="anim-pop absolute top-full z-50 mt-2 w-[min(24rem,88vw)] overflow-hidden rounded-2xl border border-milk-300 bg-milk-50 shadow-lift">
                {suggestions.map((t) => {
                  const l = leftover(t.due, t.status === "done");
                  return (
                    <button
                      key={t.id}
                      onClick={() => {
                        navigate({ page: "admin", q: t.code, status: "all" });
                        setQ("");
                      }}
                      className="flex w-full items-center gap-3 border-b border-milk-200 px-3.5 py-2.5 text-start transition last:border-0 hover:bg-brand-50"
                    >
                      <span className="font-num text-[10.5px] tabular text-ink-400">{t.code}</span>
                      <span className="flex-1 truncate text-[12.5px] font-bold text-ink-800">{t.title}</span>
                      <Badge className={STATUS_META[t.status].chip}>{STATUS_META[t.status].label}</Badge>
                      <span className={cn("text-[10.5px] font-bold", l.tone === "danger" ? "text-ember-500" : "text-ink-400")}>
                        {l.label}
                      </span>
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* clock */}
          <div className="hidden items-center gap-2 rounded-xl border border-white/12 bg-white/8 px-3 py-2 lg:flex">
            <IconClock className="h-4 w-4 text-sun-300" />
            <div className="leading-tight">
              <p className="font-num text-[13px] font-bold tabular text-milk-50">{fmtTime(clock)}</p>
              <p className="text-[9.5px] text-milk-200/70">{fmtDateLong(clock)}</p>
            </div>
          </div>

          {/* notifications */}
          <div className="relative" ref={boxRef}>
            <button
              onClick={() => setBell((v) => !v)}
              className={cn(
                "relative grid h-10 w-10 place-items-center rounded-xl border transition-all duration-300",
                bell ? "border-sun-300/70 bg-sun-300/20 text-sun-200" : "border-white/15 bg-white/10 text-milk-100 hover:bg-white/20",
              )}
              aria-label="اعلان‌ها"
            >
              <IconBell className={cn("h-[18px] w-[18px] transition-transform", bell && "rotate-12")} />
              {unread > 0 && (
                <span className="absolute -top-1 -end-1 grid h-5 w-5 place-items-center rounded-full bg-ember-500 font-num text-[10px] font-bold tabular text-white ring-2 ring-ink-900">
                  {fa(unread)}
                </span>
              )}
            </button>
            {bell && (
              <div className="anim-pop absolute end-0 top-full z-50 mt-2 w-[min(22rem,90vw)] overflow-hidden rounded-2xl border border-milk-300 bg-milk-50 shadow-lift">
                <div className="flex items-center justify-between border-b border-milk-200 bg-milk-100 px-4 py-2.5">
                  <p className="text-xs font-extrabold text-ink-800">اعلان‌های زنده</p>
                  <button
                    onClick={() => setRead(NOTIFICATIONS.map((n) => n.id))}
                    className="text-[10.5px] font-bold text-brand-600 hover:underline"
                  >
                    علامت همه به‌خوانده‌شده
                  </button>
                </div>
                <ul className="max-h-80 overflow-y-auto">
                  {NOTIFICATIONS.map((n) => (
                    <li
                      key={n.id}
                      onClick={() => setRead((r) => (r.includes(n.id) ? r : [...r, n.id]))}
                      className={cn(
                        "flex cursor-pointer gap-3 border-b border-milk-200 px-4 py-3 transition last:border-0 hover:bg-brand-50/60",
                        !read.includes(n.id) && "bg-sun-100/40",
                      )}
                    >
                      <span className="relative mt-1 flex h-2.5 w-2.5 shrink-0" style={{ color: n.tone }}>
                        <i className="h-2.5 w-2.5 rounded-full" style={{ background: n.tone }} />
                        {!read.includes(n.id) && <i className="ping-ring absolute inset-0 rounded-full" />}
                      </span>
                      <div className="flex-1">
                        <p className="text-[12px] font-extrabold text-ink-900">{n.title}</p>
                        <p className="mt-0.5 text-[11px] text-ink-500">{n.body}</p>
                        <p className="mt-1 font-num text-[10px] tabular text-ink-300">{n.when}</p>
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          <button
            onClick={() => navigate({ page: "admin", newTask: true, status: "all" })}
            className="hidden items-center gap-2 rounded-xl bg-gradient-to-l from-sun-400 to-ember-400 px-3.5 py-2.5 text-[12px] font-extrabold text-ink-900 shadow-lift transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_30px_-12px_rgba(239,98,17,.8)] sm:inline-flex"
          >
            <IconPlus className="h-4 w-4" strokeWidth={2.4} />
            تسک جدید
          </button>

          <Avatar name="یوسف صادقی" size="sm" className="hidden sm:inline-flex" />
        </div>
      </div>

      {/* title strip */}
      <div className="flex flex-wrap items-end justify-between gap-4 px-4 pb-6 pt-5 sm:px-6">
        <div className="max-w-2xl">
          <p className="mb-1.5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10.5px] font-bold text-milk-100 backdrop-blur">
            <i className="dot-live h-1.5 w-1.5 rounded-full bg-leaf-300" />
            {hero.caption}
          </p>
          <h1 className="font-display text-[34px] leading-[1.15] text-milk-50 drop-shadow-[0_2px_12px_rgba(5,14,28,.55)] sm:text-[42px]">
            {meta.title}
          </h1>
          <p className="mt-2 text-[12.5px] font-medium leading-6 text-milk-200/85">{meta.desc}</p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <StatPill label="کل تسک‌ها" value={fa(tasks.length)} hex="#93c0fd" />
          <StatPill label="انجام‌شده" value={fa(stats.done)} hex="#63d696" />
          <StatPill label="در جریان" value={fa(stats.byStatus.doing + stats.byStatus.review)} hex="#fcd34d" />
          <StatPill label="نقض SLA" value={fa(stats.breached + stats.atRisk)} hex="#fb9f5f" />
          <button
            onClick={() => toast("گزارش PDF در صف تولید قرار گرفت", "ok")}
            className="inline-flex items-center gap-2 rounded-xl border border-white/18 bg-white/10 px-3.5 py-2.5 text-[12px] font-bold text-milk-100 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/20"
          >
            <IconDownload className="h-4 w-4" />
            خروجی گزارش
          </button>
        </div>
      </div>

      <span className="pointer-events-none absolute bottom-4 start-4 text-[9.5px] font-semibold text-milk-200/45">
        تصویر پس‌زمینه: Pexels
      </span>
      <span className="palette-edge absolute inset-x-0 bottom-0 h-[3px]" />
    </header>
  );
}

const StatPill = ({ label, value, hex }: { label: string; value: string; hex: string }) => (
  <div className="min-w-[92px] rounded-2xl border border-white/12 bg-ink-950/35 px-3.5 py-2 backdrop-blur-sm transition-transform duration-300 hover:-translate-y-0.5">
    <p className="text-[10px] font-bold text-milk-200/70">{label}</p>
    <p className="font-num text-xl font-bold tabular" style={{ color: hex }}>
      {value}
    </p>
  </div>
);
