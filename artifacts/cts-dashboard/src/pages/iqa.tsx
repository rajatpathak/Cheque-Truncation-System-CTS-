import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { cn } from "@/lib/utils";
import {
  ScanLine, CheckCircle2, XCircle, RefreshCw, AlertTriangle,
  TrendingUp, Filter, ChevronLeft, ChevronRight, Award,
} from "lucide-react";
import {
  RadialBarChart, RadialBar, ResponsiveContainer, Tooltip,
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Cell, PieChart, Pie, Legend,
} from "recharts";

function useIqaSummary() {
  return useQuery({
    queryKey: ["iqa", "summary"],
    queryFn: () => fetch(`/api/iqa/summary`).then((r) => r.json()),
    refetchInterval: 60000,
  });
}

function useIqaBranches() {
  return useQuery({
    queryKey: ["iqa", "branches"],
    queryFn: () => fetch(`/api/iqa/branches`).then((r) => r.json()),
  });
}

function useIqaFailed(page: number, branch?: string, reason?: string) {
  return useQuery({
    queryKey: ["iqa", "failed", page, branch, reason],
    queryFn: () => {
      const params = new URLSearchParams({ page: String(page), limit: "15" });
      if (branch) params.set("branch", branch);
      if (reason) params.set("reason", reason);
      return fetch(`/api/iqa/failed-instruments?${params}`).then((r) => r.json());
    },
  });
}

const REASON_COLORS: Record<string, string> = {
  SKEW_EXCESSIVE: "#f59e0b",
  LOW_CONTRAST: "#3b82f6",
  LOW_RESOLUTION: "#8b5cf6",
  TORN_MUTILATED: "#ef4444",
  INK_SPREAD: "#f97316",
  FOLD_CREASE: "#06b6d4",
  OVERWRITING: "#ec4899",
  BACKGROUND_COMPLEX: "#10b981",
};

const STATUS_COLORS: Record<string, string> = {
  PENDING_RESCAN: "bg-amber-500/15 text-amber-400 border-amber-500/30",
  RESCANNED_OK: "bg-green-500/15 text-green-400 border-green-500/30",
  RETURNED_IQA: "bg-red-500/15 text-red-400 border-red-500/30",
  UNDER_REVIEW: "bg-blue-500/15 text-blue-400 border-blue-500/30",
};

const STATUS_LABELS: Record<string, string> = {
  PENDING_RESCAN: "Pending Rescan",
  RESCANNED_OK: "Rescanned OK",
  RETURNED_IQA: "Returned (IQA)",
  UNDER_REVIEW: "Under Review",
};

function KpiCard({ icon: Icon, label, value, sub, color }: {
  icon: React.ElementType; label: string; value: string; sub?: string; color?: string;
}) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-start justify-between mb-2">
        <p className="text-xs text-muted-foreground uppercase tracking-wide">{label}</p>
        <div className={cn("w-7 h-7 rounded-md flex items-center justify-center", color ?? "bg-primary/10")}>
          <Icon className="w-4 h-4 text-primary" />
        </div>
      </div>
      <p className={cn("text-2xl font-bold", color?.includes("red") ? "text-red-400" : color?.includes("green") ? "text-green-400" : color?.includes("amber") ? "text-amber-400" : "text-foreground")}>{value}</p>
      {sub && <p className="text-xs text-muted-foreground mt-0.5">{sub}</p>}
    </div>
  );
}

