import { useEffect, useMemo, useRef, useState } from "react";
import { cn } from "@/utils/cn";
import { useStore } from "@/lib/store";
import { fa, fmtDate, isoFrom, leftover, todayISO, uid, faDuration, daysBetween } from "@/lib/fa";
import {
  MEMBERS,
  PROJECTS,
  PRIORITY_META,
  PRIORITY_ORDER,
  SLA_TONE,
  STATUS_META,
  STATUS_ORDER,
  projectOf,
  slaOf,
  type Attachment,
  type Priority,
  type Status,
  type SubTask,
  type Task,
} from "@/lib/types";
import {
  Avatar,
  Badge,
  Chip,
  Empty,
  Modal,
  Panel,
  ProgressBar,
  Reveal,
  Ring,
} from "@/components/ui";
import {
  IconAlert,
  IconCalendar,
  IconCheck,
  IconChecklist,
  IconChevron,
  IconClock,
  IconClip,
  IconFlag,
  IconImage,
  IconLayers,
  IconLink,
  IconPencil,
  IconPlus,
  IconSearch,
  IconSettings,
  IconBoard,
  IconTrash,
  IconX,
  IconSpark,
  IconFilter,
} from "@/components/icons";

interface FormState {
  title: string;
  description: string;
  project: string;
  assignee: string;
  status: Status;
  priority: Priority;
  start: string;
  due: string;
  estimate: number;
  slaHours: number;
  tags: string[];
  subtasks: SubTask[];
  dependencies: string[];
  attachments: Attachment[];
}

const emptyForm = (): FormState => ({
  title: "",
  description: "",
  project: PROJECTS[0].name,
  assignee: MEMBERS[0].name,
  status: "todo",
  priority: "medium",
  start: todayISO(),
  due: isoFrom(7),
  estimate: 8,
  slaHours: 48,
  tags: [],
  subtasks: [],
  dependencies: [],
  attachments: [],
});

const readFile = (file: File, cb: (dataUrl: string) => void) => {
  const r = new FileReader();
  r.onload = () => cb(String(r.result));
  r.readAsDataURL(file);
};

