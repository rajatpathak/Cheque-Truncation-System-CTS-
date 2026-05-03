import { motion } from "framer-motion";
import {
  useGetDashboardSummary,
  useGetPipelineStatus,
  useGetThroughput,
  useGetGridSummary,
  useGetRecentInstruments,
} from "@workspace/api-client-react";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  BarChart,
  Bar,
} from "recharts";
import { Activity, AlertTriangle, CheckCircle2, Clock, TrendingUp, ShieldCheck, RefreshCw } from "lucide-react";
import { cn } from "@/lib/utils";

function fmt(n: number) {
  if (n >= 1e9) return `₹${(n / 1e9).toFixed(2)}B`;
  if (n >= 1e6) return `₹${(n / 1e6).toFixed(1)}M`;
  if (n >= 1e3) return `₹${(n / 1e3).toFixed(0)}K`;
  return `₹${n.toFixed(0)}`;
}

function fmtNum(n: number) {
  return n.toLocaleString("en-IN");
}

function KpiCard({ label, value, sub, color, delay = 0 }: { label: string; value: string; sub?: string; color?: string; delay?: number }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay, duration: 0.3 }}
      className="bg-card border border-card-border rounded p-4"
    >
      <p className="text-xs font-medium text-muted-foreground uppercase tracking-widest mb-1">{label}</p>
      <p className={cn("text-2xl font-bold tabular-nums", color ?? "text-foreground")}>{value}</p>
      {sub && <p className="text-xs text-muted-foreground mt-0.5">{sub}</p>}
    </motion.div>
  );
}

function StageBadge({ stage, count, pct }: { stage: string; count: number; pct: number }) {
  return (
    <div className="flex items-center gap-3 py-2 border-b border-border last:border-0">
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between mb-1">
          <span className="text-xs font-medium text-foreground">{stage}</span>
          <span className="text-xs font-semibold tabular-nums">{fmtNum(count)}</span>
        </div>
        <div className="h-1.5 rounded-full bg-muted overflow-hidden">
          <motion.div
            initial={{ width: 0 }}
            animate={{ width: `${pct}%` }}
            transition={{ duration: 0.8, delay: 0.1 }}
            className="h-full rounded-full bg-primary"
          />
        </div>
      </div>
      <span className="text-xs tabular-nums text-muted-foreground w-10 text-right">{pct.toFixed(1)}%</span>
    </div>
  );
}

const GRID_COLS = ["CHN", "MUM", "DEL", "KOL", "HYD", "BLR"];

