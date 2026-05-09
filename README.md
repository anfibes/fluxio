# Fluxio

AI-first command-driven CRM/ERP prototype that turns natural language into structured, validated and confirmable business actions.

---

# Overview

Fluxio is an open-source CRM/ERP prototype focused on command-first business interactions.

The goal of the project is NOT to build another traditional CRUD-heavy CRM.

Fluxio explores how business systems can evolve from:
- forms
- dashboards
- manual workflows

toward:
- natural-language intent
- structured proposals
- validation-first execution
- controlled business automation

Fluxio is intentionally architecture-first and interaction-first.

The project is designed as a technical and product-design showcase demonstrating:
- modular backend architecture
- domain separation
- API consistency
- proposal-driven workflows
- AI-assisted business interactions
- command-first UX principles

---

# Core Idea

Traditional business systems:

```text
User → Form → Validation → Save
```

Fluxio:

```text
User → Natural Language → Action Proposal → Validation → Confirmation → Execution
```

Natural language is NEVER executed directly.

Every command is transformed into a structured `ActionProposal` that must be:
- validated
- reviewable
- editable
- explicitly confirmed

before execution.

The goal is not blind automation.

The goal is controlled and transparent execution.

---

# Why Fluxio

Most CRM/ERP systems still revolve around:
- complex navigation
- repetitive forms
- rigid UI flows
- manual data entry

Fluxio explores a different interaction model:
- reduce operational friction
- increase execution speed
- preserve business control
- bridge natural language and structured systems

Fluxio is intentionally AI-assisted but NOT AI-autonomous.

The proposal remains the central object.

---

# Architecture

## Modular Monolith

Fluxio is built as a modular monolith with strong internal boundaries and future microservice extraction in mind.

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

Each module owns its:
- models
- services
- migrations
- routes
- events
- business rules

---

# Architectural Principles

## Domain Separation

Each module owns its responsibilities.

Examples:

- `Actions`
  - intent interpretation
  - proposal lifecycle
  - proposal execution orchestration

- `Tasks`
  - executable work items
  - follow-up operations

- `Leads`
  - CRM entities
  - lead lifecycle

- `Calendar`
  - scheduling
  - future temporal coordination

---

## Event-Driven Communication

Modules communicate through:
- events
- listeners
- jobs

Examples:
- `ActionExecuted`
- `LeadCreated`
- `TaskCompleted`

---

## No Direct Cross-Domain Coupling

Direct cross-domain coupling is intentionally avoided.

Incorrect:

```php
Lead::createTaskDirectly();
```

Preferred:

```php
dispatch(new CreateTaskFromLead(...));
```

---

# Actions Module

The `Actions` module is the core of Fluxio.

It transforms natural-language commands into structured and confirmable business proposals.

---

## Main Flow

```text
Natural language
→ intent resolution
→ entity extraction
→ proposal generation
→ validation
→ user confirmation
→ execution
```

---

## Example

Input:

```text
Create a follow-up task for Rossini tomorrow at 10am
```

Generated proposal:

```json
{
  "success": true,
  "message": "Command interpreted successfully.",
  "data": {
    "id": "proposal_uuid",
    "intent": "create_task",
    "status": "ready",
    "confidence": 0.91,
    "source_text": "Create a follow-up task for Rossini tomorrow at 10am",
    "entities": {
      "lead": "Rossini",
      "due_at": "2026-05-04T10:00:00Z"
    },
    "missing": [],
    "warnings": [],
    "editable_fields": [
      {
        "key": "title",
        "label": "Title",
        "value": "Follow-up task for Rossini",
        "source": "detected",
        "required": true
      }
    ],
    "changes": [
      {
        "type": "create",
        "module": "tasks",
        "label": "Create Task"
      }
    ],
    "needs_confirmation": true
  }
}
```

---

# Proposal Refinement

Fluxio supports iterative proposal refinement.

Example:

User:

```text
Schedule a call with Rossini
```

System:

```text
draft proposal
missing date/time
```

User:

```text
Tomorrow morning
```

Expected behavior:
- refine the existing proposal
- increase confidence
- reduce missing fields
- move toward execution readiness

Fluxio is NOT designed as a generic chatbot.

Conversation exists only to refine structured business proposals.

---

# Confidence UX

Fluxio never pretends certainty.

Low-confidence proposals are expected and intentionally surfaced in the UI.

Examples:
- ambiguous entities
- incomplete commands
- weak context
- unknown intent

The system should:
- expose uncertainty
- encourage review
- avoid hallucinated confidence

