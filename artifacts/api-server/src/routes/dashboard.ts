import { Router } from "express";

const router = Router();

router.get("/dashboard/summary", (req, res) => {
  const today = new Date().toISOString().split("T")[0];
  res.json({
    date: req.query.date ?? today,
    total_instruments: 94823,
    total_amount: 5182340000.0,
    iqa_pass: 92100,
    iqa_fail: 2723,
    iqa_pass_pct: 97.13,
    fraud_clear: 93870,
    fraud_flagged: 841,
    fraud_blocked: 112,
    submitted: 87450,
    pending: 7373,
    returns_today: 1248,
    high_value_count: 3721,
    active_sessions: 4,
    active_batches: 18,
    uptime_pct: 99.97,
    active_node: "DC Chennai",
  });
});

router.get("/dashboard/pipeline", (_req, res) => {
  res.json([
    { stage: "Captured", count: 94823, amount: 5182340000, pct: 100 },
    { stage: "IQA Passed", count: 92100, amount: 5041200000, pct: 97.13 },
    { stage: "Fraud Cleared", count: 91259, amount: 4988900000, pct: 96.24 },
    { stage: "Signed (PKI)", count: 90800, amount: 4956100000, pct: 95.76 },
    { stage: "Submitted CHI", count: 87450, amount: 4780300000, pct: 92.23 },
    { stage: "Settled / Final", count: 82010, amount: 4490100000, pct: 86.49 },
  ]);
});

router.get("/dashboard/throughput", (_req, res) => {
  res.json([
    { hour: "08:00", count: 2100, amount: 112000000 },
    { hour: "09:00", count: 7850, amount: 421000000 },
    { hour: "10:00", count: 12300, amount: 673000000 },
    { hour: "11:00", count: 14200, amount: 781000000 },
    { hour: "12:00", count: 11800, amount: 641000000 },
    { hour: "13:00", count: 8400, amount: 451000000 },
    { hour: "14:00", count: 10200, amount: 558000000 },
    { hour: "15:00", count: 13100, amount: 712000000 },
    { hour: "16:00", count: 9900, amount: 539000000 },
    { hour: "17:00", count: 4973, amount: 294000000 },
  ]);
});

router.get("/dashboard/grid-summary", (_req, res) => {
  res.json([
    { grid_code: "CHN", total: 28420, amount: 1551000000, submitted: 26100, fraud_flagged: 248 },
    { grid_code: "MUM", total: 22110, amount: 1208000000, submitted: 20380, fraud_flagged: 198 },
    { grid_code: "DEL", total: 18900, amount: 1031000000, submitted: 17400, fraud_flagged: 171 },
    { grid_code: "KOL", total: 11300, amount: 617000000, submitted: 10420, fraud_flagged: 102 },
    { grid_code: "HYD", total: 8200, amount: 448000000, submitted: 7550, fraud_flagged: 73 },
    { grid_code: "BLR", total: 5893, amount: 327000000, submitted: 5600, fraud_flagged: 49 },
  ]);
});

export default router;
