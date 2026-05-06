import { useQuery } from "@tanstack/react-query";
import { cn } from "@/lib/utils";
import {
  Network, Server, ArrowRightLeft, Globe2, Wifi, WifiOff,
  AlertTriangle, CheckCircle2, Clock, Activity, Building2,
  ChevronRight, Zap,
} from "lucide-react";
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip,
  ResponsiveContainer, Cell,
} from "recharts";

function useGrids() {
  return useQuery({
    queryKey: ["nodal", "grids"],
    queryFn: () => fetch(`/api/nodal/grids`).then((r) => r.json()),
    refetchInterval: 15000,
  });
}
function useHubs() {
  return useQuery({
    queryKey: ["nodal", "hubs"],
    queryFn: () => fetch(`/api/nodal/hubs`).then((r) => r.json()),
    refetchInterval: 15000,
  });
}
function useFlows() {
  return useQuery({
    queryKey: ["nodal", "flows"],
    queryFn: () => fetch(`/api/nodal/inter-grid-flows`).then((r) => r.json()),
    refetchInterval: 30000,
  });
}
function useNpci() {
  return useQuery({
    queryKey: ["nodal", "npci"],
    queryFn: () => fetch(`/api/nodal/npci-connectivity`).then((r) => r.json()),
    refetchInterval: 30000,
  });
}

const STATUS_CONFIG: Record<string, { label: string; dot: string; badge: string }> = {
  ONLINE: { label: "Online", dot: "bg-green-500", badge: "bg-green-500/15 text-green-400 border-green-500/30" },
  DEGRADED: { label: "Degraded", dot: "bg-amber-500 animate-pulse", badge: "bg-amber-500/15 text-amber-400 border-amber-500/30" },
  STANDBY: { label: "Standby", dot: "bg-blue-500", badge: "bg-blue-500/15 text-blue-400 border-blue-500/30" },
  OFFLINE: { label: "Offline", dot: "bg-red-500", badge: "bg-red-500/15 text-red-400 border-red-500/30" },
  CONNECTED: { label: "Connected", dot: "bg-green-500", badge: "bg-green-500/15 text-green-400 border-green-500/30" },
  ACTIVE: { label: "Active", dot: "bg-green-500", badge: "bg-green-500/15 text-green-400 border-green-500/30" },
  SLOW: { label: "Slow", dot: "bg-amber-500 animate-pulse", badge: "bg-amber-500/15 text-amber-400 border-amber-500/30" },
};

function StatusBadge({ status }: { status: string }) {
  const cfg = STATUS_CONFIG[status] ?? STATUS_CONFIG["OFFLINE"];
  return (
    <span className={cn("inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border", cfg.badge)}>
      <span className={cn("w-1.5 h-1.5 rounded-full", cfg.dot)} />
      {cfg.label}
    </span>
  );
}

function KpiCard({ icon: Icon, label, value, sub, color }: {
  icon: React.ElementType; label: string; value: string | number; sub?: string; color?: string;
}) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-start justify-between mb-2">
        <p className="text-xs text-muted-foreground uppercase tracking-wide">{label}</p>
        <div className={cn("w-7 h-7 rounded-md flex items-center justify-center", color ?? "bg-primary/10")}>
          <Icon className="w-4 h-4 text-primary" />
        </div>
      </div>
      <p className="text-2xl font-bold text-foreground">{value}</p>
      {sub && <p className="text-xs text-muted-foreground mt-0.5">{sub}</p>}
    </div>
  );
}

type Grid = {
  grid_id: string; name: string; type: string; location: string; hub_code: string;
  status: string; banks_connected: number; branches_connected: number;
  throughput_today: number; throughput_capacity: number; pending_instruments: number;
  settlement_lag_ms: number; replication_lag_s: number; uptime_pct: number;
  last_heartbeat: string;
};

type Hub = {
  hub_code: string; hub_name: string; grid_id: string; connected_branches: number;
  instruments_today: number; avg_processing_ms: number; status: string; scanner_count: number;
};

type Flow = {
  from: string; to: string; instruments: number; amount_cr: number; status: string;
};

type NpciService = {
  service: string; status: string; latency_ms: number; last_checked: string;
};

