import { useState } from "react";
import { motion } from "framer-motion";
import { useListSessions } from "@workspace/api-client-react";
import { cn } from "@/lib/utils";
import { CalendarClock } from "lucide-react";

const STATUS_COLORS: Record<string, string> = {
  OPEN: "bg-blue-500/15 text-blue-400",
  PROCESSING: "bg-amber-500/15 text-amber-500",
  SUBMITTED: "bg-violet-500/15 text-violet-400",
  SETTLED: "bg-green-500/15 text-green-400",
  RECONCILED: "bg-emerald-500/15 text-emerald-400",
};

function fmt(n: number) {
  if (n >= 1e9) return `₹${(n / 1e9).toFixed(2)}B`;
  if (n >= 1e7) return `₹${(n / 1e7).toFixed(2)} Cr`;
  return `₹${n.toLocaleString("en-IN")}`;
}

export default function Sessions() {
  const [status, setStatus] = useState("");
  const { data, isLoading } = useListSessions({ status: status || undefined });

  const sessions = data ?? [];
  const totalOutward = sessions.reduce((sum, s) => sum + (s.total_outward_amount ?? 0), 0);
  const totalInward = sessions.reduce((sum, s) => sum + (s.total_inward_amount ?? 0), 0);

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-foreground">Clearing Sessions</h1>
          <p className="text-xs text-muted-foreground mt-0.5">NPCI CHI sessions · Today</p>
        </div>
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="">All Statuses</option>
          {["OPEN", "PROCESSING", "SUBMITTED", "SETTLED", "RECONCILED"].map((s) => (
            <option key={s} value={s}>{s}</option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-3 gap-3">
        {[
          { label: "Total Sessions", value: sessions.length },
          { label: "Total Outward", value: fmt(totalOutward), color: "text-amber-500" },
          { label: "Total Inward", value: fmt(totalInward), color: "text-green-500" },
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
                {["Session No.", "Date", "Type", "Clearing", "Grid", "Status", "Instruments", "Outward", "Inward", "Submitted At"].map((h) => (
                  <th key={h} className="text-left px-3 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 8 }).map((_, i) => (
                    <tr key={i} className="border-b border-border">
                      {Array.from({ length: 10 }).map((_, j) => (
                        <td key={j} className="px-3 py-2"><div className="h-3 bg-muted rounded animate-pulse" /></td>
                      ))}
                    </tr>
                  ))
                : sessions.map((s, idx) => (
                    <motion.tr key={s.id} initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: idx * 0.03 }}
                      className="border-b border-border hover:bg-accent/30 transition-colors">
                      <td className="px-3 py-2 font-mono text-muted-foreground">{s.session_number}</td>
                      <td className="px-3 py-2 text-muted-foreground">{s.session_date}</td>
                      <td className="px-3 py-2 font-semibold">{s.session_type}</td>
                      <td className="px-3 py-2">{s.clearing_type}</td>
                      <td className="px-3 py-2 font-semibold">{s.grid_code}</td>
                      <td className="px-3 py-2">
                        <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold", STATUS_COLORS[s.status] ?? "bg-muted text-muted-foreground")}>
                          {s.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 tabular-nums text-right">{s.total_instruments?.toLocaleString("en-IN")}</td>
                      <td className="px-3 py-2 tabular-nums font-semibold text-right text-amber-500 whitespace-nowrap">{fmt(s.total_outward_amount ?? 0)}</td>
                      <td className="px-3 py-2 tabular-nums font-semibold text-right text-green-500 whitespace-nowrap">{fmt(s.total_inward_amount ?? 0)}</td>
                      <td className="px-3 py-2 text-muted-foreground whitespace-nowrap">
                        {s.submitted_at ? new Date(s.submitted_at).toLocaleTimeString("en-IN") : "—"}
                      </td>
                    </motion.tr>
                  ))}
            </tbody>
          </table>
        </div>
        {!isLoading && sessions.length === 0 && (
          <div className="py-16 text-center">
            <CalendarClock className="w-10 h-10 text-muted-foreground mx-auto mb-2" />
            <p className="text-sm text-muted-foreground">No sessions found</p>
          </div>
        )}
      </div>
    </div>
  );
}
