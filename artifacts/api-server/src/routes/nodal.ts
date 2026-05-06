import { Router } from "express";
import type { Request, Response } from "express";

const router = Router();

const GRIDS = [
  {
    grid_id: "GRID-CHN",
    name: "Chennai Grid",
    type: "Primary DC",
    location: "Chennai, Tamil Nadu",
    hub_code: "CHN-HUB",
    status: "ONLINE",
    color: "green",
    banks_connected: 42,
    branches_connected: 1240,
    ip_address: "10.10.1.1",
    last_heartbeat: new Date().toISOString(),
    throughput_today: 82450,
    throughput_capacity: 120000,
    pending_instruments: 312,
    settlement_lag_ms: 420,
    replication_lag_s: 0,
    uptime_pct: 99.98,
  },
  {
    grid_id: "GRID-MUM",
    name: "Mumbai Grid",
    type: "Regional Hub",
    location: "Mumbai, Maharashtra",
    hub_code: "MUM-HUB",
    status: "ONLINE",
    color: "green",
    banks_connected: 38,
    branches_connected: 1080,
    ip_address: "10.10.2.1",
    last_heartbeat: new Date().toISOString(),
    throughput_today: 71200,
    throughput_capacity: 100000,
    pending_instruments: 198,
    settlement_lag_ms: 380,
    replication_lag_s: 2,
    uptime_pct: 99.95,
  },
  {
    grid_id: "GRID-DEL",
    name: "Delhi Grid",
    type: "Regional Hub",
    location: "New Delhi, Delhi",
    hub_code: "DEL-HUB",
    status: "ONLINE",
    color: "green",
    banks_connected: 35,
    branches_connected: 920,
    ip_address: "10.10.3.1",
    last_heartbeat: new Date().toISOString(),
    throughput_today: 58300,
    throughput_capacity: 90000,
    pending_instruments: 145,
    settlement_lag_ms: 510,
    replication_lag_s: 4,
    uptime_pct: 99.91,
  },
  {
    grid_id: "GRID-KOL",
    name: "Kolkata Grid",
    type: "Regional Hub",
    location: "Kolkata, West Bengal",
    hub_code: "KOL-HUB",
    status: "DEGRADED",
    color: "amber",
    banks_connected: 28,
    branches_connected: 740,
    ip_address: "10.10.4.1",
    last_heartbeat: new Date(Date.now() - 90000).toISOString(),
    throughput_today: 39100,
    throughput_capacity: 75000,
    pending_instruments: 874,
    settlement_lag_ms: 1820,
    replication_lag_s: 18,
    uptime_pct: 98.72,
  },
  {
    grid_id: "GRID-HYD",
    name: "Hyderabad DR",
    type: "DR Node",
    location: "Hyderabad, Telangana",
    hub_code: "HYD-DR",
    status: "STANDBY",
    color: "blue",
    banks_connected: 0,
    branches_connected: 0,
    ip_address: "10.20.1.1",
    last_heartbeat: new Date().toISOString(),
    throughput_today: 0,
    throughput_capacity: 120000,
    pending_instruments: 0,
    settlement_lag_ms: 0,
    replication_lag_s: 6,
    uptime_pct: 100.0,
  },
];

const HUB_NODES = [
  { hub_code: "CHN001", hub_name: "Chennai Main Hub", grid_id: "GRID-CHN", connected_branches: 124, instruments_today: 18420, avg_processing_ms: 380, status: "ONLINE", scanner_count: 12 },
  { hub_code: "CHN002", hub_name: "Chennai South Hub", grid_id: "GRID-CHN", connected_branches: 88, instruments_today: 14300, avg_processing_ms: 410, status: "ONLINE", scanner_count: 8 },
  { hub_code: "MUM001", hub_name: "Mumbai BKC Hub", grid_id: "GRID-MUM", connected_branches: 142, instruments_today: 22100, avg_processing_ms: 355, status: "ONLINE", scanner_count: 14 },
  { hub_code: "MUM002", hub_name: "Mumbai Andheri Hub", grid_id: "GRID-MUM", connected_branches: 96, instruments_today: 15800, avg_processing_ms: 395, status: "ONLINE", scanner_count: 10 },
  { hub_code: "DEL001", hub_name: "Delhi Connaught Hub", grid_id: "GRID-DEL", connected_branches: 108, instruments_today: 17600, avg_processing_ms: 430, status: "ONLINE", scanner_count: 11 },
  { hub_code: "DEL002", hub_name: "Delhi Noida Hub", grid_id: "GRID-DEL", connected_branches: 74, instruments_today: 12200, avg_processing_ms: 455, status: "ONLINE", scanner_count: 7 },
  { hub_code: "KOL001", hub_name: "Kolkata BBD Bag Hub", grid_id: "GRID-KOL", connected_branches: 82, instruments_today: 9800, avg_processing_ms: 890, status: "DEGRADED", scanner_count: 9 },
  { hub_code: "KOL002", hub_name: "Kolkata Salt Lake Hub", grid_id: "GRID-KOL", connected_branches: 61, instruments_today: 7400, avg_processing_ms: 940, status: "DEGRADED", scanner_count: 6 },
];

