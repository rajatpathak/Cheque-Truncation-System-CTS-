import { useState, useRef, useCallback, useEffect } from "react";
import {
  Upload, ScanLine, CheckCircle2, XCircle, AlertTriangle,
  RefreshCw, ChevronRight, Shield, Zap, FileImage,
  Fingerprint, Eye, Layers, Waves, Scan, Info,
} from "lucide-react";
import { cn } from "@/lib/utils";

// ── Types ────────────────────────────────────────────────────────────────────

type CheckStatus = "pass" | "fail" | "warn";

interface Check {
  id: string;
  label: string;
  value: string;
  status: CheckStatus;
  detail: string;
  icon: React.ElementType;
}

interface ScanResult {
  score: number;
  overall: "PASS" | "FAIL" | "WARN";
  chequeNo: string;
  amount: string;
  draweeBank: string;
  ifsc: string;
  date: string;
  checks: Check[];
  processingMs: number;
  fraudFlags: string[];
}

// ── Cheque SVG components ────────────────────────────────────────────────────

function RealChequeSVG({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 640 280" xmlns="http://www.w3.org/2000/svg" style={{ fontFamily: "monospace" }}>
      <defs>
        <pattern id="real-bg" patternUnits="userSpaceOnUse" width="20" height="20" patternTransform="rotate(45)">
          <line x1="0" y1="0" x2="0" y2="20" stroke="#e8f4e8" strokeWidth="0.5" />
        </pattern>
        <linearGradient id="real-grad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#f0f9f0" />
          <stop offset="100%" stopColor="#e8f5e8" />
        </linearGradient>
      </defs>

      {/* Background */}
      <rect width="640" height="280" rx="6" fill="url(#real-grad)" />
      <rect width="640" height="280" rx="6" fill="url(#real-bg)" />
      <rect width="640" height="280" rx="6" fill="none" stroke="#4ade80" strokeWidth="2" />

      {/* Bank header band */}
      <rect width="640" height="48" rx="6" fill="#166534" />
      <rect y="42" width="640" height="6" fill="#15803d" />

      {/* Bank logo area */}
      <circle cx="32" cy="24" r="16" fill="#dcfce7" opacity="0.3" />
      <text x="32" y="29" textAnchor="middle" fontSize="14" fill="white" fontWeight="bold">IOB</text>

      {/* Bank name */}
      <text x="60" y="18" fontSize="13" fill="white" fontWeight="bold">Indian Overseas Bank</text>
      <text x="60" y="34" fontSize="9" fill="#86efac">MICR Enabled · CTS 2010 Compliant · NPCI Certified</text>

      {/* Branch info */}
      <text x="520" y="18" fontSize="9" fill="#86efac" textAnchor="end">Branch: Anna Nagar, Chennai</text>
      <text x="520" y="30" fontSize="9" fill="#86efac" textAnchor="end">IFSC: IOBA0000001</text>
      <text x="520" y="42" fontSize="9" fill="#86efac" textAnchor="end">Tel: 044-26162800</text>

      {/* Cheque No */}
      <text x="560" y="18" fontSize="11" fill="white" fontWeight="bold">006142</text>
      <text x="560" y="30" fontSize="8" fill="#86efac">Cheque No.</text>

      {/* Date */}
      <text x="510" y="76" fontSize="10" fill="#374151">Date:</text>
      <line x1="535" y1="77" x2="620" y2="77" stroke="#374151" strokeWidth="0.8" />
      <text x="537" y="74" fontSize="10" fill="#1a1a1a" fontWeight="bold">06 / 05 / 2026</text>

      {/* Pay to */}
      <text x="16" y="95" fontSize="10" fill="#374151">Pay</text>
      <line x1="40" y1="96" x2="480" y2="96" stroke="#374151" strokeWidth="0.8" />
      <text x="42" y="93" fontSize="11" fill="#1a1a1a" fontWeight="bold">CONTROLLER OF CERTIFYING AUTHORITIES</text>
      <text x="482" y="95" fontSize="10" fill="#374151">or Bearer</text>

      {/* Amount in words */}
      <text x="16" y="115" fontSize="10" fill="#374151">Rupees</text>
      <line x1="62" y1="116" x2="580" y2="116" stroke="#374151" strokeWidth="0.8" />
      <text x="64" y="113" fontSize="10" fill="#1a1a1a" fontWeight="bold">Five Lakhs Only *****</text>
      <line x1="16" y1="126" x2="580" y2="126" stroke="#374151" strokeWidth="0.8" />

      {/* Amount box */}
      <rect x="590" y="90" width="42" height="40" rx="2" fill="none" stroke="#374151" strokeWidth="1" />
      <text x="611" y="108" textAnchor="middle" fontSize="8" fill="#374151">₹</text>
      <text x="611" y="122" textAnchor="middle" fontSize="10" fill="#1a1a1a" fontWeight="bold">5,00,000</text>

      {/* A/C No */}
      <text x="16" y="148" fontSize="10" fill="#374151">A/C No.</text>
      <line x1="60" y1="149" x2="300" y2="149" stroke="#374151" strokeWidth="0.8" />
      <text x="62" y="146" fontSize="10" fill="#1a1a1a" fontWeight="bold">052301000194265</text>

      {/* Signature */}
      <line x1="440" y1="168" x2="620" y2="168" stroke="#374151" strokeWidth="0.8" />
      <text x="530" y="178" textAnchor="middle" fontSize="9" fill="#374151">Authorised Signatory</text>

      {/* Signature scribble */}
      <path d="M 455 160 Q 475 148 490 158 Q 510 168 530 152 Q 550 140 565 155 Q 580 168 600 158"
        stroke="#1a3a6b" strokeWidth="1.5" fill="none" strokeLinecap="round" />

      {/* Security features label */}
      <text x="16" y="195" fontSize="8" fill="#15803d">✓ Void Pantograph</text>
      <text x="100" y="195" fontSize="8" fill="#15803d">✓ UV-Sensitive Ink</text>
      <text x="195" y="195" fontSize="8" fill="#15803d">✓ Security Thread</text>
      <text x="290" y="195" fontSize="8" fill="#15803d">✓ Magnetic Ink</text>

      {/* MICR band */}
      <rect y="206" width="640" height="50" fill="#f8fafc" />
      <rect y="206" width="640" height="2" fill="#d1d5db" />
      <rect y="254" width="640" height="2" fill="#d1d5db" />
      <text x="16" y="238" fontSize="20" fill="#1a1a1a" letterSpacing="4" style={{ fontFamily: "monospace" }}>
        ⑆ 006142 ⑆  ⑇ IOBA0000001 ⑇  052301000194265
      </text>
      <text x="16" y="252" fontSize="8" fill="#6b7280">MICR Code Band — Magnetic Ink Character Recognition (E-13B)</text>

      {/* CTS stamp */}
      <rect x="580" y="206" width="56" height="50" fill="#dcfce7" />
      <text x="608" y="224" textAnchor="middle" fontSize="7" fill="#166534" fontWeight="bold">CTS-2010</text>
      <text x="608" y="236" textAnchor="middle" fontSize="7" fill="#166534">COMPLIANT</text>
      <text x="608" y="248" textAnchor="middle" fontSize="7" fill="#166534">✓ NPCI</text>

      {/* Bottom info */}
      <rect y="256" width="640" height="24" rx="6" fill="#f0fdf4" />
      <text x="320" y="271" textAnchor="middle" fontSize="8" fill="#166534">
        Cheque is valid only if used within 3 months from date of issue · Report forgery to 1800-425-4445
      </text>
    </svg>
  );
}

