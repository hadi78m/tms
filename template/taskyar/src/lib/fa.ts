const FA_DIGITS = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];

/** converts latin digits of any value to persian digits */
export const fa = (value: string | number): string =>
  String(value).replace(/[0-9]/g, (d) => FA_DIGITS[Number(d)]);

/** 1234 -> ۱٬۲۳۴ */
export const faNum = (n: number, digits = 0): string =>
  fa(
    Number(n).toLocaleString("en-US", {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
    }),
  );

/** 0.62 -> ۶۲٪ */
export const faPercent = (ratio: number): string => fa(Math.round(ratio * 100)) + "٪";

const dateFmt = new Intl.DateTimeFormat("fa-IR", {
  year: "numeric",
  month: "short",
  day: "numeric",
});
const dateFmtLong = new Intl.DateTimeFormat("fa-IR", {
  weekday: "long",
  year: "numeric",
  month: "long",
  day: "numeric",
});
const timeFmt = new Intl.DateTimeFormat("fa-IR", { hour: "2-digit", minute: "2-digit" });

export const fmtDate = (iso: string): string => dateFmt.format(new Date(iso + "T12:00:00"));
export const fmtDateLong = (d: Date = new Date()): string => dateFmtLong.format(d);
export const fmtTime = (d: Date = new Date()): string => timeFmt.format(d);
export const fmtShort = (iso: string): string =>
  fa(new Date(iso + "T12:00:00").toLocaleDateString("fa-IR", { month: "short", day: "numeric" }));

/** today at noon, as anchor */
export const todayISO = (): string => {
  const d = new Date();
  d.setHours(12, 0, 0, 0);
  return d.toISOString().slice(0, 10);
};

export const isoFrom = (offsetDays: number): string => {
  const d = new Date();
  d.setHours(12, 0, 0, 0);
  d.setDate(d.getDate() + offsetDays);
  return d.toISOString().slice(0, 10);
};

export const daysBetween = (from: string, to: string): number =>
  Math.round(
    (new Date(to + "T12:00:00").getTime() - new Date(from + "T12:00:00").getTime()) / 86400000,
  );

/** remaining time until due date (in hours, may be negative) */
export const hoursLeft = (due: string, now: Date = new Date()): number =>
  (new Date(due + "T18:00:00").getTime() - now.getTime()) / 3600000;

export type Leftover = {
  overdue: boolean;
  days: number;
  hours: number;
  label: string;
  tone: "danger" | "warn" | "ok";
};

export const leftover = (due: string, done: boolean, now: Date = new Date()): Leftover => {
  const h = hoursLeft(due, now);
  const overdue = !done && h < 0;
  const abs = Math.abs(h);
  const days = Math.floor(abs / 24);
  const hours = Math.round(abs % 24);
  const label = done
    ? "تحویل شد"
    : abs < 1
      ? `${fa(Math.max(1, Math.round(abs * 60)))} دقیقه`
      : days > 0
        ? `${fa(days)} روز و ${fa(hours)} ساعت`
        : `${fa(hours)} ساعت`;
  const tone: Leftover["tone"] = done ? "ok" : overdue ? "danger" : abs < 48 ? "warn" : "ok";
  return { overdue, days, hours, label, tone };
};

export const faDuration = (hours: number): string => {
  if (hours <= 0) return "۰ ساعت";
  const d = Math.floor(hours / 24);
  const h = Math.round(hours % 24);
  if (d === 0) return `${fa(h)} ساعت`;
  return h === 0 ? `${fa(d)} روز` : `${fa(d)} روز ${fa(h)} ساعت`;
};

export const clamp = (n: number, min = 0, max = 100): number => Math.min(max, Math.max(min, n));

export const initials = (name: string): string =>
  name
    .split(" ")
    .slice(0, 2)
    .map((p) => p[0])
    .join("");

export const uid = (prefix = "id"): string =>
  `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
