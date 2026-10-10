import { IMG } from "./img";

export type Status = "todo" | "doing" | "review" | "done";
export type Priority = "high" | "med" | "low";

export interface Attachment {
  name: string;
  src: string;
}

export interface Task {
  id: string;
  title: string;
  description: string;
  status: Status;
  priority: Priority;
  assignee: string;
  group: string;
  deps: string[];
  attachments: Attachment[];
  start: number;
  due: number;
  estimate: number;
  checklist: { label: string; done: boolean }[];
}

const H = 3600_000;
const now = Date.now();

export const STATUS: Record<
  Status,
  { label: string; en: string; dot: string; chip: string; bar: string }
> = {
  todo: {
    label: "کار نشده",
    en: "TO DO",
    dot: "bg-sand",
    chip: "bg-cream-200 text-sand-dark border-line",
    bar: "bg-sand",
  },
  doing: {
    label: "در حال انجام",
    en: "IN PROGRESS",
    dot: "bg-ink-500",
    chip: "bg-ink-100 text-ink-700 border-ink-300",
    bar: "bg-ink-500",
  },
  review: {
    label: "در بازبینی",
    en: "IN REVIEW",
    dot: "bg-gold-500",
    chip: "bg-gold-300/35 text-ink-900 border-gold-500",
    bar: "bg-gold-500",
  },
  done: {
    label: "انجام شده",
    en: "DONE",
    dot: "bg-jade-600",
    chip: "bg-jade-100 text-jade-600 border-jade-400",
    bar: "bg-jade-600",
  },
};

export const PRIORITY: Record<Priority, { label: string; color: string; text: string }> = {
  high: { label: "بحرانی", color: "bg-flame-500", text: "text-flame-500" },
  med: { label: "متوسط", color: "bg-gold-500", text: "text-gold-500" },
  low: { label: "کم", color: "bg-jade-400", text: "text-jade-600" },
};

export const GROUPS = [
  "بک‌اند",
  "فرانت‌اند",
  "طراحی رابط کاربری",
  "تضمین کیفیت",
  "زیرساخت و استقرار",
];

export const MEMBERS = [
  { name: "سارا محمدی", role: "فرانت‌اند", sla: 96, load: 72 },
  { name: "امیرحسین رضایی", role: "بک‌اند", sla: 88, load: 91 },
  { name: "نگار کاظمی", role: "طراحی رابط کاربری", sla: 100, load: 54 },
  { name: "پویا صادقی", role: "تضمین کیفیت", sla: 74, load: 83 },
  { name: "مرضیا نوروزی", role: "زیرساخت و استقرار", sla: 92, load: 46 },
];

