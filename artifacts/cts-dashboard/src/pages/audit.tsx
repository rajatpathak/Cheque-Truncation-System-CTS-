import { useState } from "react";
import { motion } from "framer-motion";
import { useGetAuditTrail } from "@workspace/api-client-react";
import { cn } from "@/lib/utils";
import { ClipboardList } from "lucide-react";

const MODULE_COLORS: Record<string, string> = {
  OUTWARD_CLEARING: "bg-blue-500/15 text-blue-400",
  INWARD_CLEARING: "bg-cyan-500/15 text-cyan-400",
  FRAUD_DETECTION: "bg-red-500/15 text-red-400",
  RETURN_PROCESSING: "bg-amber-500/15 text-amber-500",
  PKI_SIGNING: "bg-violet-500/15 text-violet-400",
  ADMINISTRATION: "bg-orange-500/15 text-orange-400",
  BCP_DR: "bg-green-500/15 text-green-400",
  INTEGRATION: "bg-pink-500/15 text-pink-400",
};

const ACTION_COLORS: Record<string, string> = {
  LOGIN: "text-green-500",
  LOGOUT: "text-muted-foreground",
  BATCH_SEAL: "text-blue-400",
  BATCH_SIGN: "text-violet-400",
  BATCH_SUBMIT: "text-amber-500",
  ALERT_RESOLVE: "text-green-500",
  ALERT_ESCALATE: "text-destructive",
  RETURN_PROCESS: "text-amber-500",
  FAILOVER_TRIGGER: "text-destructive font-bold",
  PARAMETER_CHANGE: "text-orange-400",
  USER_CREATE: "text-blue-400",
  PASSWORD_RESET: "text-muted-foreground",
};

export default function Audit() {
  const [module, setModule] = useState("");
  const [limit, setLimit] = useState(50);

  const { data, isLoading } = useGetAuditTrail({ module: module || undefined, limit });

  const entries = data ?? [];

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-xl font-bold text-foreground">Audit Trail</h1>
        <p className="text-xs text-muted-foreground mt-0.5">Immutable audit log · All operator actions</p>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap">
        <select value={module} onChange={(e) => setModule(e.target.value)}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring">
          <option value="">All Modules</option>
          {["OUTWARD_CLEARING", "INWARD_CLEARING", "FRAUD_DETECTION", "RETURN_PROCESSING", "PKI_SIGNING", "ADMINISTRATION", "BCP_DR", "INTEGRATION"].map((m) => (
            <option key={m} value={m}>{m.replace(/_/g, " ")}</option>
          ))}
        </select>
        <select value={limit} onChange={(e) => setLimit(Number(e.target.value))}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring">
          {[25, 50, 100, 200].map((n) => <option key={n} value={n}>Show {n}</option>)}
        </select>
        <span className="ml-auto text-xs text-muted-foreground self-center">{entries.length} records</span>
      </div>

      {/* Table */}
      <div className="bg-card border border-card-border rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                {["#", "Timestamp", "User", "Branch", "Module", "Action", "Reference", "IP Address"].map((h) => (
                  <th key={h} className="text-left px-3 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 10 }).map((_, i) => (
                    <tr key={i} className="border-b border-border">
                      {Array.from({ length: 8 }).map((_, j) => (
                        <td key={j} className="px-3 py-2"><div className="h-3 bg-muted rounded animate-pulse" /></td>
                      ))}
                    </tr>
                  ))
                : entries.map((e, idx) => (
                    <motion.tr key={e.id} initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: idx * 0.01 }}
                      className="border-b border-border hover:bg-accent/30 transition-colors">
                      <td className="px-3 py-2 tabular-nums text-muted-foreground">{e.id}</td>
                      <td className="px-3 py-2 font-mono text-muted-foreground whitespace-nowrap text-[10px]">
                        {new Date(e.timestamp ?? "").toLocaleString("en-IN")}
                      </td>
                      <td className="px-3 py-2 font-mono font-semibold">{e.user_name}</td>
                      <td className="px-3 py-2 font-mono text-muted-foreground">{e.branch_code}</td>
                      <td className="px-3 py-2">
                        <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold whitespace-nowrap",
                          MODULE_COLORS[e.module] ?? "bg-muted text-muted-foreground")}>
                          {e.module?.replace(/_/g, " ")}
                        </span>
                      </td>
                      <td className={cn("px-3 py-2 font-semibold whitespace-nowrap", ACTION_COLORS[e.action] ?? "text-foreground")}>
                        {e.action?.replace(/_/g, " ")}
                      </td>
                      <td className="px-3 py-2 font-mono text-muted-foreground text-[10px]">{e.reference_id}</td>
                      <td className="px-3 py-2 font-mono text-muted-foreground">{e.ip_address}</td>
                    </motion.tr>
                  ))}
            </tbody>
          </table>
        </div>
        {!isLoading && entries.length === 0 && (
          <div className="py-16 text-center">
            <ClipboardList className="w-10 h-10 text-muted-foreground mx-auto mb-2" />
            <p className="text-sm text-muted-foreground">No audit entries found</p>
          </div>
        )}
      </div>
    </div>
  );
}