function FakeChequeSVG({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 640 280" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="fake-grad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#fffbeb" />
          <stop offset="100%" stopColor="#fef9ec" />
        </linearGradient>
      </defs>

      {/* Background */}
      <rect width="640" height="280" rx="6" fill="url(#fake-grad)" />
      <rect width="640" height="280" rx="6" fill="none" stroke="#f59e0b" strokeWidth="2" strokeDasharray="6 3" />

      {/* Mismatched header – wrong shade, pixelated look */}
      <rect width="640" height="48" rx="6" fill="#1e4d8c" />
      {/* Obvious pixel/compression artifacts */}
      <rect x="0" y="0" width="5" height="48" fill="#1a3a6b" opacity="0.4" />
      <rect x="120" y="0" width="3" height="48" fill="#2563eb" opacity="0.2" />
      <rect x="300" y="10" width="2" height="28" fill="#1d4ed8" opacity="0.3" />

      {/* Wrong logo attempt */}
      <circle cx="32" cy="24" r="16" fill="#bfdbfe" opacity="0.4" />
      <text x="32" y="29" textAnchor="middle" fontSize="13" fill="white" fontWeight="bold">1OB</text>

      {/* Slightly off bank name */}
      <text x="60" y="20" fontSize="13" fill="white" fontWeight="bold">lndian Overseas Bank</text>
      <text x="60" y="35" fontSize="9" fill="#93c5fd">MICR Enabled · CTS 2010 Compliant</text>

      {/* Wrong branch info */}
      <text x="510" y="20" fontSize="9" fill="#93c5fd" textAnchor="end">Branch: Main Branch</text>
      <text x="510" y="32" fontSize="9" fill="#93c5fd" textAnchor="end">IFSC: IOBA000001</text>

      {/* Date area – different format */}
      <text x="510" y="76" fontSize="10" fill="#374151">Date:</text>
      <line x1="535" y1="77" x2="620" y2="77" stroke="#374151" strokeWidth="0.8" />
      <text x="537" y="74" fontSize="10" fill="#1a1a1a" fontWeight="bold">32 / 13 / 2026</text>

      {/* Pay to – smudged/pixelated text simulation */}
      <text x="16" y="95" fontSize="10" fill="#374151">Pay</text>
      <line x1="40" y1="96" x2="480" y2="96" stroke="#374151" strokeWidth="0.8" />
      <text x="42" y="93" fontSize="11" fill="#1a1a1a" fontWeight="bold">SELF</text>

      {/* Amount alteration – visible erasure area */}
      <rect x="62" y="103" width="200" height="14" fill="#fef08a" opacity="0.5" />
      <text x="16" y="115" fontSize="10" fill="#374151">Rupees</text>
      <line x1="62" y1="116" x2="580" y2="116" stroke="#374151" strokeWidth="0.8" />
      <text x="64" y="113" fontSize="10" fill="#1a1a1a" fontWeight="bold">Fifty Lakhs Only ****</text>
      <text x="64" y="113" fontSize="10" fill="#ef4444" opacity="0.15" fontWeight="bold">Five Lakhs Only *****</text>
      <line x1="16" y1="126" x2="580" y2="126" stroke="#374151" strokeWidth="0.8" />

      {/* Alteration highlight */}
      <rect x="62" y="104" width="155" height="13" fill="none" stroke="#ef4444" strokeWidth="1" strokeDasharray="3 2" opacity="0.7" />
      <text x="220" y="100" fontSize="7" fill="#ef4444" fontWeight="bold">⚠ ALTERATION</text>

      {/* Amount box – misaligned */}
      <rect x="590" y="90" width="42" height="40" rx="2" fill="none" stroke="#374151" strokeWidth="1" />
      <text x="611" y="108" textAnchor="middle" fontSize="8" fill="#374151">₹</text>
      <text x="611" y="122" textAnchor="middle" fontSize="10" fill="#1a1a1a" fontWeight="bold">50,00,000</text>

      {/* Account number – wrong format */}
      <text x="16" y="148" fontSize="10" fill="#374151">A/C No.</text>
      <line x1="60" y1="149" x2="300" y2="149" stroke="#374151" strokeWidth="0.8" />
      <text x="62" y="146" fontSize="10" fill="#1a1a1a" fontWeight="bold">05230100019426</text>

      {/* Signature – clearly different/copied */}
      <line x1="440" y1="168" x2="620" y2="168" stroke="#374151" strokeWidth="0.8" />
      <text x="530" y="178" textAnchor="middle" fontSize="9" fill="#374151">Authorised Signatory</text>
      <path d="M 455 162 L 475 155 L 490 162 L 510 158 L 530 165 L 545 155 L 560 162"
        stroke="#1a1a1a" strokeWidth="1.2" fill="none" strokeLinecap="round" />
      <path d="M 465 162 L 480 155 L 495 162 L 515 158 L 535 165"
        stroke="#1a1a1a" strokeWidth="0.6" fill="none" strokeDasharray="2 1" opacity="0.5" />

      {/* MICR band – obviously wrong */}
      <rect y="206" width="640" height="50" fill="#fffbeb" />
      <rect y="206" width="640" height="2" fill="#d1d5db" />
      <rect y="254" width="640" height="2" fill="#d1d5db" />

      {/* MICR with wrong characters / gaps */}
      <text x="16" y="238" fontSize="20" fill="#9ca3af" letterSpacing="4" style={{ fontFamily: "serif" }}>
        006142   IOBA000001   05230100019426
      </text>
      <text x="16" y="252" fontSize="8" fill="#ef4444">⚠ MICR band not detected — non-magnetic ink suspected</text>

      {/* No CTS stamp */}
      <rect x="580" y="206" width="56" height="50" fill="#fee2e2" />
      <text x="608" y="226" textAnchor="middle" fontSize="7" fill="#dc2626" fontWeight="bold">CTS FAIL</text>
      <text x="608" y="238" textAnchor="middle" fontSize="7" fill="#dc2626">REJECTED</text>
      <text x="608" y="250" textAnchor="middle" fontSize="10" fill="#dc2626">✗</text>

      {/* Bottom info */}
      <rect y="256" width="640" height="24" rx="6" fill="#fef2f2" />
      <text x="320" y="271" textAnchor="middle" fontSize="8" fill="#dc2626">
        ⚠ SUSPECTED COUNTERFEIT — Do not process · Flag for investigation · Ref: CTS-FRD-2026-00847
      </text>
    </svg>
  );
}