---

# API Design

Fluxio exposes a standardized JSON API designed for:
- frontend applications
- integrations
- future AI providers
- automation layers

---

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

# Exception Handling

Fluxio standardizes framework-level exception rendering.

Handled cases:
- ValidationException → 422
- AuthenticationException → 401
- AuthorizationException → 403
- ModelNotFoundException → 404
- NotFoundHttpException → 404
- Generic exceptions → 500

Production environments never expose internal exception details.

---

# Frontend Direction

Fluxio is NOT dashboard-first.

The frontend is proposal-centric and command-driven.

Current UI structure:
- left sidebar
- central command composer
- live parsing feedback
- recent commands
- context panels
- right-side proposal rail
- editable proposal fields
- missing information panels
- execution results

The interface should feel like:
- a business copilot
- an operational workspace
- a controlled execution system

NOT:
- a generic AI chat
- a classic ERP full of forms

---

# Current Frontend Flow

```text
Login
→ Command input
→ Interpret
→ Action Proposal
→ Confirm
→ Execute
→ Execution Result
```

---

# Frontend UX Principles

Fluxio follows:
- AI-first interaction
- proposal-centric workflows
- explicit execution confirmation
- minimal operational friction

CRM data supports the proposal flow.
It must not dominate the interface.

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
- i18n
- TypeScript

---

# Localization

Fluxio is multilingual from the beginning.

Rules:
- backend uses Laravel translation files
- frontend uses i18n
- no hardcoded user-facing strings

Primary language:
- English

Additional languages:
- Italian
- German progressively

---

# Current Project Status

Fluxio is in active development.

The backend MVP and the first command-first frontend vertical slice are implemented.

---

# Implemented

## Backend

- Modular project structure
- Core API response layer
- Centralized exception handling
- Sanctum authentication
- Leads module
- Tasks module
- Actions module
- Proposal persistence
- Proposal confirmation flow
- Idempotent proposal execution
- Task creation from proposals

---

## Frontend

- Login/logout flow
- Auth composable
- Command composer
- Proposal rail
- Proposal states
- Editable fields rendering
- Missing information rendering
- Confirm & execute flow
- Execution result rendering
- Confidence UX
- Low-confidence warnings
- Recent command history
- Dark SaaS UI foundation

---

# Not Yet Implemented

- Advanced conversational refinement
- Entity disambiguation UX
- Multi-step proposal flows
- Full calendar workflows
- Notification orchestration
- Advanced analytics
- Multi-user collaboration
- Production-grade AI provider abstraction

---

# LLM Strategy

Fluxio is deterministic-first and testable-first.

The MVP uses:
- rule-based interpretation
- explicit validation
- predictable proposal generation

Future LLM support may assist:
- intent detection
- proposal refinement
- ambiguity resolution
- entity extraction

However:
- all output must be validated
- proposals remain structured
- confirmation remains mandatory
- AI never directly executes business actions

Possible future direction:
- local lightweight models
- Ollama
- Qwen
- provider abstraction

---

# Getting Started

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- PostgreSQL
- Nginx (recommended for local HTTPS setup)

---

# Installation

## Clone repository

```bash
git clone https://github.com/anfibes/fluxio.git
cd fluxio
```

---

# Backend Setup

```bash
cd apps/api

composer install

cp .env.example .env

php artisan key:generate

php artisan migrate
```

Configure PostgreSQL credentials inside `.env`.

Run backend:

```bash
php artisan serve
```

Default backend URL:

```text
http://localhost:8000
```

---

# Frontend Setup

```bash
cd apps/web

npm install
```

Create:

```text
apps/web/.env
```

Example:

```env
NUXT_PUBLIC_API_BASE=http://localhost:8000/api
```

For local Nginx HTTPS environments:

```env
NUXT_PUBLIC_API_BASE=https://fluxio.test/api
```

Run frontend:

```bash
npm run dev
```

Default frontend URL:

```text
http://localhost:3000
```

---

# Development Philosophy

Fluxio prioritizes:
- architecture clarity
- explicit proposal lifecycle
- controlled execution
- maintainable modular design
- AI-assisted workflows without hidden automation

The project intentionally evolves in:
- small
- testable
- verifiable
steps.

---

# Project Goal

Fluxio is currently NOT production-ready.

The project exists to demonstrate:
- modern backend architecture
- modular monolith design
- AI-first business UX
- proposal-driven workflows
- controlled natural-language execution
- future-ready enterprise interaction models

---

# License

MIT