const INTER_GRID_FLOWS = [
  { from: "GRID-CHN", to: "GRID-MUM", instruments: 4820, amount_cr: 128.4, status: "ACTIVE" },
  { from: "GRID-MUM", to: "GRID-CHN", instruments: 3910, amount_cr: 98.2, status: "ACTIVE" },
  { from: "GRID-CHN", to: "GRID-DEL", instruments: 2340, amount_cr: 74.6, status: "ACTIVE" },
  { from: "GRID-DEL", to: "GRID-CHN", instruments: 1980, amount_cr: 61.8, status: "ACTIVE" },
  { from: "GRID-CHN", to: "GRID-KOL", instruments: 1120, amount_cr: 38.2, status: "SLOW" },
  { from: "GRID-KOL", to: "GRID-CHN", instruments: 890, amount_cr: 29.7, status: "SLOW" },
  { from: "GRID-MUM", to: "GRID-DEL", instruments: 3100, amount_cr: 82.1, status: "ACTIVE" },
  { from: "GRID-DEL", to: "GRID-MUM", instruments: 2760, amount_cr: 71.4, status: "ACTIVE" },
];

const NPCI_CONNECTIVITY = [
  { service: "NPCI NACH Gateway", status: "CONNECTED", latency_ms: 42, last_checked: new Date().toISOString() },
  { service: "NPCI CPPS (Positive Pay)", status: "CONNECTED", latency_ms: 68, last_checked: new Date().toISOString() },
  { service: "NPCI Image Archive", status: "CONNECTED", latency_ms: 124, last_checked: new Date().toISOString() },
  { service: "RBI SFMS Gateway", status: "CONNECTED", latency_ms: 88, last_checked: new Date().toISOString() },
  { service: "NPCI Fraud Engine", status: "CONNECTED", latency_ms: 55, last_checked: new Date().toISOString() },
  { service: "SWIFT MT103 Interface", status: "DEGRADED", latency_ms: 2140, last_checked: new Date(Date.now() - 60000).toISOString() },
  { service: "CBS Integration (Finacle)", status: "CONNECTED", latency_ms: 31, last_checked: new Date().toISOString() },
];

router.get("/nodal/grids", (_req: Request, res: Response) => {
  const total_instruments = GRIDS.reduce((s, g) => s + g.throughput_today, 0);
  const total_banks = GRIDS.filter((g) => g.status !== "STANDBY").reduce((s, g) => s + g.banks_connected, 0);
  const total_branches = GRIDS.filter((g) => g.status !== "STANDBY").reduce((s, g) => s + g.branches_connected, 0);
  res.json({
    grids: GRIDS,
    summary: {
      total_grids: GRIDS.length,
      active_grids: GRIDS.filter((g) => g.status === "ONLINE").length,
      degraded_grids: GRIDS.filter((g) => g.status === "DEGRADED").length,
      total_instruments_today: total_instruments,
      total_banks_connected: total_banks,
      total_branches_connected: total_branches,
    },
  });
});

router.get("/nodal/hubs", (_req: Request, res: Response) => {
  res.json({ hubs: HUB_NODES, total: HUB_NODES.length });
});

router.get("/nodal/inter-grid-flows", (_req: Request, res: Response) => {
  res.json({ flows: INTER_GRID_FLOWS, total: INTER_GRID_FLOWS.length });
});

router.get("/nodal/npci-connectivity", (_req: Request, res: Response) => {
  res.json({ services: NPCI_CONNECTIVITY, checked_at: new Date().toISOString() });
});

export default router;
