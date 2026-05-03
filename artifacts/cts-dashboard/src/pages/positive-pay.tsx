import { useState } from "react";
import { motion } from "framer-motion";
import {
  useGetPositivePaySummary,
  useListPositivePayRegistrations,
  useListPositivePayMismatches,
} from "@workspace/api-client-react";
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from "recharts";
import { cn } from "@/lib/utils";
import { ShieldCheck, AlertTriangle, Clock, XCircle, CheckCircle2, ChevronLeft, ChevronRight } from "lucide-react";

const MATCH_COLORS: Record<string, string> = {
  MATCHED: "bg-green-500/15 text-green-400 border border-green-500/30",
  MISMATCHED: "bg-red-500/15 text-red-400 border border-red-500/30",
  PENDING: "bg-amber-500/15 text-amber-500 border border-amber-500/30",
  NOT_REGISTERED: "bg-muted text-muted-foreground border border-border",
};

const PIE_COLORS = ["hsl(160 60% 45%)", "hsl(0 84% 60%)", "hsl(38 92% 55%)", "hsl(224 20% 50%)"];

function fmt(n: number) {
  if (n >= 1e9) return `₹${(n / 1e9).toFixed(2)}B`;
  if (n >= 1e7) return `₹${(n / 1e7).toFixed(2)} Cr`;
  if (n >= 1e5) return `₹${(n / 1e5).toFixed(1)} L`;
  return `₹${n.toLocaleString("en-IN")}`;
}

function MismatchBadge({ fields }: { fields: string[] }) {
  if (!fields || fields.length === 0) return <span className="text-muted-foreground">—</span>;
  return (
    <div className="flex gap-1 flex-wrap">
      {fields.map((f) => (
        <span key={f} className="px-1.5 py-0.5 rounded bg-destructive/15 text-destructive text-[10px] font-semibold uppercase">
          {f}
        </span>
      ))}
    </div>
  );
}

