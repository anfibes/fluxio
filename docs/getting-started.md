# Getting Started

---

# Introduction

Fluxio is an experimental AI-first CRM/ERP prototype focused on:

- natural-language business interaction
- Action Proposal workflows
- proposal refinement
- proposal continuity
- explicit confirmation before execution
- explainable AI-assisted operations

The project is structured as a modular monolith with:

- Laravel backend (`apps/api`)
- Nuxt frontend (`apps/web`)
- shared domain-oriented packages (`packages/*`)

This document explains how to run Fluxio locally for development.

---

# Current Status

Fluxio is currently an active architecture-first prototype.

Implemented vertical slice:

- authentication
- command composer
- Action Proposal interpretation
- proposal rendering
- proposal refinement
- proposal continuity
- refinement metadata rendering
- proposal confirmation
- proposal execution
- execution result rendering
- task creation flow

The project is intentionally:
- proposal-centric
- AI-first
- operational
- still incomplete by design

The goal is NOT feature quantity.

The goal is validating:
- proposal-driven workflows
- operational AI UX
- structured business execution
- deterministic refinement flows

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
| Nginx | optional but recommended |
| Git | latest |

Development OS currently used:

- macOS
- local Nginx environment
- VS Code

---

# Repository Structure

```text
fluxio/
├── apps/
│   ├── api/        Laravel backend
│   └── web/        Nuxt frontend
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
│   ├── architecture.md
│   ├── api-response-standard.md
│   ├── frontend-vision.md
│   ├── proposal-lifecycle.md
│   ├── backend-current-state.md
│   ├── development-plan.md
│   └── getting-started.md
│
└── README.md
```

---

# Clone the Repository

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

Install PHP dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the Laravel app key:

```bash
php artisan key:generate
```

---

# Configure the Database

Edit `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fluxio
DB_USERNAME=postgres
DB_PASSWORD=password
```

Create the PostgreSQL database manually if needed.

---

# Run Migrations

```bash
php artisan migrate
```

---

# Seed Demo Data

The project may include demo seeders for:

- test users
- demo tasks
- demo proposals

Run:

```bash
php artisan db:seed
```

If specific seeders exist:

```bash
php artisan db:seed --class=DemoSeeder
```

---

# Start the Laravel Server

Simple local development:

```bash
php artisan serve
```

Default:

```text
http://localhost:8000
```

---

# Optional: Local Nginx Setup

Recommended local URL:

```text
https://fluxio.test
```

Example Nginx root:

```text
/Applications/progetti/fluxio/apps/api/public
```

Typical local SSL setup:
- mkcert
- Valet-like certificates
- local trusted certificates

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

# Frontend Runtime Configuration

Fluxio frontend uses:

```text
NUXT_PUBLIC_API_BASE
```

Recommended local `.env` inside `apps/web`:

```env
NUXT_PUBLIC_API_BASE=https://fluxio.test/api
```

Fallback default exists in:

```text
nuxt.config.ts
```

---

# Start Nuxt Development Server

```bash
npm run dev
```

Default frontend URL:

```text
http://localhost:3000
```

---

# Current Local Development Flow

Typical development setup:

| Service | URL |
|---|---|
| Laravel API | `https://fluxio.test/api` |
| Nuxt frontend | `http://localhost:3000` |

Nuxt communicates with Laravel through API calls.

Current proposal lifecycle:

```text
Command
→ Interpret
→ Draft proposal
→ Proposal refinement
→ Ready proposal
→ Confirm
→ Execute
→ Execution result
```

Refinement updates the SAME proposal instead of creating a new one.

This proposal continuity behavior is one of the core architectural concepts of Fluxio.

---

# Login

Current demo login depends on seeded users.

Typical test credentials:

```text
email: test@example.com
password: password
```

Check actual seeders if credentials differ.

---

# Current Frontend Features

Implemented:
- login flow
- command composer
- Action Proposal rendering
- proposal states
- editable fields
- missing information handling
- proposal refinement
- proposal continuity
- refinement metadata rendering
- confidence rendering
- proposal confirmation
- proposal execution
- execution result rendering
- i18n support
- dark enterprise UI foundation

Current frontend UX direction:
- AI-first
- proposal-centric
- operational
- non-chatbot

---

# Current Backend Features

Implemented:
- modular package structure
- Actions module
- Tasks module
- Identity module
- standardized API responses
- centralized exception handling
- Action Proposal lifecycle
- proposal refinement lifecycle
- proposal continuity
- refinement metadata persistence
- confirm/execute flow
- deterministic refinement rules
- execution result persistence

Current Actions endpoints:

| Method | Endpoint |
|---|---|
| POST | `/api/actions/interpret` |
| POST | `/api/actions/{proposal}/refine` |
| POST | `/api/actions/{proposal}/confirm` |
| POST | `/api/actions/{proposal}/execute` |

---

# Useful Commands

## Backend

Run tests:

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

# Tailwind Notes

Fluxio currently standardizes semantic Tailwind utilities.

Preferred:

```text
bg-surface
text-muted
border-border
bg-accent
```

Avoid direct repeated CSS variable usage when reusable semantic utilities exist.

Current frontend direction:
- semantic tokens
- operational consistency
- minimal utility chaos
- maintainable design system evolution

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

Fluxio intentionally follows:
- modular monolith architecture
- explicit domain boundaries
- AI-assisted workflows
- proposal-driven execution
- deterministic refinement flows

Natural language must NEVER directly execute business actions.

All business mutations must pass through:
1. interpretation
2. proposal refinement
3. validation
4. explicit confirmation
5. execution

The proposal is the central architectural object.

---

# Documentation

Important documents:

| File | Purpose |
|---|---|
| `README.md` | project overview |
| `docs/architecture.md` | backend/frontend architecture |
| `docs/api-response-standard.md` | API contract rules |
| `docs/frontend-vision.md` | frontend philosophy |
| `docs/proposal-lifecycle.md` | Action Proposal lifecycle |
| `docs/backend-current-state.md` | implemented backend/frontend status |
| `docs/development-plan.md` | roadmap and architectural direction |

---

# AI Workflow

Recommended workflow:

- ChatGPT → architecture and planning
- Claude Code → implementation and repository execution

The repository is intentionally structured to support:
- iterative development
- AI-assisted implementation
- architecture-first evolution

Recommended model usage:
- lightweight models → docs, formatting, small components
- stronger models → proposal lifecycle, ambiguity workflows, orchestration

---

# Known Limitations

Current prototype limitations:

- no production deployment pipeline yet
- no advanced permissions
- no realtime collaboration
- no advanced LLM provider abstraction yet
- no ambiguity resolution UX yet
- no candidate entity workflows yet
- limited domain modules
- no multi-step proposal orchestration yet

This is expected at the current stage.

The current focus is intentionally narrow:
- proposal continuity
- refinement workflows
- operational AI UX
- deterministic execution

---

# Recommended Next Steps

Suggested exploration areas:

- ambiguity resolution UX
- candidate entity workflows
- contextual refinement semantics
- proposal mutation transparency
- streaming interpretation
- AI provider abstraction
- operational timeline
- contextual entity memory
- multi-step operational proposals

---

# Contributing

Fluxio is currently architecture-driven and evolving rapidly.

Contributions should prioritize:
- simplicity
- maintainability
- explicitness
- operational clarity
- explainable behavior
- proposal continuity

Avoid:
- premature abstractions
- over-engineering
- hidden magic behavior
- generic chatbot patterns

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
- proposal continuity
- explicit operational control
- deterministic execution workflows