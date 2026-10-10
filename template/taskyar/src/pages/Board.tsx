import { useEffect, useMemo, useRef, useState } from "react";
import { cn } from "@/utils/cn";
import { useStore } from "@/lib/store";
import { fa, fmtDate, leftover, faDuration } from "@/lib/fa";
import {
  CURRENT_USER,
  MEMBERS,
  PROJECTS,
  PRIORITY_META,
  SLA_TONE,
  STATUS_META,
  STATUS_ORDER,
  memberOf,
  projectOf,
  slaOf,
  type Status,
  type Task,
} from "@/lib/types";
import { Avatar, Badge, Chip, Empty, Modal, Panel, ProgressBar, Reveal, Ring } from "@/components/ui";
import {
  IconAlert,
  IconBoard,
  IconCalendar,
  IconCheck,
  IconClock,
  IconClip,
  IconDrag,
  IconLayers,
  IconLink,
  IconPencil,
  IconSearch,
  IconShield,
  IconSpark,
  IconSticky,
  IconUsers,
  IconX,
} from "@/components/icons";

const STICKY_STYLE: Record<Status, { bg: string; edge: string; text: string }> = {
  todo: { bg: "linear-gradient(160deg,#fdf8ec,#f6eed6)", edge: "#e6d3a6", text: "#19365a" },
  doing: { bg: "linear-gradient(160deg,#eaf2ff,#d8e6fb)", edge: "#bfdaff", text: "#1648ab" },
  review: { bg: "linear-gradient(160deg,#fef6c9,#fce79a)", edge: "#fcd34d", text: "#7a5a05" },
  blocked: { bg: "linear-gradient(160deg,#ffe6d1,#fdd3ae)", edge: "#fdc79b", text: "#a53d06" },
  done: { bg: "linear-gradient(160deg,#d9f6e4,#bdeed0)", edge: "#a6e9c1", text: "#0b6336" },
};

