import { Router } from "express";

const router = Router();

const GRIDS = ["CHN", "MUM", "DEL", "KOL", "HYD", "BLR"];
const SESSION_TYPES = ["MORNING", "AFTERNOON", "EVENING", "HIGH_VALUE"];
const CLEARING_TYPES = ["CTS", "MICR", "HIGH_VALUE_MICR"];
const SESSION_STATUSES = ["OPEN", "PROCESSING", "SUBMITTED", "SETTLED", "RECONCILED"];

const SESSIONS = Array.from({ length: 20 }, (_, i) => {
  const grid = GRIDS[i % GRIDS.length];
  const sessionType = SESSION_TYPES[i % SESSION_TYPES.length];
  const clearingType = CLEARING_TYPES[i % CLEARING_TYPES.length];
  const status = SESSION_STATUSES[i % SESSION_STATUSES.length];
  const instruments = 5000 + (i * 311) % 15000;
  const outward = instruments * (40000 + (i * 1337) % 200000);
  const inward = instruments * (35000 + (i * 971) % 180000);
  const d = new Date(Date.now() - i * 3600000);
  return {
    id: i + 1,
    session_number: `SN${String(20260503).padStart(8, "0")}${String(i + 1).padStart(3, "0")}`,
    session_date: "2026-05-03",
    session_type: sessionType,
    clearing_type: clearingType,
    grid_code: grid,
    status,
    total_instruments: instruments,
    total_outward_amount: outward,
    total_inward_amount: inward,
    submitted_at: status !== "OPEN" ? d.toISOString() : null,
  };
});

router.get("/sessions", (req, res) => {
  let filtered = [...SESSIONS];
  if (req.query.status) filtered = filtered.filter((x) => x.status === req.query.status);
  res.json(filtered);
});

export default router;