export default function PositivePay() {
  const [matchStatus, setMatchStatus] = useState("");
  const [branchCode, setBranchCode] = useState("");
  const [page, setPage] = useState(1);
  const limit = 25;

  const { data: summary } = useGetPositivePaySummary({}, { query: { refetchInterval: 30000 } });
  const { data: registrations, isLoading } = useListPositivePayRegistrations(
    {
      match_status: (matchStatus as "MATCHED" | "MISMATCHED" | "PENDING" | "NOT_REGISTERED") || undefined,
      branch_code: branchCode || undefined,
      page,
      limit,
    },
    { query: { keepPreviousData: true } as never }
  );
  const { data: mismatches } = useListPositivePayMismatches({ limit: 5 }, { query: { refetchInterval: 30000 } });

  const totalPages = Math.ceil((registrations?.total ?? 0) / limit);

  const pieData = [
    { name: "Matched", value: summary?.matched ?? 0 },
    { name: "Mismatched", value: summary?.mismatched ?? 0 },
    { name: "Pending", value: summary?.pending_match ?? 0 },
    { name: "Not Registered", value: summary?.not_registered ?? 0 },
  ];

  const kpis = [
    { label: "Total Registered", value: (summary?.total_registered ?? 0).toLocaleString("en-IN"), icon: ShieldCheck, color: "text-foreground" },
    { label: "Matched", value: (summary?.matched ?? 0).toLocaleString("en-IN"), icon: CheckCircle2, color: "text-green-500" },
    { label: "Mismatched", value: (summary?.mismatched ?? 0).toLocaleString("en-IN"), icon: XCircle, color: "text-destructive" },
    { label: "Pending Match", value: (summary?.pending_match ?? 0).toLocaleString("en-IN"), icon: Clock, color: "text-amber-500" },
    { label: "High Value (≥₹5L)", value: (summary?.high_value_registered ?? 0).toLocaleString("en-IN"), icon: AlertTriangle, color: "text-primary" },
    { label: "Amount Mismatched", value: fmt(summary?.amount_mismatched ?? 0), icon: XCircle, color: "text-destructive" },
  ];

  return (
    <div className="p-6 space-y-5">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-foreground">Positive Pay Register</h1>
        <p className="text-xs text-muted-foreground mt-0.5">
          NPCI CPPS · Mandatory w.e.f. 01.01.2021 · All cheques ≥ ₹5,00,000 must be registered
        </p>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
        {kpis.map(({ label, value, icon: Icon, color }, i) => (
          <motion.div
            key={label}
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="bg-card border border-card-border rounded p-3 flex items-center gap-3"
          >
            <Icon className={cn("w-5 h-5 shrink-0", color)} />
            <div>
              <p className="text-[10px] text-muted-foreground uppercase tracking-wider">{label}</p>
              <p className={cn("text-xl font-bold tabular-nums", color)}>{value}</p>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Pie chart */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.2 }}
          className="bg-card border border-card-border rounded p-4"
        >
          <h2 className="text-sm font-semibold text-foreground mb-2">Match Status Breakdown</h2>
          <ResponsiveContainer width="100%" height={200}>
            <PieChart>
              <Pie
                data={pieData}
                cx="50%"
                cy="50%"
                innerRadius={50}
                outerRadius={75}
                paddingAngle={2}
                dataKey="value"
              >
                {pieData.map((_, index) => (
                  <Cell key={index} fill={PIE_COLORS[index]} />
                ))}
              </Pie>
              <Tooltip
                contentStyle={{ background: "hsl(var(--card))", border: "1px solid hsl(var(--border))", fontSize: 12 }}
                formatter={(v: number) => [v.toLocaleString("en-IN"), ""]}
              />
              <Legend
                iconSize={8}
                wrapperStyle={{ fontSize: "11px" }}
              />
            </PieChart>
          </ResponsiveContainer>
        </motion.div>

        {/* Live mismatch alert feed */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.25 }}
          className="lg:col-span-2 bg-card border border-red-500/25 rounded p-4"
        >
          <h2 className="text-sm font-semibold text-destructive mb-3 flex items-center gap-2">
            <AlertTriangle className="w-4 h-4" />
            Live Mismatch Alerts (Latest 5)
          </h2>
          <div className="space-y-2">
            {(mismatches ?? []).length === 0 && (
              <div className="py-8 text-center text-muted-foreground text-sm">
                <CheckCircle2 className="w-8 h-8 text-green-500 mx-auto mb-2" />
                No mismatches — all presented cheques match registrations
              </div>
            )}
            {(mismatches ?? []).map((m, idx) => (
              <motion.div
                key={m.id}
                initial={{ opacity: 0, x: -8 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.3 + idx * 0.05 }}
                className="flex items-start gap-3 p-2.5 rounded border border-red-500/20 bg-red-500/5 text-xs"
              >
                <XCircle className="w-4 h-4 text-destructive shrink-0 mt-0.5" />
                <div className="flex-1 min-w-0 space-y-1">
                  <div className="flex items-center justify-between">
                    <span className="font-mono font-semibold text-foreground">
                      A/C {m.account_number} · Chq {m.cheque_number}
                    </span>
                    <span className="text-muted-foreground ml-2 shrink-0">
                      {m.branch_code}
                    </span>
                  </div>
                  <div className="flex gap-4 text-muted-foreground">
                    <span>Registered: {fmt(m.amount ?? 0)} → {m.payee_name}</span>
                    <span className="text-destructive">
                      Presented: {fmt(m.presented_amount ?? 0)} → {m.presented_payee}
                    </span>
                  </div>
                  <MismatchBadge fields={m.mismatch_fields ?? []} />
                </div>
              </motion.div>
            ))}
          </div>
        </motion.div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-center">
        <select
          value={matchStatus}
          onChange={(e) => { setMatchStatus(e.target.value); setPage(1); }}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="">All Match Statuses</option>
          {["MATCHED", "MISMATCHED", "PENDING", "NOT_REGISTERED"].map((s) => (
            <option key={s} value={s}>{s.replace("_", " ")}</option>
          ))}
        </select>
        <select
          value={branchCode}
          onChange={(e) => { setBranchCode(e.target.value); setPage(1); }}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="">All Branches</option>
          {["CHN001", "CHN002", "MUM001", "MUM002", "DEL001", "DEL002", "KOL001", "HYD001", "BLR001", "BLR002"].map((b) => (
            <option key={b} value={b}>{b}</option>
          ))}
        </select>
        <span className="ml-auto text-xs text-muted-foreground">
          {registrations?.total?.toLocaleString("en-IN") ?? "—"} registrations
        </span>
      </div>

      {/* Table */}
      <div className="bg-card border border-card-border rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                {[
                  "CPPS Ref",
                  "Account",
                  "Cheque",
                  "Registered Payee",
                  "Reg. Amount",
                  "Branch",
                  "Match Status",
                  "Presented Amount",
                  "Presented Payee",
                  "Mismatch Fields",
                  "HV",
                  "Registered At",
                ].map((h) => (
                  <th key={h} className="text-left px-3 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 10 }).map((_, i) => (
                    <tr key={i} className="border-b border-border">
                      {Array.from({ length: 12 }).map((_, j) => (
                        <td key={j} className="px-3 py-2">
                          <div className="h-3 bg-muted rounded animate-pulse" />
                        </td>
                      ))}
                    </tr>
                  ))
                : (registrations?.data ?? []).map((r, idx) => (
                    <motion.tr
                      key={r.id}
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      transition={{ delay: idx * 0.015 }}
                      className={cn(
                        "border-b border-border hover:bg-accent/30 transition-colors",
                        r.match_status === "MISMATCHED" && "bg-red-500/5"
                      )}
                    >
                      <td className="px-3 py-2 font-mono text-[10px] text-muted-foreground whitespace-nowrap">
                        {r.cpps_reference?.slice(-14)}
                      </td>
                      <td className="px-3 py-2 font-mono">{r.account_number}</td>
                      <td className="px-3 py-2 font-mono font-semibold">{r.cheque_number}</td>
                      <td className="px-3 py-2 max-w-[140px] truncate">{r.payee_name}</td>
                      <td className="px-3 py-2 tabular-nums font-semibold text-right whitespace-nowrap">
                        {fmt(r.amount ?? 0)}
                      </td>
                      <td className="px-3 py-2 font-mono">{r.branch_code}</td>
                      <td className="px-3 py-2">
                        <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold whitespace-nowrap", MATCH_COLORS[r.match_status ?? ""] ?? "")}>
                          {r.match_status?.replace("_", " ")}
                        </span>
                      </td>
                      <td className={cn("px-3 py-2 tabular-nums text-right whitespace-nowrap", r.match_status === "MISMATCHED" ? "text-destructive font-bold" : "text-muted-foreground")}>
                        {r.presented_amount != null ? fmt(r.presented_amount) : "—"}
                      </td>
                      <td className={cn("px-3 py-2 max-w-[120px] truncate", r.match_status === "MISMATCHED" ? "text-destructive" : "text-muted-foreground")}>
                        {r.presented_payee ?? "—"}
                      </td>
                      <td className="px-3 py-2">
                        <MismatchBadge fields={r.mismatch_fields ?? []} />
                      </td>
                      <td className="px-3 py-2 text-center">
                        {(r as { is_high_value?: boolean }).is_high_value
                          ? <span className="text-primary font-bold text-[10px]">HV</span>
                          : <span className="text-muted-foreground">—</span>}
                      </td>
                      <td className="px-3 py-2 text-muted-foreground whitespace-nowrap text-[10px]">
                        {r.registered_at ? new Date(r.registered_at).toLocaleTimeString("en-IN") : "—"}
                      </td>
                    </motion.tr>
                  ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex items-center justify-between px-4 py-2.5 border-t border-border">
          <span className="text-xs text-muted-foreground">
            Page {page} of {totalPages || 1} · {registrations?.total?.toLocaleString("en-IN") ?? 0} total
          </span>
          <div className="flex gap-1">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              className="p-1 rounded border border-border hover:bg-accent disabled:opacity-40 transition-colors"
            >
              <ChevronLeft className="w-3.5 h-3.5" />
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages}
              className="p-1 rounded border border-border hover:bg-accent disabled:opacity-40 transition-colors"
            >
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
