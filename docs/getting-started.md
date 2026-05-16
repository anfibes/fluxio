# Getting Started

---

# Introduction

Fluxio is an experimental proposal-driven CRM/ERP prototype exploring:

```text
controlled operational AI workflows
```

instead of:
- dashboard-first ERP interaction
- CRUD-heavy operational flows
- conversational assistant UX
- autonomous AI execution

Fluxio is intentionally:
- proposal-centric
- operational
- deterministic-first
- architecture-first
- refinement-oriented

Natural language never executes business operations directly.

Every operation must pass through:
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
```text
operational
```

NOT:
```text
conversational
```

---

# Current Operational Flow

Current implemented flow:

```text
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

The current prototype already validates:
- proposal-driven interaction
- operational AI UX
- deterministic refinement semantics
- explainable execution workflows

---

# Repository Structure

```text
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
└── README.md
```

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

```text
http://localhost:8000
```

---

# Optional Local HTTPS Setup

Recommended local API URL:

```text
https://fluxio.test
```

Typical setup:
- Nginx
- mkcert
- trusted local SSL certificates

Typical public root:

```text
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

```text
nuxt.config.ts
```

Start frontend:

```bash
npm run dev
```

Default frontend URL:

```text
http://localhost:3000
```

---

# Typical Local Development Flow

| Service | URL |
|---|---|
| Laravel API | `https://fluxio.test/api` |
| Nuxt frontend | `http://localhost:3000` |

Frontend communicates with backend through API requests.

---

# Demo Credentials

Credentials depend on current seeders.

Typical local credentials:

```text
email: test@example.com
password: password
```

Check actual seeders if credentials differ.

---

# Typical Proposal Workflow

Example:

```text
Schedule a meeting with Rossi tomorrow morning
```

Expected behavior:
- ambiguity detected
- proposal enters `draft`
- date/time extracted
- ambiguity candidates exposed

Refinement:

```text
The second one
```

Expected behavior:
- SAME proposal updated
- ambiguity resolved
- proposal becomes `ready`

Execution flow:

```text
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
- AI-first
- proposal-centric
- operational
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
php artisan test --filter=ProposalMutationIntelligenceTest
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

```text
apps/web/locales/
```

Backend translations:

```text
apps/api/lang/
```

Current parser implementation remains English-first.

---

# Tailwind Notes

Fluxio uses semantic Tailwind utilities and design tokens.

Preferred utilities:

```text
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
- explainable operational AI

Important concepts:
- proposal continuity
- mutation semantics
- ambiguity handling
- operational explainability
- explicit execution control

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

Main documents:

| File | Purpose |
|---|---|
| `README.md` | project overview |
| `docs/architecture.md` | system architecture |
| `docs/frontend-vision.md` | frontend UX philosophy |
| `docs/proposal-lifecycle.md` | Action Proposal lifecycle |
| `docs/backend-current-state.md` | backend implementation status |
| `docs/development-plan.md` | roadmap and milestones |
| `docs/api-response-standard.md` | API response contracts |

---

# Known Limitations

Current prototype limitations:
- no advanced permissions
- no production deployment pipeline
- no realtime collaboration
- no advanced provider abstraction
- limited ERP modules
- no semantic search yet
- no advanced entity resolution yet
- no voice workflows yet

Current focus intentionally remains:
- proposal continuity
- mutation semantics
- ambiguity workflows
- operational AI UX
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

```text
structured, explainable and controllable
AI-assisted business execution
```

through:
- validated Action Proposals
- proposal refinement
- ambiguity resolution
- mutation semantics
- explicit operational control
- deterministic execution workflows