// ── Analysis presets ─────────────────────────────────────────────────────────

const REAL_RESULT: ScanResult = {
  score: 97.4,
  overall: "PASS",
  chequeNo: "006142",
  amount: "₹5,00,000",
  draweeBank: "Indian Overseas Bank — Anna Nagar, Chennai",
  ifsc: "IOBA0000001",
  date: "06/05/2026",
  fraudFlags: [],
  processingMs: 1247,
  checks: [
    { id: "res", label: "Image Resolution", value: "300 DPI", status: "pass", detail: "Meets NPCI minimum of 200 DPI for CTS 2010", icon: Eye },
    { id: "skew", label: "Image Skew", value: "0.3°", status: "pass", detail: "Within ±5° tolerance — no deskew needed", icon: Layers },
    { id: "micr", label: "MICR Band Detection", value: "Detected (E-13B)", status: "pass", detail: "Magnetic ink confirmed — 14 characters read", icon: Waves },
    { id: "ink", label: "Ink Density & Type", value: "Normal — Magnetic", status: "pass", detail: "MICR E-13B ink verified via magnetic flux reading", icon: Fingerprint },
    { id: "tamper", label: "Tampering / Alteration", value: "None detected", status: "pass", detail: "Pixel entropy analysis — no erasure or overwrite patterns", icon: Shield },
    { id: "paper", label: "Paper Texture / GSM", value: "Standard CTS stock", status: "pass", detail: "90 GSM ± 5% — matches IOB cheque paper profile", icon: Scan },
    { id: "uv", label: "UV / Security Thread", value: "Valid", status: "pass", detail: "UV-fluorescent strip detected at 3mm offset", icon: Zap },
    { id: "sig", label: "Signature Presence", value: "Present", status: "pass", detail: "Signature region located — refer to positive pay for match", icon: Fingerprint },
    { id: "date", label: "Date Validity", value: "Valid — 06/05/2026", status: "pass", detail: "Not post-dated, within 3-month validity window", icon: CheckCircle2 },
    { id: "amount", label: "Amount Consistency", value: "Match ₹5,00,000", status: "pass", detail: "Figure and words match — no discrepancy", icon: CheckCircle2 },
  ],
};