export default function Board() {
  const { tasks, route, setTab, moveTask, now, stats } = useStore();
  const view = route.tab === "sticky" ? "sticky" : "kanban";

  const [member, setMember] = useState(CURRENT_USER);
  const [onlyMine, setOnlyMine] = useState(true);
  const [project, setProject] = useState<string>(route.project ?? "all");
  const [search, setSearch] = useState("");
  const [prio, setPrio] = useState<string>("all");
  const [detail, setDetail] = useState<Task | null>(null);

  useEffect(() => {
    if (route.project !== undefined) setProject(route.project ?? "all");
  }, [route.project]);

  useEffect(() => {
    if (route.mine !== undefined) setOnlyMine(route.mine);
  }, [route.mine]);

  useEffect(() => {
    if (!route.q) {
      setSearch("");
      return;
    }
    if (MEMBERS.some((m) => m.name === route.q)) {
      setMember(route.q);
      setOnlyMine(true);
      setSearch("");
    } else {
      // a task code was requested from another page — show it for whoever owns it
      setSearch(route.q);
      setOnlyMine(false);
    }
  }, [route.q]);

  const mine = useMemo(() => tasks.filter((t) => t.assignee === member), [tasks, member]);

  const visible = useMemo(() => {
    const term = search.trim().toLowerCase();
    return tasks.filter((t) => {
      if (onlyMine && t.assignee !== member) return false;
      if (project !== "all" && t.project !== project) return false;
      if (prio !== "all" && t.priority !== prio) return false;
      if (!term) return true;
      return (
        t.title.toLowerCase().includes(term) ||
        t.code.toLowerCase().includes(term) ||
        t.tags.some((g) => g.includes(search.trim()))
      );
    });
  }, [tasks, onlyMine, member, project, prio, search]);

  const myOpen = mine.filter((t) => t.status !== "done");
  const myLoad = myOpen.reduce((a, t) => a + Math.max(0, t.estimate - t.spent), 0);
  const myCompliance = mine.length ? Math.round(mine.reduce((a, t) => a + slaOf(t, now).compliance, 0) / mine.length) : 0;
  const nextDue = myOpen
    .map((t) => ({ t, l: leftover(t.due, false, now) }))
    .sort((a, b) => new Date(a.t.due).getTime() - new Date(b.t.due).getTime())[0];
  const overall = Math.round(mine.reduce((a, t) => a + t.progress, 0) / Math.max(1, mine.length));

  return (
    <div className="space-y-5">
      {/* ------------- user cockpit ------------- */}
      <Reveal>
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
          <div className="relative overflow-hidden rounded-[22px] border border-milk-300 bg-gradient-to-l from-brand-700 via-brand-600 to-ink-800 p-5 text-milk-50 shadow-lift xl:col-span-5">
            <div className="pointer-events-none absolute inset-0 opacity-25 dot-paper" />
            <div className="pointer-events-none absolute -end-10 -top-12 h-40 w-40 rounded-full bg-sun-400/30 blur-3xl anim-float" />
            <div className="relative flex flex-wrap items-center gap-4">
              <Avatar name={member} size="lg" online />
              <div className="min-w-0 flex-1">
                <p className="text-[11px] font-bold text-brand-100">کاربر فعال</p>
                <h2 className="font-display text-[28px] leading-tight">{member}</h2>
                <p className="text-[11.5px] text-brand-100/85">{memberOf(member).role}</p>
              </div>
              <Ring value={overall} size={86} stroke={9} hex="#fcd34d" track="rgba(255,255,255,.18)">
                <div className="text-center">
                  <p className="font-num text-lg font-bold tabular">{fa(overall)}٪</p>
                  <p className="text-[9px] text-milk-100/80">پیشرفت من</p>
                </div>
              </Ring>
            </div>
            <div className="relative mt-4 grid grid-cols-3 gap-2">
              <Cockpit label="تسک باز" value={fa(myOpen.length)} />
              <Cockpit label="تحویل‌شده" value={fa(mine.length - myOpen.length)} />
              <Cockpit label="بار کاری" value={faDuration(myLoad)} />
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 xl:col-span-4">
            <Panel className="h-full" title="SLA اختصاص‌یافته" subtitle={`میانگین پایبندی ${member}`} icon={<IconShield className="h-[18px] w-[18px]" />}>
              <div className="flex items-center gap-4">
                <Ring value={myCompliance} size={92} stroke={11} hex={myCompliance > 88 ? "#16a157" : myCompliance > 72 ? "#f9bb1b" : "#ef6211"}>
                  <p className="font-num text-xl font-bold tabular text-ink-900">{fa(myCompliance)}٪</p>
                </Ring>
                <ul className="flex-1 space-y-1.5">
                  {(["ok", "warn", "risk", "breach"] as const).map((k) => {
                    const c = mine.filter((t) => slaOf(t, now).tone === k).length;
                    return (
                      <li key={k} className="flex items-center gap-2 text-[11px] font-bold">
                        <i className="h-2.5 w-2.5 rounded-full" style={{ background: SLA_TONE[k].hex }} />
                        <span className="flex-1 text-ink-500">{SLA_TONE[k].label}</span>
                        <span className="font-num tabular text-ink-900">{fa(c)}</span>
                      </li>
                    );
                  })}
                </ul>
              </div>
            </Panel>

            <Panel className="h-full" title="نزدیک‌ترین سررسید" subtitle="زمان مانده به‌صورت زنده" icon={<IconClock className="h-[18px] w-[18px]" />}>
              {nextDue ? (
                <div>
                  <p className="font-num text-[11px] font-bold tabular text-brand-600">{nextDue.t.code}</p>
                  <p className="mt-1 line-clamp-2 text-[13px] font-extrabold leading-6 text-ink-900">{nextDue.t.title}</p>
                  <div
                    className={cn(
                      "mt-2 inline-flex items-center gap-2 rounded-xl px-3 py-2 text-[13px] font-extrabold",
                      nextDue.l.tone === "danger"
                        ? "bg-ember-100 text-ember-600"
                        : nextDue.l.tone === "warn"
                          ? "bg-sun-100 text-sun-600"
                          : "bg-leaf-100 text-leaf-700",
                    )}
                  >
                    <IconClock className="h-4 w-4" />
                    {nextDue.l.overdue ? `${nextDue.l.label} تأخیر` : `${nextDue.l.label} مانده`}
                  </div>
                  <p className="mt-2 text-[10.5px] font-semibold text-ink-400">سررسید: {fmtDate(nextDue.t.due)} — پنجره SLA {fa(nextDue.t.slaHours)} ساعت</p>
                  <button
                    onClick={() => setDetail(nextDue.t)}
                    className="mt-2 inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-700 underline-sweep"
                  >
                    <IconPencil className="h-3.5 w-3.5" />
                    به‌روزرسانی وضعیت
                  </button>
                </div>
              ) : (
                <Empty text="تسک بازی ندارید" hint="همه تعهدات تحویل شده‌اند" />
              )}
            </Panel>
          </div>

          <Panel className="h-full xl:col-span-3" title="وضعیت کلی پروژه" subtitle="پیشرفت همه تسک‌های سامانه" icon={<IconLayers className="h-[18px] w-[18px]" />}>
            <div className="space-y-2.5">
              {PROJECTS.map((p) => {
                const list = tasks.filter((t) => t.project === p.name);
                const prog = list.length ? Math.round(list.reduce((a, t) => a + t.progress, 0) / list.length) : 0;
                const active = project === p.name;
                return (
                  <button
                    key={p.code}
                    onClick={() => setProject(active ? "all" : p.name)}
                    className={cn(
                      "w-full rounded-xl border p-2.5 text-start transition-all duration-300",
                      active ? "border-transparent shadow-soft" : "border-milk-200 bg-white/60 hover:-translate-y-0.5 hover:border-milk-400",
                    )}
                    style={active ? { background: `${p.hex}12`, borderColor: `${p.hex}55` } : undefined}
                  >
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="truncate text-[11.5px] font-extrabold text-ink-800">{p.name}</span>
                      <span className="font-num text-[11px] font-bold tabular" style={{ color: p.hex }}>
                        {fa(prog)}٪
                      </span>
                    </div>
                    <ProgressBar className="mt-1.5" value={prog} hex={p.hex} height={6} />
                    <p className="mt-1 font-num text-[9.5px] tabular text-ink-400">
                      {fa(list.filter((t) => t.status === "done").length)} / {fa(list.length)} تسک بسته‌شده
                    </p>
                  </button>
                );
              })}
            </div>
          </Panel>
        </div>
      </Reveal>

      {/* ------------- toolbar ------------- */}
      <div className="flex flex-wrap items-center gap-2 rounded-[20px] border border-milk-300 bg-milk-50 p-3 shadow-soft">
        <div className="flex items-center gap-1">
          <button
            onClick={() => setTab("kanban")}
            className={cn(
              "inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[12px] font-extrabold transition-all duration-300",
              view === "kanban" ? "bg-brand-600 text-white shadow-lift" : "bg-milk-100 text-ink-500 hover:bg-white hover:text-brand-700",
            )}
          >
            <IconBoard className="h-4 w-4" />
            بورد کانبان
          </button>
          <button
            onClick={() => setTab("sticky")}
            className={cn(
              "inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[12px] font-extrabold transition-all duration-300",
              view === "sticky" ? "bg-sun-400 text-ink-900 shadow-lift" : "bg-milk-100 text-ink-500 hover:bg-white hover:text-sun-600",
            )}
          >
            <IconSticky className="h-4 w-4" />
            دیوار یادداشت چسبنده
          </button>
        </div>

        <span className="h-8 w-px bg-milk-300" />

        <div className="flex items-center gap-2 rounded-xl border border-milk-300 bg-white px-3 py-2">
          <IconSearch className="h-4 w-4 text-ink-300" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="جستجو در تسک‌های من…"
            className="w-40 bg-transparent text-[12px] font-semibold outline-none placeholder:text-ink-300"
          />
          {search && (
            <button onClick={() => setSearch("")} className="text-ink-300 hover:text-ember-500" aria-label="پاک کردن جستجو">
              <IconX className="h-3.5 w-3.5" />
            </button>
          )}
        </div>

        <select
          value={member}
          onChange={(e) => setMember(e.target.value)}
          className="rounded-xl border border-milk-300 bg-white px-3 py-2 text-[11.5px] font-bold text-ink-600 outline-none transition hover:border-brand-300"
        >
          {MEMBERS.map((m) => (
            <option key={m.name} value={m.name}>
              {m.name} — {m.role}
            </option>
          ))}
        </select>

        <select
          value={project}
          onChange={(e) => setProject(e.target.value)}
          className="rounded-xl border border-milk-300 bg-white px-3 py-2 text-[11.5px] font-bold text-ink-600 outline-none transition hover:border-brand-300"
        >
          <option value="all">همه پروژه‌ها</option>
          {PROJECTS.map((p) => (
            <option key={p.code} value={p.name}>
              {p.name}
            </option>
          ))}
        </select>

        <Chip active={onlyMine} onClick={() => setOnlyMine((v) => !v)} hex="#1a5cd6">
          <span className="inline-flex items-center gap-1.5">
            <IconUsers className="h-3.5 w-3.5" />
            فقط تسک‌های {onlyMine ? "من" : "همه"}
          </span>
        </Chip>
        {(["critical", "high", "medium", "low"] as const).map((p) => (
          <Chip key={p} active={prio === p} hex={PRIORITY_META[p].hex} onClick={() => setPrio(prio === p ? "all" : p)}>
            {PRIORITY_META[p].label}
          </Chip>
        ))}

        <span className="ms-auto inline-flex items-center gap-2 rounded-xl bg-milk-100 px-3 py-2 text-[11px] font-bold text-ink-500">
          <i className="dot-live h-1.5 w-1.5 rounded-full bg-leaf-400" />
          {fa(visible.length)} تسک در نما — {fa(stats.open)} تسک باز در سامانه
        </span>
      </div>

      {visible.length === 0 ? (
        <Empty text="تسکی برای نمایش وجود ندارد" hint="فیلترها را تغییر دهید یا تسک جدیدی از پنل مدیریت بسازید" />
      ) : view === "kanban" ? (
        <Kanban tasks={visible} onOpen={setDetail} onMove={moveTask} />
      ) : (
        <StickyWall tasks={visible} onOpen={setDetail} onMove={moveTask} />
      )}

      {detail && <TaskModal task={tasks.find((t) => t.id === detail.id) ?? detail} onClose={() => setDetail(null)} />}
    </div>
  );
}

