# Fluxio Architecture

---

# Purpose

Fluxio is an open-source CRM/ERP prototype exploring:

```text id="gij7a7"
proposal-driven enterprise software
```

Core hypothesis:

```text id="r2d1xz"
Business systems can evolve from CRUD-first workflows
toward validated Action Proposal workflows.
```

Fluxio focuses on:
- controlled operational AI
- deterministic execution
- proposal continuity
- ambiguity-aware workflows
- explainable business interaction

The project is intentionally:
- architecture-first
- proposal-centric
- operational
- deterministic-first
- refinement-oriented

Fluxio is intentionally NOT:
- a generic chatbot
- an autonomous agent framework
- dashboard-heavy ERP software
- prompt-wrapper infrastructure

---

# Architectural Invariants

These rules should never be violated.

- Natural language never executes business operations directly
- Proposal execution always requires explicit confirmation
- Refinements mutate existing proposals
- Proposal IDs remain stable across refinements
- Ambiguities remain explicit
- Proposal lifecycle remains deterministic
- AI never bypasses validation or execution control
- Proposal state remains explainable and inspectable

These invariants are foundational to Fluxio’s architecture.

---

# Core Interaction Model

Fluxio follows this operational flow:

```text id="w8q73g"
Natural language
→ Intent interpretation
→ Entity extraction
→ Entity resolution
→ Action Proposal
→ Validation
→ Proposal refinement
→ Confirmation
→ Execution
```

Natural language is used ONLY to:
- create proposals
- refine proposals
- resolve ambiguity
- improve confidence
- mutate proposal state

Execution always remains:
- validated
- explicit
- deterministic
- controlled

The proposal is the central architectural object.

---

# Controlled Operational Intelligence

Fluxio follows a deterministic-first operational AI strategy.

The system must:
- expose uncertainty
- preserve operational trust
- maintain explainability
- avoid hallucinated certainty
- keep execution explicit

Current architecture validates:
- proposal continuity
- refinement semantics
- ambiguity-aware workflows
- controlled execution
- proposal mutation intelligence

WITHOUT requiring LLM infrastructure.

LLMs are optional interpretation assistants, not operational authorities.

---

# Proposal Intelligence Architecture

Fluxio is evolving toward:

```text id="0kzy48"
Proposal Mutation Intelligence
```

The system must understand:
- what changed
- which fields mutated
- what remains unchanged
- whether readiness changed
- whether ambiguity changed

Current supported refinement behaviors:
- add information
- replace information
- correct information
- resolve ambiguity
- fill missing fields
- mutate collections

Examples:

```text id="5r2m2n"
Tomorrow morning
At 10:30
Friday instead
High priority
Add Mario too
Replace Luca with Marco
```

Current mutation operations:
- replace
- append
- remove
- clear
- replace + target

Key principle:

```text id="j04wdm"
Proposal continuity alone is not enough.
Fluxio also requires proposal mutation semantics.
```

---

# Proposal Continuity

Refinement updates the SAME proposal instead of creating disconnected conversational state.

Example:

```text id="svcb68"
Schedule a call with Rossini
```

Refinement:

```text id="yywv1j"
Tomorrow morning
```

Expected behavior:
- preserve proposal identity
- improve readiness
- preserve existing operational state
- mutate only affected fields

The system intentionally avoids:
- conversational timelines
- hidden memory
- assistant-style statefulness
- opaque AI reasoning

Conversation exists ONLY to improve proposals.

---

# Ambiguity Resolution

Ambiguity is treated as structured operational state.

The system must NEVER:
- silently choose entities
- hallucinate certainty
- auto-resolve business ambiguity

Example:

```text id="a6b44u"
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

# Entity Resolution Architecture

Fluxio now has a dedicated Entity Resolution Layer inside `packages/Actions/src/EntityResolution/`.

Implemented:
- `EntityResolverInterface` — `supports(string $entityType)` + `resolve(string $query, ResolutionContext $context): ResolutionResult`
- `EntityResolverRegistry` — routes queries to the first matching registered resolver
- `ResolutionContext` — carries entity type and locale
- `ResolutionCandidate` — scored match candidate (id, type, label, description?, confidence)
- `ResolutionResult` — sealed result: `autoResolved()` / `ambiguous()` / `noMatch()`
- `LeadEntityResolver` — deterministic word-boundary scoring for lead names
- `InMemoryLeadRepository` — demo dataset, injectable for tests

Scoring tiers (LeadEntityResolver):
- `1.0` — exact match (case-insensitive)
- `0.8` — label starts with query at word boundary
- `0.65` — query appears at word boundary within label

Auto-resolve rules:
- exactly one candidate
- confidence ≥ `AUTO_RESOLVE_THRESHOLD` (0.8)
- single low-confidence match surfaces as ambiguity requiring explicit selection

Separation of concerns:
- intent interpretation — `RuleBasedIntentResolver`
- entity extraction — produces `lead_query` tokens
- entity resolution — `EntityResolverRegistry` decides auto-resolve vs ambiguity

Planned additional resolvers:
- UserResolver
- ProductResolver
- CalendarParticipantResolver

Future-ready for:
- semantic search
- hybrid AI assistance
- local inference
- vector search

---

# Modular Monolith

Fluxio is designed as a modular monolith.

Principle:

```text id="ed8y79"
Modularize first, microservice later.
```

Goals:
- explicit boundaries
- simple deployment
- isolated domains
- future extraction capability

Current structure:

```text id="xf8yr0"
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
- DTOs
- actions
- migrations
- routes
- translations
- business rules