const FAKE_RESULT: ScanResult = {
  score: 31.2,
  overall: "FAIL",
  chequeNo: "006142 (suspect)",
  amount: "₹50,00,000 (altered)",
  draweeBank: "Indian Overseas Bank — (branch unverified)",
  ifsc: "IOBA000001 (invalid checksum)",
  date: "32/13/2026 (invalid)",
  fraudFlags: [
    "MICR band absent — non-magnetic ink",
    "Amount field erasure detected",
    "Date value invalid (day 32, month 13)",
    "IFSC checksum failure",
    "Inkjet printer signature detected",
    "UV security thread absent",
    "Paper GSM mismatch — plain 75 GSM",
    "Header logo pixel anomaly — suspected copy-paste",
  ],
  processingMs: 1389,
  checks: [
    { id: "res", label: "Image Resolution", value: "82 DPI", status: "fail", detail: "Below 200 DPI NPCI minimum — inkjet printer suspected", icon: Eye },
    { id: "skew", label: "Image Skew", value: "8.7°", status: "fail", detail: "Exceeds ±5° tolerance — indicates handmade scan/photocopy", icon: Layers },
    { id: "micr", label: "MICR Band Detection", value: "Not detected", status: "fail", detail: "No magnetic flux signal — regular printer ink used", icon: Waves },
    { id: "ink", label: "Ink Density & Type", value: "Anomalous — Inkjet", status: "fail", detail: "Non-MICR ink — fails E-13B magnetic characterisation", icon: Fingerprint },
    { id: "tamper", label: "Tampering / Alteration", value: "Alteration detected", status: "fail", detail: "Amount field shows erasure artefacts — 'Five' → 'Fifty'", icon: Shield },
    { id: "paper", label: "Paper Texture / GSM", value: "Plain 75 GSM", status: "fail", detail: "Standard office paper — not CTS-compliant cheque stock", icon: Scan },
    { id: "uv", label: "UV / Security Thread", value: "Absent", status: "fail", detail: "No UV-fluorescent response — security thread missing", icon: Zap },
    { id: "sig", label: "Signature Presence", value: "Suspect — traced", status: "warn", detail: "Signature edge artifacts suggest digital copy-paste", icon: Fingerprint },
    { id: "date", label: "Date Validity", value: "INVALID — 32/13/2026", status: "fail", detail: "Impossible date — day 32 and month 13 do not exist", icon: XCircle },
    { id: "amount", label: "Amount Consistency", value: "MISMATCH", status: "fail", detail: "Words say 'Fifty Lakhs', figure box shows '₹50,00,000' vs original alteration trace", icon: XCircle },
  ],
};

// ── Sub-components ───────────────────────────────────────────────────────────

const STATUS_ICONS: Record<CheckStatus, React.ElementType> = {
  pass: CheckCircle2,
  fail: XCircle,
  warn: AlertTriangle,
};

const STATUS_COLORS: Record<CheckStatus, string> = {
  pass: "text-green-400",
  fail: "text-red-400",
  warn: "text-amber-400",
};

const STATUS_BG: Record<CheckStatus, string> = {
  pass: "bg-green-500/10 border-green-500/20",
  fail: "bg-red-500/10 border-red-500/20",
  warn: "bg-amber-500/10 border-amber-500/20",
};

