import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import {
  ChevronLeft,
  LayoutDashboard,
  ListChecks,
  KanbanSquare,
  BarChart3,
  Settings2,
  Users,
  Bell,
  X,
} from "lucide-react";

export type PageKey = "dashboard" | "admin" | "board";

interface Group {
  key: string;
  label: string;
  en: string;
  icon: typeof LayoutDashboard;
  items: { label: string; hint: string; page: PageKey; active?: string }[];
}

const NAV: Group[] = [
  {
    key: "g-dash",
    label: "داشبورد",
    en: "OVERVIEW",
    icon: LayoutDashboard,
    items: [
      { label: "نمای کلی پروژه", hint: "۱۴", page: "dashboard", active: "overview" },
      { label: "روند هفتگی", hint: "۶", page: "dashboard", active: "weekly" },
      { label: "SLA و عملکرد تیم", hint: "۵", page: "dashboard", active: "sla" },
    ],
  },
  {
    key: "g-admin",
    label: "مدیریت تسک‌ها",
    en: "ADMIN",
    icon: ListChecks,
    items: [
      { label: "ایجاد تسک جدید", hint: "+", page: "admin", active: "new" },
      { label: "بک‌لاگ پروژه", hint: "۱۴", page: "admin", active: "backlog" },
      { label: "وابستگی‌ها و زیرگروه‌ها", hint: "۹", page: "admin", active: "deps" },
    ],
  },
  {
    key: "g-board",
    label: "تسک‌های من",
    en: "MY WORK",
    icon: KanbanSquare,
    items: [
      { label: "بورد کاری", hint: "۱۴", page: "board", active: "kanban" },
      { label: "زمان‌بندی و مهلت‌ها", hint: "۳", page: "board", active: "timeline" },
    ],
  },
  {
    key: "g-report",
    label: "گزارش‌ها",
    en: "REPORTS",
    icon: BarChart3,
    items: [
      { label: "گزارش هفتگی اسپرینت", hint: "PDF", page: "dashboard", active: "weekly" },
      { label: "تحلیل زمان صرف‌شده", hint: "۴۰h", page: "dashboard", active: "sla" },
    ],
  },
  {
    key: "g-cfg",
    label: "تنظیمات",
    en: "SETTINGS",
    icon: Settings2,
    items: [
      { label: "اعضا و سطح دسترسی", hint: "۵", page: "admin", active: "members" },
      { label: "قواعد اعلان و SLA", hint: "۳", page: "admin", active: "rules" },
      { label: "یکپارچه‌سازی‌ها", hint: "۷", page: "admin", active: "integrations" },
    ],
  },
];

function Mark({ className = "" }: { className?: string }) {
  return (
    <svg viewBox="0 0 48 48" className={className} aria-hidden="true">
      <rect x="2" y="2" width="44" height="44" rx="12" fill="#F2B705" />
      <path d="M11 33.5V14.5h6.2v12.2h8.4V14.5H32v19h-6.4v-6.6h-8.4v6.6H11Z" fill="#0B2545" />
      <circle cx="35.5" cy="14.5" r="4.2" fill="#E4572E" />
    </svg>
  );
}

