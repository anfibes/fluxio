# Fluxio

AI-first command-driven CRM/ERP prototype that transforms natural language into structured, validated and confirmable business actions.

Fluxio is not a chatbot.
Fluxio is an operational workspace built around proposals, ambiguity resolution and controlled execution.

---

# Overview

Fluxio explores a different interaction model for business software.

Traditional CRM/ERP systems are usually built around:
- forms
- dashboards
- menus
- repetitive manual workflows

Fluxio instead explores:
- natural-language intent
- proposal-driven workflows
- ambiguity-aware UX
- explicit confirmation
- controlled automation

The project is intentionally:
- architecture-first
- interaction-first
- deterministic-first
- AI-assisted but not AI-autonomous

Fluxio exists both as:
- an experimental AI-first business UX
- a showcase of modern backend/frontend architecture

---

# Core Interaction Model

Traditional systems:

```text
User → Form → Validation → Save
```

Fluxio:

```text
User → Natural Language
     → Action Proposal
     → Validation
     → Refinement
     → Confirmation
     → Execution
```

Natural language is NEVER executed directly.

Every command becomes a structured `ActionProposal` that must be:
- reviewable
- editable
- validated
- explicitly confirmed

before execution.

---

# Why Proposal-Driven UX

Fluxio does not try to hide uncertainty.

The system intentionally exposes:
- ambiguities
- missing information
- low-confidence interpretations
- incomplete commands

The goal is not blind automation.

The goal is:
- transparency
- operational control
- fast refinement
- safe execution

---

# Ambiguity-Aware UX

Fluxio treats ambiguity as a first-class operational state.

The system never silently chooses entities.

Example:

```text
Call Rossi
```

Possible matches:
- Mario Rossi
- Rossi SRL
- Studio Rossi

Instead of hallucinating certainty, Fluxio:
- exposes ambiguity
- asks for refinement
- preserves proposal continuity

This is a core architectural principle of the project.

---

# Current UX Direction

The interface is designed as:
- operational
- proposal-centric
- refinement-driven
- confirmation-first
- mobile-oriented
- future voice-friendly

Fluxio is NOT:
- a generic AI chat
- a conversational assistant
- a dashboard-heavy ERP

The proposal remains the central object.

---

# UX Screenshots

## Initial Proposal Generation

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: Action01.png -->
<!-- Description: Initial draft proposal with missing fields -->

![Fluxio Initial Proposal](docs/screenshots/action01.png)

---

## Proposal Refinement

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: Action02.png -->
<!-- Description: Proposal refined after missing information -->

![Fluxio Proposal Refinement](docs/screenshots/action02.png)

---

## Successful Execution

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: Action03.png -->
<!-- Description: Executed proposal with execution result -->

![Fluxio Execution Result](docs/screenshots/action03.png)

---

## Ambiguity Resolution Flow

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen01.png -->
<!-- Description: Ambiguity resolution panel -->

![Fluxio Ambiguity Resolution](docs/screenshots/screen01.png)

---

## Candidate Refinement

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen02.png -->
<!-- Description: Refinement after candidate selection -->

![Fluxio Candidate Refinement](docs/screenshots/screen02.png)

---

## Missing Information Refinement

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen03.png -->
<!-- Description: Proposal becomes ready after refinement -->

![Fluxio Missing Information](docs/screenshots/screen03.png)

---

## Execution State

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen04.png -->
<!-- Description: Final executed operational flow -->

![Fluxio Executed State](docs/screenshots/screen04.png)

---

# Architecture

## Modular Monolith

Fluxio is built as a modular monolith with strong internal boundaries.

```text
fluxio/
  apps/
    api/
    web/

  packages/
    Core/
    Identity/
    Leads/
    Tasks/
    Actions/
    Calendar/
    Analytics/
    Notifications/
```

Principle:

```text
Modularize first, microservice later.
```

Each module owns:
- models
- services
- migrations
- routes
- events
- business rules

---

# Domain Separation

Examples:

## Actions
- intent interpretation
- proposal lifecycle
- proposal execution orchestration

## Leads
- CRM entities
- lead lifecycle
- future customer intelligence

## Tasks
- operational tasks
- follow-up workflows

## Calendar
- scheduling
- future coordination workflows

---

# Event-Driven Communication

Modules communicate through:
- events
- listeners
- jobs

Examples:
- `ActionExecuted`
- `LeadCreated`
- `TaskCompleted`

Direct cross-domain coupling is intentionally avoided.

---

# Actions Module

The `Actions` module is the operational core of Fluxio.

Main flow:

```text
Natural language
→ intent detection
→ entity extraction
→ proposal generation
→ validation
→ refinement
→ confirmation
→ execution
```

---

# Example Proposal

Input:

```text
Schedule a follow-up call with Rossini tomorrow morning
```

Generated proposal:

