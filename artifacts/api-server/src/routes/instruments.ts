import { Router } from "express";

const router = Router();

const BRANCHES = ["CHN001", "CHN002", "MUM001", "MUM002", "DEL001", "DEL002", "KOL001", "HYD001", "BLR001", "BLR002"];
const GRIDS = ["CHN", "MUM", "DEL", "KOL", "HYD", "BLR"];
const IQA_STATUSES = ["PASS", "FAIL", "PENDING"];
const FRAUD_STATUSES = ["CLEAR", "FLAGGED", "BLOCKED", "PENDING"];
const STATUSES = ["CAPTURED", "IQA_DONE", "FRAUD_CLEARED", "SIGNED", "SUBMITTED", "SETTLED", "RETURNED"];
const PAYEES = [
  "Rajesh Kumar Enterprises", "Lakshmi Textiles Pvt Ltd", "Srinivasa Trading Co",
  "Chennai Steel Works", "National Agro Industries", "Bharat Logistics Ltd",
  "Venkateswara Constructions", "Dalmia Cement Ltd", "JSW Steel Coated Products",
  "Tamil Nadu Newsprint Ltd", "Indian Oil Corporation", "Hindustan Unilever Ltd",
];

function makeMicr(i: number): string {
  const cheque = String(100000 + i).padStart(6, "0");
  const sort = String(600000000 + (i % 1000)).padStart(9, "0");
  const acct = String(100000 + ((i * 37) % 900000)).padStart(6, "0");
  const txn = String(10 + (i % 90)).padStart(2, "0");
  const city = String(600 + (i % 399)).padStart(3, "0");
  return `${cheque}${sort}${acct}${txn}${city}`;
}

function makeInstrument(i: number) {
  const branch = BRANCHES[i % BRANCHES.length];
  const grid = GRIDS[i % GRIDS.length];
  const status = STATUSES[i % STATUSES.length];
  const iqaStatus = IQA_STATUSES[i % IQA_STATUSES.length];
  const fraudStatus = FRAUD_STATUSES[i % FRAUD_STATUSES.length];
  const amount = Math.round((10000 + Math.sin(i) * 9999 + i * 1.23) * 100) / 100;
  const micr = makeMicr(i);
  const payee = PAYEES[i % PAYEES.length];
  const d = new Date(Date.now() - i * 60000);
  return {
    id: i + 1,
    instrument_id: `CTS${String(20260503).padStart(8, "0")}${String(i + 1).padStart(8, "0")}`,
    cheque_number: micr.slice(0, 6),
    micr_code: micr,
    bank_sort_code: micr.slice(6, 15),
    account_number: micr.slice(15, 21),
    payee_name: payee,
    amount_figures: amount,
    instrument_date: "2026-05-03",
    branch_code: branch,
    grid_code: grid,
    status,
    iqa_status: iqaStatus,
    fraud_status: fraudStatus,
    signature_status: status === "SIGNED" || status === "SUBMITTED" || status === "SETTLED" ? "SIGNED" : "PENDING",
    is_high_value: amount > 200000,
    cts2010_compliant: i % 20 !== 0,
    clearing_type: i % 3 === 0 ? "MICR" : "CTS",
    created_at: d.toISOString(),
  };
}

const ALL_INSTRUMENTS = Array.from({ length: 500 }, (_, i) => makeInstrument(i));

router.get("/instruments/recent", (req, res) => {
  const limit = Math.min(Number(req.query.limit ?? 20), 50);
  res.json(ALL_INSTRUMENTS.slice(0, limit));
});

router.get("/instruments/:id", (req, res) => {
  const id = Number(req.params.id);
  const inst = ALL_INSTRUMENTS.find((x) => x.id === id);
  if (!inst) return res.status(404).json({ error: "Not found" });
  return res.json(inst);
});

router.get("/instruments", (req, res) => {
  let filtered = [...ALL_INSTRUMENTS];
  if (req.query.status) filtered = filtered.filter((x) => x.status === req.query.status);
  if (req.query.iqa_status) filtered = filtered.filter((x) => x.iqa_status === req.query.iqa_status);
  if (req.query.fraud_status) filtered = filtered.filter((x) => x.fraud_status === req.query.fraud_status);
  if (req.query.branch_code) filtered = filtered.filter((x) => x.branch_code === req.query.branch_code);
  const page = Math.max(1, Number(req.query.page ?? 1));
  const limit = Math.min(Number(req.query.limit ?? 50), 100);
  const total = filtered.length;
  const data = filtered.slice((page - 1) * limit, page * limit);
  res.json({ data, total, page, limit });
});

export default router;