Avoid architectural noise.

---

# Applications

---

# apps/api

Laravel backend application.

Responsibilities:
- API exposure
- proposal orchestration
- authentication
- proposal lifecycle
- refinement orchestration
- ambiguity handling
- execution workflows
- standardized responses

Controllers must remain thin.

Business logic belongs in:
- services
- action classes
- jobs
- orchestration classes

---

# apps/web

Nuxt frontend application.

Responsibilities:
- command-first UX
- proposal rendering
- ambiguity workflows
- mutation visibility
- refinement continuity
- confidence visualization
- execution rendering
- operational interaction flow

The frontend is intentionally:
- proposal-centric
- operational
- AI-first

NOT dashboard-first.

---

# Frontend UX Direction

The UI should feel like:

```text id="o1m5h9"
Operational Copilot UX
```

The interface intentionally avoids:
- assistant bubbles
- infinite chat timelines
- verbose AI prose
- conversational clutter

Current visual direction:
- dark enterprise SaaS
- compact operational spacing
- restrained motion
- minimal chrome
- proposal-first composition

Inspirations:
- Linear
- Notion
- modern European B2B SaaS

---

# Frontend Architecture

Core principles:
- composables orchestrate logic
- components remain presentational
- API calls stay outside UI components
- proposal lifecycle remains centralized
- avoid premature global stores

Current orchestration composables:
- `useAuth()`
- `useApi()`
- `useActionProposal()`
- `useTheme()`

Current frontend flow:

```text id="yr9wnk"
Command input
→ Interpret
→ Proposal
→ Refinement
→ Confirmation
→ Execution
→ Execution Result
```

---

# Current Frontend Components

```text id="3i9goz"
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

`ActionProposalRail` acts as:
- proposal inspector
- ambiguity resolver
- mutation viewer
- lifecycle surface
- operational truth panel

---

# Package Structure

Typical package structure:

```text id="h26cxn"
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

---

# Modules

---

# Core

Shared infrastructure.

Responsibilities:
- API response standard
- exception rendering
- shared contracts
- shared helpers
- base API controller

The Core module must remain:
- stable
- lightweight
- reusable

---

# Identity

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

# Leads

CRM lead management.

Implemented:
- CRUD
- filtering
- pagination
- search

Future direction:
- entity resolution
- proposal-aware lead workflows
- ambiguity-aware CRM interaction

---

# Tasks

Execution-oriented work items.

Implemented:
- CRUD
- filters
- lead relation
- protected endpoints

Tasks currently represent the primary executable operational domain.

---

# Actions

The central Fluxio module.

Responsibilities:
- intent interpretation
- entity extraction
- confidence calculation
- ambiguity handling
- proposal creation
- proposal refinement
- proposal continuity
- mutation tracking
- lifecycle orchestration
- execution orchestration

Implemented:
- intent registry
- proposal persistence
- refinement semantics
- contextual mutations
- ambiguity structures
- collection mutations
- execution idempotency
- shared temporal parser

Current intents:
- `create_task`
- `schedule_call`
- `schedule_meeting`
- `assign_lead`
- `prepare_contract_from_quote`

Execution always requires explicit confirmation.

---

# Intent Requirements Model

Each intent in the registry declares its requirements explicitly as `EntityRequirement` objects instead of plain string arrays.

`EntityRequirement` fields:
- `key` — entity key used in proposal payload
- `entityType` — semantic type (e.g. `lead_query`, `date_expression`, `participant_query`)
- `label` — human-readable label used in missing/editable field payloads
- `required` — whether absence blocks readiness
- `cardinality` — `one` (default) or `many` for collection-type entities
- `resolverRequired` — whether an EntityResolver must run before the builder

`IntentComplexity` enum classifies intents:
- `simple` — single-step, single-domain (most current intents)
- `domain` — spans domain boundaries or requires cross-module resolution (e.g. `assign_lead`)
- `workflow` — multi-step sequences (reserved, not yet implemented)

`ConfirmationPolicy` enum controls confirmation requirements:
- `required` — user must confirm before execution (current default for all intents)
- `strong` — reserved for future high-impact operations
- `optional` — reserved for future low-risk operations