```json
{
  "success": true,
  "message": "Command interpreted successfully.",
  "data": {
    "intent": "schedule_call",
    "status": "ready",
    "confidence": 0.85,
    "source_text": "Schedule a follow-up call with Rossini tomorrow morning",
    "entities": {
      "lead": "Rossini",
      "date": "2026-05-11",
      "time": "09:00"
    },
    "missing": [],
    "warnings": [],
    "needs_confirmation": true
  }
}
```

---

# Confidence UX

Fluxio never pretends certainty.

Low-confidence proposals are intentionally surfaced.

Examples:
- ambiguous entities
- incomplete commands
- weak context
- uncertain extraction

The system should:
- expose uncertainty
- encourage refinement
- preserve control
- avoid hallucinated confidence

---

# Frontend Philosophy

Fluxio follows:
- AI-first interaction
- proposal-centric workflows
- explicit execution confirmation
- low operational friction
- structured refinement

CRM data supports the workflow.
It does not dominate the interface.

---

# API Design

Fluxio exposes a standardized JSON API.

## Success Response

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

---

## Error Response

```json
{
  "success": false,
  "message": "Error message."
}
```

---

## Validation Error

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["Validation message"]
  }
}
```

---

# Technology Stack

## Backend

- Laravel
- PostgreSQL
- Modular monolith architecture
- Event-driven design
- Standardized API responses

## Frontend

- Nuxt 4
- Vue 3
- Composition API
- Tailwind CSS v4
- TypeScript
- i18n

---

# Localization

Fluxio is multilingual from the beginning.

Rules:
- backend uses Laravel translations
- frontend uses i18n
- no hardcoded UI strings

Primary language:
- English

Additional languages:
- Italian
- German (planned)

---

# Current Project Status

Fluxio is actively evolving.

The project currently includes:
- backend MVP foundation
- proposal lifecycle
- command interpretation flow
- refinement UX
- ambiguity resolution UX
- execution confirmation flow
- operational frontend shell
- responsive/mobile-first direction

---

# Implemented

## Backend

- Modular architecture
- Standardized API layer
- Exception handling
- Sanctum authentication
- Leads module
- Tasks module
- Actions module
- Proposal persistence
- Proposal execution flow
- Idempotent execution

---

## Frontend

- Login/logout flow
- Command composer
- Proposal rail
- Proposal states
- Editable fields
- Missing information panels
- Ambiguity resolution UX
- Refinement flow
- Execution result rendering
- Confidence UX
- Recent command history
- Responsive operational shell
- Dark SaaS UI foundation

---

# Not Yet Implemented

- Real LLM provider integration
- Provider abstraction layer
- Advanced proposal mutation intelligence
- Full conversational refinement
- Calendar orchestration
- Multi-step workflows
- Notifications orchestration
- Multi-user collaboration
- Voice interaction
- Mobile operational navigation
- Production-grade AI orchestration

---

# LLM Strategy

Fluxio is deterministic-first and validation-first.

The current MVP uses:
- rule-based interpretation
- predictable proposal generation
- explicit validation

Future LLM integration may assist:
- intent detection
- ambiguity resolution
- proposal refinement
- entity extraction

However:
- all output remains validated
- proposals remain structured
- confirmation remains mandatory
- AI never directly executes actions

Possible future providers:
- Ollama
- Qwen
- local lightweight models
- provider abstraction layer

---

# Documentation

Detailed documentation is available in `docs/`.

## Core Documentation

- [Architecture](docs/architecture.md)
- [API Response Standard](docs/api-response-standard.md)
- [Proposal Lifecycle](docs/proposal-lifecycle.md)
- [Frontend Vision](docs/frontend-vision.md)
- [Development Plan](docs/development-plan.md)
- [Getting Started](docs/getting-started.md)

---

# Quick Start

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- PostgreSQL

---

## Clone Repository

```bash
git clone https://github.com/anfibes/fluxio.git
cd fluxio
```

---

## Backend

```bash
cd apps/api

composer install

cp .env.example .env

php artisan key:generate

php artisan migrate

php artisan serve
```

---

## Frontend

```bash
cd apps/web

npm install

npm run dev
```

---

# Development Philosophy

Fluxio prioritizes:
- architecture clarity
- proposal transparency
- controlled execution
- modular maintainability
- deterministic behavior
- AI-assisted workflows without hidden automation

The project intentionally evolves through:
- small steps
- verifiable flows
- testable iterations
- operational UX experiments

---

# Project Goal

Fluxio is currently NOT production-ready.

The project exists to explore:
- AI-first business UX
- proposal-driven workflows
- operational ambiguity management
- controlled natural-language execution
- future enterprise interaction models

while demonstrating:
- modern backend architecture
- modular monolith design
- scalable frontend structure
- maintainable domain separation

---

# License

MIT