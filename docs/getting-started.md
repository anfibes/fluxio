# Getting Started

---

# Introduction

Fluxio is an experimental AI-first CRM/ERP prototype focused on:

- natural-language business interaction
- Action Proposal workflows
- explicit confirmation before execution
- explainable AI-assisted operations

The project is structured as a modular monolith with:

- Laravel backend (`apps/api`)
- Nuxt frontend (`apps/web`)
- shared domain-oriented packages (`packages/*`)

This document explains how to run Fluxio locally for development.

---

# Current Status

Fluxio is currently an active prototype.

Implemented vertical slice:

- authentication
- command composer
- Action Proposal interpretation
- proposal rendering
- proposal confirmation
- proposal execution
- task creation flow

The project is intentionally incomplete and architecture-focused.

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

```text id="0w1e2r"
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
│   └── getting-started.md
│
└── README.md
```

---

# Clone the Repository

```bash id="n4d8qk"
git clone https://github.com/anfibes/fluxio.git
cd fluxio
```

---

# Backend Setup (Laravel API)

Navigate to the API app:

```bash id="rq7l9f"
cd apps/api
```

Install PHP dependencies:

```bash id="d3g6pa"
composer install
```

Create the environment file:

```bash id="sl2j9q"
cp .env.example .env
```

Generate the Laravel app key:

```bash id="f7m2xp"
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

```bash id="b7v3qt"
php artisan migrate
```

---

# Seed Demo Data

The project may include demo seeders for:

- test users
- demo tasks
- demo proposals

Run:

```bash id="x2p8wr"
php artisan db:seed
```

If specific seeders exist:

```bash id="k9m4dv"
php artisan db:seed --class=DemoSeeder
```

---

# Start the Laravel Server

Simple local development:

```bash id="h6u1zc"
php artisan serve
```

Default:

```text id="m8t5qs"
http://localhost:8000
```

---

# Optional: Local Nginx Setup

Recommended local URL:

```text id="p1w7bn"
https://fluxio.test
```

Example Nginx root:

```text id="z5c9hf"
/Applications/progetti/fluxio/apps/api/public
```

Typical local SSL setup:
- mkcert
- Valet-like certificates
- local trusted certificates

---

# Frontend Setup (Nuxt)

Navigate to the frontend app:

```bash id="w3j8py"
cd ../web
```

Install dependencies:

```bash id="c8a2lr"
npm install
```

---

# Frontend Runtime Configuration

Fluxio frontend uses:

```text id="e4n6tu"
NUXT_PUBLIC_API_BASE
```

Recommended local `.env` inside `apps/web`:

```env
NUXT_PUBLIC_API_BASE=https://fluxio.test/api
```

Fallback default exists in:

```text id="o8f4kc"
nuxt.config.ts
```

---

# Start Nuxt Development Server

```bash id="u5v1ed"
npm run dev
```

Default frontend URL:

```text id="q7h2af"
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

---

# Login

Current demo login depends on seeded users.

Typical test credentials:

```text id="g9m1pb"
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
- proposal confirmation
- proposal execution
- execution result rendering
- i18n support
- dark enterprise UI foundation

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
- confirm/execute flow

---

# Useful Commands

## Backend

Run tests:

```bash id="t1f8xz"
php artisan test
```

Run specific tests:

```bash id="r6u2nm"
php artisan test --filter=ActionProposalTest
```

Clear caches:

```bash id="v2q7ye"
php artisan optimize:clear
```

---

## Frontend

Run typecheck:

```bash id="j8s4lx"
npm run typecheck
```

Build production assets:

```bash id="n3d7ka"
npm run build
```

Preview production build:

```bash id="f5m9qr"
node .output/server/index.mjs
```

---

# Tailwind Notes

Fluxio currently standardizes semantic Tailwind utilities:

Preferred:

```text id="s7n2xb"
bg-surface
text-muted
border-border
bg-accent
```

Avoid direct repeated CSS variable usage when reusable semantic utilities exist.

---

# Localization

Fluxio is multilingual from the beginning.

Current languages:
- English
- Italian

Frontend translations:
```text id="b4q8ke"
apps/web/locales/
```

Backend translations:
```text id="w9t3cp"
apps/api/lang/
```

---

# Architecture Notes

Fluxio intentionally follows:
- modular monolith architecture
- explicit domain boundaries
- AI-assisted workflows
- proposal-driven execution

Natural language must NEVER directly execute business actions.

All business mutations must pass through:
1. interpretation
2. proposal validation
3. explicit confirmation
4. execution

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

---

# AI Workflow

Recommended workflow:

- ChatGPT → architecture and planning
- Claude Code → implementation and repository execution

The repository is intentionally structured to support:
- iterative development
- AI-assisted implementation
- architecture-first evolution

---

# Known Limitations

Current prototype limitations:

- no production deployment pipeline yet
- no advanced permissions
- no realtime collaboration
- no full AI provider abstraction yet
- no complete conversational refinement system yet
- limited domain modules

This is expected at the current stage.

---

# Recommended Next Steps

Suggested exploration areas:

- proposal refinement UX
- conversational continuation
- command history
- streaming interpretation
- AI provider abstraction
- contextual suggestions
- operational timeline
- entity memory

---

# Contributing

Fluxio is currently architecture-driven and evolving rapidly.

Contributions should prioritize:
- simplicity
- maintainability
- explicitness
- operational clarity
- explainable behavior

Avoid:
- premature abstractions
- over-engineering
- hidden magic behavior

---

# Philosophy Reminder

Fluxio is not trying to become:
- another generic CRM
- a chatbot wrapper
- an autonomous AI agent

Its goal is to explore:

```text id="u2z8vc"
structured, explainable and controllable
AI-assisted business execution
```