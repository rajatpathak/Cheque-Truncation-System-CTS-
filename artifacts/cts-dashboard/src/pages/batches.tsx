import { useState } from "react";
import { motion } from "framer-motion";
import { useListBatches } from "@workspace/api-client-react";
import { cn } from "@/lib/utils";
import { Package } from "lucide-react";

const STATUS_COLORS: Record<string, string> = {
  OPEN: "bg-blue-500/15 text-blue-400",
  SEALED: "bg-cyan-500/15 text-cyan-400",
  SIGNED: "bg-violet-500/15 text-violet-400",
  SUBMITTED: "bg-amber-500/15 text-amber-500",
  ACKNOWLEDGED: "bg-green-500/15 text-green-400",
  SETTLED: "bg-emerald-500/15 text-emerald-400",
};

function fmt(n: number) {
  if (n >= 1e7) return `₹${(n / 1e7).toFixed(2)} Cr`;
  if (n >= 1e5) return `₹${(n / 1e5).toFixed(2)} L`;
  return `₹${n.toLocaleString("en-IN")}`;
}

export default function Batches() {
  const [status, setStatus] = useState("");
  const { data, isLoading } = useListBatches({ status: status || undefined });

  const batches = data ?? [];
  const stats = {
    total: batches.length,
    open: batches.filter((b) => b.status === "OPEN").length,
    submitted: batches.filter((b) => b.submitted_to_chi).length,
    signed: batches.filter((b) => b.signed).length,
  };

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-foreground">Clearing Batches</h1>
          <p className="text-xs text-muted-foreground mt-0.5">Outward / Inward batches · Today</p>
        </div>
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="">All Statuses</option>
          {["OPEN", "SEALED", "SIGNED", "SUBMITTED", "ACKNOWLEDGED", "SETTLED"].map((s) => (
            <option key={s} value={s}>{s}</option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-4 gap-3">
        {[
          { label: "Total Batches", value: stats.total },
          { label: "Open", value: stats.open, color: "text-blue-400" },
          { label: "Signed", value: stats.signed, color: "text-violet-400" },
          { label: "Submitted to CHI", value: stats.submitted, color: "text-amber-500" },
        ].map(({ label, value, color }, i) => (
          <motion.div key={label} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}
            className="bg-card border border-card-border rounded p-3">
            <p className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">{label}</p>
            <p className={cn("text-2xl font-bold tabular-nums", color ?? "text-foreground")}>{value}</p>
          </motion.div>
        ))}
      </div>

      <div className="bg-card border border-card-border rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                {["Batch No.", "Type", "Branch", "Status", "Instruments", "IQA Pass", "IQA Fail", "Amount", "Signed", "Submitted CHI", "Created"].map((h) => (
                  <th key={h} className="text-left px-3 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 8 }).map((_, i) => (
                    <tr key={i} className="border-b border-border">
                      {Array.from({ length: 11 }).map((_, j) => (
                        <td key={j} className="px-3 py-2"><div className="h-3 bg-muted rounded animate-pulse" /></td>
                      ))}
                    </tr>
                  ))
                : batches.map((b, idx) => (
                    <motion.tr key={b.id} initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: idx * 0.02 }}
                      className="border-b border-border hover:bg-accent/30 transition-colors">
                      <td className="px-3 py-2 font-mono text-muted-foreground">{b.batch_number?.slice(-12)}</td>
                      <td className="px-3 py-2 font-semibold">{b.batch_type}</td>
                      <td className="px-3 py-2 font-mono">{b.branch_code}</td>
                      <td className="px-3 py-2">
                        <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold", STATUS_COLORS[b.status] ?? "bg-muted text-muted-foreground")}>
                          {b.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 tabular-nums text-right">{b.total_instruments}</td>
                      <td className="px-3 py-2 tabular-nums text-right text-green-500">{b.iqa_pass_count}</td>
                      <td className="px-3 py-2 tabular-nums text-right text-destructive">{b.iqa_fail_count}</td>
                      <td className="px-3 py-2 tabular-nums font-semibold text-right whitespace-nowrap">{fmt(b.total_amount ?? 0)}</td>
                      <td className="px-3 py-2">{b.signed ? <span className="text-green-500 font-bold">YES</span> : <span className="text-muted-foreground">NO</span>}</td>
                      <td className="px-3 py-2">{b.submitted_to_chi ? <span className="text-amber-500 font-bold">YES</span> : <span className="text-muted-foreground">NO</span>}</td>
                      <td className="px-3 py-2 text-muted-foreground whitespace-nowrap">{new Date(b.created_at ?? "").toLocaleTimeString("en-IN")}</td>
                    </motion.tr>
                  ))}
            </tbody>
          </table>
        </div>
        {!isLoading && batches.length === 0 && (
          <div className="py-16 text-center">
            <Package className="w-10 h-10 text-muted-foreground mx-auto mb-2" />
            <p className="text-sm text-muted-foreground">No batches found for selected filter</p>
          </div>
        )}
      </div>
    </div>
  );
}
