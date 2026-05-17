# Fluxio Architecture

---

# Purpose

Fluxio is an open-source proposal-driven operational CRM prototype.

Core hypothesis:

```text id="qg5d0y"
Business systems can evolve from CRUD-first workflows
toward validated Action Proposal workflows.
```

Fluxio focuses on:
- proposal continuity
- deterministic execution
- ambiguity-aware workflows
- explainable operational interaction
- refinement-oriented UX

The project is intentionally:
- architecture-first
- proposal-centric
- deterministic-first
- operational

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

```text id="ykm8t1"
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

# Deterministic Operational Strategy

Fluxio follows a deterministic-first strategy.

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
- proposal mutation semantics

WITHOUT requiring LLM infrastructure.

LLMs are optional interpretation assistants, not operational authorities.

---

# Proposal Mutation Semantics

Fluxio supports incremental proposal mutation.

The system tracks:
- what changed
- which fields mutated
- what remains unchanged
- readiness changes
- ambiguity changes

Current supported refinement behaviors:
- add information
- replace information
- correct information
- resolve ambiguity
- fill missing fields
- mutate collections

Examples:

```text id="p8m5wq"
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

```text id="4g2ghq"
Proposal continuity alone is not enough.
Fluxio also requires proposal mutation semantics.
```

---

# Proposal Continuity

Refinement updates the SAME proposal instead of creating disconnected conversational state.

Example:

```text id="x5zjfa"
Schedule a call with Rossini
```

Refinement:

```text id="h0b4rb"
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

```text id="u7f5h1"
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

Fluxio includes a dedicated Entity Resolution Layer inside:

```text id="8vmd5l"
packages/Actions/src/EntityResolution/
```

Implemented:
- `EntityResolverInterface`
- `EntityResolverRegistry`
- `ResolutionContext`
- `ResolutionCandidate`
- `ResolutionResult`
- `LeadEntityResolver`
- `InMemoryLeadRepository`

Current scoring tiers:
- `1.0` → exact match
- `0.8` → starts-with
- `0.65` → word-boundary contains

Auto-resolve rules:
- exactly one candidate
- confidence ≥ `0.8`

Single low-confidence matches remain ambiguous and require explicit selection.

Current separation of concerns:
- intent interpretation
- entity extraction
- entity resolution
- proposal building

Planned additional resolvers:
- UserResolver
- ProductResolver
- CalendarParticipantResolver

Future-ready for:
- semantic search
- hybrid AI assistance
- local inference
- vector search

without requiring them today.

---

# Modular Monolith

Fluxio is designed as a modular monolith.

Principle:

```text id="s29o8f"
Modularize first, microservice later.
```

Goals:
- explicit boundaries
- simple deployment
- isolated domains
- future extraction capability

Current structure:

```text id="p4v3fg"
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
- workflow-oriented

NOT dashboard-first.

---

# Frontend UX Direction

The UI should feel like:

```text id="8i2l9r"
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

```text id="r8k6rj"
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

```text id="9bx1fa"
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

```text id="8k8w5j"
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
- proposal-aware workflows
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
- interpretation provider abstraction
- normalized command validation
- entity resolution layer

Current intents:
- `create_task`
- `schedule_call`
- `schedule_meeting`
- `assign_lead`
- `prepare_contract_from_quote`

Execution always requires explicit confirmation.

---

# NormalizedCommand Validation Layer

Every `NormalizedCommand` produced by a provider is validated before entering the proposal pipeline.

Flow:

```text
InterpretationProvider
→ NormalizedCommand
→ NormalizedCommandValidator
→ ActionInterpreterService
→ Entity Resolution
→ Proposal Builder
→ ActionProposalData
```

Structure:

```text
packages/Actions/src/Validation/
  NormalizedCommandValidator.php
  NormalizedCommandValidationResult.php

packages/Actions/src/Exceptions/
  InvalidNormalizedCommandException.php