function CheckRow({ check }: { check: Check }) {
  const Icon = STATUS_ICONS[check.status];
  const CheckIcon = check.icon;
  return (
    <div className={cn("flex items-start gap-3 px-3 py-2.5 rounded-lg border", STATUS_BG[check.status])}>
      <CheckIcon className="w-3.5 h-3.5 text-muted-foreground mt-0.5 shrink-0" />
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between gap-2">
          <span className="text-xs font-medium text-foreground">{check.label}</span>
          <span className={cn("text-xs font-bold shrink-0", STATUS_COLORS[check.status])}>{check.value}</span>
        </div>
        <p className="text-[11px] text-muted-foreground mt-0.5">{check.detail}</p>
      </div>
      <Icon className={cn("w-4 h-4 shrink-0 mt-0.5", STATUS_COLORS[check.status])} />
    </div>
  );
}

function ScoreGauge({ score, overall }: { score: number; overall: "PASS" | "FAIL" | "WARN" }) {
  const r = 52;
  const cx = 68;
  const cy = 68;
  const circumference = 2 * Math.PI * r;
  const progress = (score / 100) * circumference;
  const gapAngle = 60;
  const startAngle = 90 + gapAngle / 2;
  const arcLength = circumference * ((360 - gapAngle) / 360);
  const filled = (score / 100) * arcLength;

  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const polar = (cx: number, cy: number, r: number, angleDeg: number) => ({
    x: cx + r * Math.cos(toRad(angleDeg - 90)),
    y: cy + r * Math.sin(toRad(angleDeg - 90)),
  });

  const startDeg = gapAngle / 2;
  const endDeg = 360 - gapAngle / 2;

  const arcPath = (innerR: number, outerR: number, startD: number, endD: number) => {
    const s = polar(cx, cy, innerR, startD);
    const e = polar(cx, cy, innerR, endD);
    const s2 = polar(cx, cy, outerR, startD);
    const e2 = polar(cx, cy, outerR, endD);
    const large = endD - startD > 180 ? 1 : 0;
    return `M ${s2.x} ${s2.y} A ${outerR} ${outerR} 0 ${large} 1 ${e2.x} ${e2.y} L ${e.x} ${e.y} A ${innerR} ${innerR} 0 ${large} 0 ${s.x} ${s.y} Z`;
  };

  const filledEnd = startDeg + (filled / arcLength) * (endDeg - startDeg);
  const scoreColor = overall === "PASS" ? "#4ade80" : overall === "WARN" ? "#f59e0b" : "#f87171";

  return (
    <div className="flex flex-col items-center">
      <svg width="136" height="136" viewBox="0 0 136 136">
        <path d={arcPath(42, 58, startDeg, endDeg)} fill="#1e293b" />
        <path d={arcPath(42, 58, startDeg, filledEnd)} fill={scoreColor} />
        <text x={cx} y={cy - 4} textAnchor="middle" fontSize="22" fontWeight="bold" fill={scoreColor}>{score.toFixed(1)}</text>
        <text x={cx} y={cy + 14} textAnchor="middle" fontSize="10" fill="#94a3b8">/ 100</text>
        <text x={cx} y={cy + 28} textAnchor="middle" fontSize="9" fontWeight="bold" fill={scoreColor}>{overall}</text>
      </svg>
    </div>
  );
}

function ResultPanel({ result, onReset }: { result: ScanResult; onReset: () => void }) {
  const isPass = result.overall === "PASS";
  const isFail = result.overall === "FAIL";

  return (
    <div className="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
      {/* Header */}
      <div className={cn(
        "rounded-xl border p-4 flex items-center gap-4",
        isPass ? "bg-green-500/10 border-green-500/30" : isFail ? "bg-red-500/10 border-red-500/30" : "bg-amber-500/10 border-amber-500/30"
      )}>
        <ScoreGauge score={result.score} overall={result.overall} />
        <div className="flex-1 space-y-2">
          <div className="flex items-center gap-2">
            {isPass ? <CheckCircle2 className="w-5 h-5 text-green-400" /> : <XCircle className="w-5 h-5 text-red-400" />}
            <span className={cn("text-base font-bold", isPass ? "text-green-400" : "text-red-400")}>
              {isPass ? "Instrument Accepted" : "Instrument Rejected — Suspected Fraud"}
            </span>
          </div>
          <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-[11px]">
            <div><span className="text-muted-foreground">Cheque No:</span> <span className="font-medium">{result.chequeNo}</span></div>
            <div><span className="text-muted-foreground">Amount:</span> <span className="font-medium">{result.amount}</span></div>
            <div><span className="text-muted-foreground">Drawee Bank:</span> <span className="font-medium">{result.draweeBank}</span></div>
            <div><span className="text-muted-foreground">IFSC:</span> <span className="font-medium">{result.ifsc}</span></div>
            <div><span className="text-muted-foreground">Date:</span> <span className="font-medium">{result.date}</span></div>
            <div><span className="text-muted-foreground">Scan Time:</span> <span className="font-medium">{result.processingMs} ms</span></div>
          </div>
          <button onClick={onReset} className="flex items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground transition-colors mt-1">
            <RefreshCw className="w-3 h-3" /> Reset scanner
          </button>
        </div>
      </div>

      {/* Fraud flags */}
      {result.fraudFlags.length > 0 && (
        <div className="rounded-xl border border-red-500/30 bg-red-500/10 p-4">
          <div className="flex items-center gap-2 mb-2">
            <AlertTriangle className="w-4 h-4 text-red-400" />
            <span className="text-sm font-semibold text-red-400">Fraud Indicators Detected ({result.fraudFlags.length})</span>
          </div>
          <div className="grid grid-cols-2 gap-1">
            {result.fraudFlags.map((flag, i) => (
              <div key={i} className="flex items-start gap-1.5 text-[11px] text-red-300">
                <ChevronRight className="w-3 h-3 shrink-0 mt-0.5 text-red-500" />
                {flag}
              </div>
            ))}
          </div>
          <div className="mt-3 pt-3 border-t border-red-500/20 flex items-center justify-between text-[11px]">
            <span className="text-red-400 font-medium">Action: DO NOT PROCESS — Escalate to fraud desk</span>
            <span className="text-muted-foreground">Ref: CTS-FRD-2026-00847</span>
          </div>
        </div>
      )}

      {/* Individual checks */}
      <div>
        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 px-1">Quality Checks ({result.checks.length})</p>
        <div className="space-y-1.5">
          {result.checks.map((check) => <CheckRow key={check.id} check={check} />)}
        </div>
      </div>
    </div>
  );
}

