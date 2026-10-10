import { useState } from "react";
import { cn } from "@/utils/cn";
import { useStore, type Route } from "@/lib/store";
import { fa, faNum } from "@/lib/fa";
import { CURRENT_USER, MEMBERS, PROJECTS, memberOf } from "@/lib/types";
import {
  IconBoard,
  IconChevron,
  IconChecklist,
  IconGantt,
  IconGauge,
  IconShield,
  IconUsers,
  IconFolder,
  IconLayers,
  IconPlus,
  IconFlame,
  IconLink,
  IconImage,
  IconAlert,
  IconSettings,
  IconLogout,
  IconX,
  IconSticky,
  IconTrend,
} from "./icons";
import { Avatar, Ring } from "./ui";

interface Leaf {
  label: string;
  icon: React.ReactNode;
  route: Partial<Route> & { page: Route["page"] };
  badge?: number;
  tone?: string;
}

interface Group {
  id: string;
  label: string;
  icon: React.ReactNode;
  items: Leaf[];
  kind?: "custom";
}

export default function Sidebar() {
  const { route, navigate, stats, sidebarOpen, setSidebarOpen, tasks } = useStore();
  const [open, setOpen] = useState<string[]>(["views", "tasks"]);

  const toggle = (id: string) =>
    setOpen((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));

  const mine = tasks.filter((t) => t.assignee === CURRENT_USER);
  const myDone = mine.filter((t) => t.status === "done").length;
  const sprintLeft = 3;

  const groups: Group[] = [
    {
      id: "views",
      label: "نماهای کاری",
      icon: <IconGauge className="h-[18px] w-[18px]" />,
      items: [
        { label: "داشبورد مدیریتی", icon: <IconTrend className="h-[17px] w-[17px]" />, route: { page: "dashboard", tab: "overview" } },
        { label: "گزارش SLA", icon: <IconShield className="h-[17px] w-[17px]" />, route: { page: "dashboard", tab: "sla" }, badge: stats.breached || undefined, tone: "#ef6211" },
        { label: "کارکرد تیم", icon: <IconUsers className="h-[17px] w-[17px]" />, route: { page: "dashboard", tab: "team" } },
        { label: "بورد من", icon: <IconBoard className="h-[17px] w-[17px]" />, route: { page: "board", tab: "kanban", mine: true }, badge: mine.filter((t) => t.status !== "done").length },
        { label: "یادداشت‌های چسبنده", icon: <IconSticky className="h-[17px] w-[17px]" />, route: { page: "board", tab: "sticky" } },
        { label: "زمان‌بندی گانت", icon: <IconGantt className="h-[17px] w-[17px]" />, route: { page: "timeline" } },
      ],
    },
    {
      id: "tasks",
      label: "مدیریت تسک‌ها",
      icon: <IconChecklist className="h-[18px] w-[18px]" />,
      items: [
        { label: "ایجاد تسک جدید", icon: <IconPlus className="h-[17px] w-[17px]" />, route: { page: "admin", newTask: true }, tone: "#f9bb1b" },
        { label: "همه تسک‌ها", icon: <IconLayers className="h-[17px] w-[17px]" />, route: { page: "admin", status: "all" }, badge: tasks.length },
        { label: "تسک‌های بحرانی", icon: <IconFlame className="h-[17px] w-[17px]" />, route: { page: "admin", priority: "critical", status: "all" }, badge: stats.byPriority.critical, tone: "#ef6211" },
        { label: "عقب‌افتاده از موعد", icon: <IconAlert className="h-[17px] w-[17px]" />, route: { page: "admin", status: "all", q: "overdue" }, badge: stats.overdue.length, tone: "#c94a08" },
        { label: "وابستگی‌ها", icon: <IconLink className="h-[17px] w-[17px]" />, route: { page: "admin", tab: "deps", status: "all" } },
        { label: "زیرگروه‌ها و تصاویر", icon: <IconImage className="h-[17px] w-[17px]" />, route: { page: "admin", tab: "subs", status: "all" } },
      ],
    },
    {
      id: "projects",
      label: "پروژه‌ها",
      icon: <IconFolder className="h-[18px] w-[18px]" />,
      items: PROJECTS.map((p) => ({
        label: p.name,
        icon: <i className="h-2.5 w-2.5 rounded-[4px]" style={{ background: p.hex }} />,
        route: { page: "board", tab: "kanban", project: p.name, mine: false },
        badge: tasks.filter((t) => t.project === p.name && t.status !== "done").length,
      })),
    },
    {
      id: "team",
      label: "اعضای تیم",
      icon: <IconUsers className="h-[18px] w-[18px]" />,
      items: MEMBERS.map((m) => ({
        label: m.name,
        icon: <i className="h-2.5 w-2.5 rounded-full" style={{ background: m.color }} />,
        route: { page: "dashboard", tab: "team", q: m.name },
        badge: tasks.filter((t) => t.assignee === m.name).length,
      })),
    },
  ];

  const isActive = (leaf: Leaf) =>
    route.page === leaf.route.page &&
    (leaf.route.tab === undefined || route.tab === leaf.route.tab) &&
    (leaf.route.project === undefined || route.project === leaf.route.project) &&
    (leaf.route.mine === undefined || Boolean(route.mine) === leaf.route.mine) &&
    (leaf.route.priority === undefined || route.priority === leaf.route.priority) &&
    (leaf.route.q === undefined || route.q === leaf.route.q);

  return (
    <>
      {/* mobile backdrop */}
      <div
        onClick={() => setSidebarOpen(false)}
        className={cn(
          "fixed inset-0 z-40 bg-ink-950/60 backdrop-blur-sm transition-opacity duration-300 xl:hidden",
          sidebarOpen ? "opacity-100" : "pointer-events-none opacity-0",
        )}
      />

      <aside
        className={cn(
          "fixed inset-y-0 start-0 z-50 flex w-[286px] flex-col overflow-hidden border-s-0 border-e border-ink-800/60 bg-ink-900 text-milk-100 transition-transform duration-400 ease-[cubic-bezier(.22,1,.36,1)] xl:translate-x-0",
          sidebarOpen ? "translate-x-0" : "translate-x-full rtl:translate-x-full",
        )}
        style={{
          boxShadow: "0 0 60px -20px rgba(5,14,28,.8)",
        }}
      >
        {/* backdrop texture */}
        <div className="pointer-events-none absolute inset-0 opacity-[0.16] dot-paper" />
        <div className="pointer-events-none absolute -top-24 -start-16 h-64 w-64 rounded-full bg-brand-600/30 blur-3xl" />
        <div className="pointer-events-none absolute bottom-10 -end-20 h-56 w-56 rounded-full bg-sun-400/15 blur-3xl" />

        {/* brand */}
        <div className="relative flex items-center gap-3 border-b border-white/10 px-5 py-5">
          <span className="relative grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 via-brand-600 to-ink-800 shadow-lift">
            <span className="absolute inset-0 rounded-2xl ring-1 ring-white/25" />
            <svg viewBox="0 0 24 24" className="h-6 w-6 text-sun-300" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round">
              <path d="M5 7.5h9M5 12h6M5 16.5h11" />
              <path d="m17.4 15.6 1.8 1.8 3-3.6" stroke="#63d696" />
            </svg>
          </span>
          <div className="flex-1">
            <p className="font-display text-[26px] leading-none text-milk-50">تَسک‌یار</p>
            <p className="mt-1 text-[10.5px] font-medium text-brand-200/80">سامانه مدیریت پروژه نرم‌افزاری</p>
          </div>
          <button
            onClick={() => setSidebarOpen(false)}
            className="grid h-8 w-8 place-items-center rounded-lg text-milk-200/70 transition hover:bg-white/10 hover:text-white xl:hidden"
            aria-label="بستن منو"
          >
            <IconX className="h-4 w-4" />
          </button>
        </div>

        {/* sprint widget */}
        <div className="relative mx-4 mt-4 overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.09] to-white/[0.02] p-3.5">
          <div className="flex items-center gap-3">
            <Ring value={(14 - sprintLeft) / 14 * 100} size={54} stroke={6} hex="#f9bb1b" track="rgba(255,255,255,.14)">
              <span className="font-num text-[13px] font-bold tabular text-milk-50">{fa(sprintLeft)}</span>
            </Ring>
            <div className="flex-1">
              <p className="text-[11px] font-bold text-sun-300">اسپرینت ۱۴</p>
              <p className="mt-0.5 text-[10.5px] text-milk-200/70">{fa(sprintLeft)} روز تا پایان باقی مانده</p>
              <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/12">
                <div className="h-full rounded-full bg-gradient-to-l from-leaf-400 to-brand-400" style={{ width: `${Math.round((stats.done / Math.max(1, tasks.length)) * 100)}%` }} />
              </div>
            </div>
          </div>
        </div>

        {/* nav */}
        <nav className="relative flex-1 space-y-1 overflow-y-auto px-3 py-4">
          {groups.map((g) => {
            const isOpen = open.includes(g.id);
            return (
              <div key={g.id} className="mb-1">
                <button
                  onClick={() => toggle(g.id)}
                  className={cn(
                    "group flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-[12.5px] font-extrabold transition-all duration-200",
                    isOpen ? "bg-white/[0.07] text-milk-50" : "text-milk-200/70 hover:bg-white/[0.05] hover:text-milk-100",
                  )}
                >
                  <span className={cn("transition-colors", isOpen ? "text-sun-300" : "text-brand-300 group-hover:text-sun-300")}>
                    {g.icon}
                  </span>
                  <span className="flex-1 text-start">{g.label}</span>
                  <span className="rounded-md bg-white/10 px-1.5 py-0.5 font-num text-[9.5px] tabular text-milk-200/80">
                    {fa(g.items.length)}
                  </span>
                  <IconChevron
                    className={cn("h-4 w-4 text-milk-200/60 transition-transform duration-300", isOpen && "-rotate-180 text-sun-300")}
                  />
                </button>

                <div className={cn("grid transition-all duration-400 ease-[cubic-bezier(.22,1,.36,1)]", isOpen ? "mt-1 grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0")}>
                  <div className="overflow-hidden">
                    <ul className="space-y-0.5 border-s-2 border-white/8 ps-2.5 ms-3">
                      {g.items.map((leaf) => {
                        const active = isActive(leaf);
                        return (
                          <li key={leaf.label}>
                            <button
                              onClick={() => navigate(leaf.route)}
                              className={cn(
                                "relative flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[12px] font-semibold transition-all duration-200",
                                active
                                  ? "bg-gradient-to-l from-brand-600/90 to-brand-500/70 text-white shadow-lift"
                                  : "text-milk-200/75 hover:translate-x-[-3px] hover:bg-white/[0.06] hover:text-milk-50",
                              )}
                            >
                              <span className={cn(active ? "text-sun-300" : "text-milk-200/60")}>{leaf.icon}</span>
                              <span className="flex-1 truncate text-start">{leaf.label}</span>
                              {leaf.badge !== undefined && leaf.badge !== 0 && (
                                <span
                                  className={cn(
                                    "font-num rounded-md px-1.5 py-0.5 text-[9.5px] font-bold tabular",
                                    active ? "bg-white/25 text-white" : "bg-white/10 text-milk-100",
                                  )}
                                  style={!active && leaf.tone ? { background: `${leaf.tone}2e`, color: leaf.tone } : undefined}
                                >
                                  {faNum(leaf.badge)}
                                </span>
                              )}
                              {active && <i className="absolute -start-2.5 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-sun-300" />}
                            </button>
                          </li>
                        );
                      })}
                    </ul>
                  </div>
                </div>
              </div>
            );
          })}
        </nav>

        {/* user */}
        <div className="relative border-t border-white/10 bg-ink-950/40 p-4">
          <div className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.05] p-3">
            <Avatar name={CURRENT_USER} size="md" online />
            <div className="min-w-0 flex-1">
              <p className="truncate text-[12.5px] font-extrabold text-milk-50">{CURRENT_USER}</p>
              <p className="truncate text-[10px] text-milk-200/65">{memberOf(CURRENT_USER).role}</p>
              <p className="mt-1 font-num text-[10px] tabular text-sun-300">
                {fa(myDone)} / {fa(mine.length)} تسک تحویل‌شده
              </p>
            </div>
            <div className="flex flex-col gap-1.5">
              <button
                onClick={() => navigate({ page: "admin", tab: "settings" })}
                className="grid h-7 w-7 place-items-center rounded-lg text-milk-200/60 transition hover:rotate-45 hover:bg-white/10 hover:text-sun-300"
                aria-label="تنظیمات"
              >
                <IconSettings className="h-4 w-4" />
              </button>
              <button
                onClick={() => navigate({ page: "dashboard", tab: "overview" })}
                className="grid h-7 w-7 place-items-center rounded-lg text-milk-200/60 transition hover:bg-white/10 hover:text-ember-400"
                aria-label="خروج"
              >
                <IconLogout className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </aside>
    </>
  );
}
