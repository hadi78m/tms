import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { SEED_TASKS } from "./data";
import {
  MEMBERS,
  PRIORITY_META,
  STATUS_META,
  STATUS_ORDER,
  slaOf,
  type Priority,
  type Status,
  type Task,
  type Page,
} from "./types";
import { leftover, uid } from "./fa";

export interface Route {
  page: Page;
  tab?: string;
  project?: string;
  status?: Status | "all";
  priority?: Priority | "all";
  mine?: boolean;
  q?: string;
  newTask?: boolean;
}

export interface Toast {
  id: string;
  text: string;
  tone: "ok" | "info" | "warn" | "danger";
}

interface Store {
  tasks: Task[];
  route: Route;
  navigate: (r: Partial<Route> & { page: Page }) => void;
  setTab: (tab: string) => void;
  now: Date;
  toasts: Toast[];
  toast: (text: string, tone?: Toast["tone"]) => void;
  dropToast: (id: string) => void;
  addTask: (t: Task) => void;
  updateTask: (id: string, patch: Partial<Task>, silent?: boolean) => void;
  removeTask: (id: string) => void;
  moveTask: (id: string, status: Status) => void;
  placeSticky: (id: string, x: number, y: number) => void;
  stats: ReturnType<typeof computeStats>;
  sidebarOpen: boolean;
  setSidebarOpen: (v: boolean) => void;
}

const Ctx = createContext<Store | null>(null);

export const useStore = (): Store => {
  const s = useContext(Ctx);
  if (!s) throw new Error("useStore must be used inside <StoreProvider>");
  return s;
};

function computeStats(tasks: Task[], now: Date) {
  const byStatus = STATUS_ORDER.reduce(
    (acc, s) => ({ ...acc, [s]: tasks.filter((t) => t.status === s).length }),
    {} as Record<Status, number>,
  );
  const byPriority = (Object.keys(PRIORITY_META) as Priority[]).reduce(
    (acc, p) => ({ ...acc, [p]: tasks.filter((t) => t.priority === p).length }),
    {} as Record<Priority, number>,
  );
  const done = byStatus.done;
  const open = tasks.length - done;
  const overdue = tasks.filter((t) => leftover(t.due, t.status === "done", now).overdue);
  const dueSoon = tasks.filter((t) => {
    const l = leftover(t.due, t.status === "done", now);
    return !l.overdue && t.status !== "done" && (l.days * 24 + l.hours) <= 72;
  });
  const slaRows = tasks.map((t) => ({ task: t, sla: slaOf(t, now) }));
  const breached = slaRows.filter((r) => r.sla.tone === "breach").length;
  const atRisk = slaRows.filter((r) => r.sla.tone === "risk").length;
  const warn = slaRows.filter((r) => r.sla.tone === "warn").length;
  const compliance = tasks.length
    ? Math.round(slaRows.reduce((a, r) => a + r.sla.compliance, 0) / tasks.length)
    : 0;
  const avgProgress = tasks.length
    ? Math.round(tasks.reduce((a, t) => a + t.progress, 0) / tasks.length)
    : 0;
  const hoursLeft = tasks.filter((t) => t.status !== "done").reduce((a, t) => a + Math.max(0, t.estimate - t.spent), 0);

  const workload = MEMBERS.map((m) => {
    const mine = tasks.filter((t) => t.assignee === m.name);
    const load = mine.filter((t) => t.status !== "done").reduce((a, t) => a + Math.max(0, t.estimate - t.spent), 0);
    return {
      member: m,
      total: mine.length,
      done: mine.filter((t) => t.status === "done").length,
      load,
      atRisk: mine.filter((t) => slaOf(t, now).tone !== "ok" && t.status !== "done").length,
    };
  }).sort((a, b) => b.load - a.load);

  return {
    byStatus,
    byPriority,
    done,
    open,
    overdue,
    dueSoon,
    breached,
    atRisk,
    warn,
    compliance,
    avgProgress,
    hoursLeft,
    workload,
    slaRows,
  };
}

