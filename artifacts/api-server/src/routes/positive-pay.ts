import { Router } from "express";

const router = Router();

const BRANCHES = ["CHN001", "CHN002", "MUM001", "MUM002", "DEL001", "DEL002", "KOL001", "HYD001", "BLR001", "BLR002"];
const MATCH_STATUSES = ["MATCHED", "MISMATCHED", "PENDING", "NOT_REGISTERED"] as const;
const REG_STATUSES = ["ACTIVE", "EXPIRED", "CANCELLED"] as const;
const PAYEES = [
  "Rajesh Kumar Enterprises",
  "Lakshmi Textiles Pvt Ltd",
  "Chennai Steel Works",
  "National Agro Industries",
  "Bharat Logistics Ltd",
  "Tamil Nadu Newsprint Ltd",
  "JSW Steel Coated Products",
  "Indian Oil Corporation",
  "Hindustan Unilever Ltd",
  "Dalmia Cement Ltd",
  "Venkateswara Constructions",
  "Srinivasa Trading Co",
];
const MISMATCH_FIELD_POOL = ["amount", "payee_name", "issue_date"];

function makeCppsRef(i: number) {
  return `CPPS${String(20260503).padStart(8, "0")}${String(i + 1).padStart(8, "0")}`;
}

function makeRegistration(i: number) {
  const branch = BRANCHES[i % BRANCHES.length];
  const matchStatus = MATCH_STATUSES[i % MATCH_STATUSES.length];
  const regStatus = REG_STATUSES[i % REG_STATUSES.length];
  const payee = PAYEES[i % PAYEES.length];
  const amount = Math.round((25000 + (i * 17317) % 9975000) * 100) / 100;
  const chequeNum = String(100001 + i).padStart(6, "0");
  const acctNum = String(100000 + ((i * 37) % 900000)).padStart(10, "0");
  const isHighValue = amount >= 500000; // NPCI mandates PP for ≥ ₹5L w.e.f. 01.01.2021

  // For mismatches, generate slightly different presented values
  const mismatchFields: string[] = [];
  let presentedAmount = amount;
  let presentedPayee = payee;
  let presentedDate = "2026-05-03";

  if (matchStatus === "MISMATCHED") {
    // Pick 1-2 mismatch fields deterministically
    if (i % 3 === 0) {
      mismatchFields.push("amount");
      presentedAmount = Math.round(amount * 1.15 * 100) / 100; // 15% more
    }
    if (i % 5 === 0) {
      mismatchFields.push("payee_name");
      presentedPayee = PAYEES[(i + 3) % PAYEES.length];
    }
    if (i % 7 === 0) {
      mismatchFields.push("issue_date");
      presentedDate = "2026-04-28";
    }
    if (mismatchFields.length === 0) mismatchFields.push("amount");
  }

  const registeredAt = new Date(Date.now() - (i + 1) * 600000);
  const matchedAt =
    matchStatus === "MATCHED" || matchStatus === "MISMATCHED"
      ? new Date(Date.now() - i * 120000).toISOString()
      : null;

  return {
    id: i + 1,
    account_number: acctNum,
    cheque_number: chequeNum,
    amount,
    payee_name: payee,
    issue_date: "2026-05-03",
    branch_code: branch,
    registration_status: regStatus,
    match_status: matchStatus,
    mismatch_fields: mismatchFields,
    presented_amount: matchStatus === "MATCHED" || matchStatus === "MISMATCHED" ? presentedAmount : null,
    presented_payee: matchStatus === "MATCHED" || matchStatus === "MISMATCHED" ? presentedPayee : null,
    presented_date: matchStatus === "MATCHED" || matchStatus === "MISMATCHED" ? presentedDate : null,
    cpps_reference: makeCppsRef(i),
    is_high_value: isHighValue,
    registered_at: registeredAt.toISOString(),
    matched_at: matchedAt,
  };
}

const ALL_REGISTRATIONS = Array.from({ length: 400 }, (_, i) => makeRegistration(i));

router.get("/positive-pay/summary", (_req, res) => {
  const today = new Date().toISOString().split("T")[0];
  const matched = ALL_REGISTRATIONS.filter((r) => r.match_status === "MATCHED").length;
  const mismatched = ALL_REGISTRATIONS.filter((r) => r.match_status === "MISMATCHED").length;
  const pending = ALL_REGISTRATIONS.filter((r) => r.match_status === "PENDING").length;
  const notReg = ALL_REGISTRATIONS.filter((r) => r.match_status === "NOT_REGISTERED").length;
  const highVal = ALL_REGISTRATIONS.filter((r) => r.is_high_value).length;
  const amtReg = ALL_REGISTRATIONS.reduce((s, r) => s + r.amount, 0);
  const amtMis = ALL_REGISTRATIONS.filter((r) => r.match_status === "MISMATCHED")
    .reduce((s, r) => s + r.amount, 0);

  res.json({
    date: today,
    total_registered: ALL_REGISTRATIONS.length,
    matched,
    mismatched,
    pending_match: pending,
    not_registered: notReg,
    high_value_registered: highVal,
    amount_registered: Math.round(amtReg * 100) / 100,
    amount_mismatched: Math.round(amtMis * 100) / 100,
  });
});

router.get("/positive-pay/mismatches", (req, res) => {
  const limit = Math.min(Number(req.query.limit ?? 20), 100);
  const mismatches = ALL_REGISTRATIONS.filter((r) => r.match_status === "MISMATCHED");
  res.json(mismatches.slice(0, limit));
});

router.get("/positive-pay/registrations", (req, res) => {
  let filtered = [...ALL_REGISTRATIONS];
  if (req.query.match_status) {
    filtered = filtered.filter((r) => r.match_status === req.query.match_status);
  }
  if (req.query.branch_code) {
    filtered = filtered.filter((r) => r.branch_code === req.query.branch_code);
  }
  const page = Math.max(1, Number(req.query.page ?? 1));
  const limit = Math.min(Number(req.query.limit ?? 50), 100);
  const total = filtered.length;
  const data = filtered.slice((page - 1) * limit, page * limit);
  res.json({ data, total, page, limit });
});

export default router;
