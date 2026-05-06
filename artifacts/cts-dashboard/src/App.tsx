import { Switch, Route, Router as WouterRouter, Link, useLocation } from "wouter";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { useGetBcpStatus } from "@workspace/api-client-react";
import {
  LayoutDashboard,
  FileText,
  Package,
  CalendarClock,
  ShieldAlert,
  RotateCcw,
  Server,
  ClipboardList,
  Activity,
  ChevronRight,
  BadgeCheck,
  ScanLine,
  Network,
  LogOut,
  User,
  Shield,
  ChevronDown,
  Microscope,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useState } from "react";
import { AuthProvider, useAuth } from "@/context/auth";
import { SessionTimeoutModal } from "@/components/session-timeout-modal";
import Login from "@/pages/login";
import Dashboard from "@/pages/dashboard";
import Instruments from "@/pages/instruments";
import Batches from "@/pages/batches";
import Sessions from "@/pages/sessions";
import Fraud from "@/pages/fraud";
import Returns from "@/pages/returns";
import BCP from "@/pages/bcp";
import Audit from "@/pages/audit";
import PositivePay from "@/pages/positive-pay";
import IQA from "@/pages/iqa";
import Nodal from "@/pages/nodal";
import Scanner from "@/pages/scanner";
import NotFound from "@/pages/not-found";

const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 30000, retry: 2 } },
});

const NAV = [
  { path: "/", label: "Operations Overview", icon: LayoutDashboard },
  { path: "/instruments", label: "Instruments", icon: FileText },
  { path: "/batches", label: "Clearing Batches", icon: Package },
  { path: "/sessions", label: "Sessions", icon: CalendarClock },
  { path: "/fraud", label: "Fraud Alerts", icon: ShieldAlert },
  { path: "/returns", label: "Returns", icon: RotateCcw },
  { path: "/bcp", label: "BCP / DR Status", icon: Server },
  { path: "/positive-pay", label: "Positive Pay", icon: BadgeCheck },
  { path: "/iqa", label: "Image Quality (IQA)", icon: ScanLine },
  { path: "/nodal", label: "Nodal Hub Ops", icon: Network },
  { path: "/scanner", label: "Cheque Scanner", icon: Microscope },
  { path: "/audit", label: "Audit Trail", icon: ClipboardList },
];

const ROLE_COLORS: Record<string, string> = {
  admin: "bg-red-500/20 text-red-300 border-red-500/30",
  supervisor: "bg-blue-500/20 text-blue-300 border-blue-500/30",
  fraud_officer: "bg-orange-500/20 text-orange-300 border-orange-500/30",
  branch_operator: "bg-green-500/20 text-green-300 border-green-500/30",
  auditor: "bg-purple-500/20 text-purple-300 border-purple-500/30",
  checker: "bg-cyan-500/20 text-cyan-300 border-cyan-500/30",
  bcp_officer: "bg-yellow-500/20 text-yellow-300 border-yellow-500/30",
};

function UserMenu() {
  const { user, logout, lastLoginInfo } = useAuth();
  const [open, setOpen] = useState(false);

  if (!user) return null;

  return (
    <div className="relative">
      <button
        onClick={() => setOpen((s) => !s)}
        className="w-full flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-sidebar-accent transition-colors"
      >
        <div className="w-8 h-8 rounded-full bg-primary/20 border border-primary/30 flex items-center justify-center shrink-0">
          <User className="w-4 h-4 text-primary" />
        </div>
        <div className="flex-1 min-w-0 text-left">
          <p className="text-xs font-semibold text-sidebar-foreground truncate">{user.name}</p>
          <p className="text-[10px] text-muted-foreground truncate">{user.emp_id} · {user.branch_code}</p>
        </div>
        <ChevronDown className={cn("w-3 h-3 text-muted-foreground transition-transform shrink-0", open && "rotate-180")} />
      </button>

      {open && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
          <div className="absolute bottom-full left-0 right-0 mb-1 z-20 rounded-lg bg-popover border border-border shadow-xl p-3 space-y-3">
            <div>
              <p className="text-xs font-bold text-foreground">{user.name}</p>
              <p className="text-[10px] text-muted-foreground">{user.email}</p>
              <div className="mt-1.5">
                <span className={cn("inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full border", ROLE_COLORS[user.role] ?? "bg-muted text-muted-foreground border-border")}>
                  <Shield className="w-2.5 h-2.5" />
                  {user.role_label}
                </span>
              </div>
            </div>

            <div className="text-[10px] text-muted-foreground space-y-1 border-t border-border pt-2">
              <div className="flex justify-between">
                <span>Department</span>
                <span className="text-foreground font-medium">{user.department}</span>
              </div>
              <div className="flex justify-between">
                <span>Branch</span>
                <span className="text-foreground font-medium">{user.branch_code}</span>
              </div>
              {lastLoginInfo && (
                <div className="flex justify-between">
                  <span>Last Login</span>
                  <span className="text-foreground font-medium">
                    {new Date(lastLoginInfo.time).toLocaleString("en-IN", { hour12: false, hour: "2-digit", minute: "2-digit", day: "2-digit", month: "short" })}
                  </span>
                </div>
              )}
            </div>

            <button
              onClick={() => { setOpen(false); logout(); }}
              className="w-full flex items-center gap-2 px-3 py-2 rounded-md bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 text-red-400 hover:text-red-300 text-xs font-medium transition-colors"
            >
              <LogOut className="w-3.5 h-3.5" />
              Sign Out
            </button>
          </div>
        </>
      )}
    </div>
  );
}