export function StoreProvider({ children }: { children: ReactNode }) {
  const [tasks, setTasks] = useState<Task[]>(() =>
    SEED_TASKS.map((t, i) => ({
      ...t,
      sticky: {
        x: 18 + (i % 5) * 212 + ((i * 7) % 13) - 6,
        y: 22 + Math.floor(i / 5) * 198 + ((i * 5) % 11) - 5,
        rot: t.sticky.rot,
      },
    })),
  );
  const [route, setRoute] = useState<Route>({ page: "dashboard", tab: "overview" });
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [now, setNow] = useState<Date>(() => new Date());
  const [sidebarOpen, setSidebarOpen] = useState(false);

  useEffect(() => {
    const t = setInterval(() => setNow(new Date()), 20000);
    return () => clearInterval(t);
  }, []);

  const dropToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const toast = useCallback(
    (text: string, tone: Toast["tone"] = "info") => {
      const id = uid("toast");
      setToasts((prev) => [...prev.slice(-3), { id, text, tone }]);
      setTimeout(() => dropToast(id), 4200);
    },
    [dropToast],
  );

  const navigate = useCallback((r: Partial<Route> & { page: Page }) => {
    setRoute({ tab: r.page === "dashboard" ? "overview" : undefined, ...r });
    setSidebarOpen(false);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, []);

  const setTab = useCallback((tab: string) => setRoute((p) => ({ ...p, tab })), []);

  const addTask = useCallback(
    (t: Task) => {
      setTasks((prev) => [t, ...prev]);
      toast(`تسک «${t.title}» با شناسه ${t.code} ثبت شد`, "ok");
    },
    [toast],
  );

  const updateTask = useCallback(
    (id: string, patch: Partial<Task>, silent = false) => {
      setTasks((prev) => prev.map((t) => (t.id === id ? { ...t, ...patch } : t)));
      if (!silent) {
        const t = tasks.find((x) => x.id === id);
        toast(patch.status && t && patch.status !== t.status
          ? `وضعیت ${t.code} به «${STATUS_META[patch.status].label}» تغییر کرد`
          : `تسک ${t?.code ?? ""} به‌روزرسانی شد`, "info");
      }
    },
    [tasks, toast],
  );

  const removeTask = useCallback(
    (id: string) => {
      const t = tasks.find((x) => x.id === id);
      setTasks((prev) =>
        prev
          .filter((x) => x.id !== id)
          .map((x) => ({ ...x, dependencies: x.dependencies.filter((d) => d !== id) })),
      );
      toast(`تسک ${t?.code ?? ""} حذف شد`, "danger");
    },
    [tasks, toast],
  );

  const moveTask = useCallback(
    (id: string, status: Status) => {
      const t = tasks.find((x) => x.id === id);
      if (!t || t.status === status) return;
      setTasks((prev) =>
        prev.map((x) =>
          x.id === id
            ? { ...x, status, progress: status === "done" ? 100 : x.progress === 100 ? 90 : x.progress }
            : x,
        ),
      );
      toast(`${t.code} → «${STATUS_META[status].label}»`, status === "done" ? "ok" : "info");
    },
    [tasks, toast],
  );

  const placeSticky = useCallback((id: string, x: number, y: number) => {
    setTasks((prev) =>
      prev.map((t) => (t.id === id ? { ...t, sticky: { ...t.sticky, x, y } } : t)),
    );
  }, []);

  const stats = useMemo(() => computeStats(tasks, now), [tasks, now]);

  const value: Store = {
    tasks,
    route,
    navigate,
    setTab,
    now,
    toasts,
    toast,
    dropToast,
    addTask,
    updateTask,
    removeTask,
    moveTask,
    placeSticky,
    stats,
    sidebarOpen,
    setSidebarOpen,
  };

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}
