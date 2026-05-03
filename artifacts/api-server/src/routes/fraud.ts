import { Router } from "express";

const router = Router();

const ALERT_TYPES = [
  "DUPLICATE_INSTRUMENT",
  "TAMPER_DETECTED",
  "PHOTOCOPY_DETECTED",
  "BLACKLIST_MATCH",
  "POSITIVE_PAY_MISMATCH",
  "AMOUNT_ALTERATION",
  "SIGNATURE_MISMATCH",
  "CTS2010_VIOLATION",
];

const SEVERITIES = ["HIGH", "MEDIUM", "LOW"] as const;
const STATUSES = ["OPEN", "RESOLVED", "ESCALATED"] as const;

const DESCRIPTIONS: Record<string, string> = {
  DUPLICATE_INSTRUMENT: "Cheque already presented in current clearing cycle",
  TAMPER_DETECTED: "Image analysis detected probable physical tampering",
  PHOTOCOPY_DETECTED: "UV/IR scan indicates instrument is a colour photocopy",
  BLACKLIST_MATCH: "Drawer account appears on RBI/IOB blacklist",
  POSITIVE_PAY_MISMATCH: "Amount or payee does not match Positive Pay registration",
  AMOUNT_ALTERATION: "Amount in figures inconsistent with amount in words",
  SIGNATURE_MISMATCH: "Signature differs from specimen on record",
  CTS2010_VIOLATION: "Instrument paper/printing does not conform to CTS-2010 standard",
};

const ALERTS = Array.from({ length: 80 }, (_, i) => {
  const alertType = ALERT_TYPES[i % ALERT_TYPES.length];
  const severity = SEVERITIES[i % 3];
  const status = STATUSES[i % 3];
  const d = new Date(Date.now() - i * 420000);
  return {
    id: i + 1,
    instrument_id: i + 1,
    alert_type: alertType,
    severity,
    status,
    auto_blocked: severity === "HIGH" && alertType !== "CTS2010_VIOLATION",
    tamper_detected: alertType === "TAMPER_DETECTED",
    photocopy_detected: alertType === "PHOTOCOPY_DETECTED",
    duplicate_of: alertType === "DUPLICATE_INSTRUMENT" ? `CTS2026050300${String(i * 3 + 1).padStart(6, "0")}` : null,
    description: DESCRIPTIONS[alertType],
    created_at: d.toISOString(),
  };
});

router.get("/fraud/alerts", (req, res) => {
  let filtered = [...ALERTS];
  if (req.query.severity) filtered = filtered.filter((x) => x.severity === req.query.severity);
  if (req.query.status) filtered = filtered.filter((x) => x.status === req.query.status);
  const limit = Math.min(Number(req.query.limit ?? 30), 80);
  res.json(filtered.slice(0, limit));
});

router.get("/fraud/summary", (_req, res) => {
  const today = new Date().toISOString().split("T")[0];
  res.json({
    date: today,
    total_alerts: 953,
    high_severity: 112,
    medium_severity: 384,
    auto_blocked: 112,
    open_alerts: 318,
    resolved_today: 521,
    cts2010_violations: 147,
    duplicate_count: 89,
    blacklisted_hits: 34,
  });
});

export default router;