const Cockpit = ({ label, value }: { label: string; value: string }) => (
  <div className="rounded-xl border border-white/15 bg-white/10 px-3 py-2 backdrop-blur-sm">
    <p className="text-[10px] font-bold text-brand-100/85">{label}</p>
    <p className="font-num mt-0.5 text-[15px] font-bold tabular text-milk-50">{value}</p>
  </div>
);

/* =========================== KANBAN =========================== */
function Kanban({
  tasks,
  onOpen,
  onMove,
}: {
  tasks: Task[];
  onOpen: (t: Task) => void;
  onMove: (id: string, s: Status) => void;
}) {
  const [drag, setDrag] = useState<string | null>(null);
  const [over, setOver] = useState<Status | null>(null);

  return (
    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
      {STATUS_ORDER.map((s, ci) => {
        const list = tasks.filter((t) => t.status === s);
        const meta = STATUS_META[s];
        const isOver = over === s;
        return (
          <div
            key={s}
            onDragOver={(e) => {
              e.preventDefault();
              setOver(s);
            }}
            onDragLeave={(e) => {
              if (!e.currentTarget.contains(e.relatedTarget as Node)) setOver((o) => (o === s ? null : o));
            }}
            onDrop={(e) => {
              e.preventDefault();
              const id = drag ?? e.dataTransfer.getData("text/plain");
              if (id) onMove(id, s);
              setDrag(null);
              setOver(null);
            }}
            className={cn(
              "flex min-h-[420px] flex-col rounded-[20px] border-2 border-dashed p-2.5 transition-all duration-300",
              isOver ? "scale-[1.015] border-solid bg-white/85 shadow-lift" : "border-milk-300 bg-milk-100/55",
            )}
            style={isOver ? { borderColor: meta.hex, background: `${meta.hex}0f` } : undefined}
          >
            <header className="mb-2.5 flex items-center gap-2 rounded-2xl bg-white px-3 py-2.5 shadow-soft">
              <span className="h-8 w-1.5 rounded-full" style={{ background: meta.hex }} />
              <div className="flex-1">
                <p className="text-[12.5px] font-extrabold text-ink-900">{meta.label}</p>
                <p className="font-num text-[10px] tabular text-ink-400">{fa(list.length)} تسک</p>
              </div>
              <span className="grid h-7 w-7 place-items-center rounded-lg font-num text-[11px] font-bold tabular text-white" style={{ background: meta.hex }}>
                {fa(list.length)}
              </span>
            </header>

            <div className="flex-1 space-y-2.5">
              {list.map((t, i) => (
                <TaskCard key={t.id} task={t} index={i + ci} dragging={drag === t.id} onDragStart={setDrag} onOpen={onOpen} />
              ))}
              {list.length === 0 && (
                <div className="grid h-28 place-items-center rounded-2xl border border-dashed border-milk-400 text-[11px] font-bold text-ink-300">
                  تسکی را اینجا رها کنید
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

function TaskCard({
  task,
  index = 0,
  dragging,
  onDragStart,
  onOpen,
}: {
  task: Task;
  index?: number;
  dragging?: boolean;
  onDragStart?: (id: string | null) => void;
  onOpen: (t: Task) => void;
}) {
  const { now } = useStore();
  const l = leftover(task.due, task.status === "done", now);
  const sla = slaOf(task, now);
  const doneSubs = task.subtasks.filter((s) => s.done).length;
  const proj = projectOf(task.project);

  return (
    <article
      draggable
      onDragStart={(e) => {
        e.dataTransfer.setData("text/plain", task.id);
        e.dataTransfer.effectAllowed = "move";
        onDragStart?.(task.id);
      }}
      onDragEnd={() => onDragStart?.(null)}
      onClick={() => onOpen(task)}
      className={cn(
        "anim-rise group relative cursor-grab overflow-hidden rounded-2xl border border-milk-300 bg-white p-3 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-lift active:cursor-grabbing",
        dragging && "drag-ghost",
      )}
      style={{ animationDelay: `${index * 45}ms` }}
    >
      <span className="absolute inset-y-0 start-0 w-1.5" style={{ background: PRIORITY_META[task.priority].hex }} />
      <div className="flex items-start gap-2 ps-2">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5">
            <span className="font-num text-[10px] font-bold tabular" style={{ color: proj.hex }}>
              {task.code}
            </span>
            <span className="h-2.5 w-px bg-milk-300" />
            <span className="truncate text-[10px] font-bold text-ink-400">{task.project}</span>
          </div>
          <h4 className="mt-1 text-[12.5px] font-extrabold leading-5 text-ink-900">{task.title}</h4>
        </div>
        <IconDrag className="h-4 w-4 shrink-0 text-ink-200 transition group-hover:text-brand-400" />
      </div>

      <p className="mt-1.5 line-clamp-2 ps-2 text-[10.5px] leading-5 text-ink-500">{task.description}</p>

      {task.subtasks.filter((s) => s.image).length > 0 && (
        <div className="mt-2 flex gap-1.5 ps-2">
          {task.subtasks
            .filter((s) => s.image)
            .slice(0, 3)
            .map((s) => (
              <img key={s.id} src={s.image} alt={s.title} className="h-10 w-14 rounded-lg border border-milk-200 object-cover transition duration-300 group-hover:scale-105" />
            ))}
          {task.subtasks.filter((s) => s.image).length > 3 && (
            <span className="grid h-10 w-10 place-items-center rounded-lg bg-milk-100 font-num text-[10px] font-bold tabular text-ink-500">
              +{fa(task.subtasks.filter((s) => s.image).length - 3)}
            </span>
          )}
        </div>
      )}

      <div className="mt-2.5 ps-2">
        <div className="flex items-center gap-2">
          <ProgressBar value={task.progress} hex={SLA_TONE[sla.tone].hex} height={6} />
          <span className="font-num shrink-0 text-[10px] font-bold tabular text-ink-500">{fa(task.progress)}٪</span>
        </div>
      </div>

      <footer className="mt-2.5 flex flex-wrap items-center gap-1.5 ps-2">
        <Avatar name={task.assignee} size="xs" />
        <span
          className={cn(
            "inline-flex items-center gap-1 rounded-lg px-1.5 py-1 text-[10px] font-extrabold",
            l.tone === "danger" ? "bg-ember-100 text-ember-600" : l.tone === "warn" ? "bg-sun-100 text-sun-600" : "bg-milk-100 text-ink-500",
          )}
        >
          <IconClock className="h-3 w-3" />
          {task.status === "done" ? "تحویل شد" : l.overdue ? `${l.label} تأخیر` : l.label}
        </span>
        {task.subtasks.length > 0 && (
          <span className="inline-flex items-center gap-1 rounded-lg bg-milk-100 px-1.5 py-1 text-[10px] font-bold text-ink-500">
            <IconLayers className="h-3 w-3" />
            {fa(doneSubs)}/{fa(task.subtasks.length)}
          </span>
        )}
        {task.dependencies.length > 0 && (
          <span className="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-1.5 py-1 text-[10px] font-bold text-brand-600" title="دارای وابستگی">
            <IconLink className="h-3 w-3" />
            {fa(task.dependencies.length)}
          </span>
        )}
        {sla.tone !== "ok" && (
          <span className="ms-auto inline-flex items-center gap-1 rounded-lg px-1.5 py-1 text-[10px] font-extrabold" style={{ background: `${SLA_TONE[sla.tone].hex}1f`, color: SLA_TONE[sla.tone].hex }}>
            <IconAlert className="h-3 w-3" />
            SLA
          </span>
        )}
      </footer>
    </article>
  );
}

/* =========================== STICKY WALL =========================== */
function StickyWall({
  tasks,
  onOpen,
  onMove,
}: {
  tasks: Task[];
  onOpen: (t: Task) => void;
  onMove: (id: string, s: Status) => void;
}) {
  const wallRef = useRef<HTMLDivElement>(null);
  const [dragId, setDragId] = useState<string | null>(null);
  const [offset, setOffset] = useState<{ x: number; y: number } | null>(null);
  const moved = useRef(false);
  const clickTimer = useRef<number | null>(null);
  const { placeSticky, toast } = useStore();

  const startDrag = (e: React.PointerEvent, t: Task) => {
    if ((e.target as HTMLElement).closest("button")) return;
    moved.current = false;
    setDragId(t.id);
    setOffset({ x: e.clientX - t.sticky.x, y: e.clientY - t.sticky.y });
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  };

  const onPointerMove = (e: React.PointerEvent) => {
    if (!dragId || !offset) return;
    const rect = wallRef.current?.getBoundingClientRect();
    if (!rect) return;
    const x = Math.max(0, Math.min(rect.width - 190, e.clientX - offset.x));
    const y = Math.max(0, Math.min(rect.height - 160, e.clientY - offset.y));
    if (Math.abs(e.movementX) + Math.abs(e.movementY) > 1) moved.current = true;
    placeSticky(dragId, Math.round(x), Math.round(y));
  };

  const endDrag = () => {
    if (dragId) {
      setDragId(null);
      setOffset(null);
    }
  };

  const singleClick = (t: Task) => {
    if (clickTimer.current) window.clearTimeout(clickTimer.current);
    clickTimer.current = window.setTimeout(() => {
      if (!moved.current) onOpen(t);
      moved.current = false;
    }, 240);
  };

  const doubleClick = (t: Task) => {
    if (clickTimer.current) window.clearTimeout(clickTimer.current);
    moved.current = false;
    onMove(t.id, t.status === "done" ? "doing" : "done");
  };

  const tidy = () => {
    tasks.forEach((t, i) => placeSticky(t.id, (i % 5) * 210 + 20, Math.floor(i / 5) * 200 + 24));
    toast("یادداشت‌ها مرتب چیده شدند", "ok");
  };

  return (
    <Panel
      bodyClass="p-0"
      title="دیوار یادداشت‌های چسبنده"
      subtitle="یادداشت‌ها را با ماوس جابجا کنید؛ دابل‌کلیک وضعیت را به انجام‌شده تغییر می‌دهد"
      icon={<IconSticky className="h-[18px] w-[18px]" />}
      accent="#f9bb1b"
      action={
        <div className="flex items-center gap-2">
          <span className="hidden text-[10.5px] font-bold text-ink-400 sm:inline">رنگ = وضعیت تسک</span>
          <button onClick={tidy} className="rounded-lg bg-ink-900 px-3 py-1.5 text-[11px] font-bold text-milk-50 transition hover:bg-brand-700">
            چیدمان خودکار
          </button>
        </div>
      }
    >
      <div
        ref={wallRef}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerLeave={endDrag}
        className="cork relative h-[640px] w-full overflow-auto rounded-b-[19px]"
      >
        {tasks.map((t, i) => {
          const st = STICKY_STYLE[t.status];
          const l = leftover(t.due, t.status === "done");
          const isDrag = dragId === t.id;
          return (
            <div
              key={t.id}
              onPointerDown={(e) => startDrag(e, t)}
              onDoubleClick={() => doubleClick(t)}
              onClick={() => singleClick(t)}
              className={cn(
                "sticky-note sticky-fold absolute w-[188px] cursor-grab select-none rounded-[3px] p-3.5 active:cursor-grabbing",
                isDrag && "z-30 scale-[1.06] rotate-0",
              )}
              style={{
                left: 0,
                top: 0,
                transform: `translate(${t.sticky.x}px, ${t.sticky.y}px) rotate(${isDrag ? 0 : t.sticky.rot}deg)`,
                background: st.bg,
                color: st.text,
                boxShadow: `0 1px 1px rgba(10,26,48,.12), 0 ${isDrag ? 26 : 12}px 22px -10px rgba(10,26,48,.45)`,
                zIndex: isDrag ? 30 : 10 + (i % 5),
                transition: isDrag ? "none" : "transform .18s cubic-bezier(.22,1,.36,1), box-shadow .2s",
              }}
            >
              <span className="tape" />
              <div className="flex items-center justify-between gap-1">
                <span className="font-num text-[10px] font-bold tabular opacity-70">{t.code}</span>
                <span className="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[9px] font-extrabold" style={{ background: `${PRIORITY_META[t.priority].hex}22`, color: PRIORITY_META[t.priority].hex }}>
                  <IconSpark className="h-3 w-3" />
                  {PRIORITY_META[t.priority].label}
                </span>
              </div>
              <p className="mt-2 text-[13px] font-extrabold leading-6" style={{ fontFamily: "Lalezar, Vazirmatn, sans-serif" }}>
                {t.title}
              </p>
              <p className="mt-1.5 line-clamp-3 text-[10.5px] leading-5 opacity-75">{t.description}</p>
              <div className="mt-2.5 flex items-center justify-between gap-2 border-t border-current/15 pt-2 text-[10px] font-bold">
                <span className="inline-flex items-center gap-1">
                  <IconClock className="h-3 w-3" />
                  {t.status === "done" ? "تحویل شد" : l.overdue ? `${l.label} تأخیر` : l.label}
                </span>
                <span className="font-num tabular">{fa(t.progress)}٪</span>
              </div>
              <div className="mt-1.5 flex items-center gap-1.5">
                <Avatar name={t.assignee} size="xs" />
                <span className="truncate text-[9.5px] font-bold opacity-70">{t.assignee}</span>
              </div>
            </div>
          );
        })}
        {tasks.length === 0 && (
          <div className="grid h-full place-items-center">
            <p className="text-[12px] font-bold text-ink-500">یادداشتی برای نمایش نیست</p>
          </div>
        )}
      </div>
    </Panel>
  );
}

/* =========================== TASK MODAL =========================== */
function TaskModal({ task, onClose }: { task: Task; onClose: () => void }) {
  const { updateTask, tasks, now, toast } = useStore();
  const [description, setDescription] = useState(task.description);
  const [status, setStatus] = useState<Status>(task.status);
  const [progress, setProgress] = useState(task.progress);
  const [note, setNote] = useState("");
  const [notes, setNotes] = useState<{ id: string; who: string; text: string; when: string }[]>([
    { id: "seed1", who: task.reporter, text: "لطفاً پیش از سررسید، خروجی را برای بازبینی ارسال کنید.", when: fmtDate(task.createdAt) },
  ]);

  const l = leftover(task.due, task.status === "done", now);
  const sla = slaOf(task, now);
  const proj = projectOf(task.project);

  return (
    <Modal
      open
      onClose={onClose}
      wide
      title={task.title}
      subtitle={`${task.code} — ${task.project} — گزارش‌دهنده: ${task.reporter}`}
      footer={
        <>
          <div className="flex flex-wrap items-center gap-2 text-[11px] font-bold text-ink-500">
            <Badge className={STATUS_META[status].chip} dot={STATUS_META[status].hex}>
              {STATUS_META[status].label}
            </Badge>
            <Badge className={cn("ring-1", SLA_TONE[sla.tone].chip)}>{SLA_TONE[sla.tone].label}</Badge>
            <span className="inline-flex items-center gap-1">
              <IconCalendar className="h-3.5 w-3.5" />
              {fmtDate(task.start)} → {fmtDate(task.due)}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={onClose} className="rounded-xl border border-milk-300 bg-white px-4 py-2.5 text-[12px] font-bold text-ink-600 transition hover:border-ink-300">
              بستن
            </button>
            <button
              onClick={() => {
                updateTask(task.id, { description, status, progress });
                toast("تغییرات تسک ذخیره شد", "ok");
                onClose();
              }}
              className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-l from-leaf-500 to-leaf-400 px-4 py-2.5 text-[12px] font-extrabold text-white shadow-lift transition hover:-translate-y-0.5"
            >
              <IconCheck className="h-4 w-4" />
              ذخیره تغییرات
            </button>
          </div>
        </>
      }
    >
      <div className="grid gap-5 lg:grid-cols-5">
        {/* main */}
        <div className="space-y-4 lg:col-span-3">
          <div>
            <p className="mb-1.5 text-[11.5px] font-extrabold text-ink-700">توضیحات تسک (قابل ویرایش)</p>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={5}
              className="w-full resize-y rounded-2xl border border-milk-300 bg-white p-3 text-[12.5px] font-semibold leading-6 text-ink-800 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
            />
            <p className="mt-1 text-[10px] font-semibold text-ink-400">{fa(description.length)} نویسه — تغییرات پس از ذخیره روی بورد و داشبورد اعمال می‌شود</p>
          </div>

          <div>
            <p className="mb-1.5 text-[11.5px] font-extrabold text-ink-700">وضعیت</p>
            <div className="flex flex-wrap gap-1.5">
              {STATUS_ORDER.map((s) => (
                <button
                  key={s}
                  onClick={() => setStatus(s)}
                  className={cn(
                    "rounded-xl px-3 py-2 text-[11.5px] font-extrabold transition-all duration-200",
                    status === s ? "text-white shadow-lift" : "bg-milk-100 text-ink-500 hover:bg-milk-200",
                  )}
                  style={status === s ? { background: STATUS_META[s].hex } : undefined}
                >
                  {STATUS_META[s].label}
                </button>
              ))}
            </div>
          </div>

          <div>
            <div className="mb-1.5 flex items-baseline justify-between">
              <p className="text-[11.5px] font-extrabold text-ink-700">درصد پیشرفت</p>
              <span className="font-num rounded-lg bg-brand-50 px-2 py-0.5 text-[11.5px] font-bold tabular text-brand-700 ring-1 ring-brand-100">{fa(progress)}٪</span>
            </div>
            <input type="range" min={0} max={100} step={5} value={progress} onChange={(e) => setProgress(Number(e.target.value))} className="range-sun w-full" />
            <div className="mt-2 flex gap-1.5">
              {[0, 25, 50, 75, 100].map((v) => (
                <button key={v} onClick={() => setProgress(v)} className="rounded-lg bg-milk-100 px-2.5 py-1 text-[10.5px] font-bold text-ink-500 transition hover:bg-brand-50 hover:text-brand-700">
                  {fa(v)}٪
                </button>
              ))}
            </div>
          </div>

          <div>
            <p className="mb-1.5 text-[11.5px] font-extrabold text-ink-700">
              زیرگروه‌ها ({fa(task.subtasks.filter((s) => s.done).length)} از {fa(task.subtasks.length)})
            </p>
            <ul className="space-y-1.5">
              {task.subtasks.map((s) => (
                <li key={s.id} className={cn("flex items-start gap-2.5 rounded-xl border bg-white p-2.5 transition", s.done ? "border-leaf-200 bg-leaf-100/40" : "border-milk-300")}>
                  <button
                    onClick={() => updateTask(task.id, { subtasks: task.subtasks.map((x) => (x.id === s.id ? { ...x, done: !x.done } : x)) }, true)}
                    className={cn("mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-md border-2 transition", s.done ? "border-leaf-500 bg-leaf-500 text-white" : "border-ink-200 text-transparent hover:border-leaf-400")}
                    aria-label="تغییر وضعیت زیرگروه"
                  >
                    <IconCheck className="h-3 w-3" strokeWidth={3} />
                  </button>
                  <div className="min-w-0 flex-1">
                    <p className={cn("text-[12px] font-bold text-ink-800", s.done && "text-ink-400 line-through")}>{s.title}</p>
                    {s.note && <p className="mt-0.5 text-[10.5px] text-ink-400">{s.note}</p>}
                  </div>
                  {s.image && <img src={s.image} alt={s.title} className="h-11 w-16 shrink-0 rounded-lg border border-milk-300 object-cover" />}
                </li>
              ))}
              {task.subtasks.length === 0 && <p className="text-[11px] text-ink-400">زیرگروهی ثبت نشده است.</p>}
            </ul>
          </div>
        </div>

        {/* side */}
        <div className="space-y-4 lg:col-span-2">
          <div className="rounded-2xl border border-milk-300 bg-gradient-to-b from-white to-milk-100 p-4">
            <div className="flex items-center gap-4">
              <Ring value={sla.compliance} size={92} stroke={11} hex={SLA_TONE[sla.tone].hex}>
                <div className="text-center">
                  <p className="font-num text-lg font-bold tabular text-ink-900">{fa(sla.compliance)}</p>
                  <p className="text-[9px] font-bold text-ink-400">پایبندی</p>
                </div>
              </Ring>
              <div className="flex-1">
                <p className="text-[11px] font-bold text-ink-500">زمان باقی‌مانده</p>
                <p className={cn("font-num text-lg font-bold tabular", l.tone === "danger" ? "text-ember-600" : l.tone === "warn" ? "text-sun-600" : "text-leaf-600")}>
                  {task.status === "done" ? "تحویل شد" : l.overdue ? `${l.label} تأخیر` : l.label}
                </p>
                <p className="mt-1 text-[10.5px] font-semibold text-ink-400">پنجره SLA: {fa(task.slaHours)} ساعت</p>
                <p className="text-[10.5px] font-semibold text-ink-400">effort باقی‌مانده: {fa(Math.max(0, task.estimate - task.spent))} ساعت</p>
              </div>
            </div>
            <div className="mt-3 space-y-2 border-t border-milk-200 pt-3">
              <Mini label="پیشرفت مورد انتظار" value={`${fa(sla.expected)}٪`} hex="#527aae" />
              <Mini label="اولویت" value={PRIORITY_META[task.priority].label} hex={PRIORITY_META[task.priority].hex} />
              <Mini label="مسئول" value={task.assignee} hex={proj.hex} />
            </div>
          </div>

          {task.dependencies.length > 0 && (
            <div className="rounded-2xl border border-brand-100 bg-brand-50/60 p-3.5">
              <p className="inline-flex items-center gap-2 text-[11.5px] font-extrabold text-brand-800">
                <IconLink className="h-4 w-4" />
                پیش‌نیازها
              </p>
              <ul className="mt-2 space-y-1.5">
                {task.dependencies.map((d) => {
                  const dep = tasks.find((x) => x.id === d);
                  if (!dep) return null;
                  return (
                    <li key={d} className="flex items-center gap-2 rounded-xl bg-white px-2.5 py-2">
                      <i className="h-2 w-2 rounded-full" style={{ background: STATUS_META[dep.status].hex }} />
                      <span className="font-num text-[10px] tabular text-brand-600">{dep.code}</span>
                      <span className="flex-1 truncate text-[11px] font-bold text-ink-700">{dep.title}</span>
                      <span className="text-[9.5px] font-bold text-ink-400">{STATUS_META[dep.status].label}</span>
                    </li>
                  );
                })}
              </ul>
            </div>
          )}

          {task.attachments.length > 0 && (
            <div className="rounded-2xl border border-milk-300 bg-white p-3.5">
              <p className="inline-flex items-center gap-2 text-[11.5px] font-extrabold text-ink-700">
                <IconClip className="h-4 w-4" />
                پیوست‌ها
              </p>
              <div className="mt-2 flex flex-wrap gap-2">
                {task.attachments.map((a) =>
                  a.kind === "image" ? (
                    <img key={a.id} src={a.url} alt={a.name} className="h-16 w-24 rounded-xl border border-milk-300 object-cover transition hover:scale-105" />
                  ) : (
                    <span key={a.id} className="inline-flex items-center gap-1.5 rounded-xl bg-milk-100 px-2.5 py-2 text-[10.5px] font-bold text-ink-600">
                      <IconClip className="h-3.5 w-3.5 text-ink-400" />
                      {a.name}
                      <span className="text-[9.5px] text-ink-400">{a.size}</span>
                    </span>
                  ),
                )}
              </div>
            </div>
          )}

          <div className="rounded-2xl border border-milk-300 bg-white p-3.5">
            <p className="inline-flex items-center gap-2 text-[11.5px] font-extrabold text-ink-700">
              <IconSpark className="h-4 w-4 text-sun-500" />
              یادداشت‌های تسک
            </p>
            <ul className="mt-2 space-y-2">
              {notes.map((n) => (
                <li key={n.id} className="rounded-xl bg-milk-100 p-2.5">
                  <p className="text-[11px] font-bold leading-5 text-ink-700">{n.text}</p>
                  <p className="mt-1 text-[9.5px] font-semibold text-ink-400">
                    {n.who} — {n.when}
                  </p>
                </li>
              ))}
            </ul>
            <div className="mt-2.5 flex gap-2">
              <input
                value={note}
                onChange={(e) => setNote(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" && note.trim()) {
                    setNotes((n) => [...n, { id: String(Date.now()), who: task.assignee, text: note.trim(), when: fmtDate(new Date().toISOString().slice(0, 10)) }]);
                    setNote("");
                    toast("یادداشت افزوده شد", "info");
                  }
                }}
                placeholder="یادداشت کوتاه… (Enter)"
                className="flex-1 rounded-xl border border-milk-300 bg-milk-50 px-3 py-2 text-[11.5px] font-semibold outline-none transition focus:border-brand-400"
              />
              <button
                onClick={() => {
                  if (!note.trim()) return;
                  setNotes((n) => [...n, { id: String(Date.now()), who: task.assignee, text: note.trim(), when: fmtDate(new Date().toISOString().slice(0, 10)) }]);
                  setNote("");
                  toast("یادداشت افزوده شد", "info");
                }}
                className="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700"
                aria-label="افزودن یادداشت"
              >
                <IconSpark className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </Modal>
  );
}

const Mini = ({ label, value, hex }: { label: string; value: string; hex: string }) => (
  <div className="flex items-center justify-between gap-2">
    <span className="text-[10.5px] font-bold text-ink-400">{label}</span>
    <span className="truncate text-[11px] font-extrabold" style={{ color: hex }}>
      {value}
    </span>
  </div>
);
