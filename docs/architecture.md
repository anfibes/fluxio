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

Current mutation operations (the structural `operation` stored on a mutation):
- replace
- append
- remove
- clear

A collection item swap is a `replace` carrying a `target` (the item to replace).

Each recorded change can also carry a descriptive `semantic_type` that names the
operational meaning of the mutation — `replace_time`, `replace_date`,
`shift_time`, `add_participant`, `remove_participant`, `replace_participant`, or
`unknown`. This is explainability metadata surfaced in
`last_refinement.changes[].semantic_type`; it never affects lifecycle,
execution, capability legality, or persistence. See the Proposal Lifecycle doc
for the full taxonomy.

Relative temporal refinements ("push it by 30 minutes", "one hour earlier") are
resolved deterministically against a read-only, proposal-scoped
`ProposalRuntimeContext` derived from the current proposal. The interpreter stays
stateless; the refinement service resolves the shift into a concrete time replace
using that context, and never invents a time when none is present. The runtime
context is a deterministic snapshot of one proposal — not conversational or
assistant memory, never persisted separately.

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

Refinement against a blocking ambiguity resolves it (single match), progressively
narrows it (a type clarification like "the company" that still matches several
candidates replaces the active candidate set but keeps it blocking, with a
warning naming what remains), or leaves it unchanged. Narrowing is deterministic
and idempotent and changes only the active candidate set and the warning — never
resolver scoring, candidate generation, or the ambiguity payload shape. The
detailed outcomes are in the Proposal Lifecycle doc.

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

The extraction/resolution boundary is explicit: command interpretation preserves the
full user-facing entity reference span (it emits `lead_query`, e.g. the whole
"Mario Rossi"), and it never normalizes that reference down to a token. Matching,
scoring, ambiguity generation, auto-resolution, and candidate identity belong to the
resolver — the interpreter never emits a `lead_id` / `selected_candidate_id`. A richer
span lets the resolver exact-match where a reduced one would have produced a spurious
ambiguity; a genuinely short reference still resolves to a blocking ambiguity.

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
  ProposalCapabilitiesPanel
  ProposalRefinementHints

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

Implemented:
- intent capability model
- proposal-scoped mutation validation

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

The active provider is selected by config
(`actions.interpreter.provider`, env `ACTIONS_INTERPRETATION_PROVIDER`):
- `deterministic` → `DeterministicInterpretationProvider` (default, authoritative)
- `ollama` → `OllamaInterpretationProvider` (opt-in sandbox)
- any other value fails fast with `InvalidArgumentException`

`FakeLlmInterpretationProvider` also exists for tests/provider-swap checks
but is not part of the config selection. Exactly one provider is active per
request — there is no hybrid mode and no fallback chain.

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

The deterministic provider is the default and authoritative runtime path.
An opt-in `OllamaInterpretationProvider` (sandbox) can be selected, but it is
**not** production-authoritative: it only produces a candidate
`NormalizedCommand` that still passes through the same validation and proposal
lifecycle.

The LLM transport and contract boundaries live in
`packages/Actions/src/Llm/` and are consumed only when the Ollama provider (or
the development-only diagnostics) are exercised:

```text
packages/Actions/src/Llm/
  Contracts/LlmClientInterface.php
  DTO/LlmRequest.php
  DTO/LlmResponse.php
  Clients/OllamaLlmClient.php
  Prompting/InterpretationPromptBuilder.php
  Validation/LlmStructuredOutputValidator.php
  Validation/LlmStructuredOutputValidationResult.php
  Exceptions/LlmTransportException.php
  Exceptions/InvalidLlmResponseException.php
  Exceptions/InvalidLlmStructuredOutputException.php
```

Pieces:

- `LlmClientInterface` — generic, Fluxio-agnostic transport boundary.
  `OllamaLlmClient` is the first adapter (Ollama `POST /api/generate`).
  Tests use `Http::fake()`; no real LLM calls are made.
- `LlmStructuredOutputValidator` — validates the *parsed JSON* a future
  LLM provider would emit, *before* any conversion to `NormalizedCommand`.
  Enforces allowed/forbidden root keys, intent compatibility against
  `IntentRegistry`, intent-aware entity-key compatibility, scalar/list
  entity values, and the `unknown` intent contract. Raises
  `InvalidLlmStructuredOutputException` on contract violations.
- `InterpretationPromptBuilder` — builds the strict Ollama prompt from
  `IntentRegistry` (intent list and allowed entity keys enumerated
  dynamically, never hardcoded).
- Configuration (`packages/Actions/config/actions.php`) carries the active
  interpretation provider (`actions.interpreter.provider`) and the transport
  settings (`actions.llm.*`). Selecting `ollama` opts into the sandbox
  provider; the default stays `deterministic`.

When the Ollama provider is selected, the active pipeline is:

