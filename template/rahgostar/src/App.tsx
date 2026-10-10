import { useEffect, useMemo, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import Sidebar, { type PageKey } from "./components/Sidebar";
import Header, { PrimaryAction } from "./components/Header";
import Dashboard from "./pages/Dashboard";
import Admin from "./pages/Admin";
import Board, { TaskDrawer } from "./pages/Board";
import { seedTasks, type Task } from "./data";

const HEAD: Record<
  PageKey,
  { title: string; kicker: string; subtitle: string; breadcrumb: string[]; action: string }
> = {
  dashboard: {
    title: "داشبورد کنترل پروژه",
    kicker: "MISSION CONTROL",
    subtitle:
      "نمای زندهٔ تسک‌ها، نسبت انجام‌شده و نشده، پایبندی SLA و روند اسپرینت ۱۴ پروژهٔ بازارک.",
    breadcrumb: ["پروژه‌ها", "بازارک ۳٫۲", "داشبورد"],
    action: "ایجاد تسک جدید",
  },
  admin: {
    title: "مدیریت و ایجاد تسک‌ها",
    kicker: "ADMIN CONSOLE",
    subtitle:
      "تعریف تسک با زیرگروه و عکس پیوست، تعیین وابستگی‌ها، زمان‌بندی و مهلت SLA و مدیریت کل بک‌لاگ.",
    breadcrumb: ["پروژه‌ها", "بازارک ۳٫۲", "مدیریت تسک‌ها"],
    action: "ثبت سریع",
  },
  board: {
    title: "بورد کاری من",
    kicker: "MY BOARD",
    subtitle:
      "کارت‌ها را مانند یک بورد بکشید و رها کنید، وضعیت و توضیحات را ویرایش کنید و زمان باقی‌مانده تا مهلت را زنده ببینید.",
    breadcrumb: ["پروژه‌ها", "بازارک ۳٫۲", "بورد کاری"],
    action: "تسک جدید",
  },
};

export default function App() {
  const [page, setPage] = useState<PageKey>("dashboard");
  const [item, setItem] = useState("overview");
  const [tasks, setTasks] = useState<Task[]>(seedTasks);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [openId, setOpenId] = useState<string | null>(null);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, [page]);

  const go = (p: PageKey, i: string) => {
    setPage(p);
    setItem(i);
    setMobileOpen(false);
  };

  const patch = (id: string, p: Partial<Task>) =>
    setTasks((ts) => ts.map((t) => (t.id === id ? { ...t, ...p } : t)));

  const add = (t: Task) => setTasks((ts) => [t, ...ts]);
  const remove = (id: string) => setTasks((ts) => ts.filter((t) => t.id !== id));

  const doneRatio = useMemo(
    () => (tasks.length ? tasks.filter((t) => t.status === "done").length / tasks.length : 0),
    [tasks],
  );

  const openTask = tasks.find((t) => t.id === openId) ?? null;
  const h = HEAD[page];

  const action =
    page === "admin" ? (
      <PrimaryAction
        label="ثبت سریع"
        onClick={() => document.getElementById("new-task-form")?.scrollIntoView({ behavior: "smooth" })}
      />
    ) : (
      <PrimaryAction label={page === "board" ? "تسک جدید" : "ایجاد تسک جدید"} onClick={() => go("admin", "new")} />
    );

  return (
    <div className="flex min-h-screen bg-cream-100">
      <Sidebar
        page={page}
        item={item}
        onNavigate={go}
        mobileOpen={mobileOpen}
        onClose={() => setMobileOpen(false)}
        doneRatio={doneRatio}
      />

      <div className="flex min-w-0 flex-1 flex-col">
        <Header
          title={h.title}
          kicker={h.kicker}
          subtitle={h.subtitle}
          breadcrumb={h.breadcrumb}
          onMenu={() => setMobileOpen(true)}
          action={action}
        />

        <main className="paper grain relative flex-1 px-4 py-6 md:px-8">
          <AnimatePresence mode="wait">
            <motion.div
              key={page}
              initial={{ opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1] }}
            >
              {page === "dashboard" && (
                <Dashboard tasks={tasks} onOpenTask={() => go("board", "kanban")} />
              )}
              {page === "admin" && (
                <Admin tasks={tasks} onAdd={add} onPatch={patch} onDelete={remove} />
              )}
              {page === "board" && (
                <Board tasks={tasks} onPatch={patch} onOpenTask={(id) => setOpenId(id)} />
              )}
            </motion.div>
          </AnimatePresence>
        </main>

        <footer className="border-t border-line bg-cream-50 px-4 py-5 md:px-8">
          <div className="flex flex-wrap items-center justify-between gap-3 text-[11.5px] text-sand">
            <span>
              <b className="text-ink-800">رهگستر</b> — سامانهٔ مدیریت تسک و SLA پروژهٔ بازارک ۳٫۲ ·
              اسپرینت ۱۴
            </span>
            <span className="tnum flex items-center gap-4">
              <span>API v2.4</span>
              <span className="flex items-center gap-1.5">
                <span className="h-1.5 w-1.5 rounded-full bg-jade-600" /> همهٔ سرویس‌ها فعال‌اند
              </span>
            </span>
          </div>
        </footer>
      </div>

      <TaskDrawer task={openTask} onClose={() => setOpenId(null)} onPatch={patch} />
    </div>
  );
}
