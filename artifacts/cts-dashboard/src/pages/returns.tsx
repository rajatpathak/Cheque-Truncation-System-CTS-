import { useState } from "react";
import { motion } from "framer-motion";
import { useListReturns, useGetReturnsByReason } from "@workspace/api-client-react";
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
} from "recharts";
import { cn } from "@/lib/utils";
import { RotateCcw } from "lucide-react";

const STATUS_COLORS: Record<string, string> = {
  PENDING: "bg-amber-500/15 text-amber-500",
  PROCESSED: "bg-green-500/15 text-green-400",
  REPRESENTED: "bg-blue-500/15 text-blue-400",
  DISHONOURED: "bg-red-500/15 text-red-400",
};

function fmt(n: number) {
  if (n >= 1e7) return `₹${(n / 1e7).toFixed(2)} Cr`;
  if (n >= 1e5) return `₹${(n / 1e5).toFixed(1)} L`;
  return `₹${n.toLocaleString("en-IN")}`;
}

export default function Returns() {
  const [type, setType] = useState("");
  const [status, setStatus] = useState("");
  const { data: returns, isLoading } = useListReturns({ type: type || undefined, status: status || undefined });
  const { data: byReason } = useGetReturnsByReason({});

  const list = returns ?? [];
  const total = list.length;
  const totalAmt = list.reduce((s, r) => s + (r.amount ?? 0), 0);
  const dishonoured = list.filter((r) => r.status === "DISHONOURED").length;

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-xl font-bold text-foreground">Return Instruments</h1>
        <p className="text-xs text-muted-foreground mt-0.5">Outward & Inward returns · Today</p>
      </div>

      <div className="grid grid-cols-3 gap-3">
        {[
          { label: "Total Returns", value: total.toLocaleString("en-IN") },
          { label: "Total Amount", value: fmt(totalAmt), color: "text-amber-500" },
          { label: "Dishonoured", value: dishonoured.toString(), color: "text-destructive" },
        ].map(({ label, value, color }, i) => (
          <motion.div key={label} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}
            className="bg-card border border-card-border rounded p-3">
            <p className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">{label}</p>
            <p className={cn("text-2xl font-bold tabular-nums", color ?? "text-foreground")}>{value}</p>
          </motion.div>
        ))}
      </div>

      {/* Returns by reason chart */}
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.2 }}
        className="bg-card border border-card-border rounded p-4">
        <h2 className="text-sm font-semibold text-foreground mb-3">Returns by Reason Code</h2>
        <ResponsiveContainer width="100%" height={200}>
          <BarChart data={(byReason ?? []).slice(0, 10)} layout="vertical" margin={{ left: 120 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" horizontal={false} />
            <XAxis type="number" tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} />
            <YAxis type="category" dataKey="reason_code" tick={{ fontSize: 9, fill: "hsl(var(--muted-foreground))" }}
              width={115}
              tickFormatter={(code) => {
                const item = (byReason ?? []).find((r) => r.reason_code === code);
                return item ? `${code} - ${item.description?.slice(0, 18)}` : code;
              }}
            />
            <Tooltip
              contentStyle={{ background: "hsl(var(--card))", border: "1px solid hsl(var(--border))", fontSize: 12 }}
              formatter={(v: number, _name, props) => [v, props.payload?.description ?? "Count"]}
            />
            <Bar dataKey="count" fill="hsl(38 92% 55%)" radius={[0, 2, 2, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </motion.div>

      {/* Filters */}
      <div className="flex gap-3">
        <select value={type} onChange={(e) => setType(e.target.value)}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring">
          <option value="">All Types</option>
          {["OUTWARD_RETURN", "INWARD_RETURN"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <select value={status} onChange={(e) => setStatus(e.target.value)}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring">
          <option value="">All Statuses</option>
          {["PENDING", "PROCESSED", "REPRESENTED", "DISHONOURED"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-card border border-card-border rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                {["Instrument ID", "Return Type", "Reason Code", "Reason", "Date", "Amount", "Branch", "Status", "Representments"].map((h) => (
                  <th key={h} className="text-left px-3 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 8 }).map((_, i) => (
                    <tr key={i} className="border-b border-border">
                      {Array.from({ length: 9 }).map((_, j) => <td key={j} className="px-3 py-2"><div className="h-3 bg-muted rounded animate-pulse" /></td>)}
                    </tr>
                  ))
                : list.map((r, idx) => (
                    <motion.tr key={r.id} initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: idx * 0.02 }}
                      className="border-b border-border hover:bg-accent/30 transition-colors">
                      <td className="px-3 py-2 font-mono text-muted-foreground">{r.instrument_id?.slice(-10)}</td>
                      <td className="px-3 py-2 font-semibold">{r.return_type?.replace("_", " ")}</td>
                      <td className="px-3 py-2 font-semibold text-primary">{r.return_reason_code}</td>
                      <td className="px-3 py-2 max-w-[180px] truncate text-muted-foreground">{r.return_reason_description}</td>
                      <td className="px-3 py-2 text-muted-foreground">{r.return_date}</td>
                      <td className="px-3 py-2 tabular-nums font-semibold text-right whitespace-nowrap">{fmt(r.amount ?? 0)}</td>
                      <td className="px-3 py-2 font-mono">{r.branch_code}</td>
                      <td className="px-3 py-2">
                        <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold", STATUS_COLORS[r.status] ?? "")}>{r.status}</span>
                      </td>
                      <td className="px-3 py-2 text-center">{r.representment_count ?? 0}</td>
                    </motion.tr>
                  ))}
            </tbody>
          </table>
        </div>
        {!isLoading && list.length === 0 && (
          <div className="py-16 text-center">
            <RotateCcw className="w-10 h-10 text-muted-foreground mx-auto mb-2" />
            <p className="text-sm text-muted-foreground">No returns found</p>
          </div>
        )}
      </div>
    </div>
  );
}
