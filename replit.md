# Workspace

## Overview

pnpm workspace monorepo using TypeScript. Each package manages its own dependencies.

## Stack

- **Monorepo tool**: pnpm workspaces
- **Node.js version**: 24
- **Package manager**: pnpm
- **TypeScript version**: 5.9
- **API framework**: Express 5
- **Database**: PostgreSQL + Drizzle ORM
- **Validation**: Zod (`zod/v4`), `drizzle-zod`
- **API codegen**: Orval (from OpenAPI spec)
- **Build**: esbuild (CJS bundle)

## Key Commands

- `pnpm run typecheck` — full typecheck across all packages
- `pnpm run build` — typecheck + build all packages
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks and Zod schemas from OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema changes (dev only)
- `pnpm --filter @workspace/api-server run dev` — run API server locally

See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details.

## Project: IOB CTS National Grid — Operations Dashboard

### Artifacts

- **`artifacts/api-server`** — Express API server (port 8080, proxied at `/api`)
- **`artifacts/cts-dashboard`** — React + Vite + Tailwind + shadcn/ui dashboard (port 21313, proxied at `/cts-dashboard`)
- **`artifacts/mockup-sandbox`** — Component preview server for canvas mockups
- **`cts-laravel/`** — PHP Laravel 11 scaffold (82 files, 11 modules, 80+ API routes)

### Dashboard Modules (10 pages, all wired to live mock API)

1. **Operations Overview** — real-time KPIs, pipeline chart, throughput, grid node summary
2. **Instruments** — MICR cheque listing with IFSC, drawee bank, status badges
3. **Clearing Batches** — batch lifecycle, amount totals, branch breakdown
4. **Sessions** — clearing session management, cut-off timer
5. **Fraud Alerts** — rule-based + ML fraud feed, risk scores, alert timeline
6. **Returns** — return instrument register, reason codes, re-presentation tracking
7. **BCP / DR Status** — DC Chennai / DR Hyderabad health, RPO/RTO gauges
8. **Positive Pay** — NPCI CPPS registrations, mismatch alerts, high-value (≥₹5L) tracking
9. **Image Quality Analysis (IQA)** — pass/fail rates by branch, failure reason breakdown, re-scan queue
10. **Audit Trail** — full access log with user, module, action, IP

### Authentication System

- **Login page** — split layout, IOB branding, Employee ID + password
- **Two-factor OTP** — simulated 6-digit OTP (displayed on screen in demo mode), 5-minute expiry, copy-paste support
- **7 mock users** across roles: admin, supervisor, fraud_officer, branch_operator, auditor, checker, bcp_officer
- **Account lockout** — 5 failed attempts → 15-minute lockout with countdown
- **Session management** — 30-minute idle timeout, warning modal at 5 minutes remaining, session refresh
- **Role badges** — colour-coded per role in user menu
- **Last login display** — timestamp + IP shown on next login and in user menu
- **Secure sign-out** — token revoked server-side, clears localStorage

### API Routes (Express, `/api/*`)

- `GET/POST /api/auth/*` — login, verify-otp, me, logout, refresh
- `GET /api/dashboard/*` — summary, pipeline, throughput, grid-summary
- `GET /api/instruments/*` — list, recent, detail
- `GET /api/batches/*` — list, detail, summary
- `GET /api/sessions/*` — list, detail, stats
- `GET /api/fraud/*` — alerts, summary, rules
- `GET /api/returns/*` — list, summary, reason-codes
- `GET /api/bcp/*` — status, history, incidents
- `GET /api/positive-pay/*` — registrations, summary, mismatches
- `GET /api/iqa/*` — summary, branches, failed-instruments
- `GET /api/audit/*` — list, modules

### Theme

IOB navy/gold: `--primary: 224 60% 30%` (navy), `--accent: 38 92% 55%` (gold/amber)
