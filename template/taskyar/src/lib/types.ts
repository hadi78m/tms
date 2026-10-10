export type Status = "todo" | "doing" | "review" | "blocked" | "done";
export type Priority = "low" | "medium" | "high" | "critical";
export type Page = "dashboard" | "admin" | "board" | "timeline";

export interface Meta {
  label: string;
  hex: string;
  chip: string;
  solid: string;
  soft: string;
}

export const STATUS_META: Record<Status, Meta> = {
  todo: {
    label: "برنامه‌ریزی",
    hex: "#527aae",
    chip: "bg-ink-50 text-ink-700 ring-1 ring-ink-200",
    solid: "bg-ink-500",
    soft: "bg-ink-100",
  },
  doing: {
    label: "در حال انجام",
    hex: "#2f7af0",
    chip: "bg-brand-50 text-brand-700 ring-1 ring-brand-200",
    solid: "bg-brand-500",
    soft: "bg-brand-100",
  },
  review: {
    label: "بازبینی",
    hex: "#f9bb1b",
    chip: "bg-sun-100 text-sun-600 ring-1 ring-sun-300",
    solid: "bg-sun-400",
    soft: "bg-sun-100",
  },
  blocked: {
    label: "مسدود",
    hex: "#ef6211",
    chip: "bg-ember-100 text-ember-600 ring-1 ring-ember-300",
    solid: "bg-ember-500",
    soft: "bg-ember-100",
  },
  done: {
    label: "انجام‌شده",
    hex: "#16a157",
    chip: "bg-leaf-100 text-leaf-700 ring-1 ring-leaf-300",
    solid: "bg-leaf-500",
    soft: "bg-leaf-100",
  },
};

export const STATUS_ORDER: Status[] = ["todo", "doing", "review", "blocked", "done"];

export const PRIORITY_META: Record<Priority, Meta & { weight: number }> = {
  low: {
    label: "کم",
    hex: "#63d696",
    weight: 1,
    chip: "bg-leaf-100 text-leaf-700 ring-1 ring-leaf-300",
    solid: "bg-leaf-400",
    soft: "bg-leaf-100",
  },
  medium: {
    label: "متوسط",
    hex: "#2f7af0",
    weight: 2,
    chip: "bg-brand-50 text-brand-700 ring-1 ring-brand-200",
    solid: "bg-brand-500",
    soft: "bg-brand-100",
  },
  high: {
    label: "زیاد",
    hex: "#f9bb1b",
    weight: 3,
    chip: "bg-sun-100 text-sun-600 ring-1 ring-sun-300",
    solid: "bg-sun-400",
    soft: "bg-sun-100",
  },
  critical: {
    label: "بحرانی",
    hex: "#ef6211",
    weight: 4,
    chip: "bg-ember-100 text-ember-600 ring-1 ring-ember-300",
    solid: "bg-ember-500",
    soft: "bg-ember-100",
  },
};

export const PRIORITY_ORDER: Priority[] = ["critical", "high", "medium", "low"];

export interface SubTask {
  id: string;
  title: string;
  done: boolean;
  image?: string;
  note?: string;
}

export interface Attachment {
  id: string;
  name: string;
  url: string;
  kind: "image" | "file";
  size: string;
}

export interface Task {
  id: string;
  code: string;
  title: string;
  description: string;
  project: string;
  assignee: string;
  reporter: string;
  status: Status;
  priority: Priority;
  start: string;
  due: string;
  estimate: number;
  spent: number;
  progress: number;
  slaHours: number;
  tags: string[];
  subtasks: SubTask[];
  dependencies: string[];
  attachments: Attachment[];
  createdAt: string;
  sticky: { x: number; y: number; rot: number };
}

export interface Member {
  name: string;
  role: string;
  color: string;
  ring: string;
}

export interface Project {
  name: string;
  code: string;
  hex: string;
  client: string;
}

export interface ActivityItem {
  id: string;
  who: string;
  action: string;
  task: string;
  when: string;
  tone: Status | "note";
}

export const PROJECTS: Project[] = [
  { name: "پلتفرم پرداخت سیمرغ", code: "SMQ", hex: "#1a5cd6", client: "هلدینگ سیمرغ" },
  { name: "اپلیکیشن موبایل مشتریان", code: "APP", hex: "#ef6211", client: "فروشگاه رادا" },
  { name: "پنل مدیریتی داخلی", code: "ADM", hex: "#16a157", client: "داخلی" },
  { name: "زیرساخت و استقرار", code: "INF", hex: "#e39c07", client: "داخلی" },
];

export const MEMBERS: Member[] = [
  { name: "سارا محمدی", role: "توسعه‌دهنده ارشد فرانت‌اند", color: "#1a5cd6", ring: "ring-brand-200" },
  { name: "آرش کرمی", role: "توسعه‌دهنده بک‌اند", color: "#ef6211", ring: "ring-ember-200" },
  { name: "رها احمدی", role: "طراح محصول / UX", color: "#16a157", ring: "ring-leaf-200" },
  { name: "میلاد نوری", role: "مهندس DevOps", color: "#e39c07", ring: "ring-sun-200" },
  { name: "سپیده موسوی", role: "کارشناس تضمین کیفیت", color: "#527aae", ring: "ring-ink-200" },
  { name: "یوسف صادقی", role: "مدیر پروژه", color: "#c94a08", ring: "ring-ember-200" },
];

export const CURRENT_USER = "سارا محمدی";

export const memberOf = (name: string): Member =>
  MEMBERS.find((m) => m.name === name) ?? MEMBERS[0];

export const projectOf = (name: string): Project =>
  PROJECTS.find((p) => p.name === name) ?? PROJECTS[0];

/** SLA health derived from elapsed time vs progress */
export type SlaTone = "ok" | "warn" | "risk" | "breach";

export const slaOf = (task: Task, now: Date = new Date()) => {
  const total = Math.max(
    1,
    (new Date(task.due + "T18:00:00").getTime() - new Date(task.start + "T09:00:00").getTime()) /
      3600000,
  );
  const elapsed = (now.getTime() - new Date(task.start + "T09:00:00").getTime()) / 3600000;
  const ratio = Math.max(0, Math.min(1, elapsed / total));
  const expected = Math.round(ratio * 100);
  const gap = task.progress - expected;
  const done = task.status === "done";
  const left = (new Date(task.due + "T18:00:00").getTime() - now.getTime()) / 3600000;

  let tone: SlaTone = "ok";
  if (!done && left < 0) tone = "breach";
  else if (!done && (task.status === "blocked" || gap < -25)) tone = "risk";
  else if (!done && (left < 48 || gap < -10)) tone = "warn";
  else if (done) tone = "ok";

  const compliance = done ? 100 : Math.max(0, Math.min(100, 100 - Math.max(0, -gap)));
  return { expected, gap, tone, left, compliance: Math.round(compliance), ratio };
};

export const SLA_TONE: Record<SlaTone, { label: string; hex: string; chip: string }> = {
  ok: { label: "مطابق برنامه", hex: "#16a157", chip: "bg-leaf-100 text-leaf-700 ring-leaf-300" },
  warn: { label: "نیازمند توجه", hex: "#f9bb1b", chip: "bg-sun-100 text-sun-600 ring-sun-300" },
  risk: { label: "در خطر", hex: "#ef6211", chip: "bg-ember-100 text-ember-600 ring-ember-300" },
  breach: { label: "نقض SLA", hex: "#c94a08", chip: "bg-ember-200 text-ember-600 ring-ember-400" },
};
