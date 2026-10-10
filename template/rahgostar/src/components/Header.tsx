import { type ReactNode, useEffect, useState } from "react";
import { motion } from "framer-motion";
import { Menu, Search, Bell, ChevronLeft, Plus } from "lucide-react";
import { IMG } from "../img";

export default function Header({
  title,
  kicker,
  subtitle,
  breadcrumb,
  onMenu,
  action,
}: {
  title: string;
  kicker: string;
  subtitle: string;
  breadcrumb: string[];
  onMenu: () => void;
  action?: ReactNode;
}) {
  const [time, setTime] = useState(() => new Date());
  useEffect(() => {
    const t = setInterval(() => setTime(new Date()), 1000);
    return () => clearInterval(t);
  }, []);

  const hhmmss = time.toLocaleTimeString("en-GB", { hour12: false });

  return (
    <header>
      {/* بنر عکس واقعی */}
      <div className="relative h-[13.5rem] overflow-hidden md:h-[16rem]">
        <img
          src={IMG.header}
          alt="میز کار تیم نرم‌افزار با کارت‌های وظیفه"
          className="absolute inset-0 h-full w-full object-cover object-center"
          onError={(e) => {
            (e.currentTarget as HTMLImageElement).style.display = "none";
          }}
        />
        <div className="absolute inset-0 bg-gradient-to-l from-ink-950/95 via-ink-900/85 to-ink-900/40" />
        <div className="absolute inset-0 bg-[radial-gradient(120%_80%_at_100%_0%,rgba(242,183,5,0.22),transparent_55%)]" />

        <div className="relative flex h-full flex-col justify-between px-5 py-4 md:px-8 md:py-6">
          <div className="flex items-center gap-3">
            <button
              onClick={onMenu}
              className="rounded-lg border border-white/25 bg-white/10 p-2 text-cream-50 backdrop-blur transition hover:bg-white/20 lg:hidden"
              aria-label="باز کردن منو"
            >
              <Menu size={18} />
            </button>
            <nav className="flex items-center gap-1.5 text-[11px] text-ink-300 md:text-xs">
              <span className="hidden sm:inline">رهگستر</span>
              {breadcrumb.map((b, i) => (
                <span
                  key={b}
                  className={`gap-1.5 ${i === breadcrumb.length - 1 ? "flex" : "hidden sm:flex"}`}
                >
                  <ChevronLeft size={12} className="hidden sm:inline" />
                  <span className={i === breadcrumb.length - 1 ? "text-gold-500" : ""}>{b}</span>
                </span>
              ))}
            </nav>
            <div className="ms-auto flex items-center gap-2">
              <span className="hidden items-center gap-2 rounded-full border border-white/20 bg-ink-950/50 px-3 py-1.5 backdrop-blur sm:flex">
                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-jade-400" />
                <span className="micro text-cream-100">LIVE · اسپرینت ۱۴</span>
              </span>
              <span className="tnum rounded-full border border-white/20 bg-ink-950/50 px-3 py-1.5 text-xs text-gold-300 backdrop-blur">
                {hhmmss}
              </span>
            </div>
          </div>

          <div className="flex items-end justify-between gap-4">
            <div className="min-w-0">
              <motion.div
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
              >
                <div className="micro mb-2 inline-block border-e-2 border-gold-500 ps-2 text-gold-300">
                  {kicker}
                </div>
                <h1 className="truncate text-2xl font-black leading-tight text-cream-50 sm:text-3xl md:text-[2.6rem]">
                  {title}
                </h1>
                <p className="mt-1.5 line-clamp-2 max-w-2xl text-xs text-ink-100/85 sm:text-sm">
                  {subtitle}
                </p>
              </motion.div>
            </div>
            <div className="hidden shrink-0 pb-1 md:block">{action}</div>
          </div>
        </div>
        <div className="absolute inset-x-0 bottom-0 h-[3px] bg-gradient-to-l from-gold-500 via-flame-500 to-jade-400" />
      </div>

      {/* نوار ابزار چسبان */}
      <div className="sticky top-0 z-30 border-b border-line bg-cream-50/95 backdrop-blur-md">
        <div className="flex h-14 items-center gap-3 px-4 md:px-8">
          <button
            onClick={onMenu}
            className="rounded-lg border border-line p-2 text-ink-700 hover:bg-cream-100 lg:hidden"
            aria-label="منو"
          >
            <Menu size={18} />
          </button>
          <label className="group relative flex h-9 flex-1 items-center max-w-md">
            <Search
              size={15}
              className="pointer-events-none absolute right-3 text-sand transition group-focus-within:text-ink-500"
            />
            <input
              type="search"
              placeholder="جست‌وجو در تسک‌ها، اعضا، کدها…"
              className="h-full w-full rounded-lg border border-line bg-cream-100 pe-9 ps-3 text-[13px] text-ink-900 placeholder:text-sand focus:border-ink-500 focus:bg-cream-50 focus:outline-none"
            />
          </label>

          <div className="ms-auto flex items-center gap-2">
            <div className="hidden items-center gap-4 rounded-lg border border-line bg-cream-100 px-3 py-1.5 md:flex">
              <span className="micro text-sand">SLA TODAY</span>
              <span className="tnum text-xs font-semibold text-jade-600">96%</span>
              <span className="h-4 w-px bg-line" />
              <span className="micro text-sand">OPEN</span>
              <span className="tnum text-xs font-semibold text-flame-500">3</span>
            </div>
            <button
              className="relative rounded-lg border border-line p-2 text-ink-700 transition hover:bg-cream-100"
              aria-label="اعلان‌ها"
            >
              <Bell size={17} />
              <span className="absolute -top-1 -left-1 grid h-4 w-4 place-items-center rounded-full bg-flame-500 text-[9px] font-bold text-white">
                4
              </span>
            </button>
            {action && <div className="md:hidden">{action}</div>}
          </div>
        </div>
      </div>
    </header>
  );
}

export function PrimaryAction({ label, onClick }: { label: string; onClick?: () => void }) {
  return (
    <button
      onClick={onClick}
      className="flex items-center gap-2 rounded-lg bg-gold-500 px-4 py-2.5 text-[13px] font-black text-ink-950 shadow-[0_8px_20px_-10px_rgba(242,183,5,0.9)] transition hover:-translate-y-0.5 hover:bg-gold-300 active:translate-y-0"
    >
      <Plus size={16} strokeWidth={3} />
      {label}
    </button>
  );
}