const GRID_COLORS: Record<string, string> = {
  "GRID-CHN": "#22c55e",
  "GRID-MUM": "#3b82f6",
  "GRID-DEL": "#8b5cf6",
  "GRID-KOL": "#f59e0b",
  "GRID-HYD": "#06b6d4",
};

export default function Nodal() {
  const { data: gridsData } = useGrids();
  const { data: hubsData } = useHubs();
  const { data: flowsData } = useFlows();
  const { data: npciData } = useNpci();

  const grids: Grid[] = gridsData?.grids ?? [];
  const summary = gridsData?.summary;
  const hubs: Hub[] = hubsData?.hubs ?? [];
  const flows: Flow[] = flowsData?.flows ?? [];
  const npci: NpciService[] = npciData?.services ?? [];

  const chartData = grids.filter((g) => g.status !== "STANDBY").map((g) => ({
    name: g.name.replace(" Grid", "").replace(" DR", ""),
    instruments: g.throughput_today,
    capacity: g.throughput_capacity,
    fill: GRID_COLORS[g.grid_id] ?? "#64748b",
  }));

  const npciOk = npci.filter((s) => s.status === "CONNECTED").length;
  const npciDegraded = npci.filter((s) => s.status !== "CONNECTED").length;

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <Network className="w-6 h-6 text-primary" /> Nodal Hub Operations
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            NPCI CTS Multi-Grid Network · 4 Grids · DC Chennai (Primary) · DR Hyderabad
          </p>
        </div>
        <div className="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-green-500/15 border border-green-500/30 text-green-400">
          <Activity className="w-3.5 h-3.5 animate-pulse" />
          Live · Refreshing every 15s
        </div>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <KpiCard icon={Globe2} label="Total Grids" value={summary?.total_grids ?? "—"} sub={`${summary?.active_grids ?? 0} active`} />
        <KpiCard icon={Activity} label="Active Grids" value={summary?.active_grids ?? "—"} sub="Online now" color="bg-green-500/10" />
        <KpiCard icon={AlertTriangle} label="Degraded" value={summary?.degraded_grids ?? "—"} sub="Needs attention" color="bg-amber-500/10" />
        <KpiCard icon={Building2} label="Banks Connected" value={summary?.total_banks_connected?.toLocaleString("en-IN") ?? "—"} sub="Across all grids" />
        <KpiCard icon={Server} label="Branches" value={summary?.total_branches_connected?.toLocaleString("en-IN") ?? "—"} sub="Live connections" />
        <KpiCard icon={Zap} label="Instruments Today" value={summary?.total_instruments_today?.toLocaleString("en-IN") ?? "—"} sub="All grids combined" color="bg-primary/10" />
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Grid Throughput Chart */}
        <div className="lg:col-span-2 rounded-lg border border-border bg-card p-5">
          <p className="text-sm font-semibold text-foreground mb-1">Grid Throughput vs Capacity</p>
          <p className="text-xs text-muted-foreground mb-4">Today's processed instruments by CTS grid</p>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={chartData} margin={{ left: 0, right: 10 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
              <XAxis dataKey="name" tick={{ fontSize: 11, fill: "hsl(var(--muted-foreground))" }} />
              <YAxis tick={{ fontSize: 11, fill: "hsl(var(--muted-foreground))" }} tickFormatter={(v) => `${(v / 1000).toFixed(0)}K`} />
              <Tooltip formatter={(v: number, n: string) => [v.toLocaleString("en-IN"), n === "instruments" ? "Processed" : "Capacity"]} />
              <Bar dataKey="instruments" name="Processed" radius={[4, 4, 0, 0]}>
                {chartData.map((d) => <Cell key={d.name} fill={d.fill} />)}
              </Bar>
              <Bar dataKey="capacity" name="Capacity" radius={[4, 4, 0, 0]} fill="hsl(var(--border))" opacity={0.4} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* NPCI Connectivity */}
        <div className="rounded-lg border border-border bg-card p-5">
          <div className="flex items-center justify-between mb-3">
            <p className="text-sm font-semibold text-foreground">NPCI Connectivity</p>
            <div className="flex items-center gap-2 text-xs">
              <span className="text-green-400 font-medium">{npciOk} OK</span>
              {npciDegraded > 0 && <span className="text-amber-400 font-medium">{npciDegraded} degraded</span>}
            </div>
          </div>
          <div className="space-y-2">
            {npci.map((svc) => (
              <div key={svc.service} className="flex items-center justify-between py-1.5 border-b border-border/50 last:border-0">
                <div className="flex items-center gap-2 min-w-0">
                  {svc.status === "CONNECTED" ? (
                    <Wifi className="w-3.5 h-3.5 text-green-400 shrink-0" />
                  ) : (
                    <WifiOff className="w-3.5 h-3.5 text-amber-400 shrink-0" />
                  )}
                  <span className="text-xs text-foreground truncate">{svc.service}</span>
                </div>
                <div className="flex items-center gap-2 shrink-0 ml-2">
                  <span className={cn("text-xs font-mono font-medium", svc.latency_ms > 1000 ? "text-amber-400" : "text-green-400")}>
                    {svc.latency_ms > 1000 ? `${(svc.latency_ms / 1000).toFixed(1)}s` : `${svc.latency_ms}ms`}
                  </span>
                  <StatusBadge status={svc.status} />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Grid Cards */}
      <div>
        <h2 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
          <Globe2 className="w-4 h-4 text-primary" /> CTS Grid Nodes
        </h2>
        <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
          {grids.map((grid) => {
            const utilPct = grid.throughput_capacity > 0
              ? Math.round((grid.throughput_today / grid.throughput_capacity) * 100)
              : 0;
            return (
              <div key={grid.grid_id} className="rounded-lg border border-border bg-card p-5">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <div className="flex items-center gap-2 mb-0.5">
                      <div className="w-3 h-3 rounded-full" style={{ background: GRID_COLORS[grid.grid_id] ?? "#64748b" }} />
                      <p className="font-semibold text-foreground text-sm">{grid.name}</p>
                    </div>
                    <p className="text-xs text-muted-foreground pl-5">{grid.location}</p>
                  </div>
                  <StatusBadge status={grid.status} />
                </div>

                <div className="grid grid-cols-2 gap-2 text-xs mb-3">
                  <div className="rounded bg-muted/40 px-2.5 py-2">
                    <p className="text-muted-foreground">Type</p>
                    <p className="font-medium text-foreground">{grid.type}</p>
                  </div>
                  <div className="rounded bg-muted/40 px-2.5 py-2">
                    <p className="text-muted-foreground">Banks</p>
                    <p className="font-medium text-foreground">{grid.banks_connected}</p>
                  </div>
                  <div className="rounded bg-muted/40 px-2.5 py-2">
                    <p className="text-muted-foreground">Branches</p>
                    <p className="font-medium text-foreground">{grid.branches_connected.toLocaleString("en-IN")}</p>
                  </div>
                  <div className="rounded bg-muted/40 px-2.5 py-2">
                    <p className="text-muted-foreground">Uptime</p>
                    <p className={cn("font-bold", grid.uptime_pct >= 99.9 ? "text-green-400" : grid.uptime_pct >= 99 ? "text-amber-400" : "text-red-400")}>
                      {grid.uptime_pct}%
                    </p>
                  </div>
                </div>

                {grid.status !== "STANDBY" && (
                  <>
                    <div className="mb-2">
                      <div className="flex justify-between text-xs mb-1">
                        <span className="text-muted-foreground">Throughput</span>
                        <span className="font-medium text-foreground">
                          {grid.throughput_today.toLocaleString("en-IN")} / {grid.throughput_capacity.toLocaleString("en-IN")} ({utilPct}%)
                        </span>
                      </div>
                      <div className="w-full h-1.5 bg-muted rounded-full overflow-hidden">
                        <div
                          className={cn("h-full rounded-full transition-all", utilPct > 85 ? "bg-amber-500" : "bg-green-500")}
                          style={{ width: `${utilPct}%` }}
                        />
                      </div>
                    </div>

                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" />
                        Lag: <span className={cn("font-mono font-medium ml-1", grid.settlement_lag_ms > 1000 ? "text-amber-400" : "text-foreground")}>
                          {grid.settlement_lag_ms}ms
                        </span>
                      </span>
                      <span>Pending: <span className={cn("font-medium", grid.pending_instruments > 500 ? "text-amber-400" : "text-foreground")}>{grid.pending_instruments}</span></span>
                      <span>Repl: <span className={cn("font-mono font-medium", grid.replication_lag_s > 10 ? "text-amber-400" : "text-foreground")}>{grid.replication_lag_s}s</span></span>
                    </div>
                  </>
                )}

                {grid.status === "STANDBY" && (
                  <p className="text-xs text-blue-400 mt-2 flex items-center gap-1.5">
                    <CheckCircle2 className="w-3.5 h-3.5" />
                    DR node ready · Replication lag: {grid.replication_lag_s}s
                  </p>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Hub Nodes Table */}
      <div className="rounded-lg border border-border bg-card">
        <div className="px-5 py-4 border-b border-border flex items-center gap-2">
          <Server className="w-4 h-4 text-primary" />
          <p className="font-semibold text-foreground text-sm">Hub Nodes — Instrument Processing</p>
          <span className="ml-auto text-xs text-muted-foreground">{hubs.length} hubs</span>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border">
                {["Hub", "Grid", "Branches", "Instruments Today", "Avg Processing", "Scanners", "Status"].map((h) => (
                  <th key={h} className="text-left px-4 py-2.5 text-muted-foreground font-medium whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {hubs.map((hub) => (
                <tr key={hub.hub_code} className="border-b border-border/50 hover:bg-muted/30 transition-colors">
                  <td className="px-4 py-2.5">
                    <p className="font-medium text-foreground">{hub.hub_name}</p>
                    <p className="text-muted-foreground font-mono">{hub.hub_code}</p>
                  </td>
                  <td className="px-4 py-2.5">
                    <span className="inline-flex items-center gap-1.5">
                      <div className="w-2 h-2 rounded-full" style={{ background: GRID_COLORS[hub.grid_id] ?? "#64748b" }} />
                      <span className="text-muted-foreground">{hub.grid_id.replace("GRID-", "")}</span>
                    </span>
                  </td>
                  <td className="px-4 py-2.5 text-foreground font-medium">{hub.connected_branches}</td>
                  <td className="px-4 py-2.5 text-foreground font-medium">{hub.instruments_today.toLocaleString("en-IN")}</td>
                  <td className="px-4 py-2.5">
                    <span className={cn("font-mono font-bold", hub.avg_processing_ms > 600 ? "text-amber-400" : "text-green-400")}>
                      {hub.avg_processing_ms}ms
                    </span>
                  </td>
                  <td className="px-4 py-2.5 text-center text-foreground">{hub.scanner_count}</td>
                  <td className="px-4 py-2.5"><StatusBadge status={hub.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Inter-Grid Settlement Flows */}
      <div className="rounded-lg border border-border bg-card">
        <div className="px-5 py-4 border-b border-border flex items-center gap-2">
          <ArrowRightLeft className="w-4 h-4 text-primary" />
          <p className="font-semibold text-foreground text-sm">Inter-Grid Settlement Flows</p>
          <span className="ml-auto text-xs text-muted-foreground">{flows.length} active corridors</span>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border">
                {["From", "", "To", "Instruments", "Amount (₹ Cr)", "Status"].map((h, i) => (
                  <th key={i} className="text-left px-4 py-2.5 text-muted-foreground font-medium whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {flows.map((flow, i) => (
                <tr key={i} className="border-b border-border/50 hover:bg-muted/30 transition-colors">
                  <td className="px-4 py-2.5">
                    <span className="inline-flex items-center gap-1.5">
                      <div className="w-2.5 h-2.5 rounded-full" style={{ background: GRID_COLORS[flow.from] ?? "#64748b" }} />
                      <span className="font-medium text-foreground">{flow.from.replace("GRID-", "")}</span>
                    </span>
                  </td>
                  <td className="px-2 py-2.5 text-muted-foreground"><ChevronRight className="w-3.5 h-3.5" /></td>
                  <td className="px-4 py-2.5">
                    <span className="inline-flex items-center gap-1.5">
                      <div className="w-2.5 h-2.5 rounded-full" style={{ background: GRID_COLORS[flow.to] ?? "#64748b" }} />
                      <span className="font-medium text-foreground">{flow.to.replace("GRID-", "")}</span>
                    </span>
                  </td>
                  <td className="px-4 py-2.5 font-medium text-foreground">{flow.instruments.toLocaleString("en-IN")}</td>
                  <td className="px-4 py-2.5 font-medium text-foreground">₹{flow.amount_cr} Cr</td>
                  <td className="px-4 py-2.5"><StatusBadge status={flow.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