Helper methods on `IntentDefinition`:
- `requiredRequirements()` — list of required `EntityRequirement` objects
- `optionalRequirements()` — list of optional `EntityRequirement` objects
- `requiredKeys()` — required entity key strings
- `optionalKeys()` — optional entity key strings
- `requirementKeys()` — all entity key strings
- `findRequirement(string $key)` — lookup by key

This model prepares the architecture for generic entity resolution orchestration without changing the current proposal lifecycle or frontend contract.

---

# Action Proposal Lifecycle

Current lifecycle:

```text id="4mk9z4"
draft
→ ready
→ confirmed
→ executed / failed
```

Rules:
- refinement does NOT create new proposals
- proposal IDs remain stable
- ambiguities may block readiness
- ready proposals may still mutate
- execution remains explicit
- execution remains idempotent

---

# Action Proposal Contract

The `ActionProposal` is the central transport contract between:
- backend
- frontend
- future AI providers

Example:

```json id="03dfp2"
{
    "id": "proposal_uuid",
    "intent": "schedule_meeting",
    "status": "draft",
    "confidence": 0.74,
    "source_text": "Schedule a meeting with Rossi tomorrow morning",
    "entities": {},
    "missing": [],
    "warnings": [],
    "ambiguities": [],
    "editable_fields": [],
    "changes": [],
    "needs_confirmation": true,
    "last_refinement": null
}
```

The proposal contract must remain:
- stable
- deterministic
- explainable
- frontend-friendly

---

# API Response Standard

All API responses follow standardized envelopes.

Success:

```json id="cvs6g4"
{
    "success": true,
    "message": "Operation completed successfully.",
    "data": {}
}
```

Error:

```json id="2xmd2h"
{
    "success": false,
    "message": "Error message.",
    "errors": {}
}
```

Paginated:

```json id="09qhnr"
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

The frontend depends heavily on stable proposal contracts.

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
- English-only
- concise technical language

---

# Backend Rules

Controllers must remain thin.

Allowed:
- receive requests
- delegate orchestration
- return standardized responses

Avoid:
- business logic
- cross-domain orchestration
- large methods
- manual JSON construction

Business logic belongs in:
- services
- actions
- jobs
- orchestration classes

---

# Cross-Module Communication

Prefer:
- events
- listeners
- contracts
- DTOs

Avoid:
- direct domain coupling
- hidden side effects
- model-driven orchestration

Preferred:

```php id="z3xn9f"
event(new LeadFollowUpRequested($leadId, $dueAt));
```

instead of direct cross-domain mutations.

---

# LLM Strategy

Fluxio is deterministic-first.

Current implementation:
- rule-based parsing
- explicit validation
- deterministic refinement
- explainable proposal state

Future LLM support may assist:
- intent detection
- entity extraction
- ambiguity resolution
- refinement interpretation
- semantic search

Possible future providers:
- Ollama
- Qwen
- local lightweight models

However:
- AI output must remain schema-validated
- proposals remain authoritative
- confirmation remains mandatory
- AI NEVER executes business actions directly

Core principle:

```text id="hgl8vb"
LLM = interpretation assistant
Fluxio = operational control system
```

---

# Testing Strategy

Testing priorities:
- proposal continuity
- refinement consistency
- mutation tracking
- ambiguity handling
- lifecycle transitions
- API contracts
- deterministic execution

Backend priorities:
- proposal lifecycle
- mutation semantics
- refinement workflows
- execution idempotency
- API response consistency

Frontend priorities:
- proposal rendering
- ambiguity rendering
- mutation visibility
- confidence UX
- execution rendering
- API error handling

---

# Current MVP Position

Fluxio already validates:
- proposal continuity
- mutation semantics
- ambiguity-aware workflows
- operational AI-first UX
- deterministic execution
- structured refinement

Current implemented flows include:
- contextual refinements
- collection mutations
- proposal continuity
- ambiguity resolution
- operational execution workflows

The project has already moved beyond:
```text id="ppl8o1"
simple parser demo territory
```

---

# Current Development Direction

Current focus:
- entity resolution architecture
- multilingual-ready parsing
- resolver registry abstraction
- confidence scoring evolution
- operational mobile workflows
- proposal intelligence evolution

NOT:
- CRUD expansion
- dashboard accumulation
- autonomous AI behavior

---

# Long-Term Vision

Fluxio aims to demonstrate that enterprise software can evolve toward:

- proposal-centric UX
- controlled operational AI
- ambiguity-aware workflows
- deterministic business interaction
- explainable natural-language orchestration

The goal is NOT:
```text id="0c6o0z"
building another CRM
```

The goal is:

```text id="72t4vz"
validating proposal-centric operational software
as a viable future paradigm for enterprise systems.
```