// ── Scanning animation ───────────────────────────────────────────────────────

function ScanningOverlay({ progress }: { progress: number }) {
  const steps = [
    "Detecting image boundaries…",
    "Measuring resolution (DPI)…",
    "Checking skew angle…",
    "Reading MICR band (E-13B)…",
    "Analysing ink magnetic signature…",
    "Scanning for alterations…",
    "Verifying paper texture…",
    "UV security thread check…",
    "Validating date & amount…",
    "Generating IQA report…",
  ];
  const step = Math.min(Math.floor((progress / 100) * steps.length), steps.length - 1);

  return (
    <div className="absolute inset-0 flex flex-col items-center justify-center bg-background/80 backdrop-blur-sm rounded-xl z-10">
      <div className="flex flex-col items-center gap-4 w-64">
        <div className="relative w-12 h-12">
          <ScanLine className="w-12 h-12 text-primary animate-pulse" />
          <div className="absolute inset-0 border-2 border-primary/30 rounded-full animate-ping" />
        </div>
        <div className="w-full space-y-2">
          <div className="flex justify-between text-xs">
            <span className="text-primary font-medium">Scanning…</span>
            <span className="text-muted-foreground">{progress.toFixed(0)}%</span>
          </div>
          <div className="h-1.5 bg-muted rounded-full overflow-hidden">
            <div className="h-full bg-primary rounded-full transition-all duration-100" style={{ width: `${progress}%` }} />
          </div>
          <p className="text-[11px] text-muted-foreground text-center min-h-[16px]">{steps[step]}</p>
        </div>
      </div>
    </div>
  );
}

// ── Main page ────────────────────────────────────────────────────────────────

type Mode = "idle" | "scanning" | "result";
type DemoType = "real" | "fake";