export default function IQA() {
  const { data: summary } = useIqaSummary();
  const { data: branchData } = useIqaBranches();
  const [page, setPage] = useState(1);
  const [branchFilter, setBranchFilter] = useState("");
  const [reasonFilter, setReasonFilter] = useState("");
  const { data: failed } = useIqaFailed(page, branchFilter || undefined, reasonFilter || undefined);

  const branches: string[] = branchData?.branches?.map((b: { branch_code: string }) => b.branch_code) ?? [];

  const gaugeData = summary ? [
    { name: "Pass Rate", value: summary.overall_pass_rate, fill: summary.threshold_met ? "#22c55e" : "#ef4444" },
  ] : [];

  const reasonData = summary?.reason_breakdown ?? [];
  const branchChartData = (branchData?.branches ?? []).slice(-10);

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <ScanLine className="w-6 h-6 text-primary" /> Image Quality Analysis (IQA)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            NPCI CTS 2010 · IQA threshold: 98.0% pass rate · Real-time scanner monitoring
          </p>
        </div>
        {summary && (
          <div className={cn(
            "flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold border",
            summary.threshold_met
              ? "bg-green-500/15 border-green-500/30 text-green-400"
              : "bg-red-500/15 border-red-500/30 text-red-400"
          )}>
            {summary.threshold_met ? <CheckCircle2 className="w-4 h-4" /> : <XCircle className="w-4 h-4" />}
            {summary.threshold_met ? "Threshold Met" : "Below Threshold"}
          </div>
        )}
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <KpiCard icon={ScanLine} label="Total Scanned" value={summary?.total_scanned?.toLocaleString("en-IN") ?? "—"} color="bg-primary/10" />
        <KpiCard icon={CheckCircle2} label="Passed" value={summary?.total_passed?.toLocaleString("en-IN") ?? "—"} color="bg-green-500/10 green" />
        <KpiCard icon={XCircle} label="Failed" value={summary?.total_failed?.toLocaleString("en-IN") ?? "—"} color="bg-red-500/10 red" />
        <KpiCard icon={RefreshCw} label="Rescanned" value={summary?.total_rescanned?.toLocaleString("en-IN") ?? "—"} color="bg-amber-500/10 amber" />
        <KpiCard icon={TrendingUp} label="Pass Rate" value={summary ? `${summary.overall_pass_rate}%` : "—"} sub="Target: 98.0%" color="bg-green-500/10 green" />
        <KpiCard icon={Award} label="Rescan Success" value={summary ? `${summary.rescan_success_rate}%` : "—"} sub="After rescan" color="bg-primary/10" />
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Pass Rate Gauge */}
        <div className="rounded-lg border border-border bg-card p-5">
          <p className="text-sm font-semibold text-foreground mb-1">Overall Pass Rate</p>
          <p className="text-xs text-muted-foreground mb-4">vs. NPCI 98% threshold</p>
          <div className="h-40 flex items-center justify-center">
            {summary ? (
              <div className="relative w-40 h-40">
                <ResponsiveContainer width="100%" height="100%">
                  <RadialBarChart innerRadius="60%" outerRadius="90%" startAngle={180} endAngle={0} data={gaugeData}>
                    <RadialBar dataKey="value" background={{ fill: "#ffffff10" }} />
                    <Tooltip formatter={(v: number) => `${v}%`} />
                  </RadialBarChart>
                </ResponsiveContainer>
                <div className="absolute inset-0 flex flex-col items-center justify-center mt-6">
                  <span className={cn("text-2xl font-bold", summary.threshold_met ? "text-green-400" : "text-red-400")}>
                    {summary.overall_pass_rate}%
                  </span>
                  <span className="text-xs text-muted-foreground">Pass Rate</span>
                </div>
              </div>
            ) : (
              <div className="text-muted-foreground text-sm">Loading…</div>
            )}
          </div>
          <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
            <span>Threshold: 98.0%</span>
            <span className="text-foreground font-medium">{summary?.branch_count ?? 0} branches</span>
          </div>
        </div>

        {/* Failure Reason Pie */}
        <div className="rounded-lg border border-border bg-card p-5">
          <p className="text-sm font-semibold text-foreground mb-1">Failure Reasons</p>
          <p className="text-xs text-muted-foreground mb-3">Today's IQA rejection breakdown</p>
          <ResponsiveContainer width="100%" height={180}>
            <PieChart>
              <Pie data={reasonData} dataKey="count" nameKey="label" cx="50%" cy="50%" innerRadius={45} outerRadius={70}>
                {reasonData.map((r: { reason_code: string }) => (
                  <Cell key={r.reason_code} fill={REASON_COLORS[r.reason_code] ?? "#64748b"} />
                ))}
              </Pie>
              <Tooltip formatter={(v: number, n: string) => [v, n]} />
            </PieChart>
          </ResponsiveContainer>
          <div className="grid grid-cols-2 gap-1 mt-2">
            {reasonData.slice(0, 4).map((r: { reason_code: string; label: string; count: number; percentage: number }) => (
              <div key={r.reason_code} className="flex items-center gap-1.5 text-xs">
                <div className="w-2 h-2 rounded-full shrink-0" style={{ background: REASON_COLORS[r.reason_code] }} />
                <span className="text-muted-foreground truncate">{r.label}</span>
                <span className="text-foreground font-medium ml-auto">{r.percentage}%</span>
              </div>
            ))}
          </div>
        </div>

        {/* Branch Pass Rates */}
        <div className="rounded-lg border border-border bg-card p-5">
          <p className="text-sm font-semibold text-foreground mb-1">Branch Pass Rates</p>
          <p className="text-xs text-muted-foreground mb-3">Bottom 10 branches (sorted by pass rate)</p>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={branchChartData} layout="vertical" margin={{ left: 10, right: 10 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" horizontal={false} />
              <XAxis type="number" domain={[90, 100]} tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} unit="%" />
              <YAxis type="category" dataKey="branch_code" tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} width={55} />
              <Tooltip formatter={(v: number) => [`${v}%`, "Pass Rate"]} />
              <Bar dataKey="pass_rate" radius={[0, 4, 4, 0]}>
                {branchChartData.map((b: { pass_rate: number }) => (
                  <Cell key={b.pass_rate} fill={b.pass_rate >= 98 ? "#22c55e" : b.pass_rate >= 96 ? "#f59e0b" : "#ef4444"} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Failed Instruments Table */}
      <div className="rounded-lg border border-border bg-card">
        <div className="px-5 py-4 border-b border-border flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2 flex-1">
            <AlertTriangle className="w-4 h-4 text-red-400" />
            <p className="font-semibold text-foreground text-sm">IQA Failed Instruments — Re-scan Queue</p>
          </div>
          <div className="flex items-center gap-2">
            <Filter className="w-4 h-4 text-muted-foreground" />
            <select
              value={branchFilter}
              onChange={(e) => { setBranchFilter(e.target.value); setPage(1); }}
              className="bg-background border border-border rounded-md px-3 py-1.5 text-xs text-foreground"
            >
              <option value="">All Branches</option>
              {branches.map((b) => <option key={b} value={b}>{b}</option>)}
            </select>
            <select
              value={reasonFilter}
              onChange={(e) => { setReasonFilter(e.target.value); setPage(1); }}
              className="bg-background border border-border rounded-md px-3 py-1.5 text-xs text-foreground"
            >
              <option value="">All Reasons</option>
              {Object.entries({ SKEW_EXCESSIVE: "Excessive Skew", LOW_CONTRAST: "Low Contrast", LOW_RESOLUTION: "Low Resolution", TORN_MUTILATED: "Torn/Mutilated", INK_SPREAD: "Ink Spread", FOLD_CREASE: "Fold/Crease", OVERWRITING: "Overwriting", BACKGROUND_COMPLEX: "Complex Background" }).map(([k, v]) => (
                <option key={k} value={k}>{v}</option>
              ))}
            </select>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border">
                {["Instrument", "Branch", "Drawee Bank", "Amount", "IQA Score", "Failure Reason", "Status", "Rescans", "Scan Time"].map((h) => (
                  <th key={h} className="text-left px-4 py-2.5 text-muted-foreground font-medium whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {failed?.instruments?.map((inst: {
                id: number; instrument_number: string; branch_code: string; drawee_bank: string;
                amount: number; iqa_score: number; threshold_score: number; failure_label: string;
                failure_reason: string; status: string; rescan_count: number; scan_timestamp: string;
              }) => (
                <tr key={inst.id} className="border-b border-border/50 hover:bg-muted/30 transition-colors">
                  <td className="px-4 py-2.5 font-mono text-foreground whitespace-nowrap">{inst.instrument_number}</td>
                  <td className="px-4 py-2.5 text-muted-foreground">{inst.branch_code}</td>
                  <td className="px-4 py-2.5 text-muted-foreground whitespace-nowrap">{inst.drawee_bank}</td>
                  <td className="px-4 py-2.5 text-foreground font-medium whitespace-nowrap">₹{inst.amount.toLocaleString("en-IN")}</td>
                  <td className="px-4 py-2.5">
                    <span className={cn("font-bold", inst.iqa_score >= inst.threshold_score ? "text-green-400" : "text-red-400")}>
                      {inst.iqa_score}
                    </span>
                    <span className="text-muted-foreground">/{inst.threshold_score}</span>
                  </td>
                  <td className="px-4 py-2.5">
                    <span className="flex items-center gap-1.5">
                      <div className="w-2 h-2 rounded-full shrink-0" style={{ background: REASON_COLORS[inst.failure_reason] ?? "#64748b" }} />
                      <span className="text-muted-foreground">{inst.failure_label}</span>
                    </span>
                  </td>
                  <td className="px-4 py-2.5">
                    <span className={cn("px-2 py-0.5 rounded-full text-[10px] font-medium border", STATUS_COLORS[inst.status] ?? "bg-muted text-muted-foreground border-border")}>
                      {STATUS_LABELS[inst.status] ?? inst.status}
                    </span>
                  </td>
                  <td className="px-4 py-2.5 text-center text-muted-foreground">{inst.rescan_count}</td>
                  <td className="px-4 py-2.5 text-muted-foreground whitespace-nowrap">
                    {new Date(inst.scan_timestamp).toLocaleString("en-IN", { hour12: false, hour: "2-digit", minute: "2-digit", day: "2-digit", month: "short" })}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {failed && (
          <div className="px-5 py-3 flex items-center justify-between text-xs text-muted-foreground border-t border-border">
            <span>{failed.total} failed instruments{branchFilter ? ` · ${branchFilter}` : ""}{reasonFilter ? ` · ${reasonFilter}` : ""}</span>
            <div className="flex items-center gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="p-1 rounded hover:bg-muted disabled:opacity-30">
                <ChevronLeft className="w-4 h-4" />
              </button>
              <span>Page {page} of {failed.pages}</span>
              <button onClick={() => setPage((p) => Math.min(failed.pages, p + 1))} disabled={page === failed.pages} className="p-1 rounded hover:bg-muted disabled:opacity-30">
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
