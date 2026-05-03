import { Router } from "express";

const router = Router();

const BRANCHES = ["CHN001", "CHN002", "MUM001", "MUM002", "DEL001", "DEL002", "KOL001", "HYD001", "BLR001"];
const BATCH_STATUSES = ["OPEN", "SEALED", "SIGNED", "SUBMITTED", "ACKNOWLEDGED", "SETTLED"];
const BATCH_TYPES = ["OUTWARD", "INWARD", "HIGH_VALUE", "EXPRESS"];

const BATCHES = Array.from({ length: 60 }, (_, i) => {
  const branch = BRANCHES[i % BRANCHES.length];
  const status = BATCH_STATUSES[i % BATCH_STATUSES.length];
  const type = BATCH_TYPES[i % BATCH_TYPES.length];
  const instruments = 20 + (i * 7) % 280;
  const amount = instruments * (50000 + (i * 3317) % 400000);
  const iqaPass = Math.floor(instruments * 0.97);
  const d = new Date(Date.now() - i * 300000);
  return {
    id: i + 1,
    batch_number: `BT${String(20260503).padStart(8, "0")}${String(i + 1).padStart(4, "0")}`,
    batch_type: type,
    branch_code: branch,
    status,
    total_instruments: instruments,
    total_amount: amount,
    iqa_pass_count: iqaPass,
    iqa_fail_count: instruments - iqaPass,
    signed: ["SIGNED", "SUBMITTED", "ACKNOWLEDGED", "SETTLED"].includes(status),
    submitted_to_chi: ["SUBMITTED", "ACKNOWLEDGED", "SETTLED"].includes(status),
    created_at: d.toISOString(),
  };
});

router.get("/batches", (req, res) => {
  let filtered = [...BATCHES];
  if (req.query.status) filtered = filtered.filter((x) => x.status === req.query.status);
  if (req.query.branch_code) filtered = filtered.filter((x) => x.branch_code === req.query.branch_code);
  res.json(filtered);
});

export default router;
