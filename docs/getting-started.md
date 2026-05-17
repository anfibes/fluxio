# Getting Started

---

# Introduction

Fluxio is an experimental proposal-driven operational CRM prototype.

Core hypothesis:

```text id="r5g2kv"
Business systems can evolve from CRUD-heavy workflows
toward validated Action Proposal workflows.
```

Fluxio is intentionally:
- proposal-centric
- operational
- deterministic-first
- refinement-oriented
- architecture-first

Fluxio is intentionally NOT:
- a chatbot wrapper
- an autonomous AI system
- dashboard-heavy ERP software
- conversational assistant software

Natural language never executes business operations directly.

Every operation passes through:
1. interpretation
2. proposal creation
3. refinement
4. validation
5. explicit confirmation
6. execution

The proposal is the central architectural object.

---

# Architectural Invariants

These rules should never be violated.

- Natural language never executes directly
- Proposals remain authoritative
- Refinements mutate existing proposals
- Proposal continuity is preserved
- Ambiguities remain explicit
- Execution always requires confirmation
- AI never bypasses validation
- Proposal state remains explainable

Fluxio is intentionally:

```text id="f7m1cz"
operational
```

NOT:

```text id="n4w2qr"
conversational
```

---

# Current Operational Flow

Current implemented flow:

```text id="w8v6ta"
Command
→ Interpret
→ Draft proposal
→ Ambiguity resolution
→ Proposal refinement
→ Ready proposal
→ Confirm
→ Execute
→ Execution result
```

Current implemented capabilities:
- proposal continuity
- contextual refinements
- ambiguity-aware workflows
- mutation tracking
- collection mutations
- operational intent registry
- execution idempotency
- proposal-local references
- mutation summaries
- confidence-aware UX
- entity resolution
- interpretation provider abstraction
- provider validation boundaries

The current prototype already validates:
- proposal-driven interaction
- deterministic refinement workflows
- explainable proposal lifecycle
- ambiguity-aware operational UX
- controlled execution workflows

---

# Repository Structure

```text id="j9s4yf"
fluxio/
├── apps/
│   ├── api/
│   └── web/
│
├── packages/
│   ├── Core/
│   ├── Identity/
│   ├── Leads/
│   ├── Tasks/
│   ├── Actions/
│   ├── Calendar/
│   ├── Analytics/
│   └── Notifications/
│
├── docs/
│
├── .docs/
│
└── README.md
```

Documentation strategy:
- `docs/` → public versioned documentation
- `.docs/` → local internal architecture notes and working documents

---

# Requirements

Recommended environment:

| Tool | Version |
|---|---|
| PHP | 8.3+ |
| Composer | 2+ |
| Node.js | 20+ |
| npm | 10+ |
| PostgreSQL | 15+ |
| Git | latest |

Current local environment:
- macOS
- local Nginx setup
- VS Code

---

# Quick Start

Clone repository:

```bash
git clone https://github.com/anfibes/fluxio.git
cd fluxio
```

---

# Backend Setup

Navigate to API app:

```bash
cd apps/api
```

Install dependencies:

```bash
composer install
```

Create environment:

```bash
cp .env.example .env
```

Generate app key:

```bash
php artisan key:generate
```

---

# Configure PostgreSQL

Edit `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fluxio
DB_USERNAME=postgres
DB_PASSWORD=password
```

Create database manually if needed.

Run migrations:

```bash
php artisan migrate
```

Optional seeders:

```bash
php artisan db:seed
```

Start backend:

```bash
php artisan serve
```

Default API URL:

```text id="n0v6zy"
http://localhost:8000
```

---

# Optional Local HTTPS Setup

Recommended local API URL:

```text id="r1q5bo"
https://fluxio.test
```

Typical setup:
- Nginx
- mkcert
- trusted local SSL certificates

Typical public root:

```text id="d7m8tk"
/Applications/progetti/fluxio/apps/api/public
```

---

# Frontend Setup

Navigate to frontend:

```bash
cd ../web
```

Install dependencies:

```bash
npm install
```

Create `.env`:

```env
NUXT_PUBLIC_API_BASE=https://fluxio.test/api
```

Fallback configuration exists in:

```text id="v5k2sa"
nuxt.config.ts
```

Start frontend:

```bash
npm run dev
```

Default frontend URL:

```text id="k8r1fw"
http://localhost:3000
```

---

# Typical Local Development Flow

| Service | URL |
|---|---|
| Laravel API | `https://fluxio.test/api` |
| Nuxt frontend | `http://localhost:3000` |

Frontend communicates with backend exclusively through API requests.

---

# Demo Credentials

Credentials depend on current seeders.

Typical local credentials:

```text id="t3v6mn"
email: test@example.com
password: password
```

Check actual seeders if credentials differ.

---

# Typical Proposal Workflow

Example:

```text id="e9x4qp"
Schedule a meeting with Rossi tomorrow morning
```

Expected behavior:
- ambiguity detected
- proposal enters `draft`
- date/time extracted
- ambiguity candidates exposed