export default function Sidebar({
  page,
  item,
  onNavigate,
  mobileOpen,
  onClose,
  doneRatio,
}: {
  page: PageKey;
  item: string;
  onNavigate: (p: PageKey, item: string) => void;
  mobileOpen: boolean;
  onClose: () => void;
  doneRatio: number;
}) {
  const [open, setOpen] = useState<Record<string, boolean>>({
    "g-dash": true,
    "g-admin": true,
    "g-board": true,
  });

  const toggle = (k: string) => setOpen((o) => ({ ...o, [k]: !o[k] }));

  const body = (
    <div className="flex h-full flex-col bg-ink-950 text-cream-100 rail-shadow">
      {/* برند */}
      <div className="flex items-center gap-3 border-b border-white/10 px-5 py-5">
        <Mark className="h-10 w-10 shrink-0" />
        <div className="min-w-0">
          <div className="truncate text-lg font-black leading-none">رهگستر</div>
          <div className="micro mt-1.5 text-gold-500">TASK CONTROL</div>
        </div>
        <button
          onClick={onClose}
          className="ms-auto rounded-lg p-1.5 text-cream-100/70 hover:bg-white/10 lg:hidden"
          aria-label="بستن منو"
        >
          <X className="h-5 w-5" />
        </button>
      </div>

      {/* پروژه فعال */}
      <div className="border-b border-white/10 px-5 py-4">
        <div className="micro text-ink-300">ACTIVE PROJECT</div>
        <div className="mt-2 flex items-center gap-2">
          <span className="h-2 w-2 rounded-full bg-jade-400 shadow-[0_0_10px_#2fb673]" />
          <span className="truncate text-sm font-bold">بازارک ۳٫۲</span>
        </div>
        <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
          <div
            className="h-full rounded-full bg-gradient-to-l from-jade-400 to-gold-500 transition-[width] duration-700"
            style={{ width: `${Math.round(doneRatio * 100)}%` }}
          />
        </div>
        <div className="mt-1.5 flex justify-between text-[11px] text-ink-300">
          <span>پیشرفت اسپرینت ۱۴</span>
          <span className="tnum text-gold-500">{Math.round(doneRatio * 100)}%</span>
        </div>
      </div>

      {/* منوها */}
      <nav className="flex-1 overflow-y-auto py-3">
        {NAV.map((g) => {
          const isOpen = open[g.key] ?? false;
          const hasActive = g.items.some((i) => i.page === page && i.active === item);
          return (
            <div key={g.key} className="px-3 py-1">
              <button
                onClick={() => toggle(g.key)}
                aria-expanded={isOpen}
                className={`group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-right transition-colors ${
                  hasActive ? "bg-white/[0.07]" : "hover:bg-white/[0.05]"
                }`}
              >
                <g.icon
                  className={`shrink-0 ${hasActive ? "text-gold-500" : "text-ink-300"}`}
                  size={18}
                />
                <span className="flex-1 text-[13px] font-bold">{g.label}</span>
                <span className="micro text-ink-300/60 group-hover:text-ink-300">{g.en}</span>
                <ChevronLeft
                  size={14}
                  className={`shrink-0 text-ink-300 transition-transform duration-300 ${
                    isOpen ? "-rotate-90" : ""
                  }`}
                />
              </button>

              <AnimatePresence initial={false}>
                {isOpen && (
                  <motion.ul
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: "auto", opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
                    className="overflow-hidden"
                  >
                    {g.items.map((it) => {
                      const active = it.page === page && it.active === item;
                      return (
                        <li key={it.label} className="relative">
                          {active && (
                            <motion.span
                              layoutId="nav-marker"
                              className="absolute inset-y-1 right-0 w-[3px] rounded-full bg-gold-500"
                            />
                          )}
                          <button
                            onClick={() => onNavigate(it.page, it.active ?? "overview")}
                            className={`flex w-full items-center gap-2 rounded-lg py-2 pe-3 ps-6 text-right text-[13px] transition-all ${
                              active
                                ? "bg-ink-800 font-bold text-cream-50"
                                : "text-cream-100/65 hover:bg-white/5 hover:text-cream-50"
                            }`}
                          >
                            <span
                              className={`h-1.5 w-1.5 rounded-full ${
                                active ? "bg-gold-500" : "bg-ink-300/40"
                              }`}
                            />
                            <span className="flex-1 truncate">{it.label}</span>
                            <span className="tnum text-[10px] text-ink-300/70">{it.hint}</span>
                          </button>
                        </li>
                      );
                    })}
                  </motion.ul>
                )}
              </AnimatePresence>
            </div>
          );
        })}
      </nav>

      {/* کف ریل */}
      <div className="border-t border-white/10 p-4">
        <div className="flex items-center gap-2 rounded-xl bg-white/[0.06] p-3">
          <div className="grid h-9 w-9 place-items-center rounded-lg bg-gold-500 text-sm font-black text-ink-950">
            س‌م
          </div>
          <div className="min-w-0 flex-1">
            <div className="truncate text-xs font-bold">سارا محمدی</div>
            <div className="truncate text-[11px] text-ink-300">سرپرست محصول</div>
          </div>
          <div className="flex flex-col gap-1 text-ink-300">
            <Bell size={14} className="text-gold-500" />
            <Users size={14} />
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <>
      <aside className="sticky top-0 hidden h-screen w-[17rem] shrink-0 lg:block">{body}</aside>
      <AnimatePresence>
        {mobileOpen && (
          <div className="fixed inset-0 z-50 lg:hidden">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={onClose}
              className="absolute inset-0 bg-ink-950/60 backdrop-blur-sm"
            />
            <motion.aside
              initial={{ x: "100%" }}
              animate={{ x: 0 }}
              exit={{ x: "100%" }}
              transition={{ type: "spring", stiffness: 320, damping: 34 }}
              className="absolute inset-y-0 right-0 w-[17rem] max-w-[85vw]"
            >
              {body}
            </motion.aside>
          </div>
        )}
      </AnimatePresence>
    </>
  );
}