function Sidebar() {
  const [location] = useLocation();
  const { data: bcp } = useGetBcpStatus({ query: { refetchInterval: 30000 } });

  return (
    <aside className="w-64 shrink-0 flex flex-col bg-sidebar border-r border-sidebar-border h-screen sticky top-0 overflow-y-auto">
      <div className="px-4 py-5 border-b border-sidebar-border">
        <div className="flex items-center gap-2 mb-1">
          <div className="w-7 h-7 rounded bg-primary flex items-center justify-center shrink-0">
            <Activity className="w-4 h-4 text-primary-foreground" />
          </div>
          <span className="font-bold text-sidebar-foreground text-sm leading-tight tracking-tight">IOB CTS National Grid</span>
        </div>
        <p className="text-xs text-muted-foreground pl-9">Operations Dashboard</p>
      </div>

      <nav className="flex-1 px-2 py-3 space-y-0.5">
        {NAV.map(({ path, label, icon: Icon }) => {
          const active = path === "/" ? location === "/" : location.startsWith(path);
          return (
            <Link
              key={path}
              href={path}
              className={cn(
                "flex items-center gap-3 px-3 py-2 rounded text-sm font-medium transition-colors group",
                active
                  ? "bg-sidebar-primary text-sidebar-primary-foreground"
                  : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
              )}
            >
              <Icon className="w-4 h-4 shrink-0" />
              <span className="flex-1">{label}</span>
              {active && <ChevronRight className="w-3 h-3 opacity-60" />}
            </Link>
          );
        })}
      </nav>

      <div className="px-3 py-3 border-t border-sidebar-border space-y-3">
        <div className="rounded border border-sidebar-border bg-sidebar-accent/40 px-3 py-2 text-xs space-y-1">
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground">Active Node</span>
            <span className="font-semibold text-sidebar-foreground">{bcp?.active_node ?? "DC Chennai"}</span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground">DC Chennai</span>
            <span className={cn("font-semibold", bcp?.dc_status === "ONLINE" ? "text-green-500" : "text-destructive")}>
              {bcp?.dc_status ?? "ONLINE"}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground">DR Hyderabad</span>
            <span className={cn("font-semibold", bcp?.dr_status === "ONLINE" ? "text-green-500" : "text-amber-500")}>
              {bcp?.dr_status ?? "STANDBY"}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground">Uptime</span>
            <span className="font-semibold text-green-500">{bcp?.uptime_pct?.toFixed(2) ?? "99.97"}%</span>
          </div>
        </div>

        <UserMenu />

        <p className="text-[10px] text-muted-foreground text-center">GeM/2026/B/7367951 · CAPEX 5-yr</p>
      </div>
    </aside>
  );
}

function Layout() {
  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <main className="flex-1 min-w-0 overflow-y-auto">
        <Switch>
          <Route path="/" component={Dashboard} />
          <Route path="/instruments" component={Instruments} />
          <Route path="/batches" component={Batches} />
          <Route path="/sessions" component={Sessions} />
          <Route path="/fraud" component={Fraud} />
          <Route path="/returns" component={Returns} />
          <Route path="/bcp" component={BCP} />
          <Route path="/positive-pay" component={PositivePay} />
          <Route path="/iqa" component={IQA} />
          <Route path="/nodal" component={Nodal} />
          <Route path="/scanner" component={Scanner} />
          <Route path="/audit" component={Audit} />
          <Route component={NotFound} />
        </Switch>
      </main>
      <SessionTimeoutModal />
    </div>
  );
}

function AuthGate() {
  const { step, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen bg-[hsl(224,60%,10%)] flex items-center justify-center">
        <div className="flex flex-col items-center gap-4">
          <div className="w-10 h-10 border-2 border-[hsl(38,92%,55%)] border-t-transparent rounded-full animate-spin" />
          <p className="text-white/60 text-sm">Loading secure session…</p>
        </div>
      </div>
    );
  }

  if (step !== "authenticated") {
    return <Login />;
  }

  return <Layout />;
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <AuthProvider>
          <WouterRouter base={import.meta.env.BASE_URL.replace(/\/$/, "")}>
            <AuthGate />
          </WouterRouter>
          <Toaster />
        </AuthProvider>
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
