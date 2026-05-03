import { useAuth } from "@/context/auth";
import { Clock, ShieldAlert } from "lucide-react";

export function SessionTimeoutModal() {
  const { sessionWarning, idleSeconds, logout, dismissWarning } = useAuth();

  if (!sessionWarning) return null;

  const IDLE_TIMEOUT_S = 30 * 60;
  const remaining = Math.max(0, IDLE_TIMEOUT_S - idleSeconds);
  const minutes = Math.floor(remaining / 60);
  const seconds = remaining % 60;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" />
      <div className="relative bg-[hsl(224,60%,12%)] border border-amber-500/40 rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4">
        <div className="flex flex-col items-center text-center gap-4">
          <div className="w-14 h-14 rounded-full bg-amber-500/15 border-2 border-amber-500/40 flex items-center justify-center">
            <ShieldAlert className="w-7 h-7 text-amber-400" />
          </div>

          <div>
            <h2 className="text-white font-bold text-xl mb-1">Session Expiring Soon</h2>
            <p className="text-white/60 text-sm">
              Your session will expire due to inactivity. Any unsaved work may be lost.
            </p>
          </div>

          <div className="flex items-center gap-2 bg-amber-500/10 border border-amber-500/30 rounded-xl px-6 py-3">
            <Clock className="w-5 h-5 text-amber-400" />
            <span className="text-amber-300 font-mono text-2xl font-bold">
              {String(minutes).padStart(2, "0")}:{String(seconds).padStart(2, "0")}
            </span>
          </div>

          <div className="w-full flex flex-col gap-2 mt-2">
            <button
              onClick={dismissWarning}
              className="w-full bg-[hsl(38,92%,55%)] hover:bg-[hsl(38,92%,48%)] text-[hsl(224,60%,10%)] font-bold rounded-lg py-2.5 text-sm transition-colors"
            >
              Continue Session
            </button>
            <button
              onClick={logout}
              className="w-full bg-white/5 hover:bg-white/10 border border-white/15 text-white/70 hover:text-white font-medium rounded-lg py-2.5 text-sm transition-colors"
            >
              Sign Out Now
            </button>
          </div>

          <p className="text-white/30 text-xs">
            Per RBI policy, sessions auto-expire after 30 minutes of inactivity.
          </p>
        </div>
      </div>
    </div>
  );
}
