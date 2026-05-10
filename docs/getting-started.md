# Getting Started

---

# Introduction

Fluxio is an experimental AI-first CRM/ERP prototype focused on:

- Action Proposal workflows
- proposal refinement
- ambiguity-aware operations
- proposal continuity
- explicit confirmation before execution
- deterministic operational AI

Fluxio is intentionally:
- proposal-centric
- operational
- architecture-first
- deterministic-first

Natural language never directly executes business actions.

Every business operation must pass through:
1. interpretation
2. proposal refinement
3. validation
4. explicit confirmation
5. execution

---

# Current Vertical Slice

Fluxio currently validates the following operational flow:

```text
Command
→ Interpret
→ Draft proposal
→ Proposal refinement
→ Ambiguity resolution
→ Ready proposal
→ Confirm
→ Execute
→ Execution result
```

Implemented capabilities include:
- authentication
- command composer
- Action Proposal rendering
- proposal refinement
- ambiguity workflows
- proposal continuity
- refinement metadata rendering
- execution result rendering
- task creation flow
- responsive operational UI
- dark/light/system themes

The project intentionally prioritizes:
- proposal-driven workflows
- operational AI UX
- explainable execution
- deterministic refinement behavior

over:
- feature quantity
- ERP breadth
- dashboard complexity

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

Current development environment:
- macOS
- local Nginx setup
- VS Code

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

# Quick Start

Clone the repository:

```bash
git clone https://github.com/anfibes/fluxio.git
cd fluxio
```

---

# Backend Setup (Laravel API)

Navigate to the API app:

```bash
cd apps/api
```

Install dependencies:

```bash
composer install
```

Create environment file:

```bash
cp .env.example .env
```

Generate application key:

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

Create the database manually if needed.

---

# Run Migrations

```bash
php artisan migrate
```

---

# Optional Seeders

If demo seeders exist:

```bash
php artisan db:seed
```

Or:

```bash
php artisan db:seed --class=DemoSeeder
```

---

# Start Laravel

```bash
php artisan serve
```

Default URL:

```text
http://localhost:8000
```

---

# Optional Local HTTPS Setup

Recommended local API URL:

```text
https://fluxio.test
```

Typical local setup:
- Nginx
- mkcert
- trusted local SSL certificates

Example API public root:

```text
/Applications/progetti/fluxio/apps/api/public
```

---

# Frontend Setup (Nuxt)

Navigate to the frontend app:

```bash
cd ../web
```

Install dependencies:

```bash
npm install
```

---

# Frontend Environment

Create `.env` inside `apps/web`:

```env
NUXT_PUBLIC_API_BASE=https://fluxio.test/api
```

Fallback configuration exists in:

```text
nuxt.config.ts
```

---

# Start Nuxt

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

Nuxt communicates with Laravel through API requests.

---

# Demo Login

Demo credentials depend on current seeders.

Typical local credentials:

```text
email: test@example.com
password: password
```

Check actual seeders if credentials differ.

---

# Development Commands

## Backend

Run all tests:

```bash
php artisan test
```

Run specific tests:

```bash
php artisan test --filter=ActionProposalTest
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

# Current Frontend Highlights

Implemented frontend capabilities:
- command composer
- proposal rail
- ambiguity rendering
- proposal refinement
- mutation rendering
- confidence-aware UX
- execution rendering
- responsive operational shell
- theme system
- i18n support

Frontend direction:
- AI-first
- proposal-centric
- operational
- non-chatbot

---

# Current Backend Highlights

Implemented backend capabilities:
- modular package structure
- Actions module
- Tasks module
- Identity module
- standardized API responses
- centralized exception handling
- proposal lifecycle persistence
- ambiguity-aware refinement
- confirm/execute flow
- deterministic refinement logic

Current Actions endpoints:

| Method | Endpoint |
|---|---|
| POST | `/api/actions/interpret` |
| POST | `/api/actions/{proposal}/refine` |
| POST | `/api/actions/{proposal}/confirm` |
| POST | `/api/actions/{proposal}/execute` |

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

The frontend intentionally prioritizes:
- operational consistency
- semantic styling
- maintainable utility usage

---

# Localization

Fluxio is multilingual from the beginning.

Current languages:
- English
- Italian

Frontend translations:

```text
apps/web/locales/
```

Backend translations:

```text
apps/api/lang/
```

---

# Architecture Notes

Fluxio follows:
- modular monolith architecture
- explicit domain boundaries
- proposal-driven execution
- deterministic refinement workflows
- AI-assisted operational UX

The proposal is the central architectural object.

Important concepts:
- proposal continuity
- ambiguity-aware workflows
- explicit execution control
- explainable AI behavior

---

# Documentation

Main documents:

| File | Purpose |
|---|---|
| `README.md` | project overview |
| `docs/architecture.md` | system architecture |
| `docs/frontend-vision.md` | frontend interaction philosophy |
| `docs/proposal-lifecycle.md` | Action Proposal lifecycle |
| `docs/backend-current-state.md` | current implementation status |
| `docs/development-plan.md` | roadmap and milestones |
| `docs/api-response-standard.md` | API contracts |

---

# Known Limitations

Current prototype limitations:
- no advanced permissions
- no production deployment pipeline
- no advanced provider abstraction
- limited domain modules
- no realtime collaboration
- no multi-step proposal orchestration yet

Current focus remains intentionally narrow:
- proposal continuity
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
- operational clarity
- proposal continuity
- explainable workflows

Avoid:
- premature abstractions
- hidden side effects
- generic chatbot patterns
- autonomous AI behavior

---

# Philosophy Reminder

Fluxio is not trying to become:
- another generic CRM
- a chatbot wrapper
- an autonomous AI agent

Its goal is to explore:

```text
structured, explainable and controllable
AI-assisted business execution
```

through:
- validated Action Proposals
- proposal refinement
- ambiguity resolution
- explicit operational control
- deterministic execution workflows