Refinement:

```text id="h6s2ra"
The second one
```

Expected behavior:
- SAME proposal updated
- ambiguity resolved
- proposal becomes `ready`

Execution flow:

```text id="m5f1uc"
Confirm
→ Execute
→ Execution result
```

The proposal remains the operational context throughout the lifecycle.

---

# Current Backend Highlights

Implemented backend capabilities:
- modular monolith package structure
- Actions module
- Tasks module
- Identity module
- standardized API responses
- centralized exception handling
- proposal persistence
- proposal continuity
- ambiguity-aware refinement
- contextual mutation semantics
- collection mutation support
- execution idempotency
- shared temporal parser
- operational intent registry
- entity resolution layer
- interpretation provider abstraction
- normalized command validation

Current Actions endpoints:

| Method | Endpoint |
|---|---|
| POST | `/api/actions/interpret` |
| POST | `/api/actions/{proposal}/refine` |
| POST | `/api/actions/{proposal}/confirm` |
| POST | `/api/actions/{proposal}/execute` |

Current operational intents:
- `create_task`
- `schedule_call`
- `schedule_meeting`
- `assign_lead`
- `prepare_contract_from_quote`

---

# Current Frontend Highlights

Implemented frontend capabilities:
- command composer
- proposal rail
- ambiguity rendering
- contextual refinements
- mutation rendering
- mutation summaries
- confidence-aware UX
- execution rendering
- proposal continuity UX
- responsive operational shell
- dark/light/system themes
- i18n support

Frontend direction:
- proposal-centric
- operational
- deterministic-first
- non-chatbot

---

# Development Commands

## Backend

Run all tests:

```bash
php artisan test
```

Run filtered tests:

```bash
php artisan test --filter=ProposalMutationTest
```

Clear caches:

```bash
php artisan optimize:clear
```

---

## Frontend

Run typecheck:

```bash
npm run typecheck
```

Build production assets:

```bash
npm run build
```

Preview production build:

```bash
node .output/server/index.mjs
```

---

# Localization

Fluxio is multilingual from the beginning.

Current languages:
- English
- Italian

Planned:
- German

Frontend translations:

```text id="q8p4zd"
apps/web/locales/
```

Backend translations:

```text id="u2m7xr"
apps/api/lang/
```

Current parser implementation remains English-first.

---

# Tailwind Notes

Fluxio uses semantic Tailwind utilities and design tokens.

Preferred utilities:

```text id="b4s6wy"
bg-surface
text-muted
border-border
bg-accent
```

Frontend direction prioritizes:
- semantic styling
- operational readability
- maintainable utility usage

Avoid uncontrolled utility sprawl.

---

# Architecture Notes

Fluxio follows:
- modular monolith architecture
- proposal-driven interaction
- deterministic refinement workflows
- ambiguity-aware operations
- controlled execution
- explainable operational workflows

Important implemented concepts:
- proposal continuity
- mutation semantics
- ambiguity handling
- entity resolution
- provider validation
- explicit execution control

Current provider flow:

```text id="z1m8ta"
Interpretation Provider
→ NormalizedCommand
→ Validation
→ Proposal Lifecycle
```

The project intentionally prioritizes:
- operational consistency
- explainability
- deterministic workflows

over:
- ERP breadth
- dashboard accumulation
- autonomous AI behavior

---

# Documentation

Public versioned documentation:

| File | Purpose |
|---|---|
| `README.md` | project overview |
| `docs/architecture.md` | system architecture |
| `docs/frontend-vision.md` | frontend UX philosophy |
| `docs/proposal-lifecycle.md` | Action Proposal lifecycle |
| `docs/backend-current-state.md` | backend implementation status |
| `docs/development-plan.md` | roadmap and milestones |
| `docs/api-response-standard.md` | API response contracts |

Internal local notes:
- `.docs/`

Used for:
- architecture notes
- implementation planning
- work-in-progress documentation
- internal technical tracking

---

# Known Limitations

Current prototype limitations:
- no advanced permissions
- no production deployment pipeline
- no realtime collaboration
- limited ERP modules
- no semantic search yet
- no voice workflows yet
- no real LLM providers yet

Current focus intentionally remains:
- proposal continuity
- mutation semantics
- ambiguity workflows
- operational UX
- deterministic execution

---

# Contributing

Fluxio is architecture-driven and evolving rapidly.

Contributions should prioritize:
- simplicity
- maintainability
- explicit behavior
- proposal continuity
- operational clarity
- explainable workflows

Avoid:
- premature abstractions
- hidden side effects
- generic chatbot behavior
- autonomous AI execution

---

# Philosophy Reminder

Fluxio is NOT:
- another generic CRM
- a chatbot wrapper
- an autonomous AI agent

Fluxio explores:

```text id="p7n2vd"
structured, explainable and controllable
proposal-driven business interaction
```

through:
- validated Action Proposals
- proposal refinement
- ambiguity resolution
- mutation semantics
- explicit operational control
- deterministic execution workflows