```text
LlmClientInterface
→ LlmResponse::parsedJson
→ LlmStructuredOutputValidator   (provider-level contract, fail-closed)
→ OllamaInterpretationProvider builds NormalizedCommand
→ NormalizedCommandValidator     (existing boundary)
→ ActionInterpreterService       (authoritative — unchanged)
```

The phased rollout is described in
[`.docs/llm-interpretation-contract.md`](../.docs/llm-interpretation-contract.md).
Phases 2–3 (transport + structured-output validation), Phase 4
(`OllamaInterpretationProvider` sandbox, opt-in) and Phases 5A–5C
(comparison, corpus evaluation, drift metrics — development-only Artisan
tooling) are done. Runtime hybrid interpretation remains planned; the
runtime stays single-provider with deterministic as the default.

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

# Intent Capability Layer

The Intent Capability Layer sits between the refinement interpreter output and proposal mutation application.

Its role is mutation legality enforcement — not workflow orchestration, not execution planning.

## Refinement flow with capability validation

```text
Refinement text
→ RefinementInterpreter
→ NormalizedMutation[]
→ IntentCapabilityRegistry (allowsMutation per intent)
→ Allowed mutations → Mutation Engine → Proposal updated
→ Rejected mutations → Warning added, proposal unchanged
```

## Separation of concerns

The interpreter detects what the user said.
The capability layer enforces what the intent semantically permits.

These are deliberately separate:
- a mutation may be syntactically valid (interpreter detects it)
- but semantically disallowed for that intent (capability denies it)

Example: the interpreter correctly detects "Add Mario too" as a participant append.
For `create_task`, `IntentCapabilityRegistry.allowsMutation()` returns false.
The mutation is rejected, a warning is added, and the proposal remains unchanged.

## IntentCapabilityRegistry

`IntentCapabilityRegistry` is a legality oracle — NOT a workflow engine, NOT an orchestrator.

Responsibilities:
- `register(IntentCapability)` — registers a capability at boot
- `find(string $intent)` — lookup by intent key
- `allowsMutation(string $intent, NormalizedMutation)` — returns bool

Deny-by-default: if no capability is registered for an intent, all mutations are denied.

## Capability declarations

Each `IntentCapability` declares:
- `mutations` — list of `MutationCapability` entries (operation + fields + collection flag)
- `refinements` — list of `RefinementCapabilityType` enum cases
- `supportsAmbiguityResolution` — bool, gates the ambiguity resolution code path
- `supportsCollectionMutations` — bool
- `supportsContextualReferences` — bool

`RefinementCapabilityType` cases:
- `ReplaceField`, `ClearField`
- `AppendCollectionItem`, `RemoveCollectionItem`, `ReplaceCollectionItem`
- `ResolveAmbiguity`, `ContextualReference`

## Ambiguity resolution gating

Ambiguity resolution is capability-scoped.

If a proposal has an unresolved blocking ambiguity AND the intent does not declare
`supportsAmbiguityResolution = true`, the resolution attempt is skipped entirely.
A warning is added; the proposal remains continuable.

**Capability/lifecycle invariant.** An intent that can *generate* a resolver-backed
blocking ambiguity MUST also support resolving it. Otherwise a proposal could become
structurally blocked yet operationally unresolvable (`draft` → blocking ambiguity →
no resolution capability → never confirmable). Concretely, any intent declaring an
`EntityRequirement` with `resolverRequired = true` whose entity type has a registered
resolver must declare `supportsAmbiguityResolution = true` and expose
`ResolveAmbiguity`. All five MVP intents (including `create_task`, whose lead is
optional but still resolver-backed) satisfy this. The invariant is enforced as a
runtime guardrail test (`AmbiguityCapabilityInvariantTest`) derived from the live
`IntentRegistry` + `EntityResolverRegistry`, so the skipped-resolution path above
applies only to intents that cannot emit a resolver-backed blocking ambiguity in the
first place.

## Static, deterministic, in-memory

Capability declarations are:
- registered once at provider boot
- never persisted to the database
- deterministic — same input always produces the same legality decision
- inspectable — `DefaultIntentCapabilities::all()` is the single source of truth

Structure:

```text
packages/Actions/src/DTO/IntentCapability.php
packages/Actions/src/DTO/MutationCapability.php
packages/Actions/src/Enums/RefinementCapabilityType.php
packages/Actions/src/Registry/IntentCapabilityRegistry.php
packages/Actions/src/Support/DefaultIntentCapabilities.php
```

## Capability-driven frontend UX

Capability declarations are surfaced to the frontend through the proposal
payload (`capabilities` block). The proposal rail consumes them in two
read-only components:

- `ProposalCapabilitiesPanel` — renders the supported operations, fields and
  refinement labels for the current intent.
- `ProposalRefinementHints` — derives compact, contextually-gated guidance
  (missing fields, resolvable blocking ambiguities, allowed refinements) from
  capabilities + proposal state. The frontend is intent-agnostic: it reads
  capability flags and labels, never branches on `proposal.intent`.

