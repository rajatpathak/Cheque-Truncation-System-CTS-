import { Router } from "express";

const router = Router();

const MODULES = ["OUTWARD_CLEARING", "INWARD_CLEARING", "FRAUD_DETECTION", "RETURN_PROCESSING", "PKI_SIGNING", "ADMINISTRATION", "BCP_DR", "INTEGRATION"];
const ACTIONS = ["LOGIN", "LOGOUT", "BATCH_SEAL", "BATCH_SIGN", "BATCH_SUBMIT", "ALERT_RESOLVE", "ALERT_ESCALATE", "RETURN_PROCESS", "FAILOVER_TRIGGER", "PARAMETER_CHANGE", "USER_CREATE", "PASSWORD_RESET"];
const USERS = ["admin.kumar", "supervisor.rajan", "hub.operator.1", "fraud.officer.1", "return.officer.1", "bcp.officer.1", "signing.officer.1", "integration.officer.1"];
const BRANCHES = ["CHN001", "CHN002", "MUM001", "DEL001", "KOL001", "HYD001", "BLR001", "HEAD_OFFICE"];
const IPS = ["10.10.1.100", "10.10.1.101", "10.20.1.50", "10.30.1.75", "192.168.10.20"];

const ENTRIES = Array.from({ length: 200 }, (_, i) => {
  const module = MODULES[i % MODULES.length];
  const action = ACTIONS[i % ACTIONS.length];
  const user = USERS[i % USERS.length];
  const branch = BRANCHES[i % BRANCHES.length];
  const ip = IPS[i % IPS.length];
  const d = new Date(Date.now() - i * 180000);
  return {
    id: i + 1,
    user_name: user,
    branch_code: branch,
    action,
    module,
    reference_id: `REF${String(d.getTime()).slice(-10)}`,
    ip_address: ip,
    timestamp: d.toISOString(),
  };
});

router.get("/audit/trail", (req, res) => {
  let filtered = [...ENTRIES];
  if (req.query.module) filtered = filtered.filter((x) => x.module === req.query.module);
  const limit = Math.min(Number(req.query.limit ?? 50), 200);
  res.json(filtered.slice(0, limit));
});

export default router;
