# Fluxio Architecture

---

# Purpose

Fluxio is an open-source CRM/ERP prototype focused on proposal-driven business operations.

The project explores how enterprise software can evolve from:
- forms
- dashboards
- manual workflows

toward:
- natural-language intent
- structured action proposals
- controlled refinement
- validation-first execution
- explainable operational AI

Fluxio is intentionally:
- architecture-first
- proposal-centric
- deterministic-first
- operational

The core idea is simple:

```text
Natural language should never directly execute business actions.
```

Every operation must pass through a structured proposal lifecycle.

---

# Core Interaction Model

Fluxio follows this flow:

```text
Natural language input
→ Intent resolution
→ Entity extraction
→ Action Proposal
→ Proposal refinement
→ Validation
→ User confirmation
→ Execution
```

Natural language only:
- creates proposals
- refines proposals
- resolves ambiguities
- improves confidence

Execution always requires:
- validation
- explicit confirmation
- deterministic orchestration

The proposal is the central architectural object.

---

# Proposal-Driven Architecture

Fluxio is NOT:
- a generic chatbot
- an autonomous agent framework
- a CRUD-heavy ERP
- a dashboard-first system

Fluxio is:
- proposal-centric
- operational
- explainable
- controlled
- refinement-oriented

Conversation exists only to improve proposals.

The system intentionally avoids:
- assistant prose
- conversational clutter
- infinite timelines
- hidden automation
- fake certainty

---

# Deterministic Operational AI

Fluxio follows a deterministic-first AI strategy.

The system must:
- expose uncertainty
- preserve explainability
- avoid hallucinated confidence
- maintain operational trust

Core principles:
- proposals are structured
- ambiguities are explicit
- confidence is visible
- execution is controlled
- proposal continuity is preserved

---

# Proposal Continuity

Fluxio supports stateful proposal continuity.

Refinement updates the SAME proposal instead of creating disconnected conversational steps.

Example:

```text
Schedule a call with Rossini
```

System:
- creates draft proposal
- detects missing information

User refinement:

```text
Tomorrow morning
```

Expected behavior:
- refine existing proposal
- improve confidence
- reduce missing fields
- preserve proposal identity

Proposal continuity is one of Fluxio’s main architectural differentiators.

---

# Proposal Mutation Transparency

Fluxio intentionally exposes proposal mutations.

The UI should clearly communicate:
- what changed
- why it changed
- how confidence evolved
- what still blocks execution

Example:

```text
Last update
"Tomorrow morning"

Date → 2026-05-10
Time → 09:00
Status → ready
```

This interaction model is intentionally:
- compact
- operational
- deterministic
- explainable

NOT:
- conversational
- assistant-oriented
- chat-driven

---

# Ambiguity Resolution

Fluxio treats ambiguity as a first-class operational concept.

The system must NEVER:
- silently choose entities
- hallucinate certainty
- auto-resolve business ambiguity

Example:

```text
Call Rossi
```

Possible matches:
- Mario Rossi
- Rossi SRL
- Studio Rossi

Expected behavior:
- proposal remains incomplete
- ambiguity becomes explicit
- candidates are surfaced structurally
- refinement updates the SAME proposal

Ambiguity is part of the proposal lifecycle.

---

# Modular Monolith

Fluxio is designed as a modular monolith.

Principle:

```text
Modularize first, microservice later.
```

Goals:
- simple deployment
- explicit internal boundaries
- isolated domains
- future extraction capability

Current structure:

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

Each module owns:
- models
- services
- actions
- migrations
- routes
- events
- listeners
- translations
- business rules

---

# Applications

## apps/api

Laravel backend application.

Responsibilities:
- expose APIs
- orchestrate modules
- handle authentication
- manage proposal lifecycle
- execute validated actions
- expose standardized responses

Controllers must remain thin.

Business logic belongs in:
- services
- actions
- jobs
- domain classes

---

## apps/web

Nuxt frontend application.

Responsibilities:
- command-first UX
- proposal rendering
- refinement workflows
- ambiguity resolution UX
- mutation visibility
- execution visualization
- confidence rendering
- operational interaction flow

The frontend is intentionally:
- proposal-centric
- operational
- AI-first

NOT dashboard-first.

---

# Frontend Architecture & UX Principles

## UX Direction

The UI should feel like:
- a business copilot
- a structured operational workspace
- a controlled execution environment