export default function Dashboard() {
  const { data: summary, isLoading: sumLoading } = useGetDashboardSummary(
    {},
    { query: { refetchInterval: 30000 } }
  );
  const { data: pipeline } = useGetPipelineStatus({}, { query: { refetchInterval: 30000 } });
  const { data: throughput } = useGetThroughput({ query: { refetchInterval: 60000 } });
  const { data: grid } = useGetGridSummary({}, { query: { refetchInterval: 60000 } });
  const { data: recent } = useGetRecentInstruments({ limit: 8 }, { query: { refetchInterval: 30000 } });

  const STATUS_COLOR: Record<string, string> = {
    CAPTURED: "bg-blue-500/20 text-blue-400",
    IQA_DONE: "bg-cyan-500/20 text-cyan-400",
    FRAUD_CLEARED: "bg-green-500/20 text-green-400",
    SIGNED: "bg-violet-500/20 text-violet-400",
    SUBMITTED: "bg-amber-500/20 text-amber-400",
    SETTLED: "bg-emerald-500/20 text-emerald-400",
    RETURNED: "bg-red-500/20 text-red-400",
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-foreground">Operations Overview</h1>
          <p className="text-xs text-muted-foreground mt-0.5">
            {new Date().toLocaleString("en-IN", { dateStyle: "full", timeStyle: "short" })} · CTS National Grid
          </p>
        </div>
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <RefreshCw className="w-3 h-3" />
          Auto-refresh 30s
        </div>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard
          label="Total Instruments"
          value={sumLoading ? "—" : fmtNum(summary?.total_instruments ?? 0)}
          sub={`${fmtNum(summary?.high_value_count ?? 0)} high-value`}
          delay={0}
        />
        <KpiCard
          label="Total Amount"
          value={sumLoading ? "—" : fmt(summary?.total_amount ?? 0)}
          sub="Today's clearing volume"
          delay={0.05}
        />
        <KpiCard
          label="IQA Pass Rate"
          value={sumLoading ? "—" : `${summary?.iqa_pass_pct?.toFixed(2) ?? 0}%`}
          sub={`${fmtNum(summary?.iqa_fail ?? 0)} failed`}
          color="text-green-500"
          delay={0.1}
        />
        <KpiCard
          label="System Uptime"
          value={sumLoading ? "—" : `${summary?.uptime_pct?.toFixed(2) ?? 0}%`}
          sub={summary?.active_node ?? "DC Chennai"}
          color="text-primary"
          delay={0.15}
        />
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard label="Submitted" value={fmtNum(summary?.submitted ?? 0)} sub="To CHI" delay={0.2} />
        <KpiCard label="Pending" value={fmtNum(summary?.pending ?? 0)} sub="In pipeline" color="text-amber-500" delay={0.25} />
        <KpiCard
          label="Fraud Flagged"
          value={fmtNum(summary?.fraud_flagged ?? 0)}
          sub={`${summary?.fraud_blocked ?? 0} auto-blocked`}
          color="text-destructive"
          delay={0.3}
        />
        <KpiCard label="Returns" value={fmtNum(summary?.returns_today ?? 0)} sub="Today" delay={0.35} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Throughput Chart */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.2 }}
          className="lg:col-span-2 bg-card border border-card-border rounded p-4"
        >
          <h2 className="text-sm font-semibold text-foreground mb-4 flex items-center gap-2">
            <TrendingUp className="w-4 h-4 text-primary" />
            Hourly Instrument Throughput
          </h2>
          <ResponsiveContainer width="100%" height={200}>
            <AreaChart data={throughput ?? []}>
              <defs>
                <linearGradient id="tg" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="hsl(38 92% 55%)" stopOpacity={0.25} />
                  <stop offset="95%" stopColor="hsl(38 92% 55%)" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
              <XAxis dataKey="hour" tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} />
              <YAxis tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} />
              <Tooltip
                contentStyle={{ background: "hsl(var(--card))", border: "1px solid hsl(var(--border))", fontSize: 12 }}
                formatter={(v: number) => [fmtNum(v), "Instruments"]}
              />
              <Area type="monotone" dataKey="count" stroke="hsl(38 92% 55%)" fill="url(#tg)" strokeWidth={2} />
            </AreaChart>
          </ResponsiveContainer>
        </motion.div>

        {/* Pipeline */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.25 }}
          className="bg-card border border-card-border rounded p-4"
        >
          <h2 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
            <Activity className="w-4 h-4 text-primary" />
            Instrument Pipeline
          </h2>
          <div className="space-y-1">
            {(pipeline ?? []).map((s) => (
              <StageBadge key={s.stage} stage={s.stage} count={s.count} pct={s.pct ?? 0} />
            ))}
          </div>
        </motion.div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Grid Summary */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.3 }}
          className="bg-card border border-card-border rounded p-4"
        >
          <h2 className="text-sm font-semibold text-foreground mb-4 flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-primary" />
            Volume by Grid
          </h2>
          <ResponsiveContainer width="100%" height={180}>
            <BarChart data={grid ?? []}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
              <XAxis dataKey="grid_code" tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} />
              <YAxis tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} />
              <Tooltip
                contentStyle={{ background: "hsl(var(--card))", border: "1px solid hsl(var(--border))", fontSize: 12 }}
                formatter={(v: number) => [fmtNum(v), ""]}
              />
              <Bar dataKey="total" fill="hsl(224 60% 40%)" name="Total" radius={[2, 2, 0, 0]} />
              <Bar dataKey="submitted" fill="hsl(38 92% 55%)" name="Submitted" radius={[2, 2, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
          <div className="flex gap-4 mt-2 text-xs text-muted-foreground">
            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-blue-700 inline-block" />Total</span>
            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-primary inline-block" />Submitted</span>
          </div>
        </motion.div>

        {/* Recent Instruments */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.35 }}
          className="bg-card border border-card-border rounded p-4"
        >
          <h2 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
            <Clock className="w-4 h-4 text-primary" />
            Recent Instruments
          </h2>
          <div className="space-y-1.5">
            {(recent ?? []).map((inst) => (
              <div key={inst.id} className="flex items-center gap-2 text-xs py-1 border-b border-border last:border-0">
                <span className="font-mono text-muted-foreground w-32 truncate">{inst.instrument_id}</span>
                <span className="flex-1 truncate text-foreground">{inst.payee_name}</span>
                <span className="font-semibold tabular-nums text-right">{fmt(inst.amount_figures ?? 0)}</span>
                <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold", STATUS_COLOR[inst.status] ?? "bg-muted text-muted-foreground")}>
                  {inst.status}
                </span>
              </div>
            ))}
            {!recent && (
              <div className="space-y-2">
                {Array.from({ length: 6 }).map((_, i) => (
                  <div key={i} className="h-5 bg-muted rounded animate-pulse" />
                ))}
              </div>
            )}
          </div>
        </motion.div>
      </div>

      {/* Status bar */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.4 }}
        className="flex items-center gap-6 text-xs text-muted-foreground border border-border rounded px-4 py-2.5"
      >
        <span className="flex items-center gap-1.5">
          <ShieldCheck className="w-3.5 h-3.5 text-green-500" />
          Fraud check: ACTIVE
        </span>
        <span className="flex items-center gap-1.5">
          <Activity className="w-3.5 h-3.5 text-green-500" />
          CHI link: CONNECTED
        </span>
        <span className="flex items-center gap-1.5">
          <AlertTriangle className="w-3.5 h-3.5 text-amber-500" />
          Active alerts: {summary?.fraud_flagged ?? 0}
        </span>
        <span className="ml-auto">Active sessions: {summary?.active_sessions ?? 0} · Batches: {summary?.active_batches ?? 0}</span>
      </motion.div>
    </div>
  );
}
