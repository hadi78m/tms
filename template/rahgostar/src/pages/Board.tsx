import { useEffect, useMemo, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import {
  GripVertical,
  Clock3,
  Paperclip,
  Link2,
  ChevronLeft,
  ChevronRight,
  X,
  Save,
  CircleAlert,
} from "lucide-react";
import {
  type Task,
  type Status,
  STATUS,
  PRIORITY,
  COLUMNS,
  SLA_ALLOCATED,
  countdown,
  faNum,
  clock,
} from "../data";

function Chip({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10.5px] font-bold ${className}`}
    >
      {children}
    </span>
  );
}

function TaskCard({
  t,
  now,
  onOpen,
  onMove,
  dragging,
  setDragging,
}: {
  t: Task;
  now: number;
  onOpen: () => void;
  onMove: (dir: -1 | 1) => void;
  dragging: boolean;
  setDragging: (v: string | null) => void;
}) {
  const cd = countdown(t.due, now);
  const late = cd.over && t.status !== "done";
  const doneSteps = t.checklist.filter((c) => c.done).length;
  const idx = COLUMNS.findIndex((c) => c.key === t.status);

  return (
    <motion.article
      layout
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, scale: 0.96 }}
      transition={{ duration: 0.28 }}
      draggable
      onDragStart={() => setDragging(t.id)}
      onDragEnd={() => setDragging(null)}
      onDragOver={(e) => e.preventDefault()}
      onClick={onOpen}
      className={`group relative cursor-grab overflow-hidden rounded-xl border bg-cream-50 p-3.5 transition-all active:cursor-grabbing hover:-translate-y-0.5 hover:shadow-[0_16px_30px_-22px_rgba(7,27,54,0.9)] ${
        dragging ? "scale-[1.02] rotate-[-1deg] border-ink-500 opacity-70 shadow-[0_22px_40px_-20px_rgba(7,27,54,0.85)]" : "border-line"
      }`}
    >
      <span className={`absolute inset-y-0 right-0 w-[3px] ${PRIORITY[t.priority].color}`} />

      <div className="flex items-center gap-2">
        <GripVertical size={13} className="text-line group-hover:text-sand" />
        <span className="tnum text-[10.5px] font-semibold text-ink-500">{t.id}</span>
        <Chip className="border-transparent bg-cream-200 text-sand-dark">{t.group}</Chip>
        <span className="ms-auto flex items-center gap-1">
          {t.attachments.length > 0 && (
            <span className="tnum flex items-center gap-0.5 text-[10px] text-sand">
              <Paperclip size={11} />
              {t.attachments.length}
            </span>
          )}
          {t.deps.length > 0 && (
            <span className="tnum flex items-center gap-0.5 text-[10px] text-sand">
              <Link2 size={11} />
              {t.deps.length}
            </span>
          )}
        </span>
      </div>

      <h4 className="mt-2 text-[13.5px] font-black leading-snug text-ink-900">{t.title}</h4>

      <div className="mt-2.5 flex items-center gap-2">
        <span className="grid h-6 w-6 place-items-center rounded-md bg-ink-900 text-[9px] font-black text-cream-50">
          {t.assignee.slice(0, 1)}
        </span>
        <span className="truncate text-[11px] text-sand-dark">{t.assignee}</span>
        <span className={`ms-auto flex items-center gap-1 text-[11px] ${PRIORITY[t.priority].text}`}>
          <span className={`h-2 w-2 rounded-full ${PRIORITY[t.priority].color}`} />
          {PRIORITY[t.priority].label}
        </span>
      </div>

      {t.checklist.length > 0 && (
        <div className="mt-3">
          <div className="mb-1 flex justify-between text-[10px] text-sand">
            <span>چک‌لیست</span>
            <span className="tnum">
              {doneSteps}/{t.checklist.length}
            </span>
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-cream-200">
            <div
              className="h-full rounded-full bg-jade-600 transition-[width] duration-500"
              style={{ width: `${(doneSteps / t.checklist.length) * 100}%` }}
            />
          </div>
        </div>
      )}

      <div
        className={`mt-3 flex items-center gap-2 rounded-lg border px-2 py-1.5 ${
          late
            ? "border-flame-500/60 bg-flame-300/20"
            : t.status === "done"
              ? "border-jade-400/50 bg-jade-100"
              : "border-line bg-cream-100"
        }`}
      >
        <Clock3 size={13} className={late ? "text-flame-500" : "text-sand"} />
        <span className="micro text-sand">SLA</span>
        <span
          className={`tnum ms-auto text-[12px] font-semibold ${
            late ? "text-flame-500" : t.status === "done" ? "text-jade-600" : "text-ink-800"
          }`}
        >
          {t.status === "done" ? "بسته‌شده" : cd.text}
        </span>
        <span className="tnum text-[10px] text-sand">{clock(t.due)}</span>
      </div>

      <div className="mt-2.5 flex items-center justify-between border-t border-line pt-2">
        <span className="text-[10.5px] text-sand">
          شروع: <span className="tnum">{clock(t.start)}</span>
        </span>
        <span className="flex gap-1">
          <button
            disabled={idx === 0}
            onClick={(e) => {
              e.stopPropagation();
              onMove(1);
            }}
            className="rounded-md border border-line p-1 text-ink-700 transition hover:bg-ink-100 disabled:opacity-30"
            aria-label="انتقال به ستون قبلی"
          >
            <ChevronRight size={13} />
          </button>
          <button
            disabled={idx === COLUMNS.length - 1}
            onClick={(e) => {
              e.stopPropagation();
              onMove(-1);
            }}
            className="rounded-md border border-line p-1 text-ink-700 transition hover:bg-ink-100 disabled:opacity-30"
            aria-label="انتقال به ستون بعدی"
          >
            <ChevronLeft size={13} />
          </button>
        </span>
      </div>
    </motion.article>
  );
}

export default function Board({
  tasks,
  onPatch,
  onOpenTask,
}: {
  tasks: Task[];
  onPatch: (id: string, patch: Partial<Task>) => void;
  onOpenTask: (id: string) => void;
}) {
  const [now, setNow] = useState(Date.now());
  const [dragId, setDragId] = useState<string | null>(null);
  const [overCol, setOverCol] = useState<Status | null>(null);
  const [prio, setPrio] = useState<"all" | "high" | "med" | "low">("all");

  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);

  const sprintEnd = useMemo(() => Date.now() + 62 * 3600_000, []);
  const remain = countdown(sprintEnd, now);

  const mine = tasks.filter((t) => (prio === "all" ? true : t.priority === prio));
  const doneCount = tasks.filter((t) => t.status === "done").length;
  const ratio = tasks.length ? doneCount / tasks.length : 0;
  const spent = Math.round(tasks.filter((t) => t.status === "done").reduce((s, t) => s + t.estimate, 0));

  const move = (id: string, status: Status) => onPatch(id, { status });

  return (
    <div className="space-y-6">
      {/* وضعیت کلی پروژه + SLA */}
      <section className="overflow-hidden rounded-xl border border-line bg-cream-50 card-lift">
        <div className="grid gap-0 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
          <div className="border-line p-5">
            <div className="micro text-sand">PROJECT HEALTH · بازارک ۳٫۲</div>
            <div className="mt-2 flex items-end gap-3">
              <span className="text-[2.7rem] font-black leading-none text-ink-900 tnum">
                {Math.round(ratio * 100)}٪
              </span>
              <span className="mb-1.5 text-[12px] text-sand-dark">
                {faNum(doneCount)} از {faNum(tasks.length)} تسک بسته شده
              </span>
            </div>
            <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-cream-200">
              <motion.div
                animate={{ width: `${ratio * 100}%` }}
                transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
                className="h-full rounded-full bg-gradient-to-l from-jade-600 via-gold-500 to-flame-500"
              />
            </div>
            <p className="mt-2.5 text-[11.5px] text-sand">
              وضعیت کلی: <b className="text-jade-600">در مسیر</b> · ریسک اصلی، وابستگی تسک‌های بک‌اند
            </p>
          </div>

          {[
            { k: "SLA اختصاص‌یافته", v: `${faNum(SLA_ALLOCATED)}h`, s: "در هفته برای شما", hot: false },
            {
              k: "زمان صرف‌شده",
              v: `${faNum(spent)}h`,
              s: `${faNum(Math.min(100, Math.round((spent / SLA_ALLOCATED) * 100)))}٪ از سهمیه`,
              hot: spent > SLA_ALLOCATED * 0.8,
            },
            { k: "تا پایان اسپرینت", v: remain.text, s: "باقی‌مانده", hot: true },
          ].map((x, i) => (
            <div
              key={x.k}
              className={`border-line p-5 md:border-t-0 ${i === 0 ? "border-t" : "border-t md:border-s"}`}
            >
              <div className="micro text-sand">{x.k}</div>
              <div
                className={`tnum mt-2 text-2xl font-black ${
                  x.hot ? "text-flame-500" : "text-ink-900"
                }`}
              >
                {x.v}
              </div>
              <div className="mt-1 text-[11px] text-sand">{x.s}</div>
            </div>
          ))}
        </div>
      </section>

      {/* فیلترها */}
      <div className="flex flex-wrap items-center gap-2">
        <span className="micro me-1 text-sand">FILTER</span>
        {(
          [
            ["all", "همه"],
            ["high", "بحرانی"],
            ["med", "متوسط"],
            ["low", "کم"],
          ] as const
        ).map(([k, l]) => (
          <button
            key={k}
            onClick={() => setPrio(k)}
            className={`rounded-full border px-3.5 py-1.5 text-[12px] font-bold transition ${
              prio === k
                ? "border-ink-900 bg-ink-900 text-cream-50"
                : "border-line bg-cream-50 text-sand-dark hover:border-ink-500 hover:text-ink-700"
            }`}
          >
            {l}
          </button>
        ))}
        <span className="ms-auto flex items-center gap-2 text-[11.5px] text-sand">
          <CircleAlert size={14} className="text-gold-500" />
          کارت‌ها را بکشید و رها کنید یا از کلیدهای ← → جابه‌جا کنید
        </span>
      </div>

      {/* بورد */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {COLUMNS.map((col) => {
          const list = mine.filter((t) => t.status === col.key);
          const meta = STATUS[col.key];
          return (
            <section
              key={col.key}
              onDragOver={(e) => {
                e.preventDefault();
                setOverCol(col.key);
              }}
              onDragLeave={() => setOverCol((c) => (c === col.key ? null : c))}
              onDrop={() => {
                if (dragId) move(dragId, col.key);
                setDragId(null);
                setOverCol(null);
              }}
              className={`flex min-h-[220px] flex-col rounded-xl border bg-cream-200/60 transition-colors ${
                overCol === col.key ? "border-ink-500 bg-ink-100/70" : "border-line"
              }`}
            >
              <header className="sticky top-14 z-20 flex items-center gap-2 rounded-t-xl border-b border-line bg-cream-50/95 px-3.5 py-3 backdrop-blur">
                <span className={`h-2.5 w-2.5 rounded-full ${meta.dot}`} />
                <h3 className="text-[13px] font-black text-ink-900">{meta.label}</h3>
                <span className="micro text-sand">{meta.en}</span>
                <span className="tnum ms-auto rounded-md bg-cream-200 px-2 py-0.5 text-[11px] font-bold text-sand-dark">
                  {faNum(list.length)}/{faNum(col.wip)}
                </span>
              </header>

              <div className="flex flex-1 flex-col gap-3 p-3">
                <AnimatePresence mode="popLayout">
                  {list.map((t) => (
                    <TaskCard
                      key={t.id}
                      t={t}
                      now={now}
                      dragging={dragId === t.id}
                      setDragging={setDragId}
                      onOpen={() => onOpenTask(t.id)}
                      onMove={(dir) => {
                        const i = COLUMNS.findIndex((c) => c.key === t.status);
                        const n = COLUMNS[i + dir];
                        if (n) move(t.id, n.key);
                      }}
                    />
                  ))}
                </AnimatePresence>

                {list.length === 0 && (
                  <div className="flex flex-1 flex-col items-center justify-center rounded-lg border border-dashed border-line px-4 py-8 text-center">
                    <span className="grid h-10 w-10 place-items-center rounded-full bg-cream-50 text-sand">
                      <GripVertical size={18} />
                    </span>
                    <p className="mt-3 text-[12px] font-bold text-sand-dark">ستون خالی است</p>
                    <p className="mt-1 text-[11px] leading-relaxed text-sand">
                      یک کارت را اینجا رها کنید تا وضعیتش تغییر کند
                    </p>
                  </div>
                )}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}

/* ── کشوی جزئیات (ویرایش توضیحات و وضعیت) ── */
export function TaskDrawer({
  task,
  onClose,
  onPatch,
}: {
  task: Task | null;
  onClose: () => void;
  onPatch: (id: string, patch: Partial<Task>) => void;
}) {
  const [draft, setDraft] = useState("");
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    setDraft(task?.description ?? "");
    setSaved(false);
  }, [task?.id]);

  return (
    <AnimatePresence>
      {task && (
        <div className="fixed inset-0 z-50">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="absolute inset-0 bg-ink-950/45 backdrop-blur-[2px]"
          />
          <motion.aside
            initial={{ x: "-100%" }}
            animate={{ x: 0 }}
            exit={{ x: "-100%" }}
            transition={{ type: "spring", stiffness: 300, damping: 32 }}
            className="absolute inset-y-0 left-0 flex w-full max-w-[430px] flex-col border-e border-line bg-cream-50 shadow-2xl"
          >
            <header className="flex items-start gap-3 border-b border-line bg-ink-950 px-5 py-4 text-cream-50">
              <div className="min-w-0 flex-1">
                <div className="tnum text-[11px] text-gold-500">{task.id}</div>
                <h3 className="mt-1 text-[15px] font-black leading-snug">{task.title}</h3>
                <div className="mt-2 flex flex-wrap gap-1.5">
                  <Chip className="border-white/20 bg-white/10 text-cream-100">{task.group}</Chip>
                  <Chip className="border-white/20 bg-white/10 text-cream-100">{task.assignee}</Chip>
                  <Chip className={`border-transparent ${PRIORITY[task.priority].color} text-ink-950`}>
                    {PRIORITY[task.priority].label}
                  </Chip>
                </div>
              </div>
              <button
                onClick={onClose}
                className="rounded-lg p-1.5 text-cream-100/80 transition hover:bg-white/10"
                aria-label="بستن"
              >
                <X size={18} />
              </button>
            </header>

            <div className="flex-1 space-y-5 overflow-y-auto p-5">
              <div>
                <div className="micro mb-2 text-sand">STATUS · وضعیت</div>
                <div className="grid grid-cols-2 gap-2">
                  {COLUMNS.map((c) => (
                    <button
                      key={c.key}
                      onClick={() => onPatch(task.id, { status: c.key })}
                      className={`flex items-center gap-2 rounded-lg border px-3 py-2.5 text-[12.5px] font-bold transition ${
                        task.status === c.key
                          ? "border-ink-900 bg-ink-900 text-cream-50"
                          : "border-line bg-cream-100 text-sand-dark hover:border-ink-500"
                      }`}
                    >
                      <span className={`h-2 w-2 rounded-full ${STATUS[c.key].dot}`} />
                      {STATUS[c.key].label}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <div className="micro mb-2 text-sand">DESCRIPTION · توضیحات</div>
                <textarea
                  value={draft}
                  onChange={(e) => {
                    setDraft(e.target.value);
                    setSaved(false);
                  }}
                  rows={6}
                  className="w-full resize-y rounded-lg border border-line bg-cream-100 p-3 text-[13px] leading-relaxed text-ink-900 focus:border-ink-500 focus:bg-cream-50 focus:outline-none"
                />
                <div className="mt-2 flex items-center gap-2">
                  <button
                    onClick={() => {
                      onPatch(task.id, { description: draft });
                      setSaved(true);
                    }}
                    className="flex items-center gap-1.5 rounded-lg bg-ink-900 px-3.5 py-2 text-[12px] font-bold text-cream-50 transition hover:bg-ink-700"
                  >
                    <Save size={14} /> ذخیرهٔ توضیحات
                  </button>
                  {saved && (
                    <motion.span
                      initial={{ opacity: 0, y: 4 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="text-[12px] font-bold text-jade-600"
                    >
                      ذخیره شد ✓
                    </motion.span>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="rounded-lg border border-line bg-cream-100 p-3">
                  <div className="micro text-sand">DUE</div>
                  <div className="tnum mt-1 text-[13px] font-bold text-ink-900">{clock(task.due)}</div>
                </div>
                <div className="rounded-lg border border-line bg-cream-100 p-3">
                  <div className="micro text-sand">ESTIMATE</div>
                  <div className="tnum mt-1 text-[13px] font-bold text-ink-900">{task.estimate}h</div>
                </div>
              </div>

              <div>
                <div className="micro mb-2 text-sand">CHECKLIST · چک‌لیست</div>
                <ul className="space-y-2">
                  {task.checklist.map((c, i) => (
                    <li key={c.label}>
                      <label className="flex cursor-pointer items-center gap-2.5 rounded-lg border border-line bg-cream-100 px-3 py-2 text-[12.5px] text-ink-800 hover:border-ink-500">
                        <input
                          type="checkbox"
                          checked={c.done}
                          onChange={() => {
                            const next = task.checklist.map((x, j) =>
                              j === i ? { ...x, done: !x.done } : x,
                            );
                            onPatch(task.id, { checklist: next });
                          }}
                          className="h-4 w-4 accent-[#17864F]"
                        />
                        <span className={c.done ? "text-sand line-through" : ""}>{c.label}</span>
                      </label>
                    </li>
                  ))}
                </ul>
              </div>

              {task.attachments.length > 0 && (
                <div>
                  <div className="micro mb-2 text-sand">ATTACHMENTS · پیوست‌ها</div>
                  <div className="grid grid-cols-2 gap-2">
                    {task.attachments.map((a) => (
                      <figure
                        key={a.name}
                        className="overflow-hidden rounded-lg border border-line bg-cream-100"
                      >
                        <img src={a.src} alt={a.name} className="h-24 w-full object-cover" />
                        <figcaption className="truncate px-2 py-1.5 text-[10px] text-sand">
                          {a.name}
                        </figcaption>
                      </figure>
                    ))}
                  </div>
                </div>
              )}

              {task.deps.length > 0 && (
                <div>
                  <div className="micro mb-2 text-sand">DEPENDENCIES · وابستگی‌ها</div>
                  <div className="flex flex-wrap gap-1.5">
                    {task.deps.map((d) => (
                      <Chip key={d} className="border-ink-300 bg-ink-100 text-ink-700">
                        <Link2 size={11} /> {d}
                      </Chip>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
