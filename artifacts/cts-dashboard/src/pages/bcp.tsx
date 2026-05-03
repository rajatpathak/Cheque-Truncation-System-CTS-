import { motion } from "framer-motion";
import { useGetBcpStatus } from "@workspace/api-client-react";
import { cn } from "@/lib/utils";
import { Server, Activity, CheckCircle2, AlertTriangle, Clock } from "lucide-react";

function Gauge({ value, max, label, color }: { value: number; max: number; label: string; color: string }) {
  const pct = Math.min(100, (value / max) * 100);
  const radius = 50;
  const circ = 2 * Math.PI * radius;
  const dash = (pct / 100) * circ;
  return (
    <div className="flex flex-col items-center gap-2">
      <svg width="130" height="80" viewBox="0 0 130 80">
        <path d="M 15 75 A 50 50 0 0 1 115 75" fill="none" stroke="hsl(var(--muted))" strokeWidth="10" strokeLinecap="round" />
        <motion.path
          d="M 15 75 A 50 50 0 0 1 115 75"
          fill="none"
          stroke={color}
          strokeWidth="10"
          strokeLinecap="round"
          strokeDasharray={`${(pct / 100) * 157} 157`}
          initial={{ strokeDasharray: "0 157" }}
          animate={{ strokeDasharray: `${(pct / 100) * 157} 157` }}
          transition={{ duration: 1, delay: 0.3 }}
        />
        <text x="65" y="65" textAnchor="middle" fontSize="16" fontWeight="700" fill="currentColor" className="text-foreground">
          {value}{max === 100 ? "%" : " min"}
        </text>
      </svg>
      <p className="text-xs text-muted-foreground text-center">{label}</p>
    </div>
  );
}

function NodeCard({ name, status, role }: { name: string; status: string; role: string }) {
  const online = status === "ONLINE";
  const standby = status === "STANDBY";
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.97 }}
      animate={{ opacity: 1, scale: 1 }}
      className={cn("bg-card border rounded p-5 flex flex-col gap-3", online ? "border-green-500/40" : standby ? "border-amber-500/40" : "border-destructive/40")}
    >
      <div className="flex items-center gap-3">
        <div className={cn("w-3 h-3 rounded-full", online ? "bg-green-500 animate-pulse" : standby ? "bg-amber-500" : "bg-destructive")} />
        <h3 className="font-bold text-sm text-foreground">{name}</h3>
        <span className={cn("ml-auto text-xs font-semibold px-2 py-0.5 rounded",
          online ? "bg-green-500/15 text-green-400" : standby ? "bg-amber-500/15 text-amber-500" : "bg-destructive/15 text-destructive"
        )}>{status}</span>
      </div>
      <div className="flex items-center justify-between text-xs">
        <span className="text-muted-foreground">Role</span>
        <span className="font-semibold text-foreground">{role}</span>
      </div>
      <Server className={cn("w-8 h-8", online ? "text-green-500/40" : "text-muted-foreground/30")} />
    </motion.div>
  );
}

const HISTORY = [
  { event: "Scheduled DR Drill", node: "DR Hyderabad", time: "2026-04-15 02:00", duration: "18 min", rto_met: true },
  { event: "Network flap — auto-recovery", node: "DC Chennai", time: "2026-03-22 14:33", duration: "4 min", rto_met: true },
  { event: "Scheduled DR Drill", node: "DR Hyderabad", time: "2026-02-20 02:00", duration: "22 min", rto_met: true },
  { event: "UPS test failover", node: "DC Chennai", time: "2026-01-10 03:00", duration: "11 min", rto_met: true },
];

