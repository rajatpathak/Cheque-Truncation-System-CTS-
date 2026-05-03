import { useState, useRef, useEffect } from "react";
import { useAuth } from "@/context/auth";
import { cn } from "@/lib/utils";
import {
  ShieldCheck, Eye, EyeOff, AlertCircle, Lock, User, KeyRound,
  Clock, Info, CheckCircle2, XCircle,
} from "lucide-react";

const DEMO_USERS = [
  { emp_id: "IOB001", password: "IOB@Admin123", role: "System Administrator" },
  { emp_id: "IOB002", password: "IOB@Super123", role: "Clearing Supervisor" },
  { emp_id: "IOB003", password: "IOB@Fraud123", role: "Fraud Detection Officer" },
  { emp_id: "IOB004", password: "IOB@Branch123", role: "Branch Operator" },
  { emp_id: "IOB005", password: "IOB@Audit123", role: "Internal Auditor" },
  { emp_id: "IOB006", password: "IOB@Check123", role: "Clearing Checker" },
  { emp_id: "IOB007", password: "IOB@BCP123", role: "BCP / DR Officer" },
];

export default function Login() {
  const { login, verifyOtp, step, otpHint, employeeNameHint, roleLabelHint, lastLoginInfo } = useAuth();

  const [empId, setEmpId] = useState("");
  const [password, setPassword] = useState("");
  const [showPw, setShowPw] = useState(false);
  const [otp, setOtp] = useState(["", "", "", "", "", ""]);
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [locked, setLocked] = useState(false);
  const [otpTimer, setOtpTimer] = useState(300);
  const otpRefs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    if (step !== "otp") return;
    const interval = setInterval(() => setOtpTimer((t) => Math.max(0, t - 1)), 1000);
    return () => clearInterval(interval);
  }, [step]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setSubmitting(true);
    const result = await login(empId.trim(), password);
    setSubmitting(false);
    if (!result.success) {
      setError(result.error ?? "Login failed.");
      if (result.locked) setLocked(true);
    }
  };

  const handleOtpChange = (idx: number, val: string) => {
    if (!/^\d?$/.test(val)) return;
    const next = [...otp];
    next[idx] = val;
    setOtp(next);
    if (val && idx < 5) otpRefs.current[idx + 1]?.focus();
  };

  const handleOtpKeyDown = (idx: number, e: React.KeyboardEvent) => {
    if (e.key === "Backspace" && !otp[idx] && idx > 0) otpRefs.current[idx - 1]?.focus();
    if (e.key === "ArrowLeft" && idx > 0) otpRefs.current[idx - 1]?.focus();
    if (e.key === "ArrowRight" && idx < 5) otpRefs.current[idx + 1]?.focus();
  };

  const handleOtpPaste = (e: React.ClipboardEvent) => {
    const pasted = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
    if (pasted.length === 6) {
      setOtp(pasted.split(""));
      otpRefs.current[5]?.focus();
    }
  };

  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    const code = otp.join("");
    if (code.length !== 6) { setError("Please enter the complete 6-digit OTP."); return; }
    setError("");
    setSubmitting(true);
    const result = await verifyOtp(code);
    setSubmitting(false);
    if (!result.success) setError(result.error ?? "OTP verification failed.");
  };

  const otpMinutes = Math.floor(otpTimer / 60);
  const otpSeconds = otpTimer % 60;

  return (
    <div className="min-h-screen bg-[hsl(224,60%,10%)] flex">
      {/* Left panel */}
      <div className="hidden lg:flex lg:w-1/2 flex-col justify-between p-10 bg-[hsl(224,60%,8%)] border-r border-white/5">
        <div>
          <div className="flex items-center gap-3 mb-10">
            <div className="w-10 h-10 rounded-lg bg-[hsl(38,92%,55%)] flex items-center justify-center">
              <ShieldCheck className="w-6 h-6 text-[hsl(224,60%,10%)]" />
            </div>
            <div>
              <p className="text-white font-bold text-lg leading-tight">IOB CTS National Grid</p>
              <p className="text-white/50 text-xs">GeM/2026/B/7367951 · CAPEX 5-yr</p>
            </div>
          </div>

          <h1 className="text-4xl font-bold text-white leading-tight mb-4">
            Cheque Truncation<br />System Operations
          </h1>
          <p className="text-white/60 text-base leading-relaxed">
            NPCI-certified multi-grid clearing platform for Indian Overseas Bank.
            Secure, role-based access for authorised personnel only.
          </p>

          <div className="mt-10 grid grid-cols-2 gap-4">
            {[
              { label: "Instruments/Day", value: "2.4 L+" },
              { label: "Grid Nodes", value: "3 DC + 1 DR" },
              { label: "Uptime SLA", value: "99.97%" },
              { label: "Processing Time", value: "< 4 hrs" },
            ].map((s) => (
              <div key={s.label} className="rounded-lg bg-white/5 border border-white/10 p-4">
                <p className="text-[hsl(38,92%,55%)] text-2xl font-bold">{s.value}</p>
                <p className="text-white/50 text-xs mt-1">{s.label}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="space-y-3">
          <div className="rounded-lg bg-[hsl(38,92%,55%)]/10 border border-[hsl(38,92%,55%)]/30 px-4 py-3">
            <p className="text-[hsl(38,92%,55%)] text-xs font-semibold uppercase tracking-wide mb-1">
              Security Notice
            </p>
            <p className="text-white/60 text-xs leading-relaxed">
              This system is for authorised IOB personnel only. All access is monitored, logged,
              and subject to RBI/NPCI compliance requirements. Unauthorised access is a criminal
              offence under IT Act 2000.
            </p>
          </div>
          <p className="text-white/30 text-[10px] text-center">
            © {new Date().getFullYear()} Indian Overseas Bank · Powered by NPCI CTS Infrastructure
          </p>
        </div>
      </div>

      {/* Right panel */}
      <div className="flex-1 flex items-center justify-center p-6">
        <div className="w-full max-w-md">

          {/* Mobile header */}
          <div className="lg:hidden text-center mb-8">
            <div className="w-12 h-12 rounded-xl bg-[hsl(38,92%,55%)] flex items-center justify-center mx-auto mb-3">
              <ShieldCheck className="w-7 h-7 text-[hsl(224,60%,10%)]" />
            </div>
            <p className="text-white font-bold text-xl">IOB CTS National Grid</p>
            <p className="text-white/40 text-xs">Operations Dashboard</p>
          </div>

          {lastLoginInfo && step === "login" && (
            <div className="mb-4 rounded-lg bg-white/5 border border-white/10 px-4 py-3 flex items-start gap-3">
              <Info className="w-4 h-4 text-[hsl(38,92%,55%)] shrink-0 mt-0.5" />
              <div className="text-xs text-white/60">
                <span className="font-medium text-white/80">Last login: </span>
                {new Date(lastLoginInfo.time).toLocaleString("en-IN")} · IP {lastLoginInfo.ip}
              </div>
            </div>
          )}

          <div className="rounded-2xl bg-white/5 border border-white/10 p-8 backdrop-blur-sm">
            {step === "login" ? (
              <>
                <h2 className="text-white font-semibold text-xl mb-1">Sign in to your account</h2>
                <p className="text-white/50 text-sm mb-6">Use your Employee ID and network password</p>

                {error && (
                  <div className={cn(
                    "rounded-lg px-4 py-3 mb-5 flex items-start gap-2 text-sm",
                    locked
                      ? "bg-red-900/30 border border-red-500/40 text-red-300"
                      : "bg-red-900/20 border border-red-500/30 text-red-400"
                  )}>
                    {locked ? <Lock className="w-4 h-4 shrink-0 mt-0.5" /> : <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />}
                    <span>{error}</span>
                  </div>
                )}

                <form onSubmit={handleLogin} className="space-y-4">
                  <div>
                    <label className="block text-white/60 text-xs font-medium mb-1.5 uppercase tracking-wide">
                      Employee ID
                    </label>
                    <div className="relative">
                      <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                      <input
                        type="text"
                        value={empId}
                        onChange={(e) => setEmpId(e.target.value.toUpperCase())}
                        autoComplete="username"
                        placeholder="e.g. IOB001"
                        disabled={locked || submitting}
                        className="w-full bg-white/5 border border-white/15 rounded-lg pl-10 pr-4 py-3 text-white placeholder-white/25 text-sm focus:outline-none focus:border-[hsl(38,92%,55%)]/60 focus:ring-1 focus:ring-[hsl(38,92%,55%)]/40 disabled:opacity-50"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-white/60 text-xs font-medium mb-1.5 uppercase tracking-wide">
                      Password
                    </label>
                    <div className="relative">
                      <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                      <input
                        type={showPw ? "text" : "password"}
                        autoComplete="current-password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="Network password"
                        disabled={locked || submitting}
                        className="w-full bg-white/5 border border-white/15 rounded-lg pl-10 pr-10 py-3 text-white placeholder-white/25 text-sm focus:outline-none focus:border-[hsl(38,92%,55%)]/60 focus:ring-1 focus:ring-[hsl(38,92%,55%)]/40 disabled:opacity-50"
                      />
                      <button type="button" onClick={() => setShowPw((s) => !s)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-white/30 hover:text-white/60">
                        {showPw ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                      </button>
                    </div>
                  </div>

                  <button
                    type="submit"
                    disabled={locked || submitting || !empId || !password}
                    className="w-full bg-[hsl(38,92%,55%)] hover:bg-[hsl(38,92%,48%)] text-[hsl(224,60%,10%)] font-bold rounded-lg py-3 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                  >
                    {submitting ? (
                      <><div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin" /> Authenticating…</>
                    ) : (
                      <><ShieldCheck className="w-4 h-4" /> Proceed to OTP Verification</>
                    )}
                  </button>
                </form>

                <div className="mt-6 border-t border-white/10 pt-5">
                  <p className="text-white/40 text-xs font-medium mb-3 flex items-center gap-2">
                    <Info className="w-3 h-3" /> Demo credentials
                  </p>
                  <div className="space-y-1.5 max-h-36 overflow-y-auto">
                    {DEMO_USERS.map((u) => (
                      <button
                        key={u.emp_id}
                        type="button"
                        onClick={() => { setEmpId(u.emp_id); setPassword(u.password); setError(""); setLocked(false); }}
                        className="w-full text-left rounded-md bg-white/5 hover:bg-white/10 border border-white/10 px-3 py-2 transition-colors"
                      >
                        <span className="text-[hsl(38,92%,55%)] font-mono text-xs font-bold">{u.emp_id}</span>
                        <span className="text-white/40 text-xs ml-2">· {u.role}</span>
                      </button>
                    ))}
                  </div>
                </div>
              </>
            ) : (
              <>
                <div className="flex items-center gap-3 mb-5">
                  <div className="w-10 h-10 rounded-full bg-[hsl(38,92%,55%)]/15 border border-[hsl(38,92%,55%)]/30 flex items-center justify-center">
                    <KeyRound className="w-5 h-5 text-[hsl(38,92%,55%)]" />
                  </div>
                  <div>
                    <h2 className="text-white font-semibold text-lg">OTP Verification</h2>
                    <p className="text-white/40 text-xs">{employeeNameHint} · {roleLabelHint}</p>
                  </div>
                </div>

                {otpHint && (
                  <div className="mb-5 rounded-lg bg-[hsl(38,92%,55%)]/10 border border-[hsl(38,92%,55%)]/30 px-4 py-3">
                    <p className="text-[hsl(38,92%,55%)] text-xs font-semibold mb-1">
                      Demo Mode — Your OTP
                    </p>
                    <p className="text-white font-mono text-3xl font-bold tracking-widest">{otpHint}</p>
                    <p className="text-white/40 text-xs mt-1">In production this is sent via SMS and email</p>
                  </div>
                )}

                <p className="text-white/60 text-sm mb-4">
                  Enter the 6-digit OTP sent to your registered mobile and email.
                </p>

                {error && (
                  <div className="rounded-lg bg-red-900/20 border border-red-500/30 px-4 py-3 mb-4 flex items-start gap-2 text-sm text-red-400">
                    <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />{error}
                  </div>
                )}

                <form onSubmit={handleVerifyOtp} className="space-y-5">
                  <div>
                    <label className="block text-white/60 text-xs font-medium mb-3 uppercase tracking-wide">
                      One-Time Password
                    </label>
                    <div className="flex gap-2 justify-between" onPaste={handleOtpPaste}>
                      {otp.map((digit, idx) => (
                        <input
                          key={idx}
                          ref={(el) => { otpRefs.current[idx] = el; }}
                          type="text"
                          inputMode="numeric"
                          maxLength={1}
                          value={digit}
                          onChange={(e) => handleOtpChange(idx, e.target.value)}
                          onKeyDown={(e) => handleOtpKeyDown(idx, e)}
                          className="w-12 h-14 text-center text-white font-mono text-xl font-bold bg-white/5 border border-white/15 rounded-lg focus:outline-none focus:border-[hsl(38,92%,55%)]/60 focus:ring-1 focus:ring-[hsl(38,92%,55%)]/40"
                        />
                      ))}
                    </div>
                  </div>

                  <div className="flex items-center gap-2 text-xs text-white/40">
                    <Clock className="w-3.5 h-3.5" />
                    <span>OTP expires in <span className={cn("font-mono font-bold", otpTimer < 60 ? "text-red-400" : "text-white/60")}>{otpMinutes}:{String(otpSeconds).padStart(2, "0")}</span></span>
                  </div>

                  <button
                    type="submit"
                    disabled={submitting || otp.join("").length !== 6}
                    className="w-full bg-[hsl(38,92%,55%)] hover:bg-[hsl(38,92%,48%)] text-[hsl(224,60%,10%)] font-bold rounded-lg py-3 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                  >
                    {submitting ? (
                      <><div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin" /> Verifying…</>
                    ) : (
                      <><CheckCircle2 className="w-4 h-4" /> Verify & Sign In</>
                    )}
                  </button>

                  <button
                    type="button"
                    onClick={() => { setOtp(["","","","","",""]); setError(""); }}
                    className="w-full flex items-center justify-center gap-2 text-white/40 hover:text-white/60 text-sm transition-colors"
                  >
                    <XCircle className="w-4 h-4" /> Back to Login
                  </button>
                </form>
              </>
            )}
          </div>

          <div className="mt-4 flex flex-wrap items-center justify-center gap-3 text-[10px] text-white/30">
            <span className="flex items-center gap-1"><ShieldCheck className="w-3 h-3" /> RBI Compliant</span>
            <span>·</span>
            <span>NPCI CTS 2010</span>
            <span>·</span>
            <span>ISO 27001</span>
            <span>·</span>
            <span>TLS 1.3 Encrypted</span>
          </div>
        </div>
      </div>
    </div>
  );
}
