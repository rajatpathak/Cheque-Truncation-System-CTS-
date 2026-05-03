import { useState } from "react";
import { motion } from "framer-motion";
import { useListInstruments } from "@workspace/api-client-react";
import { Search, ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";

const STATUS_COLORS: Record<string, string> = {
  CAPTURED: "bg-blue-500/15 text-blue-400 border border-blue-500/30",
  IQA_DONE: "bg-cyan-500/15 text-cyan-400 border border-cyan-500/30",
  FRAUD_CLEARED: "bg-green-500/15 text-green-400 border border-green-500/30",
  SIGNED: "bg-violet-500/15 text-violet-400 border border-violet-500/30",
  SUBMITTED: "bg-amber-500/15 text-amber-500 border border-amber-500/30",
  SETTLED: "bg-emerald-500/15 text-emerald-400 border border-emerald-500/30",
  RETURNED: "bg-red-500/15 text-red-400 border border-red-500/30",
};
const IQA_COLORS: Record<string, string> = {
  PASS: "text-green-500",
  FAIL: "text-destructive",
  PENDING: "text-amber-500",
};
const FRAUD_COLORS: Record<string, string> = {
  CLEAR: "text-green-500",
  FLAGGED: "text-amber-500",
  BLOCKED: "text-destructive",
  PENDING: "text-muted-foreground",
};

function fmt(n: number) {
  return new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 }).format(n);
}

export default function Instruments() {
  const [status, setStatus] = useState("");
  const [iqaStatus, setIqaStatus] = useState("");
  const [fraudStatus, setFraudStatus] = useState("");
  const [page, setPage] = useState(1);
  const limit = 25;

  const { data, isLoading } = useListInstruments(
    { status: status || undefined, iqa_status: iqaStatus || undefined, fraud_status: fraudStatus || undefined, page, limit },
    { query: { keepPreviousData: true } as never }
  );

  const total = data?.total ?? 0;
  const totalPages = Math.ceil(total / limit);

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-foreground">Instruments</h1>
          <p className="text-xs text-muted-foreground mt-0.5">{total.toLocaleString("en-IN")} instruments · Today</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <div className="relative">
          <Search className="absolute left-2.5 top-2 w-3.5 h-3.5 text-muted-foreground" />
          <select
            value={status}
            onChange={(e) => { setStatus(e.target.value); setPage(1); }}
            className="pl-8 pr-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
          >
            <option value="">All Statuses</option>
            {["CAPTURED", "IQA_DONE", "FRAUD_CLEARED", "SIGNED", "SUBMITTED", "SETTLED", "RETURNED"].map((s) => (
              <option key={s} value={s}>{s}</option>
            ))}
          </select>
        </div>
        <select
          value={iqaStatus}
          onChange={(e) => { setIqaStatus(e.target.value); setPage(1); }}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="">All IQA</option>
          {["PASS", "FAIL", "PENDING"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <select
          value={fraudStatus}
          onChange={(e) => { setFraudStatus(e.target.value); setPage(1); }}
          className="px-3 py-1.5 text-xs bg-card border border-card-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="">All Fraud</option>
          {["CLEAR", "FLAGGED", "BLOCKED", "PENDING"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-card border border-card-border rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                {["Instrument ID", "Cheque No.", "Payee", "Amount", "Branch", "Grid", "Status", "IQA", "Fraud", "HV", "Date"].map((h) => (
                  <th key={h} className="text-left px-3 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 10 }).map((_, i) => (
                    <tr key={i} className="border-b border-border">
                      {Array.from({ length: 11 }).map((_, j) => (
                        <td key={j} className="px-3 py-2"><div className="h-3 bg-muted rounded animate-pulse" /></td>
                      ))}
                    </tr>
                  ))
                : (data?.data ?? []).map((inst, idx) => (
                    <motion.tr
                      key={inst.id}
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      transition={{ delay: idx * 0.02 }}
                      className="border-b border-border hover:bg-accent/30 transition-colors"
                    >
                      <td className="px-3 py-2 font-mono text-muted-foreground whitespace-nowrap">{inst.instrument_id?.slice(-12)}</td>
                      <td className="px-3 py-2 font-mono">{inst.cheque_number}</td>
                      <td className="px-3 py-2 max-w-[140px] truncate">{inst.payee_name}</td>
                      <td className="px-3 py-2 tabular-nums font-semibold text-right whitespace-nowrap">{fmt(inst.amount_figures ?? 0)}</td>
                      <td className="px-3 py-2 font-mono">{inst.branch_code}</td>
                      <td className="px-3 py-2 font-semibold">{inst.grid_code}</td>
                      <td className="px-3 py-2">
                        <span className={cn("px-1.5 py-0.5 rounded text-[10px] font-semibold whitespace-nowrap", STATUS_COLORS[inst.status] ?? "bg-muted text-muted-foreground")}>
                          {inst.status}
                        </span>
                      </td>
                      <td className={cn("px-3 py-2 font-semibold", IQA_COLORS[inst.iqa_status ?? ""] ?? "text-muted-foreground")}>{inst.iqa_status}</td>
                      <td className={cn("px-3 py-2 font-semibold", FRAUD_COLORS[inst.fraud_status ?? ""] ?? "text-muted-foreground")}>{inst.fraud_status}</td>
                      <td className="px-3 py-2">{inst.is_high_value ? <span className="text-primary font-bold">HV</span> : <span className="text-muted-foreground">—</span>}</td>
                      <td className="px-3 py-2 text-muted-foreground whitespace-nowrap">{inst.instrument_date}</td>
                    </motion.tr>
                  ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex items-center justify-between px-4 py-2.5 border-t border-border">
          <span className="text-xs text-muted-foreground">Page {page} of {totalPages || 1}</span>
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