## Localized capability labels

The capability payload exposes both technical keys and localized
human-readable labels. Serialization is centralized in
`Fluxio\Actions\Http\Resources\IntentCapabilityResource`, which resolves
labels through the `actions::actions.capabilities.*` translation namespace
(operations, fields, refinements, plus an intent-specific override map).

Locale selection follows the request: `Fluxio\Core\Http\Middleware\SetApiLocale`
parses `Accept-Language` and sets the Laravel locale (supported: `en`, `it`;
fallback: `en`). The frontend sends the active i18n locale via `useApi`.

The backend owns operational semantics and labels; the frontend renders what
it receives.

## Refinement warning deduplication

`ActionProposalRefinementService::addWarning()` centralizes warning append
logic and is idempotent: repeated refinements that fail for the same reason
(unsupported mutation, ambiguity resolution not supported, etc.) do not
accumulate duplicate warnings on the proposal.

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
- execution remains explicit (confirmation is mandatory)
- execution is atomic and runs at most once
- execution terminates in exactly one outcome: `executed` (typed result) or
  `failed` (typed, sanitized failure)

Execution is a deterministic runtime authority (`ActionExecutionService`): it is the
only path that produces business side effects, runs only from a `confirmed`
proposal, locks the proposal row, re-checks status under the lock, and executes once.
A `ValidationException` raised at execution time (e.g. an ambiguous lead surfacing
in the executor) is a 422 that leaves the proposal `confirmed` — it is NOT a `failed`
terminal state. Only an unexpected executor error (or an unsupported intent) marks
the proposal `failed`, with the executor's partial side effects rolled back.

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
    "confirmed_at": null,
    "executed_at": null,
    "failed_at": null,
    "failure_reason": null,
    "failure_reason_code": null,
    "execution_failure": null,
    "execution_result": null,
    "last_refinement": null,
    "capabilities": {}
}
```

The proposal contract must remain:
- stable
- deterministic
- explainable
- frontend-friendly

Execution outcome fields are typed and consistent across intents:
- `execution_result` (on `executed`): `{ "summary": string, "details": { … } }` —
  one shape for every intent, instead of ad-hoc per-executor payloads.
- `execution_failure` (on `failed`): `{ "reason": "unsupported_intent" |
  "execution_failed", "message": string }` — a closed reason taxonomy plus a
  sanitized, localized message. `failure_reason` keeps the same message string for
  compatibility; `failure_reason_code` persists the reason. Raw exception text is
  never exposed.

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

Preferred (illustrative — the named event does not exist yet):

```php id="1sj34j"
event(new LeadFollowUpRequested($leadId, $dueAt));
```

instead of direct cross-domain mutations.

**Current state (honest):** this is a *design rule*, not an implemented mechanism.
No domain events are dispatched today and there are no `Events/` directories. The
MVP executors still call other modules' models directly — e.g.
`CreateTaskActionExecutor` calls `Task::create(...)` and `Lead::where(...)`. This is
an accepted transitional simplification for the MVP; the boundaries are kept crisp
so the direct calls can later be replaced by events/listeners without reshaping the
modules.

---

# LLM Strategy

Fluxio is deterministic-first.

Default runtime interpretation:
- rule-based parsing (`DeterministicInterpretationProvider`)
- explicit validation (`NormalizedCommandValidator`)
- deterministic refinement
- explainable proposal state

LLM sandbox (opt-in via `actions.interpreter.provider=ollama`; not the default):
- candidate interpretation (`OllamaInterpretationProvider`,
  `InterpretationPromptBuilder`)
- generic transport boundary (`LlmClientInterface`, `OllamaLlmClient`)
- provider-level JSON contract validation
  (`LlmStructuredOutputValidator`)
- development-only comparison/corpus/drift diagnostics (Artisan tooling)

When the Ollama provider is active it runs **after** that sandbox validation
and **before** `NormalizedCommandValidator`. LLM interpretation may assist:
- intent detection
- entity extraction
- ambiguity disambiguation hints
- refinement interpretation

It is not production-authoritative; the deterministic provider stays the
default and the proposal lifecycle is unchanged either way.

Possible future runtime providers:
- Ollama
- Qwen
- local lightweight models

Invariants that hold across all phases:
- AI output is schema-validated twice (LLM contract, then `NormalizedCommand`)
- proposals remain authoritative
- mutation legality stays gated by `IntentCapabilityRegistry`
- confirmation remains mandatory
- AI NEVER executes business actions directly

Core principle:

```text id="9tr0q0"
LLM = interpretation assistant
Fluxio = operational control system
```

The detailed contract that any future LLM provider must satisfy — JSON output
shape, allowed intents/entities, validation rules, fallback strategy and
phased rollout — lives in [`.docs/llm-interpretation-contract.md`](../.docs/llm-interpretation-contract.md).

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