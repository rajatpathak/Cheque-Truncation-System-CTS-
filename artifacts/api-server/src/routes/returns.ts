import { Router } from "express";

const router = Router();

const REASON_CODES: { code: string; desc: string }[] = [
  { code: "01", desc: "Funds Insufficient" },
  { code: "02", desc: "Exceed Arrangement" },
  { code: "03", desc: "Effect Not Cleared" },
  { code: "04", desc: "Refer to Drawer" },
  { code: "05", desc: "Differ (Figures and Words)" },
  { code: "06", desc: "Date Unclear / Not Legible" },
  { code: "07", desc: "Stale Cheque" },
  { code: "08", desc: "Post Dated Cheque" },
  { code: "09", desc: "Signature Absent" },
  { code: "10", desc: "Signature Differs" },
  { code: "11", desc: "Payee Name Differs" },
  { code: "12", desc: "Alteration Requires Drawer Signature" },
  { code: "13", desc: "Account Frozen / Dormant" },
  { code: "14", desc: "Account Closed" },
  { code: "15", desc: "Image Not Legible / Poor Quality" },
  { code: "16", desc: "Positive Pay Mismatch" },
];

const BRANCHES = ["CHN001", "CHN002", "MUM001", "MUM002", "DEL001", "KOL001", "HYD001", "BLR001"];
const RETURN_STATUSES = ["PENDING", "PROCESSED", "REPRESENTED", "DISHONOURED"];
const RETURN_TYPES = ["OUTWARD_RETURN", "INWARD_RETURN"];

const RETURNS = Array.from({ length: 120 }, (_, i) => {
  const rc = REASON_CODES[i % REASON_CODES.length];
  const branch = BRANCHES[i % BRANCHES.length];
  const status = RETURN_STATUSES[i % RETURN_STATUSES.length];
  const type = RETURN_TYPES[i % RETURN_TYPES.length];
  const amount = Math.round((5000 + (i * 1237) % 500000) * 100) / 100;
  const d = new Date(Date.now() - i * 300000);
  return {
    id: i + 1,
    instrument_id: `CTS202605030000${String(i + 1).padStart(4, "0")}`,
    return_type: type,
    return_reason_code: rc.code,
    return_reason_description: rc.desc,
    return_date: "2026-05-03",
    amount,
    status,
    branch_code: branch,
    representment_count: status === "REPRESENTED" ? 1 : status === "DISHONOURED" ? 2 : 0,
  };
});

router.get("/returns", (req, res) => {
  let filtered = [...RETURNS];
  if (req.query.type) filtered = filtered.filter((x) => x.return_type === req.query.type);
  if (req.query.status) filtered = filtered.filter((x) => x.status === req.query.status);
  res.json(filtered);
});

router.get("/returns/by-reason", (_req, res) => {
  const counts: Record<string, { count: number; total_amount: number; desc: string }> = {};
  for (const r of RETURNS) {
    if (!counts[r.return_reason_code]) {
      counts[r.return_reason_code] = { count: 0, total_amount: 0, desc: r.return_reason_description };
    }
    counts[r.return_reason_code].count++;
    counts[r.return_reason_code].total_amount += r.amount;
  }
  const result = Object.entries(counts).map(([code, v]) => ({
    reason_code: code,
    description: v.desc,
    count: v.count,
    total_amount: Math.round(v.total_amount * 100) / 100,
  }));
  result.sort((a, b) => b.count - a.count);
  res.json(result);
});

export default router;
