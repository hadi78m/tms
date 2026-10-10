import { useEffect, useState } from "react";
import { cn } from "@/utils/cn";
import { StoreProvider, useStore } from "@/lib/store";
import { fa, fmtDateLong } from "@/lib/fa";
import { PRIORITY_META, SLA_TONE, STATUS_META, STATUS_ORDER, PROJECTS } from "@/lib/types";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import { Toaster } from "@/components/ui";
import Dashboard from "@/pages/Dashboard";
import Admin from "@/pages/Admin";
import Board from "@/pages/Board";
import Timeline from "@/pages/Timeline";
import { IconArrowLeft, IconChevron, IconSpark } from "@/components/icons";

function Shell() {
  const { route } = useStore();
  const [top, setTop] = useState(false);

  useEffect(() => {
    const onScroll = () => setTop(window.scrollY > 520);
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <div className="relative min-h-screen bg-blueprint">
      <div className="pointer-events-none fixed inset-0 -z-10 grid-paper opacity-70" />
      <div className="pointer-events-none fixed -bottom-32 -start-24 -z-10 h-80 w-80 rounded-full bg-brand-300/20 blur-3xl" />
      <div className="pointer-events-none fixed -top-24 end-1/4 -z-10 h-72 w-72 rounded-full bg-sun-300/20 blur-3xl" />

      <Sidebar />

      <div className="xl:ps-[286px]">
        <Header />

        <main key={route.page} className="anim-rise px-3 py-5 sm:px-5 lg:px-6">
          {route.page === "dashboard" && <Dashboard />}
          {route.page === "admin" && <Admin />}
          {route.page === "board" && <Board />}
          {route.page === "timeline" && <Timeline />}
        </main>

        <Footer />
      </div>

      <button
        onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
        className={cn(
          "fixed bottom-5 end-5 z-[110] grid h-11 w-11 place-items-center rounded-2xl bg-ink-900 text-sun-300 shadow-lift transition-all duration-400 hover:-translate-y-1 hover:bg-brand-700",
          top ? "translate-y-0 opacity-100" : "pointer-events-none translate-y-6 opacity-0",
        )}
        aria-label="بازگشت به بالا"
      >
        <IconChevron className="h-5 w-5 rotate-180" />
      </button>

      <Toaster />
    </div>
  );
}

function Footer() {
  const { tasks, stats, navigate, now } = useStore();
  const subCount = tasks.reduce((a, t) => a + t.subtasks.length, 0);
  const imgCount = tasks.reduce((a, t) => a + t.subtasks.filter((s) => s.image).length, 0);
  const depCount = tasks.reduce((a, t) => a + t.dependencies.length, 0);

  return (
    <footer className="relative mt-6 overflow-hidden rounded-t-[28px] border-t border-ink-800/40 bg-ink-900 text-milk-100">
      <div className="pointer-events-none absolute inset-x-0 h-full opacity-20 dot-paper" />
      <div className="relative grid gap-8 px-5 py-8 lg:grid-cols-12 lg:px-8">
        <div className="lg:col-span-4">
          <p className="font-display text-[30px] leading-none text-milk-50">تَسک‌یار</p>
          <p className="mt-2 max-w-sm text-[11.5px] leading-6 text-milk-200/75">
            سامانه مدیریت پروژه نرم‌افزاری با سه نمای ادمین، داشبورد پایش و بورد کاربری. تعریف تسک، زیرگروه تصویری، وابستگی،
            زمان‌بندی و پایش لحظه‌ای SLA در یک جریان کاری واحد.
          </p>
          <p className="mt-3 inline-flex items-center gap-2 rounded-full border border-white/12 bg-white/8 px-3 py-1.5 text-[10.5px] font-bold text-sun-300">
            <IconSpark className="h-3.5 w-3.5" />
            {fmtDateLong(now)}
          </p>
        </div>

        <div className="lg:col-span-3">
          <p className="text-[11.5px] font-extrabold text-sun-300">دسترسی سریع</p>
          <ul className="mt-3 space-y-2 text-[11.5px] font-semibold text-milk-200/80">
            {[
              { l: "داشبورد مدیریتی", r: { page: "dashboard", tab: "overview" } as const },
              { l: "گزارش SLA", r: { page: "dashboard", tab: "sla" } as const },
              { l: "ایجاد تسک جدید", r: { page: "admin", newTask: true, status: "all" } as const },
              { l: "بورد کانبان من", r: { page: "board", tab: "kanban", mine: true } as const },
              { l: "دیوار یادداشت چسبنده", r: { page: "board", tab: "sticky" } as const },
              { l: "زمان‌بندی گانت", r: { page: "timeline" } as const },
            ].map((x) => (
              <li key={x.l}>
                <button onClick={() => navigate(x.r)} className="group inline-flex items-center gap-2 transition hover:text-sun-300">
                  <IconArrowLeft className="h-3.5 w-3.5 opacity-0 transition-all duration-300 group-hover:opacity-100" />
                  <span className="underline-sweep">{x.l}</span>
                </button>
              </li>
            ))}
          </ul>
        </div>

        <div className="lg:col-span-3">
          <p className="text-[11.5px] font-extrabold text-sun-300">راهنمای رنگ‌ها</p>
          <ul className="mt-3 space-y-1.5 text-[11px] font-semibold text-milk-200/80">
            {STATUS_ORDER.map((s) => (
              <li key={s} className="flex items-center gap-2">
                <i className="h-2.5 w-2.5 rounded-sm" style={{ background: STATUS_META[s].hex }} />
                {STATUS_META[s].label}
                <span className="ms-auto font-num tabular text-milk-100/60">{fa(stats.byStatus[s])}</span>
              </li>
            ))}
            <li className="mt-2 flex flex-wrap items-center gap-2 border-t border-white/10 pt-2">
              {(Object.keys(PRIORITY_META) as (keyof typeof PRIORITY_META)[]).map((p) => (
                <span key={p} className="inline-flex items-center gap-1">
                  <i className="h-2 w-2 rounded-full" style={{ background: PRIORITY_META[p].hex }} />
                  {PRIORITY_META[p].label}
                </span>
              ))}
            </li>
          </ul>
        </div>

        <div className="lg:col-span-2">
          <p className="text-[11.5px] font-extrabold text-sun-300">وضعیت سامانه</p>
          <ul className="mt-3 space-y-2 text-[11px] font-semibold text-milk-200/80">
            <li className="flex items-center justify-between gap-2">
              <span>تسک‌ها</span>
              <span className="font-num tabular text-milk-50">{fa(tasks.length)}</span>
            </li>
            <li className="flex items-center justify-between gap-2">
              <span>زیرگروه‌ها</span>
              <span className="font-num tabular text-milk-50">{fa(subCount)}</span>
            </li>
            <li className="flex items-center justify-between gap-2">
              <span>تصاویر پیوست</span>
              <span className="font-num tabular text-milk-50">{fa(imgCount)}</span>
            </li>
            <li className="flex items-center justify-between gap-2">
              <span>وابستگی‌ها</span>
              <span className="font-num tabular text-milk-50">{fa(depCount)}</span>
            </li>
            <li className="flex items-center justify-between gap-2">
              <span>پایبندی SLA</span>
              <span className="font-num tabular text-leaf-300">{fa(stats.compliance)}٪</span>
            </li>
            <li className="flex items-center justify-between gap-2">
              <span>پروژه‌ها</span>
              <span className="font-num tabular text-milk-50">{fa(PROJECTS.length)}</span>
            </li>
          </ul>
        </div>
      </div>

      <div className="relative flex flex-wrap items-center justify-between gap-3 border-t border-white/10 bg-ink-950/50 px-5 py-3.5 text-[10.5px] font-semibold text-milk-200/60 lg:px-8">
        <p>© ۱۴۰۴ تسک‌یار — نمونه نمایشی سامانه مدیریت پروژه نرم‌افزاری</p>
        <p className="flex flex-wrap items-center gap-3">
          {(Object.keys(SLA_TONE) as (keyof typeof SLA_TONE)[]).map((k) => (
            <span key={k} className="inline-flex items-center gap-1.5">
              <i className="h-2 w-2 rounded-full" style={{ background: SLA_TONE[k].hex }} />
              {SLA_TONE[k].label}
            </span>
          ))}
        </p>
      </div>
    </footer>
  );
}

export default function App() {
  return (
    <StoreProvider>
      <Shell />
    </StoreProvider>
  );
}