NOT:
- a chatbot
- a consumer AI tool
- a traditional ERP

Current visual direction:
- dark enterprise SaaS
- compact typography
- restrained motion
- minimal chrome
- proposal-focused layout

Inspirations:
- Linear
- Notion
- modern European B2B SaaS

---

## State Management

Frontend principles:
- composables orchestrate logic
- visual components remain presentational
- API calls stay outside components
- proposal lifecycle remains centralized
- avoid premature global stores

Current orchestration composables:
- `useAuth()`
- `useApi()`
- `useActionProposal()`
- `useTheme()`

---

## Proposal Orchestration

Current frontend flow:

```text
Command input
→ Interpret
→ Proposal
→ Refinement
→ Confirmation
→ Execution
→ Execution Result
```

Current `useActionProposal()` responsibilities:
- interpret proposals
- refine proposals
- resolve ambiguities
- confirm proposals
- execute proposals
- preserve proposal continuity
- centralize proposal lifecycle state

---

## Current Frontend Milestones

Implemented:
- proposal rail
- refinement rendering
- ambiguity rendering
- mutation visibility
- confidence UX
- contextual placeholders
- operational reset flow
- responsive mobile shell
- dark/light/system theme support

Current UX direction:
- operational
- compact
- mobile-capable
- proposal-driven

---

# Current Frontend Components

```text
layout/
  AppSidebar
  AppTopbar

command/
  CommandComposer
  LiveParsingFeedback
  RecentHistory

proposal/
  ActionProposalRail
  ProposalStatusBanner
  AmbiguityPanel
  EditableProposalField
  MissingInformationPanel
  ProposedChangesList
  LastRefinementPanel
  ExecutionResultPanel

context/
  ContextTabs
```

The proposal rail is the central UI object.

It acts as:
- proposal inspector
- lifecycle viewer
- ambiguity resolver
- mutation summary
- operational truth surface

---

# Package Structure

Typical module structure:

```text
src/
  Http/
    Controllers/
    Requests/
    Resources/
  Models/
  Services/
  Actions/
  DTO/
  Events/
  Listeners/
  Jobs/

database/
  migrations/

routes/
lang/
```

Folders should only exist when needed.

Avoid architectural noise.

---

# Modules

## Core

Shared infrastructure.

Responsibilities:
- base API controller
- response standard
- exception rendering
- shared contracts
- shared helpers

The Core module must remain:
- stable
- lightweight
- reusable

---

## Identity

Authentication and user identity.

Implemented:
- Sanctum authentication
- login
- logout
- me endpoint

Planned:
- password reset
- OTP
- email verification

---

## Leads

Basic CRM lead management.

Implemented:
- CRUD
- pagination
- filtering
- search

Future:
- proposal integration
- ambiguity-aware entity workflows

---

## Tasks

Execution-oriented work items.

Implemented:
- CRUD
- filters
- lead relation
- protected endpoints

Tasks are currently the primary executable domain used by the Actions module.

---

## Actions

The most distinctive Fluxio module.

Responsibilities:
- parse natural language
- detect intent
- extract entities
- calculate confidence
- detect ambiguities
- detect missing information
- create proposals
- refine proposals
- preserve continuity
- track mutations
- validate lifecycle transitions
- execute confirmed actions

Implemented:
- rule-based intent resolver
- proposal persistence
- refinement flow
- ambiguity structure
- mutation tracking
- confirmation flow
- execution flow
- idempotent execution

Current intents:
- `create_task`
- `schedule_call`
- `unknown`

Execution always requires explicit confirmation.

---

# Action Proposal Lifecycle

Current lifecycle:

```text
draft
→ ready
→ confirmed
→ executed / failed
```

Meaning:
- `draft`
  incomplete proposal

- `ready`
  valid and confirmable proposal

- `confirmed`
  approved proposal awaiting execution

- `executed`
  successful execution

- `failed`
  execution failure

Rules:
- refinement does NOT create new proposals
- proposal IDs remain stable
- ready proposals may still be refined
- execution must remain explicit

---

# Action Proposal Contract

The `ActionProposal` is the central contract between:
- backend
- frontend
- future AI providers

Example:

