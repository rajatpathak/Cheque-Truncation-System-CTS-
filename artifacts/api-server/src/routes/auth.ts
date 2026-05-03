import { Router } from "express";
import type { Request, Response } from "express";
import crypto from "crypto";

const router = Router();

const MOCK_USERS = [
  {
    emp_id: "IOB001",
    password: "IOB@Admin123",
    name: "Kumar Rajan",
    role: "admin",
    role_label: "System Administrator",
    branch_code: "HEAD",
    branch_name: "Head Office, Chennai",
    email: "kumar.rajan@iob.in",
    mobile: "98401XXXXX",
    department: "IT Operations",
    permissions: ["all"],
  },
  {
    emp_id: "IOB002",
    password: "IOB@Super123",
    name: "Meenakshi Sundaram",
    role: "supervisor",
    role_label: "Clearing Supervisor",
    branch_code: "DC001",
    branch_name: "DC Chennai – CTS Hub",
    email: "meenakshi.s@iob.in",
    mobile: "94440XXXXX",
    department: "Clearing Operations",
    permissions: ["instruments", "batches", "sessions", "returns", "positive_pay"],
  },
  {
    emp_id: "IOB003",
    password: "IOB@Fraud123",
    name: "Rajesh Kumar",
    role: "fraud_officer",
    role_label: "Fraud Detection Officer",
    branch_code: "DC001",
    branch_name: "DC Chennai – CTS Hub",
    email: "rajesh.k@iob.in",
    mobile: "91766XXXXX",
    department: "Risk & Fraud Management",
    permissions: ["fraud", "instruments", "audit"],
  },
  {
    emp_id: "IOB004",
    password: "IOB@Branch123",
    name: "Priya Lakshmi",
    role: "branch_operator",
    role_label: "Branch Operator",
    branch_code: "CHN001",
    branch_name: "Chennai Main Branch",
    email: "priya.l@iob.in",
    mobile: "97890XXXXX",
    department: "Branch Operations",
    permissions: ["instruments", "batches"],
  },
  {
    emp_id: "IOB005",
    password: "IOB@Audit123",
    name: "Suresh Gopal",
    role: "auditor",
    role_label: "Internal Auditor",
    branch_code: "HEAD",
    branch_name: "Head Office, Chennai",
    email: "suresh.g@iob.in",
    mobile: "98845XXXXX",
    department: "Internal Audit",
    permissions: ["audit", "instruments", "batches", "sessions", "fraud", "returns"],
  },
  {
    emp_id: "IOB006",
    password: "IOB@Check123",
    name: "Anand Krishnan",
    role: "checker",
    role_label: "Clearing Checker",
    branch_code: "HUB001",
    branch_name: "Mumbai CTS Hub",
    email: "anand.k@iob.in",
    mobile: "98201XXXXX",
    department: "Clearing Operations",
    permissions: ["instruments", "batches", "sessions", "returns"],
  },
  {
    emp_id: "IOB007",
    password: "IOB@BCP123",
    name: "Divya Nair",
    role: "bcp_officer",
    role_label: "BCP / DR Officer",
    branch_code: "DC002",
    branch_name: "DR Hyderabad – CTS Hub",
    email: "divya.n@iob.in",
    mobile: "96660XXXXX",
    department: "IT Operations",
    permissions: ["bcp", "audit"],
  },
];

const failedAttempts = new Map<string, { count: number; lockedUntil?: number }>();
const otpStore = new Map<string, { otp: string; empId: string; expiresAt: number }>();
const sessions = new Map<string, { user: (typeof MOCK_USERS)[0]; loginAt: number; lastActivity: number; ip: string }>();

const DEMO_OTP = "123456";
const MAX_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;
const SESSION_TTL_MS = 30 * 60 * 1000;

