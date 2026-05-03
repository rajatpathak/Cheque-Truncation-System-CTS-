import { useState } from "react";
import { motion } from "framer-motion";
import { useListFraudAlerts, useGetFraudSummary } from "@workspace/api-client-react";
import { cn } from "@/lib/utils";
import { ShieldAlert, ShieldX, AlertTriangle, ShieldCheck } from "lucide-react";

const SEV_COLORS: Record<string, string> = {
  HIGH: "bg-red-500/15 text-red-400 border border-red-500/30",
  MEDIUM: "bg-amber-500/15 text-amber-500 border border-amber-500/30",
  LOW: "bg-blue-500/15 text-blue-400 border border-blue-500/30",
};
const STA_COLORS: Record<string, string> = {
  OPEN: "text-amber-500",
  RESOLVED: "text-green-500",
  ESCALATED: "text-destructive",
};
const TYPE_LABELS: Record<string, string> = {
  DUPLICATE_INSTRUMENT: "Duplicate",
  TAMPER_DETECTED: "Tamper",
  PHOTOCOPY_DETECTED: "Photocopy",
  BLACKLIST_MATCH: "Blacklist",
  POSITIVE_PAY_MISMATCH: "Pos-Pay Mismatch",
  AMOUNT_ALTERATION: "Amount Alteration",
  SIGNATURE_MISMATCH: "Sig Mismatch",
  CTS2010_VIOLATION: "CTS-2010 Violation",
};

export default function Fraud() {
  const [severity, setSeverity] = useState("");
  const [status, setStatus] = useState("");

  const { data: summary } = useGetFraudSummary({}, { query: { refetchInterval: 30000 } });
  const { data: alerts, isLoading } = useListFraudAlerts(
    { severity: (severity as "HIGH" | "MEDIUM" | "LOW") || undefined, status: (status as "OPEN" | "RESOLVED" | "ESCALATED") || undefined, limit: 50 },
    { query: { refetchInterval: 30000 } }
  );

  const checks = [
    { label: "Duplicate Detection", value: summary?.duplicate_count ?? 0, icon: ShieldX, color: "text-red-400" },
    { label: "Tamper / Photocopy", value: (summary?.total_alerts ?? 0) - (summary?.cts2010_violations ?? 0) - (summary?.duplicate_count ?? 0) - (summary?.blacklisted_hits ?? 0), icon: AlertTriangle, color: "text-amber-500" },
    { label: "Blacklist Hits", value: summary?.blacklisted_hits ?? 0, icon: ShieldX, color: "text-red-400" },
    { label: "CTS-2010 Violations", value: summary?.cts2010_violations ?? 0, icon: AlertTriangle, color: "text-amber-500" },
    { label: "Auto-Blocked", value: summary?.auto_blocked ?? 0, icon: ShieldAlert, color: "text-destructive" },
    { label: "Resolved Today", value: summary?.resolved_today ?? 0, icon: ShieldCheck, color: "text-green-500" },
  ];

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-xl font-bold text-foreground">Fraud Detection</h1>
        <p className="text-xs text-muted-foreground mt-0.5">8-check pipeline · Real-time alerts</p>
      </div>

      {/* Summary KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {[
          { label: "Total Alerts", value: summary?.total_alerts ?? 0, color: "text-foreground" },
          { label: "High Severity", value: summary?.high_severity ?? 0, color: "text-destructive" },
          { label: "Open Alerts", value: summary?.open_alerts ?? 0, color: "text-amber-500" },
          { label: "Auto-Blocked", value: summary?.auto_blocked ?? 0, color: "text-destructive" },
        ].map(({ label, value, color }, i) => (
          <motion.div key={label} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}
            className="bg-card border border-card-border rounded p-3">
            <p className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">{label}</p>
            <p className={cn("text-2xl font-bold tabular-nums", color)}>{value.toLocaleString("en-IN")}</p>
          </motion.div>
        ))}
      </div>

      {/* 8-check breakdown */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
        {checks.map(({ label, value, icon: Icon, color }, i) => (
          <motion.div key={label} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 + i * 0.04 }}
            className="bg-card border border-card-border rounded p-3 flex items-center gap-3">
            <Icon className={cn("w-5 h-5 shrink-0", color)} />
            <div>
              <p className="text-[10px] text-muted-foreground">{label}</p>
              <p className={cn("text-lg font-bold tabular-nums", color)}>{value.toLocaleString("en-IN")}</p>
            </div>
          </motion.div>
        ))}
      </div>

      {/* Filters */}
      <div className="flex gap-3">
        <select value={severity} onChange={(e) => setSeverity(e.target.value)}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring">
          <option value="">All Severities</option>
          {["HIGH", "MEDIUM", "LOW"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <select value={status} onChange={(e) => setStatus(e.target.value)}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring">
          <option value="">All Statuses</option>
          {["OPEN", "RESOLVED", "ESCALATED"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {/* Alerts table */}
      <div className="bg-card border border-card-border rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                {["ID", "Alert Type", "Severity", "Status", "Auto-Blocked", "Tamper", "Photocopy", "Duplicate Of", "Description", "Raised"].map((h) => (
                  <th key={h} className="text-left px-3 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 8 }).map((_, i) => (
                    <tr key={i} className="border-b border-border">
                      {Array.from({ length: 10 }).map((_, j) => <td key={j} className="px-3 py-2"><div className="h-3 bg-muted rounded animate-pulse" /></td>)}
                    </tr>
                  ))
                : (alerts ?? []).map((a, idx) => (
                    <motion.tr key={a.id} initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: idx * 0.02 }}
                      className="border-b border-border hover:bg-accent/30 transition-colors">
                      <td className="px-3 py-2 tabular-nums text-muted-foreground">{a.id}</td>
                      <td className="px-3 py-2 font-semibold whitespace-nowrap">{TYPE_LABELS[a.alert_type] ?? a.alert_type}</td>
                      <td className="px-3 py-2">
                        <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold", SEV_COLORS[a.severity] ?? "")}>{a.severity}</span>
                      </td>
                      <td className={cn("px-3 py-2 font-semibold", STA_COLORS[a.status] ?? "")}>{a.status}</td>
                      <td className="px-3 py-2">{a.auto_blocked ? <span className="text-destructive font-bold">YES</span> : <span className="text-muted-foreground">—</span>}</td>
                      <td className="px-3 py-2">{a.tamper_detected ? <span className="text-destructive font-bold">YES</span> : "—"}</td>
                      <td className="px-3 py-2">{a.photocopy_detected ? <span className="text-destructive font-bold">YES</span> : "—"}</td>
                      <td className="px-3 py-2 font-mono text-muted-foreground">{a.duplicate_of ?? "—"}</td>
                      <td className="px-3 py-2 max-w-[220px] truncate text-muted-foreground">{a.description}</td>
                      <td className="px-3 py-2 text-muted-foreground whitespace-nowrap">{new Date(a.created_at ?? "").toLocaleTimeString("en-IN")}</td>
                    </motion.tr>
                  ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
