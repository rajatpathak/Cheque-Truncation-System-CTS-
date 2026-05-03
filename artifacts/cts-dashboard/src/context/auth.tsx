import { createContext, useContext, useEffect, useRef, useState, useCallback } from "react";
import type { ReactNode } from "react";

const BASE = import.meta.env.BASE_URL.replace(/\/$/, "");

export interface AuthUser {
  emp_id: string;
  name: string;
  role: string;
  role_label: string;
  branch_code: string;
  branch_name: string;
  email: string;
  mobile: string;
  department: string;
  permissions: string[];
  login_at: string;
  last_activity?: string;
  ip_address?: string;
}

interface AuthContextValue {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  step: "login" | "otp" | "authenticated";
  otpToken: string | null;
  otpHint: string | null;
  employeeNameHint: string | null;
  roleLabelHint: string | null;
  lastLoginInfo: { time: string; ip: string } | null;
  login: (empId: string, password: string) => Promise<{ success: boolean; error?: string; locked?: boolean }>;
  verifyOtp: (otp: string) => Promise<{ success: boolean; error?: string }>;
  logout: () => Promise<void>;
  refreshSession: () => Promise<void>;
  idleSeconds: number;
  sessionWarning: boolean;
  dismissWarning: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

const TOKEN_KEY = "iob_cts_token";
const LAST_LOGIN_KEY = "iob_cts_last_login";
const IDLE_TIMEOUT_S = 30 * 60;
const WARN_AT_S = 5 * 60;

async function apiFetch(path: string, options?: RequestInit) {
  const res = await fetch(`${BASE}/api${path}`, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      ...(options?.headers ?? {}),
    },
  });
  const data = await res.json();
  return { ok: res.ok, status: res.status, data };
}

function authFetch(path: string, token: string, options?: RequestInit) {
  return apiFetch(path, {
    ...options,
    headers: {
      ...(options?.headers ?? {}),
      Authorization: `Bearer ${token}`,
    },
  });
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(() => localStorage.getItem(TOKEN_KEY));
  const [loading, setLoading] = useState(true);
  const [step, setStep] = useState<"login" | "otp" | "authenticated">("login");
  const [otpToken, setOtpToken] = useState<string | null>(null);
  const [otpHint, setOtpHint] = useState<string | null>(null);
  const [employeeNameHint, setEmployeeNameHint] = useState<string | null>(null);
  const [roleLabelHint, setRoleLabelHint] = useState<string | null>(null);
  const [lastLoginInfo, setLastLoginInfo] = useState<{ time: string; ip: string } | null>(() => {
    try { return JSON.parse(localStorage.getItem(LAST_LOGIN_KEY) ?? "null"); } catch { return null; }
  });

  const [idleSeconds, setIdleSeconds] = useState(0);
  const [sessionWarning, setSessionWarning] = useState(false);
  const idleRef = useRef(0);
  const activityRef = useRef(0);

  const resetIdle = useCallback(() => {
    idleRef.current = 0;
    activityRef.current = Date.now();
  }, []);

  useEffect(() => {
    if (!token) { setLoading(false); return; }
    apiFetch("/auth/me", { headers: { Authorization: `Bearer ${token}` } }).then(({ ok, data }) => {
      if (ok && data.success) {
        setUser(data.user);
        setStep("authenticated");
      } else {
        localStorage.removeItem(TOKEN_KEY);
        setToken(null);
      }
      setLoading(false);
    });
  }, []);

  useEffect(() => {
    if (step !== "authenticated") return;
    const events = ["mousemove", "keydown", "click", "touchstart", "scroll"];
    const handler = () => resetIdle();
    events.forEach((e) => window.addEventListener(e, handler, { passive: true }));

    const interval = setInterval(() => {
      idleRef.current += 1;
      setIdleSeconds(idleRef.current);
      if (idleRef.current >= IDLE_TIMEOUT_S) {
        logout();
      } else if (idleRef.current >= IDLE_TIMEOUT_S - WARN_AT_S) {
        setSessionWarning(true);
      }
    }, 1000);

    return () => {
      events.forEach((e) => window.removeEventListener(e, handler));
      clearInterval(interval);
    };
  }, [step]);

  const login = useCallback(async (empId: string, password: string) => {
    const { ok, data } = await apiFetch("/auth/login", {
      method: "POST",
      body: JSON.stringify({ emp_id: empId, password }),
    });
    if (ok && data.success) {
      setOtpToken(data.otp_token);
      setOtpHint(data.otp_hint);
      setEmployeeNameHint(data.employee_name);
      setRoleLabelHint(data.role_label);
      setStep("otp");
      return { success: true };
    }
    return { success: false, error: data.error, locked: data.locked };
  }, []);

  const verifyOtp = useCallback(async (otp: string) => {
    if (!otpToken) return { success: false, error: "No OTP session." };
    const { ok, data } = await apiFetch("/auth/verify-otp", {
      method: "POST",
      body: JSON.stringify({ otp_token: otpToken, otp }),
    });
    if (ok && data.success) {
      const newToken: string = data.token;
      localStorage.setItem(TOKEN_KEY, newToken);
      setToken(newToken);
      setUser(data.user);
      setStep("authenticated");
      setOtpToken(null);
      setOtpHint(null);

      const ipRes = await authFetch("/auth/me", newToken);
      const loginMeta = {
        time: new Date().toISOString(),
        ip: ipRes.data?.user?.ip_address ?? "unknown",
      };
      localStorage.setItem(LAST_LOGIN_KEY, JSON.stringify(loginMeta));
      setLastLoginInfo(loginMeta);

      return { success: true };
    }
    return { success: false, error: data.error };
  }, [otpToken]);

  const logout = useCallback(async () => {
    if (token) {
      await authFetch("/auth/logout", token, { method: "POST" }).catch(() => {});
    }
    localStorage.removeItem(TOKEN_KEY);
    setToken(null);
    setUser(null);
    setStep("login");
    setOtpToken(null);
    setOtpHint(null);
    setSessionWarning(false);
    setIdleSeconds(0);
    idleRef.current = 0;
  }, [token]);

  const refreshSession = useCallback(async () => {
    if (!token) return;
    await authFetch("/auth/refresh", token, { method: "POST" });
    resetIdle();
    setSessionWarning(false);
  }, [token, resetIdle]);

  const dismissWarning = useCallback(() => {
    refreshSession();
  }, [refreshSession]);

  return (
    <AuthContext.Provider value={{
      user, token, loading, step,
      otpToken, otpHint, employeeNameHint, roleLabelHint,
      lastLoginInfo, login, verifyOtp, logout,
      refreshSession, idleSeconds, sessionWarning, dismissWarning,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
