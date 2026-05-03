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
} from "lucide-react";
import { cn } from "@/lib/utils";
import Dashboard from "@/pages/dashboard";
import Instruments from "@/pages/instruments";
import Batches from "@/pages/batches";
import Sessions from "@/pages/sessions";
import Fraud from "@/pages/fraud";
import Returns from "@/pages/returns";
import BCP from "@/pages/bcp";
import Audit from "@/pages/audit";
import PositivePay from "@/pages/positive-pay";
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
  { path: "/audit", label: "Audit Trail", icon: ClipboardList },
];

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

      <div className="px-3 py-3 border-t border-sidebar-border">
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
        <p className="text-[10px] text-muted-foreground mt-2 text-center">GeM/2026/B/7367951 · CAPEX 5-yr</p>
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
          <Route path="/audit" component={Audit} />
          <Route component={NotFound} />
        </Switch>
      </main>
    </div>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <WouterRouter base={import.meta.env.BASE_URL.replace(/\/$/, "")}>
          <Layout />
        </WouterRouter>
        <Toaster />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