export default function Admin() {
  const { tasks, route, addTask, updateTask, removeTask, toast, navigate, now, stats } = useStore();
  const [form, setForm] = useState<FormState>(emptyForm);
  const [editing, setEditing] = useState<string | null>(null);
  const [tagDraft, setTagDraft] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [panel, setPanel] = useState<"list" | "deps" | "subs" | "settings">((route.tab as never) ?? "list");
  const [q, setQ] = useState(route.q === "overdue" ? "" : (route.q ?? ""));
  const [fStatus, setFStatus] = useState<Status | "all">(route.status ?? "all");
  const [fPriority, setFPriority] = useState<Priority | "all">(route.priority ?? "all");
  const [fProject, setFProject] = useState<string>("all");
  const [onlyOverdue, setOnlyOverdue] = useState(route.q === "overdue");
  const [sort, setSort] = useState<{ key: "due" | "priority" | "progress" | "code"; dir: 1 | -1 }>({ key: "due", dir: 1 });
  const [expanded, setExpanded] = useState<string[]>([]);
  const [picked, setPicked] = useState<string[]>([]);
  const [confirmDel, setConfirmDel] = useState<Task | null>(null);
  const formRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (route.tab === "deps" || route.tab === "subs" || route.tab === "settings") setPanel(route.tab as never);
    if (route.tab === undefined || route.tab === null) setPanel("list");
  }, [route.tab]);

  useEffect(() => {
    if (route.q !== undefined) {
      setQ(route.q === "overdue" ? "" : route.q);
      setOnlyOverdue(route.q === "overdue");
    }
    if (route.status !== undefined) setFStatus(route.status);
    if (route.priority !== undefined) setFPriority(route.priority);
  }, [route.q, route.status, route.priority]);

  useEffect(() => {
    if (route.page === "admin" && route.newTask) {
      setEditing(null);
      setForm(emptyForm());
      setErrors({});
      setTimeout(() => formRef.current?.scrollIntoView({ behavior: "smooth", block: "start" }), 120);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [route]);

  const nextCode = useMemo(() => {
    const max = tasks.reduce((m, t) => Math.max(m, Number(t.code.replace(/\D/g, "")) || 0), 1000);
    return `TS-${max + 1}`;
  }, [tasks]);

  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    let list = tasks.filter((t) => {
      if (fStatus !== "all" && t.status !== fStatus) return false;
      if (fPriority !== "all" && t.priority !== fPriority) return false;
      if (fProject !== "all" && t.project !== fProject) return false;
      if (onlyOverdue && !leftover(t.due, t.status === "done", now).overdue) return false;
      if (!term) return true;
      return (
        t.title.toLowerCase().includes(term) ||
        t.code.toLowerCase().includes(term) ||
        t.assignee.includes(q.trim()) ||
        t.project.includes(q.trim()) ||
        t.tags.some((g) => g.includes(q.trim()))
      );
    });
    list = [...list].sort((a, b) => {
      const { key, dir } = sort;
      if (key === "priority") return (PRIORITY_META[a.priority].weight - PRIORITY_META[b.priority].weight) * dir;
      if (key === "progress") return (a.progress - b.progress) * dir;
      if (key === "code") return a.code.localeCompare(b.code) * dir;
      return (new Date(a.due).getTime() - new Date(b.due).getTime()) * dir;
    });
    return list;
  }, [tasks, q, fStatus, fPriority, fProject, onlyOverdue, sort, now]);

  /* ---------- form handlers ---------- */
  const patch = (p: Partial<FormState>) => setForm((f) => ({ ...f, ...p }));

  const validate = (): boolean => {
    const e: Record<string, string> = {};
    if (form.title.trim().length < 4) e.title = "عنوان تسک باید حداقل ۴ نویسه باشد";
    if (form.description.trim().length < 10) e.description = "توضیحات را کامل‌تر بنویسید (حداقل ۱۰ نویسه)";
    if (daysBetween(form.start, form.due) < 0) e.due = "تاریخ سررسید نمی‌تواند پیش از تاریخ شروع باشد";
    if (form.subtasks.some((s) => s.title.trim().length < 3)) e.subtasks = "عنوان زیرگروه‌ها نمی‌تواند خالی باشد";
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const submit = () => {
    if (!validate()) {
      toast("فرم دارای خطا است — موارد قرمز را بررسی کنید", "warn");
      return;
    }
    const progress = form.status === "done" ? 100 : Math.round((form.subtasks.filter((s) => s.done).length / Math.max(1, form.subtasks.length)) * 100);
    if (editing) {
      updateTask(editing, { ...form, progress: form.status === "done" ? 100 : progress });
      toast(`تسک ${tasks.find((t) => t.id === editing)?.code ?? ""} به‌روزرسانی شد`, "ok");
      setEditing(null);
    } else {
      addTask({
        id: uid("t"),
        code: nextCode,
        ...form,
        progress,
        spent: 0,
        reporter: "یوسف صادقی",
        createdAt: todayISO(),
        sticky: {
          x: 18 + (tasks.length % 5) * 212,
          y: 22 + Math.floor(tasks.length / 5) * 198,
          rot: (tasks.length % 7) - 3,
        },
      });
    }
    setForm(emptyForm());
    setTagDraft("");
    setErrors({});
    navigate({ page: "admin", status: "all" });
  };

  const startEdit = (t: Task) => {
    setEditing(t.id);
    setForm({
      title: t.title,
      description: t.description,
      project: t.project,
      assignee: t.assignee,
      status: t.status,
      priority: t.priority,
      start: t.start,
      due: t.due,
      estimate: t.estimate,
      slaHours: t.slaHours,
      tags: t.tags,
      subtasks: t.subtasks,
      dependencies: t.dependencies,
      attachments: t.attachments,
    });
    formRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  /* preview computation */
  const preview: Task = {
    id: "preview",
    code: editing ? (tasks.find((t) => t.id === editing)?.code ?? nextCode) : nextCode,
    title: form.title || "عنوان تسک هنوز وارد نشده",
    description: form.description,
    project: form.project,
    assignee: form.assignee,
    reporter: "یوسف صادقی",
    status: form.status,
    priority: form.priority,
    start: form.start,
    due: form.due,
    estimate: form.estimate,
    spent: 0,
    progress: form.status === "done" ? 100 : Math.round((form.subtasks.filter((s) => s.done).length / Math.max(1, form.subtasks.length)) * 100),
    slaHours: form.slaHours,
    tags: form.tags,
    subtasks: form.subtasks,
    dependencies: form.dependencies,
    attachments: form.attachments,
    createdAt: todayISO(),
    sticky: { x: 0, y: 0, rot: 0 },
  };
  const previewSla = slaOf(preview, now);

  return (
    <div className="space-y-5">
      {/* strip */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {[
          { label: "کل تسک‌های ثبت‌شده", value: tasks.length, hex: "#1a5cd6", icon: <IconLayers className="h-[18px] w-[18px]" /> },
          { label: "در جریان کار", value: stats.byStatus.doing + stats.byStatus.review, hex: "#f9bb1b", icon: <IconClock className="h-[18px] w-[18px]" /> },
          { label: "عقب‌افتاده از موعد", value: stats.overdue.length, hex: "#ef6211", icon: <IconAlert className="h-[18px] w-[18px]" /> },
          { label: "زیرگروه دارای تصویر", value: tasks.reduce((a, t) => a + t.subtasks.filter((s) => s.image).length, 0), hex: "#16a157", icon: <IconImage className="h-[18px] w-[18px]" /> },
        ].map((k, i) => (
          <Reveal key={k.label} delay={i * 60}>
            <div className="lift relative overflow-hidden rounded-2xl border border-milk-300 bg-milk-50 p-4 shadow-soft">
              <span className="absolute inset-y-0 start-0 w-1.5" style={{ background: k.hex }} />
              <div className="flex items-center justify-between gap-2">
                <p className="text-[11.5px] font-bold text-ink-500">{k.label}</p>
                <span className="grid h-9 w-9 place-items-center rounded-xl" style={{ background: `${k.hex}1a`, color: k.hex }}>
                  {k.icon}
                </span>
              </div>
              <p className="font-num mt-1.5 text-[30px] font-bold leading-none tabular text-ink-900">{fa(k.value)}</p>
            </div>
          </Reveal>
        ))}
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-12">
        {/* ---------------- FORM ---------------- */}
        <div className="xl:col-span-5" ref={formRef}>
          <Panel
            className="xl:sticky xl:top-4"
            title={editing ? "ویرایش تسک" : "ایجاد تسک جدید"}
            subtitle={editing ? `شناسه ${preview.code}` : `شناسه پیشنهادی ${nextCode}`}
            icon={editing ? <IconPencil className="h-[18px] w-[18px]" /> : <IconPlus className="h-[18px] w-[18px]" />}
            accent="#f9bb1b"
            action={
              editing ? (
                <button
                  onClick={() => {
                    setEditing(null);
                    setForm(emptyForm());
                    setErrors({});
                  }}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-milk-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-ink-500 transition hover:border-ember-300 hover:text-ember-500"
                >
                  <IconX className="h-3.5 w-3.5" />
                  انصراف از ویرایش
                </button>
              ) : (
                <Badge className="bg-sun-100 text-sun-600 ring-1 ring-sun-300">فرم مدیر پروژه</Badge>
              )
            }
          >
            <div className="space-y-4">
              <Field label="عنوان تسک" error={errors.title} required>
                <input
                  value={form.title}
                  onChange={(e) => patch({ title: e.target.value })}
                  placeholder="مثلاً: پیاده‌سازی سرویس تسویه با درگاه بانکی"
                  className={inputCls(!!errors.title)}
                />
              </Field>

              <Field label="توضیحات و معیار پذیرش" error={errors.description} hint={`${fa(form.description.length)} نویسه`}>
                <textarea
                  value={form.description}
                  onChange={(e) => patch({ description: e.target.value })}
                  rows={4}
                  placeholder="شرح کار، خروجی مورد انتظار و معیار پذیرش…"
                  className={cn(inputCls(!!errors.description), "resize-y leading-6")}
                />
              </Field>

              <div className="grid grid-cols-2 gap-3">
                <Field label="پروژه">
                  <select value={form.project} onChange={(e) => patch({ project: e.target.value })} className={inputCls(false)}>
                    {PROJECTS.map((p) => (
                      <option key={p.code} value={p.name}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="مسئول اجرا">
                  <select value={form.assignee} onChange={(e) => patch({ assignee: e.target.value })} className={inputCls(false)}>
                    {MEMBERS.map((m) => (
                      <option key={m.name} value={m.name}>
                        {m.name} — {m.role}
                      </option>
                    ))}
                  </select>
                </Field>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <Field label="وضعیت">
                  <div className="flex flex-wrap gap-1.5">
                    {STATUS_ORDER.map((s) => (
                      <button
                        key={s}
                        type="button"
                        onClick={() => patch({ status: s })}
                        className={cn(
                          "rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition-all duration-200",
                          form.status === s ? STATUS_META[s].chip + " scale-105 shadow-soft" : "bg-milk-100 text-ink-400 hover:bg-milk-200",
                        )}
                      >
                        {STATUS_META[s].label}
                      </button>
                    ))}
                  </div>
                </Field>
                <Field label="اولویت">
                  <div className="flex flex-wrap gap-1.5">
                    {PRIORITY_ORDER.map((p) => (
                      <button
                        key={p}
                        type="button"
                        onClick={() => patch({ priority: p })}
                        className={cn(
                          "inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition-all duration-200",
                          form.priority === p ? "text-white shadow-soft" : "bg-milk-100 text-ink-400 hover:bg-milk-200",
                        )}
                        style={form.priority === p ? { background: PRIORITY_META[p].hex } : undefined}
                      >
                        <IconFlag className="h-3 w-3" />
                        {PRIORITY_META[p].label}
                      </button>
                    ))}
                  </div>
                </Field>
              </div>

              <div className="rounded-2xl border border-brand-100 bg-brand-50/50 p-3.5">
                <p className="mb-3 inline-flex items-center gap-2 text-[11.5px] font-extrabold text-brand-800">
                  <IconCalendar className="h-4 w-4" />
                  زمان‌بندی و تعهد SLA
                </p>
                <div className="grid grid-cols-2 gap-3">
                  <Field label="تاریخ شروع">
                    <input type="date" value={form.start} onChange={(e) => patch({ start: e.target.value })} className={inputCls(false)} />
                  </Field>
                  <Field label="تاریخ سررسید" error={errors.due}>
                    <input type="date" value={form.due} onChange={(e) => patch({ due: e.target.value })} className={inputCls(!!errors.due)} />
                  </Field>
                </div>
                <div className="mt-3 space-y-3">
                  <Slider
                    label="تخمین effort"
                    value={form.estimate}
                    min={2}
                    max={80}
                    step={2}
                    onChange={(v) => patch({ estimate: v })}
                    display={faDuration(form.estimate)}
                  />
                  <Slider
                    label="پنجره SLA"
                    value={form.slaHours}
                    min={4}
                    max={240}
                    step={4}
                    onChange={(v) => patch({ slaHours: v })}
                    display={`${fa(form.slaHours)} ساعت`}
                  />
                </div>
                <p className="mt-3 text-[10.5px] font-semibold text-ink-500">
                  فاصله شروع تا سررسید: <span className="font-num tabular text-brand-700">{fa(daysBetween(form.start, form.due))}</span> روز —
                  سررسید: <span className="font-bold text-ink-700">{fmtDate(form.due)}</span>
                </p>
              </div>

              {/* subtasks with images */}
              <Field
                label="زیرگروه‌ها (با امکان تصویر)"
                error={errors.subtasks}
                hint={`${fa(form.subtasks.length)} زیرگروه — ${fa(form.subtasks.filter((s) => s.image).length)} تصویر`}
              >
                <div className="space-y-2">
                  {form.subtasks.map((s, i) => (
                    <div
                      key={s.id}
                      className={cn(
                        "group flex items-start gap-2.5 rounded-2xl border bg-white p-2.5 transition-all duration-300",
                        s.done ? "border-leaf-200 bg-leaf-100/40" : "border-milk-300 hover:border-brand-200 hover:shadow-soft",
                      )}
                    >
                      <button
                        type="button"
                        onClick={() =>
                          patch({ subtasks: form.subtasks.map((x) => (x.id === s.id ? { ...x, done: !x.done } : x)) })
                        }
                        className={cn(
                          "mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg border-2 transition-all duration-200",
                          s.done ? "border-leaf-500 bg-leaf-500 text-white" : "border-ink-200 bg-white text-transparent hover:border-leaf-400",
                        )}
                        aria-label="تغییر وضعیت زیرگروه"
                      >
                        <IconCheck className="h-3.5 w-3.5" strokeWidth={3} />
                      </button>
                      <div className="min-w-0 flex-1 space-y-1.5">
                        <input
                          value={s.title}
                          onChange={(e) => patch({ subtasks: form.subtasks.map((x) => (x.id === s.id ? { ...x, title: e.target.value } : x)) })}
                          placeholder={`عنوان زیرگروه ${fa(i + 1)}`}
                          className={cn("w-full rounded-lg border border-transparent bg-milk-100 px-2.5 py-1.5 text-[12px] font-bold text-ink-800 outline-none transition focus:border-brand-300 focus:bg-white", s.done && "text-ink-400 line-through")}
                        />
                        <input
                          value={s.note ?? ""}
                          onChange={(e) => patch({ subtasks: form.subtasks.map((x) => (x.id === s.id ? { ...x, note: e.target.value } : x)) })}
                          placeholder="یادداشت اختیاری زیرگروه…"
                          className="w-full rounded-lg border border-transparent bg-milk-100/70 px-2.5 py-1 text-[11px] text-ink-600 outline-none transition focus:border-brand-300 focus:bg-white"
                        />
                        <div className="flex items-center gap-2">
                          <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-dashed border-brand-300 bg-brand-50 px-2 py-1 text-[10.5px] font-bold text-brand-700 transition hover:bg-brand-100">
                            <IconImage className="h-3.5 w-3.5" />
                            {s.image ? "تعویض تصویر" : "افزودن تصویر"}
                            <input
                              type="file"
                              accept="image/*"
                              className="hidden"
                              onChange={(e) => {
                                const f = e.target.files?.[0];
                                if (!f) return;
                                if (f.size > 4_000_000) {
                                  toast("حجم تصویر باید کمتر از ۴ مگابایت باشد", "warn");
                                  return;
                                }
                                readFile(f, (url) =>
                                  patch({ subtasks: form.subtasks.map((x) => (x.id === s.id ? { ...x, image: url } : x)) }),
                                );
                              }}
                            />
                          </label>
                          {s.image && (
                            <span className="relative">
                              <img src={s.image} alt={s.title} className="h-9 w-14 rounded-lg border border-milk-300 object-cover" />
                              <button
                                type="button"
                                onClick={() => patch({ subtasks: form.subtasks.map((x) => (x.id === s.id ? { ...x, image: undefined } : x)) })}
                                className="absolute -end-1.5 -top-1.5 grid h-4 w-4 place-items-center rounded-full bg-ember-500 text-white opacity-0 transition group-hover:opacity-100"
                                aria-label="حذف تصویر"
                              >
                                <IconX className="h-2.5 w-2.5" />
                              </button>
                            </span>
                          )}
                        </div>
                      </div>
                      <button
                        type="button"
                        onClick={() => patch({ subtasks: form.subtasks.filter((x) => x.id !== s.id) })}
                        className="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg text-ink-300 transition hover:bg-ember-100 hover:text-ember-600"
                        aria-label="حذف زیرگروه"
                      >
                        <IconTrash className="h-4 w-4" />
                      </button>
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={() => patch({ subtasks: [...form.subtasks, { id: uid("st"), title: "", done: false }] })}
                    className="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-milk-400 bg-milk-100/60 py-2.5 text-[11.5px] font-bold text-ink-500 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                  >
                    <IconPlus className="h-4 w-4" />
                    افزودن زیرگروه
                  </button>
                </div>
              </Field>

              {/* dependencies */}
              <Field
                label="وابستگی‌ها"
                hint={`${fa(form.dependencies.length)} وابستگی فعال`}
                error={form.dependencies.some((d) => tasks.find((t) => t.id === d)?.status === "blocked") ? "یکی از پیش‌نیازها مسدود است" : undefined}
              >
                <div className="max-h-40 space-y-1.5 overflow-y-auto rounded-2xl border border-milk-300 bg-milk-100/50 p-2">
                  {tasks
                    .filter((t) => t.id !== editing)
                    .map((t) => {
                      const on = form.dependencies.includes(t.id);
                      return (
                        <button
                          key={t.id}
                          type="button"
                          onClick={() => patch({ dependencies: on ? form.dependencies.filter((d) => d !== t.id) : [...form.dependencies, t.id] })}
                          className={cn(
                            "flex w-full items-center gap-2 rounded-xl px-2.5 py-1.5 text-start transition-all duration-200",
                            on ? "bg-brand-600 text-white shadow-soft" : "bg-white/70 text-ink-600 hover:bg-white",
                          )}
                        >
                          <span className={cn("grid h-4 w-4 place-items-center rounded border", on ? "border-white bg-white text-brand-700" : "border-ink-200")}>
                            {on && <IconCheck className="h-3 w-3" strokeWidth={3} />}
                          </span>
                          <span className="font-num text-[10px] tabular opacity-70">{t.code}</span>
                          <span className="flex-1 truncate text-[11.5px] font-bold">{t.title}</span>
                          <i className="h-2 w-2 shrink-0 rounded-full" style={{ background: STATUS_META[t.status].hex }} />
                        </button>
                      );
                    })}
                </div>
              </Field>

              {/* tags */}
              <Field label="برچسب‌ها" hint="با کلید Enter اضافه کنید">
                <div className="flex flex-wrap items-center gap-1.5 rounded-xl border border-milk-300 bg-white p-2">
                  {form.tags.map((t) => (
                    <span key={t} className="inline-flex items-center gap-1 rounded-lg bg-sun-100 px-2 py-1 text-[11px] font-bold text-sun-600 ring-1 ring-sun-300">
                      {t}
                      <button type="button" onClick={() => patch({ tags: form.tags.filter((x) => x !== t) })} className="transition hover:text-ember-600" aria-label={`حذف ${t}`}>
                        <IconX className="h-3 w-3" />
                      </button>
                    </span>
                  ))}
                  <input
                    value={tagDraft}
                    onChange={(e) => setTagDraft(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === "Enter" && tagDraft.trim()) {
                        e.preventDefault();
                        if (!form.tags.includes(tagDraft.trim())) patch({ tags: [...form.tags, tagDraft.trim()] });
                        setTagDraft("");
                      }
                    }}
                    placeholder="برچسب جدید…"
                    className="min-w-[8rem] flex-1 bg-transparent px-1 py-1 text-[12px] font-semibold outline-none placeholder:text-ink-300"
                  />
                </div>
              </Field>

              {/* attachments */}
              <Field label="پیوست‌ها" hint={`${fa(form.attachments.length)} فایل`}>
                <div className="flex flex-wrap items-center gap-2">
                  <label className="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-brand-300 bg-brand-50 px-3 py-2 text-[11.5px] font-bold text-brand-700 transition hover:bg-brand-100">
                    <IconClip className="h-4 w-4" />
                    انتخاب فایل یا تصویر
                    <input
                      type="file"
                      multiple
                      className="hidden"
                      onChange={(e) => {
                        const files = Array.from(e.target.files ?? []);
                        files.forEach((f) => {
                          const isImg = f.type.startsWith("image/");
                          const attach = (url: string) =>
                            patch({
                              attachments: [
                                ...form.attachments,
                                {
                                  id: uid("at"),
                                  name: f.name,
                                  url,
                                  kind: isImg ? "image" : "file",
                                  size: `${fa(Math.max(1, Math.round(f.size / 1024)))} کیلوبایت`,
                                },
                              ],
                            });
                          if (isImg) readFile(f, attach);
                          else attach("#");
                        });
                        e.target.value = "";
                      }}
                    />
                  </label>
                  {form.attachments.map((a) => (
                    <span key={a.id} className="group inline-flex items-center gap-2 rounded-xl border border-milk-300 bg-white px-2 py-1.5 text-[11px] font-bold text-ink-600">
                      {a.kind === "image" ? (
                        <img src={a.url} alt={a.name} className="h-6 w-8 rounded object-cover" />
                      ) : (
                        <IconClip className="h-4 w-4 text-ink-400" />
                      )}
                      <span className="max-w-[9rem] truncate">{a.name}</span>
                      <button type="button" onClick={() => patch({ attachments: form.attachments.filter((x) => x.id !== a.id) })} className="text-ink-300 transition hover:text-ember-500" aria-label="حذف پیوست">
                        <IconX className="h-3 w-3" />
                      </button>
                    </span>
                  ))}
                </div>
              </Field>

              {/* live preview */}
              <div className="rounded-2xl border border-milk-300 bg-gradient-to-l from-milk-100 to-white p-3.5">
                <p className="mb-2.5 inline-flex items-center gap-2 text-[11px] font-extrabold text-ink-500">
                  <IconSpark className="h-4 w-4 text-sun-500" />
                  پیش‌نمایش زنده کارت تسک
                </p>
                <div className="overflow-hidden rounded-xl border border-milk-300 bg-white shadow-soft">
                  <div className="flex items-center gap-2 border-b border-milk-200 px-3 py-2" style={{ background: `${projectOf(preview.project).hex}12` }}>
                    <span className="font-num text-[10.5px] font-bold tabular" style={{ color: projectOf(preview.project).hex }}>
                      {preview.code}
                    </span>
                    <span className="h-3 w-px bg-ink-200" />
                    <span className="truncate text-[10.5px] font-bold text-ink-500">{preview.project}</span>
                    <Badge className={cn("ms-auto ring-1", SLA_TONE[previewSla.tone].chip)}>{SLA_TONE[previewSla.tone].label}</Badge>
                  </div>
                  <div className="p-3">
                    <p className="text-[13px] font-extrabold leading-6 text-ink-900">{preview.title}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-[10.5px] font-bold text-ink-500">
                      <Badge className={STATUS_META[preview.status].chip} dot={STATUS_META[preview.status].hex}>
                        {STATUS_META[preview.status].label}
                      </Badge>
                      <Badge className={PRIORITY_META[preview.priority].chip} dot={PRIORITY_META[preview.priority].hex}>
                        اولویت {PRIORITY_META[preview.priority].label}
                      </Badge>
                      <span className="inline-flex items-center gap-1">
                        <Avatar name={preview.assignee} size="xs" />
                        {preview.assignee}
                      </span>
                      <span className="inline-flex items-center gap-1">
                        <IconCalendar className="h-3.5 w-3.5" />
                        {fmtDate(preview.start)} → {fmtDate(preview.due)}
                      </span>
                    </div>
                    <div className="mt-2.5 flex items-center gap-2">
                      <ProgressBar value={preview.progress} hex={SLA_TONE[previewSla.tone].hex} height={7} />
                      <span className="font-num text-[10.5px] tabular text-ink-500">{fa(preview.progress)}٪</span>
                    </div>
                    {preview.subtasks.filter((s) => s.image).length > 0 && (
                      <div className="mt-2.5 flex gap-1.5">
                        {preview.subtasks
                          .filter((s) => s.image)
                          .slice(0, 5)
                          .map((s) => (
                            <img key={s.id} src={s.image} alt={s.title} className="h-11 w-16 rounded-lg border border-milk-300 object-cover" />
                          ))}
                      </div>
                    )}
                  </div>
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-2 border-t border-milk-200 pt-4">
                <button
                  onClick={submit}
                  className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-l from-brand-600 to-brand-500 px-4 py-3 text-[13px] font-extrabold text-white shadow-lift transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_18px_34px_-14px_rgba(26,92,214,.85)]"
                >
                  {editing ? <IconCheck className="h-4 w-4" /> : <IconPlus className="h-4 w-4" />}
                  {editing ? "ذخیره تغییرات تسک" : "ثبت تسک در پروژه"}
                </button>
                <button
                  onClick={() => {
                    setForm(emptyForm());
                    setEditing(null);
                    setErrors({});
                    toast("فرم پاک شد", "info");
                  }}
                  className="rounded-xl border border-milk-300 bg-white px-4 py-3 text-[12.5px] font-bold text-ink-500 transition hover:border-ember-300 hover:text-ember-600"
                >
                  پاک‌کردن فرم
                </button>
              </div>
            </div>
          </Panel>
        </div>

        {/* ---------------- RIGHT PANELS ---------------- */}
        <div className="space-y-4 xl:col-span-7">
          <div className="flex flex-wrap items-center gap-2">
            {[
              { id: "list", label: `فهرست تسک‌ها (${fa(tasks.length)})`, icon: <IconChecklist className="h-4 w-4" /> },
              { id: "deps", label: "نقشه وابستگی‌ها", icon: <IconLink className="h-4 w-4" /> },
              { id: "subs", label: "زیرگروه‌ها و تصاویر", icon: <IconImage className="h-4 w-4" /> },
              { id: "settings", label: "تنظیمات", icon: <IconSettings className="h-4 w-4" /> },
            ].map((t) => (
              <button
                key={t.id}
                onClick={() => setPanel(t.id as never)}
                className={cn(
                  "inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[12px] font-extrabold transition-all duration-300",
                  panel === t.id
                    ? "bg-ink-900 text-milk-50 shadow-lift"
                    : "border border-milk-300 bg-milk-50 text-ink-500 hover:-translate-y-0.5 hover:border-brand-300 hover:text-brand-700",
                )}
              >
                <span className={panel === t.id ? "text-sun-300" : "text-ink-400"}>{t.icon}</span>
                {t.label}
              </button>
            ))}
          </div>

          {panel === "list" && (
            <Panel
              bodyClass="p-0"
              title="مدیریت تسک‌ها"
              subtitle={`${fa(filtered.length)} نتیجه — برای دیدن جزئیات روی هر ردیف کلیک کنید`}
              icon={<IconFilter className="h-[18px] w-[18px]" />}
              action={
                picked.length > 0 ? (
                  <div className="flex items-center gap-2">
                    <span className="font-num text-[11px] font-bold tabular text-ink-500">{fa(picked.length)} انتخاب</span>
                    <select
                      onChange={(e) => {
                        const s = e.target.value as Status;
                        picked.forEach((id) => updateTask(id, { status: s }, true));
                        toast(`وضعیت ${fa(picked.length)} تسک به «${STATUS_META[s].label}» تغییر کرد`, "ok");
                        setPicked([]);
                        e.target.value = "";
                      }}
                      defaultValue=""
                      className="rounded-lg border border-milk-300 bg-white px-2 py-1.5 text-[11px] font-bold text-ink-600 outline-none"
                    >
                      <option value="" disabled>
                        تغییر گروهی…
                      </option>
                      {STATUS_ORDER.map((s) => (
                        <option key={s} value={s}>
                          {STATUS_META[s].label}
                        </option>
                      ))}
                    </select>
                    <button
                      onClick={() => {
                        picked.forEach((id) => removeTask(id));
                        setPicked([]);
                      }}
                      className="inline-flex items-center gap-1 rounded-lg bg-ember-500 px-2.5 py-1.5 text-[11px] font-bold text-white transition hover:bg-ember-600"
                    >
                      <IconTrash className="h-3.5 w-3.5" />
                      حذف
                    </button>
                  </div>
                ) : undefined
              }
            >
              {/* filters */}
              <div className="flex flex-wrap items-center gap-2 border-b border-milk-200 bg-milk-100/60 p-3.5">
                <div className="flex items-center gap-2 rounded-xl border border-milk-300 bg-white px-3 py-2">
                  <IconSearch className="h-4 w-4 text-ink-300" />
                  <input
                    value={q}
                    onChange={(e) => {
                      setQ(e.target.value);
                      setOnlyOverdue(false);
                    }}
                    placeholder="جستجو در عنوان، کد، برچسب…"
                    className="w-44 bg-transparent text-[12px] font-semibold outline-none placeholder:text-ink-300"
                  />
                </div>
                <select value={fProject} onChange={(e) => setFProject(e.target.value)} className="rounded-xl border border-milk-300 bg-white px-3 py-2 text-[11.5px] font-bold text-ink-600 outline-none">
                  <option value="all">همه پروژه‌ها</option>
                  {PROJECTS.map((p) => (
                    <option key={p.code} value={p.name}>
                      {p.name}
                    </option>
                  ))}
                </select>
                <Chip active={fStatus === "all"} onClick={() => setFStatus("all")}>
                  همه وضعیت‌ها
                </Chip>
                {STATUS_ORDER.map((s) => (
                  <Chip key={s} active={fStatus === s} hex={STATUS_META[s].hex} onClick={() => setFStatus(s)}>
                    {STATUS_META[s].label}
                  </Chip>
                ))}
                <Chip active={onlyOverdue} hex="#ef6211" onClick={() => setOnlyOverdue((v) => !v)}>
                  فقط عقب‌افتاده
                </Chip>
                <div className="ms-auto flex items-center gap-1.5">
                  {(["due", "priority", "progress", "code"] as const).map((k) => (
                    <button
                      key={k}
                      onClick={() => setSort((s) => ({ key: k, dir: s.key === k && s.dir === 1 ? -1 : 1 }))}
                      className={cn(
                        "rounded-lg px-2.5 py-1.5 text-[10.5px] font-bold transition",
                        sort.key === k ? "bg-brand-600 text-white" : "bg-white text-ink-400 ring-1 ring-milk-300 hover:text-brand-700",
                      )}
                    >
                      {{ due: "سررسید", priority: "اولویت", progress: "پیشرفت", code: "کد" }[k]}
                      {sort.key === k && <span className="ms-1">{sort.dir === 1 ? "↑" : "↓"}</span>}
                    </button>
                  ))}
                </div>
              </div>

              {/* rows */}
              {filtered.length === 0 ? (
                <div className="p-5">
                  <Empty text="تسکی با این فیلترها پیدا نشد" hint="عبارت جستجو یا فیلتر وضعیت را تغییر دهید" />
                </div>
              ) : (
                <ul className="divide-y divide-milk-200">
                  {filtered.map((t) => {
                    const l = leftover(t.due, t.status === "done", now);
                    const sla = slaOf(t, now);
                    const isOpen = expanded.includes(t.id);
                    const doneSubs = t.subtasks.filter((s) => s.done).length;
                    return (
                      <li key={t.id} className="group bg-white/40 transition-colors hover:bg-sun-100/25">
                        <div className="flex items-center gap-3 px-3.5 py-3">
                          <button
                            onClick={() => setPicked((p) => (p.includes(t.id) ? p.filter((x) => x !== t.id) : [...p, t.id]))}
                            className={cn(
                              "grid h-5 w-5 shrink-0 place-items-center rounded-md border-2 transition",
                              picked.includes(t.id) ? "border-brand-600 bg-brand-600 text-white" : "border-ink-200 text-transparent hover:border-brand-400",
                            )}
                            aria-label="انتخاب تسک"
                          >
                            <IconCheck className="h-3 w-3" strokeWidth={3} />
                          </button>

                          <button onClick={() => setExpanded((e) => (isOpen ? e.filter((x) => x !== t.id) : [...e, t.id]))} className="flex min-w-0 flex-1 items-center gap-3 text-start">
                            <span className="h-9 w-1.5 shrink-0 rounded-full" style={{ background: PRIORITY_META[t.priority].hex }} />
                            <span className="min-w-0 flex-1">
                              <span className="flex items-center gap-2">
                                <span className="font-num text-[10.5px] font-bold tabular text-brand-600">{t.code}</span>
                                <span className="truncate text-[12.5px] font-extrabold text-ink-900">{t.title}</span>
                                {t.subtasks.length > 0 && (
                                  <span className="hidden shrink-0 items-center gap-1 rounded-md bg-milk-200 px-1.5 py-0.5 text-[9.5px] font-bold text-ink-500 sm:inline-flex">
                                    <IconLayers className="h-3 w-3" />
                                    {fa(doneSubs)}/{fa(t.subtasks.length)}
                                  </span>
                                )}
                                {t.dependencies.length > 0 && (
                                  <span className="hidden shrink-0 items-center gap-1 rounded-md bg-brand-50 px-1.5 py-0.5 text-[9.5px] font-bold text-brand-600 sm:inline-flex">
                                    <IconLink className="h-3 w-3" />
                                    {fa(t.dependencies.length)}
                                  </span>
                                )}
                              </span>
                              <span className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10.5px] font-semibold text-ink-400">
                                <span className="inline-flex items-center gap-1.5">
                                  <Avatar name={t.assignee} size="xs" />
                                  {t.assignee}
                                </span>
                                <span style={{ color: projectOf(t.project).hex }}>{t.project}</span>
                                <span className={cn("inline-flex items-center gap-1", l.tone === "danger" ? "font-bold text-ember-600" : l.tone === "warn" ? "text-sun-600" : undefined)}>
                                  <IconClock className="h-3.5 w-3.5" />
                                  {t.status === "done" ? "تحویل شد" : l.overdue ? `${l.label} تأخیر` : `${l.label} مانده`}
                                </span>
                              </span>
                            </span>
                            <IconChevron className={cn("h-4 w-4 shrink-0 text-ink-300 transition-transform duration-300", isOpen && "-rotate-180 text-brand-600")} />
                          </button>

                          <div className="hidden w-28 shrink-0 md:block">
                            <select
                              value={t.status}
                              onChange={(e) => updateTask(t.id, { status: e.target.value as Status })}
                              className={cn("w-full rounded-lg px-2 py-1.5 text-[10.5px] font-extrabold outline-none transition", STATUS_META[t.status].chip)}
                            >
                              {STATUS_ORDER.map((s) => (
                                <option key={s} value={s}>
                                  {STATUS_META[s].label}
                                </option>
                              ))}
                            </select>
                          </div>

                          <div className="hidden w-24 shrink-0 sm:block">
                            <ProgressBar value={t.progress} hex={SLA_TONE[sla.tone].hex} height={7} />
                            <p className="mt-1 font-num text-[9.5px] tabular text-ink-400">{fa(t.progress)}٪ پیشرفت</p>
                          </div>

                          <div className="flex shrink-0 items-center gap-1">
                            <button onClick={() => startEdit(t)} className="grid h-8 w-8 place-items-center rounded-lg border border-milk-300 bg-white text-ink-400 transition hover:-translate-y-0.5 hover:border-brand-300 hover:text-brand-600" aria-label="ویرایش">
                              <IconPencil className="h-4 w-4" />
                            </button>
                            <button onClick={() => navigate({ page: "board", tab: "kanban", q: t.code })} className="grid h-8 w-8 place-items-center rounded-lg border border-milk-300 bg-white text-ink-400 transition hover:-translate-y-0.5 hover:border-leaf-300 hover:text-leaf-600" aria-label="نمایش در بورد">
                              <IconBoard className="h-4 w-4" />
                            </button>
                            <button onClick={() => setConfirmDel(t)} className="grid h-8 w-8 place-items-center rounded-lg border border-milk-300 bg-white text-ink-400 transition hover:-translate-y-0.5 hover:border-ember-300 hover:text-ember-600" aria-label="حذف">
                              <IconTrash className="h-4 w-4" />
                            </button>
                          </div>
                        </div>

                        {/* expanded detail */}
                        <div className={cn("grid transition-all duration-400 ease-[cubic-bezier(.22,1,.36,1)]", isOpen ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0")}>
                          <div className="overflow-hidden">
                            <div className="border-t border-dashed border-milk-300 bg-milk-100/60 px-4 py-4">
                              <div className="grid gap-4 lg:grid-cols-3">
                                <div className="lg:col-span-2">
                                  <p className="text-[11px] font-extrabold text-ink-500">توضیحات</p>
                                  <p className="mt-1 text-[12px] leading-6 text-ink-700">{t.description}</p>

                                  {t.subtasks.length > 0 && (
                                    <>
                                      <p className="mt-4 text-[11px] font-extrabold text-ink-500">
                                        زیرگروه‌ها ({fa(doneSubs)} از {fa(t.subtasks.length)} انجام‌شده)
                                      </p>
                                      <ul className="mt-2 grid gap-2 sm:grid-cols-2">
                                        {t.subtasks.map((s) => (
                                          <li key={s.id} className={cn("flex items-start gap-2 rounded-xl border bg-white p-2", s.done ? "border-leaf-200" : "border-milk-300")}>
                                            <button
                                              onClick={() => updateTask(t.id, { subtasks: t.subtasks.map((x) => (x.id === s.id ? { ...x, done: !x.done } : x)) }, true)}
                                              className={cn("mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-md border-2 transition", s.done ? "border-leaf-500 bg-leaf-500 text-white" : "border-ink-200 text-transparent hover:border-leaf-400")}
                                              aria-label="تغییر وضعیت"
                                            >
                                              <IconCheck className="h-3 w-3" strokeWidth={3} />
                                            </button>
                                            <div className="min-w-0 flex-1">
                                              <p className={cn("text-[11.5px] font-bold text-ink-800", s.done && "text-ink-400 line-through")}>{s.title}</p>
                                              {s.note && <p className="mt-0.5 text-[10.5px] text-ink-400">{s.note}</p>}
                                            </div>
                                            {s.image && <img src={s.image} alt={s.title} className="h-10 w-14 shrink-0 rounded-lg border border-milk-300 object-cover transition hover:scale-105" />}
                                          </li>
                                        ))}
                                      </ul>
                                    </>
                                  )}
                                </div>

                                <div className="space-y-3">
                                  <div className="flex items-center gap-3 rounded-xl border border-milk-300 bg-white p-3">
                                    <Ring value={sla.compliance} size={58} stroke={7} hex={SLA_TONE[sla.tone].hex}>
                                      <span className="font-num text-[11px] font-bold tabular text-ink-800">{fa(sla.compliance)}</span>
                                    </Ring>
                                    <div>
                                      <p className="text-[11px] font-extrabold text-ink-700">پایبندی SLA</p>
                                      <p className="mt-0.5 text-[10.5px] text-ink-400">پنجره {fa(t.slaHours)} ساعت</p>
                                      <p className="mt-0.5 text-[10.5px] font-bold" style={{ color: SLA_TONE[sla.tone].hex }}>
                                        انتظار {fa(sla.expected)}٪ / واقعی {fa(t.progress)}٪
                                      </p>
                                    </div>
                                  </div>

                                  <div>
                                    <p className="text-[11px] font-extrabold text-ink-500">وابستگی‌ها</p>
                                    <div className="mt-1.5 space-y-1.5">
                                      {t.dependencies.length === 0 && <p className="text-[10.5px] text-ink-300">بدون پیش‌نیاز</p>}
                                      {t.dependencies.map((d) => {
                                        const dep = tasks.find((x) => x.id === d);
                                        if (!dep) return null;
                                        return (
                                          <div key={d} className="flex items-center gap-2 rounded-lg border border-milk-300 bg-white px-2 py-1.5">
                                            <IconLink className="h-3.5 w-3.5 shrink-0 text-brand-500" />
                                            <span className="font-num text-[10px] tabular text-brand-600">{dep.code}</span>
                                            <span className="flex-1 truncate text-[10.5px] font-bold text-ink-600">{dep.title}</span>
                                            <i className="h-2 w-2 rounded-full" style={{ background: STATUS_META[dep.status].hex }} />
                                          </div>
                                        );
                                      })}
                                    </div>
                                  </div>

                                  {t.attachments.length > 0 && (
                                    <div>
                                      <p className="text-[11px] font-extrabold text-ink-500">پیوست‌ها</p>
                                      <div className="mt-1.5 flex flex-wrap gap-1.5">
                                        {t.attachments.map((a) =>
                                          a.kind === "image" ? (
                                            <img key={a.id} src={a.url} alt={a.name} className="h-12 w-16 rounded-lg border border-milk-300 object-cover" />
                                          ) : (
                                            <span key={a.id} className="inline-flex items-center gap-1.5 rounded-lg border border-milk-300 bg-white px-2 py-1.5 text-[10.5px] font-bold text-ink-600">
                                              <IconClip className="h-3.5 w-3.5 text-ink-400" />
                                              {a.name}
                                            </span>
                                          ),
                                        )}
                                      </div>
                                    </div>
                                  )}

                                  <div className="flex flex-wrap gap-1.5">
                                    {t.tags.map((g) => (
                                      <span key={g} className="rounded-lg bg-milk-200 px-2 py-1 text-[10px] font-bold text-ink-600">
                                        #{g}
                                      </span>
                                    ))}
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              )}
            </Panel>
          )}

          {panel === "deps" && <DependencyMap />}
          {panel === "subs" && <SubGallery />}
          {panel === "settings" && <SettingsPanel />}
        </div>
      </div>

      <Modal
        open={!!confirmDel}
        onClose={() => setConfirmDel(null)}
        title="حذف تسک"
        subtitle={confirmDel ? `${confirmDel.code} — ${confirmDel.title}` : ""}
        footer={
          <>
            <p className="text-[11.5px] font-bold text-ink-500">وابستگی‌های این تسک از سایر تسک‌ها حذف می‌شود.</p>
            <div className="flex gap-2">
              <button onClick={() => setConfirmDel(null)} className="rounded-xl border border-milk-300 bg-white px-4 py-2.5 text-[12px] font-bold text-ink-600 transition hover:border-ink-300">
                انصراف
              </button>
              <button
                onClick={() => {
                  if (confirmDel) removeTask(confirmDel.id);
                  setConfirmDel(null);
                }}
                className="inline-flex items-center gap-2 rounded-xl bg-ember-500 px-4 py-2.5 text-[12px] font-extrabold text-white shadow-lift transition hover:bg-ember-600"
              >
                <IconTrash className="h-4 w-4" />
                حذف قطعی
              </button>
            </div>
          </>
        }
      >
        <div className="rounded-2xl border border-ember-200 bg-ember-100/50 p-4 text-[12.5px] leading-6 text-ink-700">
          این عملیات قابل بازگشت نیست. آیا از حذف تسک مطمئن هستید؟
        </div>
      </Modal>
    </div>
  );
}

/* ---------------- small building blocks ---------------- */
const inputCls = (err: boolean) =>
  cn(
    "w-full rounded-xl border bg-white px-3 py-2.5 text-[12.5px] font-semibold text-ink-800 outline-none transition-all duration-200 placeholder:text-ink-300",
    err ? "border-ember-400 bg-ember-100/30 ring-2 ring-ember-200" : "border-milk-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-100",
  );

function Field({
  label,
  children,
  error,
  hint,
  required,
}: {
  label: string;
  children: React.ReactNode;
  error?: string;
  hint?: string;
  required?: boolean;
}) {
  return (
    <div>
      <div className="mb-1.5 flex items-baseline justify-between gap-2">
        <label className="text-[11.5px] font-extrabold text-ink-700">
          {label}
          {required && <span className="ms-1 text-ember-500">*</span>}
        </label>
        {hint && !error && <span className="text-[10px] font-semibold text-ink-400">{hint}</span>}
      </div>
      {children}
      {error && (
        <p className="mt-1.5 inline-flex items-center gap-1.5 text-[10.5px] font-bold text-ember-600">
          <IconAlert className="h-3.5 w-3.5" />
          {error}
        </p>
      )}
    </div>
  );
}

function Slider({
  label,
  value,
  min,
  max,
  step,
  onChange,
  display,
}: {
  label: string;
  value: number;
  min: number;
  max: number;
  step: number;
  onChange: (v: number) => void;
  display: string;
}) {
  return (
    <div>
      <div className="mb-1.5 flex items-baseline justify-between">
        <span className="text-[11px] font-bold text-ink-600">{label}</span>
        <span className="rounded-md bg-white px-2 py-0.5 font-num text-[11px] font-bold tabular text-brand-700 ring-1 ring-brand-100">{display}</span>
      </div>
      <input type="range" min={min} max={max} step={step} value={value} onChange={(e) => onChange(Number(e.target.value))} className="range-sun w-full" />
    </div>
  );
}

/* ---------------- dependency map ---------------- */
function DependencyMap() {
  const { tasks, now } = useStore();
  const edges = tasks.flatMap((t) =>
    t.dependencies.map((d) => ({ from: tasks.find((x) => x.id === d), to: t })).filter((e) => e.from),
  ) as { from: Task; to: Task }[];

  const blocked = edges.filter((e) => e.from.status === "blocked" || leftover(e.from.due, e.from.status === "done", now).overdue);
  const orphans = tasks.filter((t) => t.dependencies.length === 0 && !tasks.some((x) => x.dependencies.includes(t.id)));

  return (
    <div className="space-y-4">
      <Panel
        title="زنجیره وابستگی‌ها"
        subtitle={`${fa(edges.length)} رابطه — ${fa(blocked.length)} رابطه دارای ریسک`}
        icon={<IconLink className="h-[18px] w-[18px]" />}
        accent="#1a5cd6"
      >
        <div className="space-y-2.5">
          {edges.map((e, i) => {
            const risky = e.from.status === "blocked" || leftover(e.from.due, e.from.status === "done", now).overdue;
            return (
              <Reveal key={`${e.from.id}-${e.to.id}`} delay={i * 40}>
                <div
                  className={cn(
                    "flex flex-wrap items-center gap-2 rounded-2xl border p-3 transition-all duration-300 hover:shadow-soft",
                    risky ? "border-ember-200 bg-ember-100/40" : "border-milk-300 bg-white/70",
                  )}
                >
                  <DepNode task={e.from} />
                  <span className={cn("flex items-center gap-1 px-1 text-[10.5px] font-bold", risky ? "text-ember-600" : "text-ink-400")}>
                    <span className="h-px w-6 bg-current" />
                    <IconChevron className="h-4 w-4 -rotate-90" />
                    <span className="h-px w-6 bg-current" />
                  </span>
                  <DepNode task={e.to} />
                  {risky && (
                    <Badge className="ms-auto bg-ember-500 text-white ring-1 ring-ember-600">
                      <IconAlert className="h-3.5 w-3.5" />
                      پیش‌نیاز در تأخیر
                    </Badge>
                  )}
                </div>
              </Reveal>
            );
          })}
        </div>
      </Panel>

      <Panel title="تسک‌های مستقل" subtitle="بدون وابستگی ورودی یا خروجی" icon={<IconLayers className="h-[18px] w-[18px]" />}>
        {orphans.length === 0 ? (
          <Empty text="همه تسک‌ها حداقل یک وابستگی دارند" />
        ) : (
          <div className="flex flex-wrap gap-2">
            {orphans.map((t) => (
              <span key={t.id} className="inline-flex items-center gap-2 rounded-xl border border-milk-300 bg-white px-3 py-2 text-[11.5px] font-bold text-ink-600">
                <span className="font-num text-[10px] tabular text-brand-600">{t.code}</span>
                {t.title}
              </span>
            ))}
          </div>
        )}
      </Panel>
    </div>
  );
}

const DepNode = ({ task }: { task: Task }) => (
  <span className="inline-flex min-w-0 items-center gap-2 rounded-xl px-2.5 py-1.5" style={{ background: `${STATUS_META[task.status].hex}14` }}>
    <i className="h-2 w-2 shrink-0 rounded-full" style={{ background: STATUS_META[task.status].hex }} />
    <span className="font-num text-[10px] font-bold tabular text-ink-500">{task.code}</span>
    <span className="max-w-[13rem] truncate text-[11.5px] font-extrabold text-ink-800">{task.title}</span>
  </span>
);

/* ---------------- subtask / image gallery ---------------- */
function SubGallery() {
  const { tasks, updateTask } = useStore();
  const withImage = tasks.flatMap((t) => t.subtasks.filter((s) => s.image).map((s) => ({ t, s })));
  const noImage = tasks.flatMap((t) => t.subtasks.filter((s) => !s.image).map((s) => ({ t, s })));

  return (
    <div className="space-y-4">
      <Panel
        title="گالری تصاویر زیرگروه‌ها"
        subtitle={`${fa(withImage.length)} تصویر مستند — کلیک روی تصویر وضعیت زیرگروه را برعکس می‌کند`}
        icon={<IconImage className="h-[18px] w-[18px]" />}
        accent="#16a157"
      >
        {withImage.length === 0 ? (
          <Empty text="هنوز تصویری به زیرگروه‌ها اضافه نشده" hint="در فرم ساخت تسک، برای هر زیرگروه تصویر بارگذاری کنید" />
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {withImage.map(({ t, s }, i) => (
              <Reveal key={s.id} delay={i * 45}>
                <figure className="lift group overflow-hidden rounded-2xl border border-milk-300 bg-white shadow-soft">
                  <div className="relative h-32 overflow-hidden">
                    <img src={s.image} alt={s.title} className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                    <span className="absolute inset-0 bg-gradient-to-t from-ink-950/70 via-transparent to-transparent" />
                    <span className="absolute bottom-2 start-2 font-num text-[10px] font-bold tabular text-sun-300">{t.code}</span>
                    <button
                      onClick={() => updateTask(t.id, { subtasks: t.subtasks.map((x) => (x.id === s.id ? { ...x, done: !x.done } : x)) }, true)}
                      className={cn(
                        "absolute end-2 top-2 grid h-7 w-7 place-items-center rounded-lg backdrop-blur transition",
                        s.done ? "bg-leaf-500 text-white" : "bg-white/85 text-ink-400 hover:text-leaf-600",
                      )}
                      aria-label="تغییر وضعیت زیرگروه"
                    >
                      <IconCheck className="h-4 w-4" strokeWidth={3} />
                    </button>
                  </div>
                  <figcaption className="p-3">
                    <p className={cn("truncate text-[12px] font-extrabold text-ink-900", s.done && "text-ink-400 line-through")}>{s.title}</p>
                    <p className="mt-1 truncate text-[10.5px] text-ink-400">{t.title}</p>
                  </figcaption>
                </figure>
              </Reveal>
            ))}
          </div>
        )}
      </Panel>

      <Panel title="زیرگروه‌های بدون تصویر" subtitle={`${fa(noImage.length)} مورد — می‌توانید بعداً تصویر اضافه کنید`} icon={<IconChecklist className="h-[18px] w-[18px]" />}>
        <ul className="grid gap-2 sm:grid-cols-2">
          {noImage.map(({ t, s }) => (
            <li key={s.id} className="flex items-center gap-2 rounded-xl border border-milk-300 bg-white/70 px-3 py-2">
              <i className={cn("h-2 w-2 shrink-0 rounded-full", s.done ? "bg-leaf-500" : "bg-ink-200")} />
              <span className="font-num text-[10px] tabular text-brand-600">{t.code}</span>
              <span className={cn("flex-1 truncate text-[11.5px] font-bold text-ink-700", s.done && "text-ink-400 line-through")}>{s.title}</span>
              <span className="text-[9.5px] font-bold text-ink-300">بدون تصویر</span>
            </li>
          ))}
        </ul>
      </Panel>
    </div>
  );
}

/* ---------------- settings ---------------- */
function SettingsPanel() {
  const { toast } = useStore();
  const [slaTarget, setSlaTarget] = useState(90);
  const [warnHours, setWarnHours] = useState(48);
  const [workHours, setWorkHours] = useState(8);
  const [toggles, setToggles] = useState({ email: true, push: true, weekly: false, breach: true });

  const Row = ({ label, desc, children }: { label: string; desc: string; children: React.ReactNode }) => (
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-milk-200 py-3 last:border-0">
      <div>
        <p className="text-[12.5px] font-extrabold text-ink-800">{label}</p>
        <p className="mt-0.5 text-[11px] text-ink-400">{desc}</p>
      </div>
      {children}
    </div>
  );

  return (
    <Panel title="تنظیمات سامانه" subtitle="قواعد SLA، ساعت کاری و اطلاع‌رسانی" icon={<IconSettings className="h-[18px] w-[18px]" />} accent="#f9bb1b">
      <Row label="آستانه پایبندی SLA" desc="کمتر از این مقدار، پروژه قرمز گزارش می‌شود">
        <div className="flex items-center gap-3">
          <input type="range" min={60} max={100} value={slaTarget} onChange={(e) => setSlaTarget(Number(e.target.value))} className="range-sun w-40" />
          <span className="font-num w-12 rounded-lg bg-brand-50 px-2 py-1 text-center text-[11.5px] font-bold tabular text-brand-700 ring-1 ring-brand-100">{fa(slaTarget)}٪</span>
        </div>
      </Row>
      <Row label="هشدار سررسید نزدیک" desc="چند ساعت پیش از سررسید اعلان ارسال شود">
        <div className="flex items-center gap-3">
          <input type="range" min={6} max={120} step={6} value={warnHours} onChange={(e) => setWarnHours(Number(e.target.value))} className="range-sun w-40" />
          <span className="font-num w-16 rounded-lg bg-sun-100 px-2 py-1 text-center text-[11.5px] font-bold tabular text-sun-600 ring-1 ring-sun-300">{fa(warnHours)} ساعت</span>
        </div>
      </Row>
      <Row label="ساعت کاری روزانه" desc="مبنای محاسبه effort و بار کاری تیم">
        <div className="flex items-center gap-3">
          <input type="range" min={4} max={12} value={workHours} onChange={(e) => setWorkHours(Number(e.target.value))} className="range-sun w-40" />
          <span className="font-num w-14 rounded-lg bg-leaf-100 px-2 py-1 text-center text-[11.5px] font-bold tabular text-leaf-700 ring-1 ring-leaf-300">{fa(workHours)} ساعت</span>
        </div>
      </Row>
      <Row label="کانال‌های اطلاع‌رسانی" desc="اعلان‌ها روی کدام بسترها ارسال شود">
        <div className="flex flex-wrap gap-2">
          {[
            { k: "email" as const, label: "رایانامه" },
            { k: "push" as const, label: "پوش وب" },
            { k: "weekly" as const, label: "گزارش هفتگی" },
            { k: "breach" as const, label: "فقط نقض SLA" },
          ].map((o) => (
            <button
              key={o.k}
              onClick={() => setToggles((t) => ({ ...t, [o.k]: !t[o.k] }))}
              className={cn(
                "rounded-xl px-3 py-2 text-[11.5px] font-bold transition-all duration-300",
                toggles[o.k] ? "bg-ink-900 text-sun-300 shadow-soft" : "bg-milk-100 text-ink-400 hover:bg-milk-200",
              )}
            >
              {o.label}
            </button>
          ))}
        </div>
      </Row>
      <div className="mt-4 flex items-center justify-between gap-3 rounded-2xl border border-milk-300 bg-milk-100 p-3.5">
        <p className="text-[11px] font-semibold leading-5 text-ink-500">
          تغییرات به‌صورت پیش‌نمایش اعمال می‌شود و پس از ذخیره روی داشبورد و گزارش SLA اثر می‌گذارد.
        </p>
        <button
          onClick={() => toast(`تنظیمات ذخیره شد — آستانه SLA روی ${fa(slaTarget)}٪ تنظیم گردید`, "ok")}
          className="inline-flex shrink-0 items-center gap-2 rounded-xl bg-leaf-500 px-4 py-2.5 text-[12px] font-extrabold text-white shadow-lift transition hover:-translate-y-0.5 hover:bg-leaf-600"
        >
          <IconCheck className="h-4 w-4" />
          ذخیره تنظیمات
        </button>
      </div>
    </Panel>
  );
}