```json
{
    "id": "proposal_uuid",
    "intent": "create_task",
    "status": "draft",
    "confidence": 0.74,
    "source_text": "Call Rossi",
    "entities": {},
    "missing": [],
    "warnings": [],
    "ambiguities": [
        {
            "key": "lead",
            "label": "Lead",
            "reason": "multiple_matches",
            "blocking": true,
            "selected_candidate_id": null,
            "candidates": [
                {
                    "id": 1,
                    "type": "person",
                    "label": "Mario Rossi"
                },
                {
                    "id": 7,
                    "type": "company",
                    "label": "Rossi SRL"
                }
            ]
        }
    ],
    "editable_fields": [],
    "changes": [],
    "needs_confirmation": true
}
```

The proposal contract must remain:
- stable
- deterministic
- explainable
- frontend-friendly

---

# API Response Standard

All API responses must use standardized structures.

Success:

```json
{
    "success": true,
    "message": "Operation completed successfully.",
    "data": {}
}
```

Error:

```json
{
    "success": false,
    "message": "Error message.",
    "errors": {}
}
```

Paginated:

```json
{
    "success": true,
    "message": "Data retrieved successfully.",
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100
    }
}
```

The frontend depends heavily on stable contracts because proposal payloads drive:
- proposal rendering
- refinement rendering
- ambiguity UX
- mutation visibility
- confidence UX
- execution state

---

# Exception Handling

Exception rendering is centralized.

Mappings:
- `ValidationException` → 422
- `AuthenticationException` → 401
- `AuthorizationException` → 403
- `ModelNotFoundException` → 404
- `NotFoundHttpException` → 404
- generic `Throwable` → 500

Production environments must never expose internal exception details.

---

# Localization

Fluxio is multilingual from the beginning.

Backend:
- Laravel translations
- translation keys
- no hardcoded messages

Frontend:
- i18n
- English-first
- Italian progressively
- German progressively

Documentation:
- English only
- concise technical language
- suitable for European B2B audiences

---

# Controller Rules

Controllers must remain thin.

Allowed:
- receive requests
- delegate orchestration
- return standardized responses

Avoid:
- business logic
- manual JSON construction
- cross-domain orchestration
- large methods

---

# Services and Action Classes

Business logic belongs in:
- services
- action classes
- jobs
- orchestration classes

Guidelines:
- services → orchestration
- actions → focused operations
- jobs → async execution

Avoid:
- god services
- hidden side effects
- multi-domain coupling

---

# Cross-Module Communication

Prefer:
- events
- listeners
- contracts
- DTOs

Avoid direct domain coupling.

Preferred:

```php
event(new LeadFollowUpRequested($leadId, $dueAt));
```

instead of:
- model-driven orchestration
- hidden side effects
- direct cross-domain mutations

---

# LLM Strategy

Fluxio is deterministic-first.

The MVP currently uses:
- rule-based parsing
- explicit validation
- predictable refinement flows

Future LLM support may assist:
- intent detection
- entity extraction
- ambiguity resolution
- proposal refinement

Possible future providers:
- Ollama
- Qwen
- local lightweight models

However:
- all AI output must be validated
- proposals remain structured
- confirmation remains mandatory
- AI NEVER executes business actions directly

---

# Testing Strategy

Testing priorities:
- proposal integrity
- proposal continuity
- refinement consistency
- ambiguity handling
- lifecycle transitions
- API contracts
- deterministic execution

Backend priorities:
- response standard
- exception rendering
- Actions parser
- confidence calculation
- proposal lifecycle
- refinement tracking
- idempotent execution

Frontend priorities:
- command UX
- proposal rendering
- ambiguity rendering
- confidence UX
- mutation visibility
- execution rendering
- API error handling

---

# Current MVP Scope

Current implemented vertical slice:

```text
Schedule a call with Rossini
→ refinement
→ ambiguity resolution
→ confirmation
→ execution
```

This validates:
- proposal continuity
- operational refinement
- ambiguity-aware workflows
- deterministic execution
- AI-first operational UX

Current focus:
- proposal continuity
- ambiguity resolution
- mutation transparency
- mobile operational UX
- controlled execution

NOT:
- CRUD expansion
- dashboard complexity
- autonomous AI behavior

---

# Long-Term Vision

Fluxio aims to demonstrate that enterprise software can evolve toward:

- proposal-centric UX
- controlled operational AI
- ambiguity-aware workflows
- deterministic business automation
- explainable natural-language interaction

The goal is not to build “another CRM”.

The goal is to validate a new operational interaction model for business software.