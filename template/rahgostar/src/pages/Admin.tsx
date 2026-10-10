import { useMemo, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import {
  ImagePlus,
  Link2,
  Trash2,
  CalendarClock,
  Send,
  Users,
  Layers3,
  Search,
} from "lucide-react";
import { type Task, type Status, STATUS, PRIORITY, GROUPS, MEMBERS, faNum, clock, faDate } from "../data";
import { IMG } from "../img";

const SAMPLE_IMAGES = [
  { name: "wireframe-checkout.jpg", src: IMG.wireframe },
  { name: "dashboard-night.jpg", src: IMG.screen },
];

const toInput = (t: number) => {
  const d = new Date(t - new Date().getTimezoneOffset() * 60000);
  return d.toISOString().slice(0, 16);
};

function Field({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="micro mb-1.5 flex items-center justify-between text-sand">
        {label}
        {hint && <span className="text-ink-400">{hint}</span>}
      </span>
      {children}
    </label>
  );
}

const inputCls =
  "w-full rounded-lg border border-line bg-cream-100 px-3 py-2.5 text-[13px] text-ink-900 placeholder:text-sand focus:border-ink-500 focus:bg-cream-50 focus:outline-none";

export default function Admin({
  tasks,
  onAdd,
  onPatch,
  onDelete,
}: {
  tasks: Task[];
  onAdd: (t: Task) => void;
  onPatch: (id: string, patch: Partial<Task>) => void;
  onDelete: (id: string) => void;
}) {
  const now = Date.now();
  const [title, setTitle] = useState("");
  const [desc, setDesc] = useState("");
  const [group, setGroup] = useState(GROUPS[0]);
  const [assignee, setAssignee] = useState(MEMBERS[0].name);
  const [priority, setPriority] = useState<Task["priority"]>("med");
  const [deps, setDeps] = useState<string[]>([]);
  const [images, setImages] = useState<{ name: string; src: string }[]>([]);
  const [start, setStart] = useState(toInput(now));
  const [due, setDue] = useState(toInput(now + 48 * 3600_000));
  const [estimate, setEstimate] = useState(8);
  const [toast, setToast] = useState<string | null>(null);
  const [q, setQ] = useState("");
  const fileRef = useRef<HTMLInputElement>(null);

  const groups = useMemo(
    () =>
      GROUPS.map((g) => ({
        name: g,
        count: tasks.filter((t) => t.group === g).length,
        open: tasks.filter((t) => t.group === g && t.status !== "done").length,
      })),
    [tasks],
  );

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim()) {
      setToast("عنوان تسک را وارد کنید");
      setTimeout(() => setToast(null), 2600);
      return;
    }
    const maxId = tasks.reduce((m, t) => Math.max(m, Number(t.id.replace(/\D/g, "")) || 0), 1000);
    onAdd({
      id: `TSK-${maxId + 1}`,
      title: title.trim(),
      description: desc.trim() || "توضیحی ثبت نشده است.",
      status: "todo",
      priority,
      assignee,
      group,
      deps,
      attachments: images,
      start: new Date(start).getTime(),
      due: new Date(due).getTime(),
      estimate,
      checklist: [{ label: "بررسی دقیق الزامات", done: false }],
    });
    setToast(`تسک TSK-${maxId + 1} با موفقیت به بک‌لاگ اضافه شد`);
    setTitle("");
    setDesc("");
    setDeps([]);
    setImages([]);
    setTimeout(() => setToast(null), 3600);
  };

  const onFiles = (files: FileList | null) => {
    if (!files) return;
    Array.from(files).slice(0, 4).forEach((f) => {
      const r = new FileReader();
      r.onload = () =>
        setImages((im) => [...im, { name: f.name, src: String(r.result) }].slice(0, 6));
      r.readAsDataURL(f);
    });
  };

  const shown = tasks.filter(
    (t) => t.title.includes(q) || t.id.toLowerCase().includes(q.toLowerCase()) || t.group.includes(q),
  );

  return (
    <div className="space-y-6">
      <div className="grid gap-5 xl:grid-cols-[1.7fr_1fr]">
        {/* فرم ایجاد تسک */}
        <form
          id="new-task-form"
          onSubmit={submit}
          className="rounded-xl border border-line bg-cream-50 p-5 card-lift md:p-6"
        >
          <div className="mb-5 flex items-start justify-between gap-3 border-b border-line pb-4">
            <div>
              <div className="micro text-sand">CREATE TASK</div>
              <h2 className="mt-1 text-lg font-black text-ink-900">ایجاد تسک جدید</h2>
            </div>
            <span className="tnum rounded-md border border-line bg-cream-100 px-2.5 py-1 text-[11px] text-sand">
              اسپرینت ۱۴
            </span>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="md:col-span-2">
              <Field label="عنوان تسک" hint="REQUIRED">
                <input
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="مثلاً: بازطراحی فلوی ثبت سفارش"
                  className={inputCls}
                />
              </Field>
            </div>

            <Field label="زیرگروه">
              <select value={group} onChange={(e) => setGroup(e.target.value)} className={inputCls}>
                {GROUPS.map((g) => (
                  <option key={g}>{g}</option>
                ))}
              </select>
            </Field>

            <Field label="مسئول انجام">
              <select
                value={assignee}
                onChange={(e) => setAssignee(e.target.value)}
                className={inputCls}
              >
                {MEMBERS.map((m) => (
                  <option key={m.name}>{m.name}</option>
                ))}
              </select>
            </Field>

            <div className="md:col-span-2">
              <Field label="اولویت">
                <div className="flex gap-2">
                  {(["high", "med", "low"] as const).map((p) => (
                    <button
                      key={p}
                      type="button"
                      onClick={() => setPriority(p)}
                      className={`flex flex-1 items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-[12.5px] font-bold transition ${
                        priority === p
                          ? "border-ink-900 bg-ink-900 text-cream-50"
                          : "border-line bg-cream-100 text-sand-dark hover:border-ink-500"
                      }`}
                    >
                      <span className={`h-2.5 w-2.5 rounded-sm ${PRIORITY[p].color}`} />
                      {PRIORITY[p].label}
                    </button>
                  ))}
                </div>
              </Field>
            </div>

            <div className="md:col-span-2">
              <Field label="توضیحات" hint={`${faNum(desc.length)} کاراکتر`}>
                <textarea
                  value={desc}
                  onChange={(e) => setDesc(e.target.value)}
                  rows={4}
                  placeholder="شرح کامل کار، خروجی مورد انتظار و معیار پذیرش…"
                  className={`${inputCls} resize-y leading-relaxed`}
                />
              </Field>
            </div>

            {/* زیرگروه + افزودن عکس */}
            <div className="md:col-span-2">
              <Field label="عکس‌های زیرگروه" hint="JPG / PNG · تا ۶ فایل">
                <div className="rounded-lg border border-dashed border-ink-300 bg-ink-100/40 p-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={() => fileRef.current?.click()}
                      className="flex items-center gap-2 rounded-lg border border-ink-500 bg-cream-50 px-3 py-2 text-[12.5px] font-bold text-ink-700 transition hover:bg-ink-100"
                    >
                      <ImagePlus size={15} /> افزودن عکس
                    </button>
                    <span className="text-[11px] text-sand">یا از نمونه‌ها:</span>
                    {SAMPLE_IMAGES.map((s) => (
                      <button
                        key={s.name}
                        type="button"
                        onClick={() =>
                          setImages((im) =>
                            im.some((x) => x.src === s.src) ? im : [...im, s].slice(0, 6),
                          )
                        }
                        className="h-9 overflow-hidden rounded-md border border-line transition hover:border-ink-500"
                        title={s.name}
                      >
                        <img src={s.src} alt={s.name} className="h-full w-12 object-cover" />
                      </button>
                    ))}
                    <input
                      ref={fileRef}
                      type="file"
                      accept="image/*"
                      multiple
                      hidden
                      onChange={(e) => onFiles(e.target.files)}
                    />
                  </div>

                  {images.length > 0 ? (
                    <ul className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                      {images.map((im, i) => (
                        <motion.li
                          key={im.src + i}
                          initial={{ opacity: 0, scale: 0.9 }}
                          animate={{ opacity: 1, scale: 1 }}
                          className="group relative overflow-hidden rounded-md border border-line bg-cream-50"
                        >
                          <img src={im.src} alt={im.name} className="h-16 w-full object-cover" />
                          <button
                            type="button"
                            onClick={() => setImages((x) => x.filter((_, j) => j !== i))}
                            className="absolute left-1 top-1 rounded bg-ink-950/80 p-1 text-cream-50 opacity-0 transition group-hover:opacity-100"
                            aria-label="حذف تصویر"
                          >
                            <Trash2 size={11} />
                          </button>
                          <span className="block truncate px-1.5 py-1 text-[9px] text-sand">
                            {im.name}
                          </span>
                        </motion.li>
                      ))}
                    </ul>
                  ) : (
                    <p className="mt-3 text-[11.5px] text-sand">
                      هنوز تصویری پیوست نشده — تصاویر مرجع زیرگروه اینجا نمایش داده می‌شوند.
                    </p>
                  )}
                </div>
              </Field>
            </div>

            {/* وابستگی */}
            <div className="md:col-span-2">
              <Field label="وابستگی به تسک‌های دیگر" hint={`${faNum(deps.length)} انتخاب`}>
                <div className="flex max-h-32 flex-wrap gap-1.5 overflow-y-auto rounded-lg border border-line bg-cream-100 p-2.5">
                  {tasks.map((t) => {
                    const on = deps.includes(t.id);
                    return (
                      <button
                        key={t.id}
                        type="button"
                        onClick={() =>
                          setDeps((d) => (on ? d.filter((x) => x !== t.id) : [...d, t.id]))
                        }
                        className={`flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-bold transition ${
                          on
                            ? "border-ink-900 bg-ink-900 text-cream-50"
                            : "border-line bg-cream-50 text-sand-dark hover:border-ink-500"
                        }`}
                      >
                        <Link2 size={11} />
                        <span className="tnum">{t.id.replace("TSK-", "")}</span>
                        <span className="max-w-[130px] truncate">{t.title}</span>
                      </button>
                    );
                  })}
                </div>
              </Field>
            </div>

            <Field label="زمان شروع">
              <input
                type="datetime-local"
                value={start}
                onChange={(e) => setStart(e.target.value)}
                className={`${inputCls} tnum`}
              />
            </Field>
            <Field label="مهلت انجام (SLA)">
              <input
                type="datetime-local"
                value={due}
                onChange={(e) => setDue(e.target.value)}
                className={`${inputCls} tnum`}
              />
            </Field>

            <div className="md:col-span-2">
              <Field label="برآورد زمانی" hint={`${faNum(estimate)} ساعت`}>
                <input
                  type="range"
                  min={1}
                  max={40}
                  value={estimate}
                  onChange={(e) => setEstimate(Number(e.target.value))}
                  className="w-full accent-[#123E73]"
                />
                <div className="mt-1 flex justify-between text-[10px] text-sand tnum">
                  <span>1h</span>
                  <span>20h</span>
                  <span>40h</span>
                </div>
              </Field>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button
              type="submit"
              className="flex items-center gap-2 rounded-lg bg-gold-500 px-5 py-3 text-[13px] font-black text-ink-950 shadow-[0_10px_24px_-12px_rgba(242,183,5,1)] transition hover:-translate-y-0.5 hover:bg-gold-300"
            >
              <Send size={15} strokeWidth={2.6} /> ثبت تسک در بک‌لاگ
            </button>
            <button
              type="button"
              onClick={() => {
                setTitle("");
                setDesc("");
                setDeps([]);
                setImages([]);
              }}
              className="rounded-lg border border-line px-4 py-3 text-[13px] font-bold text-sand-dark transition hover:border-ink-500 hover:text-ink-700"
            >
              پاک کردن فرم
            </button>
            <span className="text-[11.5px] text-sand">
              پس از ثبت، تسک در ستون «کار نشده» بورد کاربران ظاهر می‌شود.
            </span>
          </div>
        </form>

        {/* ستون کناری */}
        <div className="space-y-5">
          <section className="rounded-xl border border-line bg-cream-50 p-5 card-lift">
            <div className="micro text-sand">SUB-GROUPS · زیرگروه‌ها</div>
            <h3 className="mt-1 mb-4 text-base font-black text-ink-900">توزیع کار به تفکیک زیرگروه</h3>
            <ul className="space-y-3.5">
              {groups.map((g, i) => (
                <li key={g.name}>
                  <div className="flex items-center justify-between text-[12.5px]">
                    <span className="flex items-center gap-2 font-bold text-ink-800">
                      <Layers3 size={14} className="text-ink-500" />
                      {g.name}
                    </span>
                    <span className="tnum text-sand">
                      {faNum(g.open)}/{faNum(g.count)}
                    </span>
                  </div>
                  <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-cream-200">
                    <motion.div
                      initial={{ width: 0 }}
                      animate={{ width: `${g.count ? (g.open / g.count) * 100 : 0}%` }}
                      transition={{ duration: 0.7, delay: i * 0.06 }}
                      className="h-full rounded-full bg-gradient-to-l from-ink-500 to-gold-500"
                    />
                  </div>
                </li>
              ))}
            </ul>
          </section>

          <section className="rounded-xl border border-line bg-ink-950 p-5 text-cream-100">
            <div className="micro text-gold-500">SLA RULE</div>
            <p className="mt-2 text-[13px] leading-relaxed text-cream-100/90">
              تسک‌های با اولویت <b className="text-flame-300">بحرانی</b> حداکثر ۲۴ ساعت پس از شروع
              باید وارد مرحلهٔ بازبینی شوند؛ در غیر این صورت هشدار خودکار برای سرپرست ارسال می‌شود.
            </p>
            <div className="mt-4 grid grid-cols-3 gap-2 border-t border-white/10 pt-4 text-center">
              {[
                ["۲۴h", "بحرانی"],
                ["۷۲h", "متوسط"],
                ["۷d", "کم"],
              ].map(([v, l]) => (
                <div key={l} className="rounded-lg bg-white/[0.07] py-2.5">
                  <div className="tnum text-sm font-black text-gold-300">{v}</div>
                  <div className="mt-0.5 text-[10.5px] text-ink-300">{l}</div>
                </div>
              ))}
            </div>
          </section>
        </div>
      </div>

      {/* دفتر بک‌لاگ */}
      <section className="overflow-hidden rounded-xl border border-line bg-cream-50 card-lift">
        <div className="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
          <div>
            <div className="micro text-sand">BACKLOG LEDGER</div>
            <h3 className="mt-1 text-base font-black text-ink-900">دفتر تسک‌های پروژه</h3>
          </div>
          <label className="group relative ms-auto flex h-9 w-full max-w-xs items-center">
            <Search size={14} className="pointer-events-none absolute right-3 text-sand" />
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="جست‌وجوی عنوان، کد یا زیرگروه…"
              className="h-full w-full rounded-lg border border-line bg-cream-100 pe-9 ps-3 text-[12.5px] focus:border-ink-500 focus:outline-none"
            />
          </label>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[820px] border-collapse text-right">
            <thead>
              <tr className="border-b border-line bg-cream-100">
                {["کد", "عنوان", "زیرگروه", "وابستگی", "مهلت", "وضعیت", "عملیات"].map((h) => (
                  <th key={h} className="micro px-4 py-3 text-sand">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {shown.map((t, i) => {
                const late = t.status !== "done" && t.due < Date.now();
                return (
                  <tr
                    key={t.id}
                    className={`border-b border-line/70 transition hover:bg-cream-100 ${
                      i % 2 ? "bg-cream-50" : "bg-cream-50/40"
                    }`}
                  >
                    <td className="tnum whitespace-nowrap px-4 py-3 text-[12px] font-semibold text-ink-500">
                      {t.id}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <span className={`h-2 w-2 shrink-0 rounded-sm ${PRIORITY[t.priority].color}`} />
                        <span className="max-w-[260px] truncate text-[13px] font-bold text-ink-900">
                          {t.title}
                        </span>
                        {t.attachments.length > 0 && (
                          <ImagePlus size={12} className="shrink-0 text-sand" />
                        )}
                      </div>
                      <span className="mt-0.5 block text-[11px] text-sand">{t.assignee}</span>
                    </td>
                    <td className="whitespace-nowrap px-4 py-3 text-[12px] text-sand-dark">
                      {t.group}
                    </td>
                    <td className="px-4 py-3">
                      {t.deps.length ? (
                        <span className="tnum flex flex-wrap gap-1">
                          {t.deps.map((d) => (
                            <span
                              key={d}
                              className="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] text-ink-700"
                            >
                              {d.replace("TSK-", "")}
                            </span>
                          ))}
                        </span>
                      ) : (
                        <span className="text-[11px] text-sand">—</span>
                      )}
                    </td>
                    <td className="whitespace-nowrap px-4 py-3">
                      <div className={`tnum text-[12px] ${late ? "text-flame-500" : "text-ink-800"}`}>
                        {clock(t.due)}
                      </div>
                      <div className="text-[10.5px] text-sand">{faDate(t.due)}</div>
                    </td>
                    <td className="px-4 py-3">
                      <select
                        value={t.status}
                        onChange={(e) => onPatch(t.id, { status: e.target.value as Status })}
                        className={`rounded-md border px-2 py-1 text-[11.5px] font-bold focus:outline-none ${STATUS[t.status].chip}`}
                      >
                        {(Object.keys(STATUS) as Status[]).map((s) => (
                          <option key={s} value={s} className="bg-cream-50 text-ink-900">
                            {STATUS[s].label}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="px-4 py-3">
                      <button
                        onClick={() => onDelete(t.id)}
                        className="rounded-md border border-line p-1.5 text-sand transition hover:border-flame-500 hover:bg-flame-300/20 hover:text-flame-500"
                        aria-label={`حذف ${t.id}`}
                      >
                        <Trash2 size={14} />
                      </button>
                    </td>
                  </tr>
                );
              })}
              {shown.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-10 text-center text-[13px] text-sand">
                    تسکی با این عبارت پیدا نشد
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-cream-100 px-5 py-3 text-[11.5px] text-sand">
          <span className="flex items-center gap-2">
            <Users size={14} /> {faNum(MEMBERS.length)} عضو فعال · {faNum(shown.length)} ردیف نمایش داده
            شده
          </span>
          <span className="flex items-center gap-2">
            <CalendarClock size={14} /> آخرین به‌روزرسانی: {clock(Date.now())}
          </span>
        </div>
      </section>

      <AnimatePresence>
        {toast && (
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 24 }}
            className="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-lg border border-jade-400 bg-ink-950 px-5 py-3 text-[13px] font-bold text-cream-50 shadow-2xl"
          >
            <span className="me-2 text-jade-400">✓</span>
            {toast}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