export const seedTasks: Task[] = [
  {
    id: "TSK-1042",
    title: "بازطراحی فلوی ثبت سفارش در سه گام",
    description:
      "گام‌های «سبد خرید»، «اطلاعات ارسال» و «پرداخت» باید در یک صفحه ادغام شوند. خروجی باید با کامپوننت‌های فرم موجود هم‌خوان باشد و خطای اعتبارسنجی سمت سرور نمایش داده شود.",
    status: "doing",
    priority: "high",
    assignee: "سارا محمدی",
    group: "فرانت‌اند",
    deps: ["TSK-1038"],
    attachments: [{ name: "wireframe-checkout.jpg", src: IMG.wireframe }],
    start: now - 26 * H,
    due: now + 3 * H + 42 * 60000,
    estimate: 14,
    checklist: [
      { label: "طراحی حالت خالی سبد خرید", done: true },
      { label: "اعتبارسنجی فرم ارسال", done: true },
      { label: "اتصال به API پرداخت", done: false },
    ],
  },
  {
    id: "TSK-1043",
    title: "پیاده‌سازی وب‌هوک اعلان‌های سفارش",
    description:
      "دریافت رویدادهای تغییر وضعیت سفارش از سرویس مرکزی و ارسال آن به کلاینت از طریق SSE، همراه با صف تکرار در صورت قطع اتصال.",
    status: "doing",
    priority: "high",
    assignee: "امیرحسین رضایی",
    group: "بک‌اند",
    deps: ["TSK-1041"],
    attachments: [],
    start: now - 48 * H,
    due: now + 19 * H,
    estimate: 20,
    checklist: [
      { label: "اتصال به صف پیام", done: true },
      { label: "مدیریت اتصال قطع", done: false },
      { label: "تست بار هم‌زمان", done: false },
    ],
  },
  {
    id: "TSK-1044",
    title: "بهینه‌سازی کوئری جستجوی محصولات",
    description:
      "افزودن ایندکس ترکیبی روی نام و دسته محصول و کاهش زمان پاسخ از ۹۰۰ میلی‌ثانیه به زیر ۲۰۰ میلی‌ثانیه در پروفایل تولید.",
    status: "review",
    priority: "med",
    assignee: "امیرحسین رضایی",
    group: "بک‌اند",
    deps: [],
    attachments: [{ name: "dashboard-night.jpg", src: IMG.screen }],
    start: now - 72 * H,
    due: now - 2 * H,
    estimate: 8,
    checklist: [
      { label: "اجرای EXPLAIN روی کوئری", done: true },
      { label: "بازبینی ایندکس", done: true },
    ],
  },
  {
    id: "TSK-1045",
    title: "تست رگرسیون درگاه پرداخت پارسان",
    description:
      "پوشش سناریوهای پرداخت موفق، ناموفق، انصراف کاربر و بازگشت وجه روی دو مرورگر دسکتاپ و دو مدل موبایل.",
    status: "todo",
    priority: "high",
    assignee: "پویا صادقی",
    group: "تضمین کیفیت",
    deps: ["TSK-1042"],
    attachments: [],
    start: now + 4 * H,
    due: now + 30 * H,
    estimate: 6,
    checklist: [{ label: "تهیه محیط تست", done: false }],
  },
  {
    id: "TSK-1046",
    title: "کامپوننت اسلایدر محصولات موبایل",
    description: "اسلایدر افقی با snap بر اساس اسکرول، پشتیبانی از کیبورد و کاهش حرکت برای کاربران حساس.",
    status: "done",
    priority: "med",
    assignee: "سارا محمدی",
    group: "فرانت‌اند",
    deps: [],
    attachments: [],
    start: now - 96 * H,
    due: now - 30 * H,
    estimate: 10,
    checklist: [
      { label: "پیاده‌سازی", done: true },
      { label: "دسترس‌پذیری", done: true },
    ],
  },
  {
    id: "TSK-1047",
    title: "مهاجرت جدول سفارش‌ها به شارد دوم",
    description:
      "تقسیم افقی جدول orders بر اساس تاریخ، با حفظ انسجام داده و بدون از دست رفتن سوابق مالی. پنجره انتقال در ساعت ۲ بامداد.",
    status: "doing",
    priority: "high",
    assignee: "مرضیا نوروزی",
    group: "زیرساخت و استقرار",
    deps: ["TSK-1035"],
    attachments: [],
    start: now - 12 * H,
    due: now + 7 * H + 15 * 60000,
    estimate: 12,
    checklist: [
      { label: "پشتیبان‌گیری کامل", done: true },
      { label: "اجرای مهاجرت", done: false },
      { label: "راستی‌آزمایی داده", done: false },
    ],
  },
  {
    id: "TSK-1048",
    title: "مستندسازی راه‌اندازی محیط توسعه",
    description: "راهنمای گام‌به‌گام برای توسعه‌دهندگان تازه‌وارد، شامل متغیرهای محیطی و اجرای تست‌ها.",
    status: "todo",
    priority: "low",
    assignee: "نگار کاظمی",
    group: "طراحی رابط کاربری",
    deps: [],
    attachments: [],
    start: now + 20 * H,
    due: now + 68 * H,
    estimate: 5,
    checklist: [{ label: "پیش‌نویس", done: false }],
  },
  {
    id: "TSK-1049",
    title: "رفع باگ احراز هویت دو مرحله‌ای",
    description:
      "کد یک‌بارمصرف برای برخی شماره‌ها باطل اعلام می‌شود؛ بازبینی منطق انقضا و هم‌زمانی سرور با سرور زمان جهانی.",
    status: "review",
    priority: "high",
    assignee: "امیرحسین رضایی",
    group: "بک‌اند",
    deps: [],
    attachments: [],
    start: now - 20 * H,
    due: now + 1 * H + 5 * 60000,
    estimate: 4,
    checklist: [
      { label: "بازتولید خطا", done: true },
      { label: "اصلاح انقضا", done: true },
      { label: "بازبینی کد", done: false },
    ],
  },
  {
    id: "TSK-1050",
    title: "داشبورد تحلیل رفتار کاربران",
    description: "نمایش قیف خرید، نرخ پرش صفحات و مسیر پرکاربردترین کاربران در هفت روز اخیر.",
    status: "todo",
    priority: "med",
    assignee: "نگار کاظمی",
    group: "طراحی رابط کاربری",
    deps: ["TSK-1044"],
    attachments: [{ name: "wireframe-analytics.jpg", src: IMG.wireframe }],
    start: now + 2 * H,
    due: now + 44 * H,
    estimate: 16,
    checklist: [{ label: "نمونه اولیه", done: false }],
  },
  {
    id: "TSK-1051",
    title: "به‌روزرسانی وابستگی‌های امنیتی",
    description: "ارتقای سه کتابخانه دارای هشدار CVE و اجرای مجدد اسکن نفوذ پیش از انتشار.",
    status: "done",
    priority: "high",
    assignee: "مرضیا نوروزی",
    group: "زیرساخت و استقرار",
    deps: [],
    attachments: [],
    start: now - 120 * H,
    due: now - 52 * H,
    estimate: 3,
    checklist: [{ label: "اسکن نفوذ", done: true }],
  },
  {
    id: "TSK-1052",
    title: "تست فشار سرویس پرداخت",
    description: "شبیه‌سازی ۱۲۰۰ درخواست هم‌زمان در دقیقه و پایش زمان پاسخ و نرخ خطا.",
    status: "todo",
    priority: "med",
    assignee: "پویا صادقی",
    group: "تضمین کیفیت",
    deps: ["TSK-1043"],
    attachments: [],
    start: now + 10 * H,
    due: now + 26 * H,
    estimate: 7,
    checklist: [{ label: "آماده‌سازی اسکریپت بار", done: false }],
  },
  {
    id: "TSK-1053",
    title: "طراحی ریسپانسیو صفحه پروفایل",
    description: "بازچینش بخش‌ها در عرض کمتر از ۳۶۰ پیکسل بدون از دست رفتن دکمه‌های اصلی.",
    status: "done",
    priority: "low",
    assignee: "نگار کاظمی",
    group: "طراحی رابط کاربری",
    deps: [],
    attachments: [],
    start: now - 60 * H,
    due: now - 8 * H,
    estimate: 6,
    checklist: [{ label: "بررسی در ۵ دستگاه", done: true }],
  },
  {
    id: "TSK-1054",
    title: "یکپارچه‌سازی سرویس پیامک کوتاه",
    description: "قالب‌بندی متن فارسی، مدیریت صف ارسال و ثبت رسید تحویل در سوابق سفارش.",
    status: "doing",
    priority: "med",
    assignee: "امیرحسین رضایی",
    group: "بک‌اند",
    deps: [],
    attachments: [],
    start: now - 30 * H,
    due: now + 52 * H,
    estimate: 9,
    checklist: [
      { label: "دریافت کلید API", done: true },
      { label: "قالب پیام", done: false },
    ],
  },
  {
    id: "TSK-1055",
    title: "آماده‌سازی انتشار نسخه ۳٫۲",
    description: "چک‌لیست انتشار: یادداشت نسخه، به‌روزرسانی پایگاه داده، اعلام به پشتیبانی و برنامه بازگشت.",
    status: "todo",
    priority: "high",
    assignee: "سارا محمدی",
    group: "زیرساخت و استقرار",
    deps: ["TSK-1045", "TSK-1047"],
    attachments: [],
    start: now + 30 * H,
    due: now + 74 * H,
    estimate: 4,
    checklist: [{ label: "یادداشت نسخه", done: false }],
  },
];