```

Validation rules:
1. intent must exist or be `unknown`
2. confidence must be within `[0.0, 1.0]`
3. entity values must not be null or empty
4. entity keys must match `EntityRequirement` definitions or transitional parser keys

Invalid provider output throws:

```text id="3p9e8m"
InvalidNormalizedCommandException
```

which becomes a safe `422` response.

Important distinction:
- invalid command structure → validation failure
- missing required entities → valid command, draft proposal

Future LLM providers must produce structurally valid `NormalizedCommand` payloads.

---

# Interpretation Sandbox Layer

Fluxio has an explicit provider abstraction for interpretation.

Flow:

```text
InterpretationProviderInterface
→ NormalizedCommand
→ ActionInterpreterService
→ Entity Resolution
→ Proposal Builder
→ ActionProposalData
```

Structure:

```text
packages/Actions/src/Interpretation/
  Contracts/InterpretationProviderInterface.php
  DTO/InterpretationContext.php
  Providers/DeterministicInterpretationProvider.php
  Providers/FakeLlmInterpretationProvider.php
  InterpretationProviderAdapter.php
```

`InterpretationProviderInterface`:

```php
public function interpret(
    string $text,
    InterpretationContext $context
): NormalizedCommand;
```

Current providers:
- `DeterministicInterpretationProvider`
- `FakeLlmInterpretationProvider` (sandbox/test only)

Providers produce ONLY:
- `NormalizedCommand`

Providers do NOT:
- create proposals
- resolve entities
- decide readiness
- execute business actions

`ActionInterpreterService` remains authoritative for:
- entity resolution
- proposal construction
- ambiguity generation
- lifecycle state

Real LLM integration is intentionally not implemented.

---

## Current Runtime Limitation

`InterpretationContext` already exists, but runtime interpretation still passes through:

```php
CommandInterpreterInterface::interpret(string $text)
```

Because the legacy interface does not accept context, `InterpretationProviderAdapter` currently creates a default empty context.

This is acceptable for now because Fluxio does not yet require:
- locale-aware interpretation
- timezone-aware interpretation
- tenant context
- voice metadata
- runtime CRM context

When those become necessary, `ActionInterpreterService` should depend directly on `InterpretationProviderInterface`.

That migration must preserve:
- `NormalizedCommand`
- proposal lifecycle semantics
- entity resolution behavior
- ambiguity payload shape
- refinement semantics
- frontend contract compatibility

---

# Intent Requirements Model

Each intent declares requirements explicitly through `EntityRequirement`.

Fields:
- `key`
- `entityType`
- `label`
- `required`
- `cardinality`
- `resolverRequired`

`IntentComplexity`:
- `simple`
- `domain`
- `workflow` (reserved)

`ConfirmationPolicy`:
- `required`
- `strong` (reserved)
- `optional` (reserved)

Current architecture prepares future:
- generic resolver orchestration
- richer workflows
- stronger validation semantics

without changing the current lifecycle.

---

# Action Proposal Lifecycle

Current lifecycle:

```text id="c5zx1v"
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
- future providers

Example:

```json id="4l3nh5"
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

```json id="9ehm8t"
{
    "success": true,
    "message": "Operation completed successfully.",
    "data": {}
}
```

Error:

```json id="gm4vfy"
{
    "success": false,
    "message": "Error message.",
    "errors": {}
}
```

Paginated:

```json id="t1u3ol"
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

```php id="1sj34j"
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

```text id="9tr0q0"
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
- deterministic execution
- structured refinement

Current implemented flows include:
- contextual refinements
- collection mutations
- proposal continuity
- ambiguity resolution
- operational execution workflows

The project has already moved beyond:

```text id="g7m8u9"
simple parser demo territory
```

---

# Current Development Direction

Current focus:
- resolver expansion
- multilingual-ready parsing
- confidence scoring evolution
- operational mobile workflows
- proposal mutation evolution
- interpretation provider boundaries

NOT:
- CRUD expansion
- dashboard accumulation
- autonomous AI behavior

---

# Long-Term Vision

Fluxio aims to demonstrate that enterprise software can evolve toward:
- proposal-centric UX
- ambiguity-aware workflows
- deterministic business interaction
- explainable natural-language orchestration

The goal is NOT:

```text id="1qz0ps"
building another CRM
```

The goal is:

```text id="4s6fwi"
validating proposal-centric operational software
as a viable interaction model for enterprise systems.
```