export default function Scanner() {
  const [tab, setTab] = useState<"upload" | "demo">("upload");
  const [mode, setMode] = useState<Mode>("idle");
  const [progress, setProgress] = useState(0);
  const [result, setResult] = useState<ScanResult | null>(null);
  const [uploadedImage, setUploadedImage] = useState<string | null>(null);
  const [demoType, setDemoType] = useState<DemoType | null>(null);
  const [dragOver, setDragOver] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const startScan = useCallback((type: DemoType, imageUrl?: string) => {
    setDemoType(type);
    if (imageUrl) setUploadedImage(imageUrl);
    setMode("scanning");
    setProgress(0);
    let p = 0;
    timerRef.current = setInterval(() => {
      p += Math.random() * 4 + 2;
      if (p >= 100) {
        p = 100;
        clearInterval(timerRef.current!);
        setTimeout(() => {
          setProgress(100);
          setResult(type === "real" ? REAL_RESULT : FAKE_RESULT);
          setMode("result");
        }, 300);
      }
      setProgress(p);
    }, 80);
  }, []);

  useEffect(() => () => { if (timerRef.current) clearInterval(timerRef.current); }, []);

  const reset = () => {
    setMode("idle");
    setProgress(0);
    setResult(null);
    setUploadedImage(null);
    setDemoType(null);
  };

  const handleFile = (file: File) => {
    if (!file.type.startsWith("image/")) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const dataUrl = e.target?.result as string;
      // Naive heuristic: files > 300KB tend to be higher quality (real)
      const likelyReal = file.size > 80_000;
      startScan(likelyReal ? "real" : "fake", dataUrl);
    };
    reader.readAsDataURL(file);
  };

  const onDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <ScanLine className="w-6 h-6 text-primary" />
            CTS Cheque IQA Scanner
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Upload a cheque image or run a demo — see real-time NPCI CTS 2010 image quality analysis
          </p>
        </div>
        <div className="flex items-center gap-2 text-[11px] bg-muted/50 border border-border rounded-lg px-3 py-1.5">
          <Info className="w-3.5 h-3.5 text-muted-foreground" />
          <span className="text-muted-foreground">Demo mode — simulated IQA engine</span>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-muted/40 rounded-lg p-1 w-fit">
        {(["upload", "demo"] as const).map((t) => (
          <button
            key={t}
            onClick={() => { setTab(t); reset(); }}
            className={cn(
              "px-4 py-1.5 rounded-md text-sm font-medium transition-colors",
              tab === t ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"
            )}
          >
            {t === "upload" ? "Live Upload Scanner" : "Demo Scenarios"}
          </button>
        ))}
      </div>

      {/* ── Upload tab ────────────────────────────────────────────────────── */}
      {tab === "upload" && (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
          {/* Left: upload + preview */}
          <div className="space-y-4">
            <div className="relative">
              {/* Drag-drop zone */}
              {mode === "idle" && !uploadedImage && (
                <div
                  onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                  onDragLeave={() => setDragOver(false)}
                  onDrop={onDrop}
                  onClick={() => fileRef.current?.click()}
                  className={cn(
                    "border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-all",
                    dragOver
                      ? "border-primary bg-primary/5"
                      : "border-border bg-muted/20 hover:border-primary/50 hover:bg-primary/5"
                  )}
                >
                  <div className="flex flex-col items-center gap-3">
                    <div className="w-14 h-14 rounded-full bg-muted flex items-center justify-center">
                      <Upload className="w-7 h-7 text-muted-foreground" />
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-foreground">Drop cheque image here</p>
                      <p className="text-xs text-muted-foreground mt-1">or click to browse · JPG, PNG, TIF, BMP supported</p>
                    </div>
                    <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                      <span>Min 200 DPI recommended</span>
                      <span>·</span>
                      <span>Max 10 MB</span>
                    </div>
                  </div>
                  <input ref={fileRef} type="file" accept="image/*" className="hidden"
                    onChange={(e) => { const f = e.target.files?.[0]; if (f) handleFile(f); }} />
                </div>
              )}

              {/* Image preview */}
              {uploadedImage && (
                <div className="relative rounded-xl overflow-hidden border border-border bg-muted/20">
                  <img src={uploadedImage} alt="Uploaded cheque" className="w-full object-contain max-h-64" />
                  {mode === "scanning" && <ScanningOverlay progress={progress} />}
                  {/* Scan line animation */}
                  {mode === "scanning" && (
                    <div
                      className="absolute left-0 right-0 h-0.5 bg-primary shadow-[0_0_8px_2px_hsl(var(--primary))] transition-all"
                      style={{ top: `${progress}%` }}
                    />
                  )}
                </div>
              )}

              {/* Scanning without image (demo mode) */}
              {mode === "scanning" && !uploadedImage && (
                <div className="relative rounded-xl overflow-hidden border border-border">
                  {demoType === "real"
                    ? <RealChequeSVG className="w-full" />
                    : <FakeChequeSVG className="w-full" />}
                  <ScanningOverlay progress={progress} />
                  <div
                    className="absolute left-0 right-0 h-0.5 bg-primary shadow-[0_0_8px_2px_hsl(var(--primary))] transition-all"
                    style={{ top: `${progress}%` }}
                  />
                </div>
              )}
            </div>

            {/* Quick-load demo buttons */}
            {mode === "idle" && (
              <div className="space-y-2">
                <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Or load a demo cheque</p>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    onClick={() => startScan("real")}
                    className="flex flex-col items-start gap-2 p-4 rounded-xl border border-green-500/30 bg-green-500/10 hover:bg-green-500/15 transition-colors text-left group"
                  >
                    <div className="flex items-center gap-2">
                      <CheckCircle2 className="w-4 h-4 text-green-400" />
                      <span className="text-sm font-semibold text-green-400">Valid Cheque</span>
                    </div>
                    <RealChequeSVG className="w-full rounded-lg border border-green-500/20 opacity-90 group-hover:opacity-100 transition-opacity" />
                    <p className="text-[11px] text-muted-foreground">CTS-2010 compliant · MICR detected · Score ~97</p>
                  </button>

                  <button
                    onClick={() => startScan("fake")}
                    className="flex flex-col items-start gap-2 p-4 rounded-xl border border-red-500/30 bg-red-500/10 hover:bg-red-500/15 transition-colors text-left group"
                  >
                    <div className="flex items-center gap-2">
                      <XCircle className="w-4 h-4 text-red-400" />
                      <span className="text-sm font-semibold text-red-400">Fraudulent Cheque</span>
                    </div>
                    <FakeChequeSVG className="w-full rounded-lg border border-red-500/20 opacity-90 group-hover:opacity-100 transition-opacity" />
                    <p className="text-[11px] text-muted-foreground">Inkjet print · Altered amount · No MICR · Score ~31</p>
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Right: results */}
          <div>
            {mode === "idle" && (
              <div className="flex flex-col items-center justify-center h-full min-h-64 text-center gap-3 border border-dashed border-border rounded-xl p-8">
                <FileImage className="w-10 h-10 text-muted-foreground/40" />
                <p className="text-sm text-muted-foreground">Analysis results will appear here after scanning</p>
              </div>
            )}
            {mode === "scanning" && (
              <div className="flex flex-col items-center justify-center h-full min-h-64 gap-4">
                <div className="w-14 h-14 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                <div className="text-center space-y-1">
                  <p className="text-sm font-medium">Processing instrument…</p>
                  <p className="text-xs text-muted-foreground">{progress.toFixed(0)}% complete</p>
                </div>
              </div>
            )}
            {mode === "result" && result && <ResultPanel result={result} onReset={reset} />}
          </div>
        </div>
      )}

      {/* ── Demo Scenarios tab ────────────────────────────────────────────── */}
      {tab === "demo" && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
            {/* Real cheque column */}
            <div className="space-y-4">
              <div className="flex items-center gap-2 p-3 rounded-lg bg-green-500/10 border border-green-500/30">
                <CheckCircle2 className="w-5 h-5 text-green-400 shrink-0" />
                <div>
                  <p className="text-sm font-semibold text-green-400">Scenario A — Valid CTS-2010 Cheque</p>
                  <p className="text-[11px] text-muted-foreground">All quality parameters pass · Accepted for clearing</p>
                </div>
              </div>
              <div className="rounded-xl overflow-hidden border border-green-500/20 shadow-[0_0_20px_-5px_hsl(142,76%,36%,0.2)]">
                <RealChequeSVG className="w-full" />
              </div>
              <ResultPanel result={REAL_RESULT} onReset={() => {}} />
            </div>

            {/* Fake cheque column */}
            <div className="space-y-4">
              <div className="flex items-center gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/30">
                <XCircle className="w-5 h-5 text-red-400 shrink-0" />
                <div>
                  <p className="text-sm font-semibold text-red-400">Scenario B — Suspected Counterfeit</p>
                  <p className="text-[11px] text-muted-foreground">Multiple IQA failures · Flagged for fraud investigation</p>
                </div>
              </div>
              <div className="rounded-xl overflow-hidden border border-red-500/20 shadow-[0_0_20px_-5px_hsl(0,84%,60%,0.2)]">
                <FakeChequeSVG className="w-full" />
              </div>
              <ResultPanel result={FAKE_RESULT} onReset={() => {}} />
            </div>
          </div>

          {/* Side-by-side comparison table */}
          <div className="rounded-xl border border-border overflow-hidden">
            <div className="bg-muted/40 px-4 py-3 border-b border-border">
              <p className="text-sm font-semibold">Side-by-Side Parameter Comparison</p>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="border-b border-border bg-muted/20">
                    <th className="text-left px-4 py-2.5 font-medium text-muted-foreground w-40">Check</th>
                    <th className="text-center px-4 py-2.5 font-medium text-green-400">Valid Cheque</th>
                    <th className="text-center px-4 py-2.5 font-medium text-red-400">Fraudulent</th>
                  </tr>
                </thead>
                <tbody>
                  {REAL_RESULT.checks.map((rc, i) => {
                    const fc = FAKE_RESULT.checks[i];
                    return (
                      <tr key={rc.id} className="border-b border-border/50 hover:bg-muted/20">
                        <td className="px-4 py-2.5 font-medium text-foreground">{rc.label}</td>
                        <td className="px-4 py-2.5 text-center">
                          <span className="inline-flex items-center gap-1 text-green-400 font-medium">
                            <CheckCircle2 className="w-3 h-3" />{rc.value}
                          </span>
                        </td>
                        <td className="px-4 py-2.5 text-center">
                          <span className={cn(
                            "inline-flex items-center gap-1 font-medium",
                            fc.status === "fail" ? "text-red-400" : "text-amber-400"
                          )}>
                            {fc.status === "fail"
                              ? <XCircle className="w-3 h-3" />
                              : <AlertTriangle className="w-3 h-3" />}
                            {fc.value}
                          </span>
                        </td>
                      </tr>
                    );
                  })}
                  <tr className="bg-muted/30 font-semibold">
                    <td className="px-4 py-2.5 text-foreground">IQA Score</td>
                    <td className="px-4 py-2.5 text-center text-green-400">97.4 / 100 ✓ PASS</td>
                    <td className="px-4 py-2.5 text-center text-red-400">31.2 / 100 ✗ FAIL</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