export default function BCP() {
  const { data: bcp, isLoading } = useGetBcpStatus({ query: { refetchInterval: 30000 } });

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-xl font-bold text-foreground">BCP / DR Status</h1>
        <p className="text-xs text-muted-foreground mt-0.5">DC Chennai (Primary) · DR Hyderabad (Standby) · SLA: 99.95% uptime</p>
      </div>

      {/* Node cards */}
      <div className="grid grid-cols-2 gap-4">
        <NodeCard
          name="DC Chennai (Primary)"
          status={isLoading ? "..." : bcp?.dc_status ?? "ONLINE"}
          role={bcp?.active_node === "DC Chennai" ? "ACTIVE" : "STANDBY"}
        />
        <NodeCard
          name="DR Hyderabad (Secondary)"
          status={isLoading ? "..." : bcp?.dr_status ?? "STANDBY"}
          role={bcp?.active_node === "DR Hyderabad" ? "ACTIVE" : "STANDBY"}
        />
      </div>

      {/* Gauges */}
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.2 }}
        className="bg-card border border-card-border rounded p-5">
        <h2 className="text-sm font-semibold text-foreground mb-4 flex items-center gap-2">
          <Activity className="w-4 h-4 text-primary" />
          SLA Metrics
        </h2>
        <div className="flex flex-wrap justify-around gap-6">
          <Gauge value={bcp?.uptime_pct ?? 99.97} max={100} label="System Uptime %" color="hsl(160 60% 45%)" />
          <Gauge value={bcp?.replication_lag_minutes ?? 0.8} max={5} label="Replication Lag (min)" color={
            (bcp?.replication_lag_minutes ?? 0) < 2 ? "hsl(160 60% 45%)" : "hsl(38 92% 55%)"
          } />
          <Gauge value={bcp?.rto_target_min ?? 30} max={60} label={`RTO Target: ${bcp?.rto_target_min ?? 30} min`} color="hsl(224 60% 60%)" />
          <Gauge value={bcp?.rpo_target_min ?? 5} max={15} label={`RPO Target: ${bcp?.rpo_target_min ?? 5} min`} color="hsl(224 60% 60%)" />
        </div>
      </motion.div>

      {/* Detail cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {[
          { label: "Active Node", value: bcp?.active_node ?? "DC Chennai", icon: Server, color: "text-green-500" },
          { label: "RPO Status", value: bcp?.rpo_status ?? "WITHIN_TARGET", icon: CheckCircle2, color: "text-green-500" },
          { label: "Replication Lag", value: `${bcp?.replication_lag_minutes ?? 0.8} min`, icon: Activity, color: (bcp?.replication_lag_minutes ?? 0) < 2 ? "text-green-500" : "text-amber-500" },
          { label: "Last Checked", value: bcp?.last_checked ? new Date(bcp.last_checked).toLocaleTimeString("en-IN") : "—", icon: Clock, color: "text-muted-foreground" },
        ].map(({ label, value, icon: Icon, color }, i) => (
          <motion.div key={label} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 + i * 0.05 }}
            className="bg-card border border-card-border rounded p-3 flex items-center gap-3">
            <Icon className={cn("w-5 h-5 shrink-0", color)} />
            <div>
              <p className="text-[10px] text-muted-foreground">{label}</p>
              <p className={cn("text-sm font-bold", color)}>{value}</p>
            </div>
          </motion.div>
        ))}
      </div>

      {/* Failover history */}
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.3 }}
        className="bg-card border border-card-border rounded overflow-hidden">
        <div className="px-4 py-3 border-b border-border">
          <h2 className="text-sm font-semibold text-foreground">Failover / DR Drill History</h2>
        </div>
        <table className="w-full text-xs">
          <thead>
            <tr className="border-b border-border bg-muted/30">
              {["Event", "Node", "Date/Time", "Duration", "RTO Met"].map((h) => (
                <th key={h} className="text-left px-4 py-2.5 font-semibold text-muted-foreground uppercase tracking-wider">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {HISTORY.map((h, idx) => (
              <motion.tr key={idx} initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.35 + idx * 0.05 }}
                className="border-b border-border last:border-0 hover:bg-accent/30 transition-colors">
                <td className="px-4 py-2.5 font-semibold">{h.event}</td>
                <td className="px-4 py-2.5 text-muted-foreground">{h.node}</td>
                <td className="px-4 py-2.5 font-mono text-muted-foreground">{h.time}</td>
                <td className="px-4 py-2.5">{h.duration}</td>
                <td className="px-4 py-2.5">
                  {h.rto_met
                    ? <span className="flex items-center gap-1 text-green-500 font-bold"><CheckCircle2 className="w-3 h-3" />YES</span>
                    : <span className="flex items-center gap-1 text-destructive font-bold"><AlertTriangle className="w-3 h-3" />NO</span>}
                </td>
              </motion.tr>
            ))}
          </tbody>
        </table>
      </motion.div>
    </div>
  );
}
