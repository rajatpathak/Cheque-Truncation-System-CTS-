import { Router } from "express";
import type { Request, Response } from "express";

const router = Router();

const BRANCHES = [
  "CHN001", "CHN002", "CHN003", "MUM001", "MUM002", "DEL001", "DEL002",
  "BLR001", "BLR002", "HYD001", "KOL001", "PNE001", "AHM001", "CHE001",
];

const FAILURE_REASONS = [
  "SKEW_EXCESSIVE",
  "LOW_CONTRAST",
  "LOW_RESOLUTION",
  "TORN_MUTILATED",
  "INK_SPREAD",
  "FOLD_CREASE",
  "OVERWRITING",
  "BACKGROUND_COMPLEX",
];

const FAILURE_LABELS: Record<string, string> = {
  SKEW_EXCESSIVE: "Excessive Skew",
  LOW_CONTRAST: "Low Contrast",
  LOW_RESOLUTION: "Low Resolution",
  TORN_MUTILATED: "Torn / Mutilated",
  INK_SPREAD: "Ink Spread",
  FOLD_CREASE: "Fold / Crease",
  OVERWRITING: "Overwriting",
  BACKGROUND_COMPLEX: "Complex Background",
};

function seededRand(seed: number): number {
  const x = Math.sin(seed) * 10000;
  return x - Math.floor(x);
}

function branchStats(branch: string, idx: number) {
  const total = Math.floor(seededRand(idx * 7 + 1) * 800) + 400;
  const failCount = Math.floor(seededRand(idx * 7 + 2) * total * 0.08);
  const rescanned = Math.floor(seededRand(idx * 7 + 3) * failCount * 0.7);
  const passed = total - failCount;
  const passRate = parseFloat(((passed / total) * 100).toFixed(2));
  return { branch_code: branch, total, passed, failed: failCount, rescanned, pass_rate: passRate };
}

router.get("/iqa/summary", (_req: Request, res: Response) => {
  const branchList = BRANCHES.map((b, i) => branchStats(b, i));
  const total = branchList.reduce((s, b) => s + b.total, 0);
  const failed = branchList.reduce((s, b) => s + b.failed, 0);
  const passed = total - failed;
  const rescanned = branchList.reduce((s, b) => s + b.rescanned, 0);

  const reasonBreakdown = FAILURE_REASONS.map((r, i) => ({
    reason_code: r,
    label: FAILURE_LABELS[r],
    count: Math.floor(seededRand(i * 3 + 99) * failed * 0.25) + 10,
    percentage: 0,
  }));
  const totalReasonCount = reasonBreakdown.reduce((s, r) => s + r.count, 0);
  reasonBreakdown.forEach((r) => {
    r.percentage = parseFloat(((r.count / totalReasonCount) * 100).toFixed(1));
  });
  reasonBreakdown.sort((a, b) => b.count - a.count);

  res.json({
    date: new Date().toISOString().split("T")[0],
    total_scanned: total,
    total_passed: passed,
    total_failed: failed,
    total_rescanned: rescanned,
    rescan_success_rate: parseFloat(((rescanned / failed) * 100).toFixed(2)),
    overall_pass_rate: parseFloat(((passed / total) * 100).toFixed(2)),
    threshold_pass_rate: 98.0,
    threshold_met: (passed / total) * 100 >= 98.0,
    reason_breakdown: reasonBreakdown,
    branch_count: BRANCHES.length,
  });
});

router.get("/iqa/branches", (_req: Request, res: Response) => {
  const branches = BRANCHES.map((b, i) => branchStats(b, i));
  branches.sort((a, b) => a.pass_rate - b.pass_rate);
  res.json({ branches, total: branches.length });
});

router.get("/iqa/failed-instruments", (req: Request, res: Response) => {
  const page = parseInt(String(req.query["page"] ?? "1"));
  const limit = parseInt(String(req.query["limit"] ?? "20"));
  const branch = req.query["branch"] as string | undefined;
  const reason = req.query["reason"] as string | undefined;

  const instruments = [];
  const bankNames = ["HDFC Bank", "ICICI Bank", "SBI", "Axis Bank", "Canara Bank", "PNB", "BOB", "Kotak Bank", "Yes Bank"];
  const chequeTypes = ["MICR", "CTS-2010", "NON-CTS"];
  const statuses = ["PENDING_RESCAN", "RESCANNED_OK", "RETURNED_IQA", "UNDER_REVIEW"];

  for (let i = 0; i < 300; i++) {
    const branchCode = BRANCHES[i % BRANCHES.length];
    const reasonCode = FAILURE_REASONS[i % FAILURE_REASONS.length];
    instruments.push({
      id: 10000 + i,
      instrument_number: `IQA${String(100000 + i).padStart(6, "0")}`,
      branch_code: branchCode,
      drawee_bank: bankNames[i % bankNames.length],
      cheque_type: chequeTypes[i % chequeTypes.length],
      amount: parseFloat((Math.floor(seededRand(i * 11 + 5) * 500000) + 1000).toFixed(2)),
      failure_reason: reasonCode,
      failure_label: FAILURE_LABELS[reasonCode],
      scan_timestamp: new Date(Date.now() - i * 180000).toISOString(),
      status: statuses[i % statuses.length],
      rescan_count: Math.floor(seededRand(i * 13) * 3),
      iqa_score: parseFloat((seededRand(i * 17 + 3) * 40 + 40).toFixed(1)),
      threshold_score: 80.0,
      scanner_id: `SCN-${branchCode}-${String((i % 4) + 1).padStart(2, "0")}`,
    });
  }

  let filtered = instruments;
  if (branch) filtered = filtered.filter((x) => x.branch_code === branch);
  if (reason) filtered = filtered.filter((x) => x.failure_reason === reason);

  const total = filtered.length;
  const offset = (page - 1) * limit;
  const items = filtered.slice(offset, offset + limit);

  res.json({ instruments: items, total, page, limit, pages: Math.ceil(total / limit) });
});

export default router;
