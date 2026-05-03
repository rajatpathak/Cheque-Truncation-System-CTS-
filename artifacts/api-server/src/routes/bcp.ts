import { Router } from "express";

const router = Router();

router.get("/bcp/status", (_req, res) => {
  res.json({
    active_node: "DC Chennai",
    dc_status: "ONLINE",
    dr_status: "STANDBY",
    replication_lag_minutes: 0.8,
    rpo_status: "WITHIN_TARGET",
    uptime_pct: 99.97,
    rto_target_min: 30,
    rpo_target_min: 5,
    last_checked: new Date().toISOString(),
  });
});

export default router;