/** روند شش هفته اخیر اسپرینت */
export const WEEKLY = [
  { label: "هفته ۱", created: 18, done: 14 },
  { label: "هفته ۲", created: 22, done: 19 },
  { label: "هفته ۳", created: 16, done: 17 },
  { label: "هفته ۴", created: 25, done: 20 },
  { label: "هفته ۵", created: 19, done: 23 },
  { label: "هفته ۶", created: 21, done: 16 },
];

/** روند سوزاندن اسپرینت ۱۴ */
export const BURNDOWN = [96, 88, 79, 74, 61, 55, 47, 41];
export const BURNDOWN_IDEAL = [96, 84, 72, 60, 48, 36, 24, 12];

export const ACTIVITY = [
  { who: "نگار کاظمی", what: "وضعیت TSK-1053 را روی «انجام شده» گذاشت", when: "۱۲ دقیقه پیش" },
  { who: "پویا صادقی", what: "گزارش باگ احراز هویت را ثبت کرد", when: "۴۸ دقیقه پیش" },
  { who: "امیرحسین رضایی", what: "کد TSK-1044 را برای بازبینی فرستاد", when: "۲ ساعت پیش" },
  { who: "مرضیا نوروزی", what: "پنجره انتقال پایگاه داده را زمان‌بندی کرد", when: "۴ ساعت پیش" },
  { who: "سارا محمدی", what: "دو پیوست به TSK-1042 اضافه کرد", when: "دیروز، ۱۷:۲۰" },
];

export const COLUMNS: { key: Status; wip: number }[] = [
  { key: "todo", wip: 8 },
  { key: "doing", wip: 4 },
  { key: "review", wip: 3 },
  { key: "done", wip: 99 },
];

export const faNum = (n: number) => n.toLocaleString("fa-IR");
export const faDate = (t: number) =>
  new Date(t).toLocaleDateString("fa-IR", { month: "short", day: "numeric" });
export const clock = (t: number) =>
  new Date(t).toLocaleTimeString("fa-IR", { hour: "2-digit", minute: "2-digit" });

/** شمارش معکوس زنده — قالب مونو برای تختهٔ عملیات */
export function countdown(due: number, ref = Date.now()) {
  const diff = due - ref;
  const over = diff < 0;
  const abs = Math.abs(diff);
  const d = Math.floor(abs / 86400000);
  const h = Math.floor((abs % 86400000) / 3600000);
  const m = Math.floor((abs % 3600000) / 60000);
  const s = Math.floor((abs % 60000) / 1000);
  const pad = (x: number) => String(x).padStart(2, "0");
  const body = d > 0 ? `${d}d ${pad(h)}:${pad(m)}` : `${pad(h)}:${pad(m)}:${pad(s)}`;
  return { over, text: over ? `-${body}` : body };
}

export const SLA_ALLOCATED = 40; // ساعت اختصاص‌یافته در هفته