router.post("/auth/login", (req: Request, res: Response) => {
  const { emp_id, password } = req.body as { emp_id: string; password: string };

  if (!emp_id || !password) {
    res.status(400).json({ success: false, error: "Employee ID and password are required." });
    return;
  }

  const id = emp_id.toUpperCase().trim();
  const attempts = failedAttempts.get(id);
  if (attempts && attempts.lockedUntil && Date.now() < attempts.lockedUntil) {
    const remaining = Math.ceil((attempts.lockedUntil - Date.now()) / 60000);
    res.status(423).json({
      success: false,
      error: `Account locked due to ${MAX_ATTEMPTS} failed attempts. Try again in ${remaining} minute(s).`,
      locked: true,
      locked_until: attempts.lockedUntil,
    });
    return;
  }

  const user = MOCK_USERS.find((u) => u.emp_id === id && u.password === password);
  if (!user) {
    const current = failedAttempts.get(id) ?? { count: 0 };
    const newCount = current.count + 1;
    const locked = newCount >= MAX_ATTEMPTS;
    failedAttempts.set(id, {
      count: newCount,
      lockedUntil: locked ? Date.now() + LOCKOUT_MINUTES * 60 * 1000 : undefined,
    });
    const remaining = MAX_ATTEMPTS - newCount;
    res.status(401).json({
      success: false,
      error: locked
        ? `Account locked for ${LOCKOUT_MINUTES} minutes after ${MAX_ATTEMPTS} failed attempts.`
        : `Invalid credentials. ${remaining} attempt(s) remaining before lockout.`,
      locked,
      attempts_remaining: Math.max(0, remaining),
    });
    return;
  }

  failedAttempts.delete(id);

  const otpToken = crypto.randomBytes(24).toString("hex");
  otpStore.set(otpToken, { otp: DEMO_OTP, empId: id, expiresAt: Date.now() + 5 * 60 * 1000 });

  res.json({
    success: true,
    otp_token: otpToken,
    otp_hint: DEMO_OTP,
    message: `OTP sent to registered mobile ${user.mobile} and email ${user.email}`,
    employee_name: user.name,
    role_label: user.role_label,
  });
});

router.post("/auth/verify-otp", (req: Request, res: Response) => {
  const { otp_token, otp } = req.body as { otp_token: string; otp: string };

  if (!otp_token || !otp) {
    res.status(400).json({ success: false, error: "OTP token and OTP are required." });
    return;
  }

  const record = otpStore.get(otp_token);
  if (!record) {
    res.status(401).json({ success: false, error: "Invalid or expired OTP session. Please login again." });
    return;
  }

  if (Date.now() > record.expiresAt) {
    otpStore.delete(otp_token);
    res.status(401).json({ success: false, error: "OTP has expired. Please login again." });
    return;
  }

  if (record.otp !== otp.trim()) {
    res.status(401).json({ success: false, error: "Incorrect OTP. Please try again." });
    return;
  }

  otpStore.delete(otp_token);

  const user = MOCK_USERS.find((u) => u.emp_id === record.empId);
  if (!user) {
    res.status(500).json({ success: false, error: "User lookup failed." });
    return;
  }

  const token = crypto.randomBytes(32).toString("hex");
  const ip = req.headers["x-forwarded-for"]?.toString() || req.socket.remoteAddress || "unknown";
  sessions.set(token, { user, loginAt: Date.now(), lastActivity: Date.now(), ip });

  const { password: _pw, ...safeUser } = user;

  res.json({
    success: true,
    token,
    user: {
      ...safeUser,
      login_at: new Date().toISOString(),
      session_expires_at: new Date(Date.now() + SESSION_TTL_MS).toISOString(),
    },
  });
});

router.get("/auth/me", (req: Request, res: Response) => {
  const authHeader = req.headers["authorization"];
  const token = authHeader?.replace("Bearer ", "");

  if (!token) {
    res.status(401).json({ success: false, error: "No session token provided." });
    return;
  }

  const session = sessions.get(token);
  if (!session) {
    res.status(401).json({ success: false, error: "Session not found or expired." });
    return;
  }

  if (Date.now() - session.lastActivity > SESSION_TTL_MS) {
    sessions.delete(token);
    res.status(401).json({ success: false, error: "Session expired due to inactivity." });
    return;
  }

  session.lastActivity = Date.now();
  const { password: _pw, ...safeUser } = session.user;

  res.json({
    success: true,
    user: {
      ...safeUser,
      login_at: new Date(session.loginAt).toISOString(),
      last_activity: new Date(session.lastActivity).toISOString(),
      ip_address: session.ip,
    },
  });
});

router.post("/auth/logout", (req: Request, res: Response) => {
  const authHeader = req.headers["authorization"];
  const token = authHeader?.replace("Bearer ", "");

  if (token) {
    sessions.delete(token);
  }

  res.json({ success: true, message: "You have been securely logged out." });
});

router.post("/auth/refresh", (req: Request, res: Response) => {
  const authHeader = req.headers["authorization"];
  const token = authHeader?.replace("Bearer ", "");

  if (!token) {
    res.status(401).json({ success: false, error: "No session token provided." });
    return;
  }

  const session = sessions.get(token);
  if (!session) {
    res.status(401).json({ success: false, error: "Session not found." });
    return;
  }

  session.lastActivity = Date.now();
  res.json({
    success: true,
    session_expires_at: new Date(Date.now() + SESSION_TTL_MS).toISOString(),
  });
});

export default router;
