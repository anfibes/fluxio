# Fluxio

Proposal-driven operational CRM/ERP prototype exploring controlled and explainable business execution through structured Action Proposals.

Fluxio is NOT:
- a chatbot
- an autonomous AI agent
- a dashboard-heavy ERP
- a generic AI wrapper

Fluxio is:
- proposal-centric
- ambiguity-aware
- refinement-oriented
- deterministic-first
- execution-controlled

---

# Core Idea

Traditional business software usually follows:

```text id="w7n2ps"
User → Form → Validation → Save
```

Many AI systems follow:

```text id="x4k8mb"
User → AI → Execute
```

Fluxio explores a different model:

```text id="q6v1rc"
User
→ Natural Language
→ Action Proposal
→ Proposal Refinement
→ Validation
→ Confirmation
→ Execution
```

Natural language is NEVER executed directly.

Every command becomes a structured proposal that must be:
- reviewable
- refinable
- explainable
- explicitly confirmed

before execution.

The proposal is the authoritative operational object.

---

# Architectural Invariants

These rules define Fluxio.

- Proposals are authoritative
- Natural language never executes directly
- Refinements mutate existing proposals
- Proposal continuity is preserved
- Ambiguities remain explicit
- Execution requires confirmation
- Proposal mutations remain explainable
- AI output remains advisory
- Execution stays deterministic

Fluxio intentionally prioritizes:
- operational clarity
- explainability
- controlled execution
- proposal transparency

over:
- blind automation
- assistant realism
- autonomous behavior

---

# Why Proposal-Driven UX

Fluxio treats ambiguity and uncertainty as operational states.

The system intentionally exposes:
- ambiguities
- missing information
- low-confidence interpretations
- proposal mutations
- execution consequences

The goal is NOT:

```text id="m5x9tn"
AI autonomy
```

The goal is:

```text id="d3q7vk"
safe, explainable and controllable
proposal-driven business interaction
```

---

# Proposal Lifecycle

Core lifecycle:

```text id="j8w4qs"
Natural Language
→ Intent Interpretation
→ Entity Extraction
→ Entity Resolution
→ Action Proposal
→ Proposal Refinement
→ Validation
→ Confirmation
→ Execution
```

Current proposal states:

```text id="u9m1pr"
draft
→ ready
→ confirmed
→ executed / failed
```

The SAME proposal evolves over time.

Conversation exists ONLY to improve proposal state.

---

# Proposal Mutation Semantics

Fluxio supports:

```text id="b7n4zk"
controlled proposal mutation semantics
```

Refinements are NOT generic chat replies.

Refinements mutate proposal state explicitly.

Current structural mutation operations (the closed application vocabulary):
- replace
- append
- remove
- clear

A targeted collection replace ("replace Mario with Marco") is a `replace` carrying
a target item, not a separate operation.

Examples:

```text id="h2q8tm"
Move it to Friday
At 10:30
Push it by 30 minutes
Add Mario too
Remove Luca
Replace Mario with Marco
```

Relative temporal refinements ("push it by 30 minutes") are resolved
deterministically against the proposal's current time — never invented.

Each tracked change can also carry a descriptive `semantic_type`
(`shift_time`, `replace_time`, `replace_date`, `add_participant`,
`remove_participant`, `replace_participant`, `replace_priority`, `clear_priority`) —
explainability metadata only, surfaced in `last_refinement.changes` and rendered in
the proposal rail.

Fluxio tracks:
- what changed
- what remained unchanged
- proposal continuity
- readiness transitions
- ambiguity evolution

The UX should communicate:

```text id="r5k1vx"
"The proposal evolved."
```

NOT:

```text id="f6z3mn"
"The assistant replied."
```

---

# Ambiguity-Aware UX

Fluxio treats ambiguity as a first-class operational concept.

The system never silently chooses entities.

Example:

```text id="n4t7wj"
Call Rossi
```

Possible matches:
- Mario Rossi
- Rossi SRL
- Studio Rossi

Instead of hallucinating certainty, Fluxio:
- exposes ambiguity
- blocks execution
- preserves proposal continuity
- supports refinement
- progressively narrows candidates (a clarification like "the company" that
  still matches several keeps the ambiguity blocking and reports what remains)

Example refinement flow:

```text id="p2x8qa"
Schedule a meeting with Rossi tomorrow morning
→ ambiguity detected

The second one
→ ambiguity resolved

Move it to Friday at 10:30
→ proposal mutated

Add Mario too
→ participant appended
```

---

# Current UX Direction

Fluxio frontend is intentionally:
- proposal-centric
- operational
- refinement-oriented
- confirmation-first
- future voice-friendly

Fluxio is NOT:
- a generic AI chat
- a conversational assistant
- an autonomous workflow engine

The proposal rail remains the operational center of the interface.

---

# UX Screenshots

## Initial Proposal Generation

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: Action01.png -->

![Fluxio Initial Proposal](docs/screenshots/action01.png)

---

## Proposal Refinement

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: Action02.png -->

![Fluxio Proposal Refinement](docs/screenshots/action02.png)

---

## Successful Execution

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: Action03.png -->

![Fluxio Execution Result](docs/screenshots/action03.png)

---

## Ambiguity Resolution

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen01.png -->

![Fluxio Ambiguity Resolution](docs/screenshots/screen01.png)

---

## Proposal Mutation Flow

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen02.png -->

![Fluxio Proposal Mutation](docs/screenshots/screen02.png)

---

## Ready Proposal

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen03.png -->

![Fluxio Ready Proposal](docs/screenshots/screen03.png)

---

## Executed Operational Flow

<!-- PLACEHOLDER SCREENSHOT -->
<!-- Use: screen04.png -->

![Fluxio Executed State](docs/screenshots/screen04.png)

---

# Architecture

## Modular Monolith

Fluxio is built as a modular monolith with explicit boundaries.

```text id="s4m7vb"
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
```

Core principle:

```text id="g1n8qk"
Modularize first, microservice later.
```

Each module owns:
- services
- models
- routes
- migrations
- business rules
- events

---

# Actions Module

The `Actions` module is the operational core of Fluxio.

Responsibilities:
- intent interpretation
- proposal lifecycle
- proposal mutation semantics
- ambiguity handling
- execution orchestration
- execution safety
- refinement tracking
- entity resolution
- mutation legality (intent capability model)

Current capabilities:
- proposal continuity
- contextual refinements
- relative temporal refinements (resolved against a read-only, proposal-scoped runtime context)
- collection mutations
- proposal-local references
- mutation summaries
- semantic mutation types (descriptive change metadata)
- temporal field explanations (refreshed after refinement)
- progressive ambiguity narrowing
- operational intent registry
- deterministic execution flows
- interpretation provider abstraction
- normalized command validation
- entity resolution layer
- intent capability model (per-intent mutation/refinement legality)
- localized capability labels in the proposal payload
- refinement warning deduplication
- typed, atomic execution (a deterministic execution authority: at-most-once,
  confirmed-only, transactional, with a typed result and a typed/sanitized failure)
- canonical proposal narration (an additive, read-only `canonical_phrase`: a
  deterministic, locale-aware sentence projected purely from proposal state —
  never authoritative, `null` for incomplete or unsupported proposals)
- declarative intent examples (a backend-only, read-only multilingual library of
  expression patterns per intent; describes phrasings, not behavior, and is not
  wired into the runtime)

---

# Example Operational Flow

Input:

```text id="x8k3pb"
Schedule a meeting with Rossi tomorrow morning
```

System:
- detects ambiguity
- extracts date/time
- creates proposal
- blocks execution

Refinement:

```text id="m4v1tn"
The second one
```

System:
- resolves ambiguity
- preserves proposal continuity
- proposal becomes `ready`

Refinement:

```text id="c7q5zx"
Move it to Friday at 10:30
```

System:
- mutates SAME proposal
- replaces date/time
- preserves unrelated fields

Execution:

```text id="u5n2wr"
Confirm
→ Execute
```

Result:
- operation executed
- execution result rendered
- proposal becomes immutable

---

# Entity Resolution Layer

Fluxio now includes a dedicated entity resolution architecture.

Current structure:

```text id="b9m6qv"
EntityResolverInterface
→ EntityResolverRegistry
→ Resolver implementations
→ ResolutionResult
```

Current implemented resolver:
- `LeadEntityResolver`

Current behaviors:
- deterministic scoring
- confidence ordering
- ambiguity generation
- auto-resolution thresholds
- proposal-scoped refinement

---

# Provider Validation Boundary

Every interpreted command is validated before entering the proposal lifecycle.

Current flow:

```text id="q3w8tp"
Interpretation Provider
→ NormalizedCommand
→ Validation Layer
→ Proposal Lifecycle
```

Current validation includes:
- intent validation
- confidence validation
- entity validation
- requirement compatibility checks

Malformed provider output is rejected before proposal creation.

This protects:
- proposal integrity
- frontend stability
- deterministic lifecycle semantics

---

# Cross-Module Communication

The architectural rule is that modules stay decoupled: cross-domain work should
flow through events/listeners and DTO contracts rather than direct calls into
another module's services or models.

Status — this is a **design rule, not yet a wired mechanism**. No domain events are
dispatched today; the MVP executors (e.g. `CreateTaskActionExecutor`) still call
domain models directly (`Task::create(...)`, `Lead::where(...)`). The event-driven
decoupling (e.g. an `ActionExecuted` event consumed by other modules) is planned,
not implemented. Keeping module boundaries crisp now is what makes that extraction
possible later.

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

# API Design

Fluxio exposes a standardized JSON API.

## Success Response

```json id="r6m2zc"
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

---

## Error Response

```json id="w1q7vn"
{
  "success": false,
  "message": "Error message."
}
```

---

## Validation Error

```json id="f8t4pk"
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["Validation message"]
  }
}
```

---

# Localization

Fluxio is multilingual from the beginning.

Current languages:
- English
- Italian

Planned:
- German

Current parser implementation remains English-first.

---

# Current Project Status

Fluxio already includes:
- modular backend architecture
- standardized API layer
- proposal lifecycle
- proposal mutation semantics
- ambiguity-aware refinement
- operational intent registry
- contextual mutations
- collection mutations
- proposal-local references
- deterministic execution flows
- proposal-centric frontend shell
- ambiguity-aware UX
- execution rendering
- responsive operational UI
- entity resolution layer
- interpretation provider abstraction
- normalized command validation

Current implemented operational intents:
- `create_task`
- `schedule_call`
- `schedule_meeting`
- `assign_lead`
- `prepare_contract_from_quote`

---

# Current Frontend

Implemented frontend capabilities:
- command composer
- proposal rail
- mutation rendering
- semantic refinement rendering (semantic_type → operational summaries)
- temporal field explanations (how a date/time value was derived)
- ambiguity rendering
- confidence-aware UX
- proposal continuity UX
- execution rendering
- contextual refinements
- capability panel (operational metadata from the backend)
- contextual refinement hints (gated by capabilities and proposal state)
- proposal canonical-phrase summary near the command input (renders the
  backend `canonical_phrase`; read-only, hidden when absent)
- locale-aware API calls (`Accept-Language`)
- responsive/mobile-safe shell
- dark/light/system themes
- i18n support

Frontend direction remains:
- proposal-centric
- operational
- deterministic-first
- non-chatbot

---

# Not Yet Implemented

- LLM-backed interpretation as the **default** runtime provider
  (an opt-in Ollama sandbox provider exists, but deterministic remains the
  default and authoritative path)
- Runtime hybrid interpretation (both providers in one request — comparison
  is currently diagnostics-only)
- Event-driven cross-module communication (a design rule; executors currently call
  domain models directly)
- Semantic entity search
- Advanced resolver ranking
- Voice workflows
- Multi-step orchestration
- Multi-user collaboration
- Advanced calendar coordination (availability, slot suggestions)
- Realtime collaboration
- Production deployment pipeline

---

# LLM Strategy

Fluxio is deterministic-first and validation-first.

By default, runtime interpretation uses:
- rule-based interpretation (`DeterministicInterpretationProvider`)
- deterministic proposal mutations
- structured validation (`NormalizedCommandValidator`)
- explicit execution control

An **opt-in** Ollama interpretation provider now exists as a sandbox,
selectable via `ACTIONS_INTERPRETATION_PROVIDER=ollama`:

- `OllamaInterpretationProvider` + `InterpretationPromptBuilder` — builds a
  strict prompt from the intent registry and produces a candidate
  `NormalizedCommand` (Phase 4)
- `LlmClientInterface` + `OllamaLlmClient` — generic LLM transport (Phase 2)
- `LlmStructuredOutputValidator` — provider-level JSON contract validation,
  fail-closed, before any `NormalizedCommand` is built (Phase 3)

Development-only diagnostics compare deterministic vs. Ollama interpretation
over versioned corpora (`actions:compare-interpretation`,
`actions:evaluate-interpretation-corpus`). These are Artisan-only, blocked in
production, and never run during the proposal runtime.

That diagnostics layer has since grown into a dedicated **evaluation harness** —
still observation-only, with no runtime, proposal, or execution authority: a
held-out 93-case Italian corpus, a multilingual example library used for opt-in
few-shot, capability-class model profiles, deterministic exemplar selection,
side-by-side model comparison, and append-only prompt-variant experiments
(`actions:observe-italian-corpus`). It measures whether a small local model could
interpret well; it does not make any model authoritative. See
[`.docs/diagnostics-architecture.md`](.docs/diagnostics-architecture.md) and
[`docs/llm-interpretation-evaluation.md`](docs/llm-interpretation-evaluation.md).

The deterministic provider remains the default and authoritative path. The
Ollama provider is a sandbox and is not production-authoritative. Any
LLM-assisted interpretation:

- assists interpretation only (intent / entity extraction)
- still goes through `LlmStructuredOutputValidator` and `NormalizedCommandValidator`
- still goes through the proposal lifecycle and `IntentCapabilityRegistry`
- never owns proposal state
- never executes, confirms, or mutates proposals directly

Core principle:

```text id="n2v5rk"
LLM assists interpretation.
Fluxio controls execution.
```

The detailed contract is in [`.docs/llm-interpretation-contract.md`](.docs/llm-interpretation-contract.md).

Possible future runtime providers:
- Ollama
- Qwen
- local lightweight models

---

# Documentation

Public, versioned documentation lives in `docs/`. Internal architectural
notes and working documents live in `.docs/`.

## Public documentation (`docs/`)

- [Architecture](docs/architecture.md)
- [Frontend Vision](docs/frontend-vision.md)
- [Proposal Lifecycle](docs/proposal-lifecycle.md)
- [LLM Interpretation Evaluation](docs/llm-interpretation-evaluation.md)
- [API Response Standard](docs/api-response-standard.md)
- [Getting Started](docs/getting-started.md)

## Internal notes (`.docs/`)

- [Runtime Architecture v1](.docs/runtime-architecture-v1.md) — authoritative current-state runtime reference (authorities, lowering boundaries, execution runtime)
- [Backend Current State](.docs/backend-current-state.md) — implementation-grounded, phase-by-phase runtime state
- [Development Plan](.docs/development-plan.md) — roadmap and implemented-phase log
- [LLM Interpretation Contract](.docs/llm-interpretation-contract.md) — interpretation boundary contract
- [Refinement IR Contract](.docs/refinement-ir-contract.md) — semantic refinement IR and lowering boundary
- [Diagnostics & Evaluation Architecture](.docs/diagnostics-architecture.md) — diagnostics-only interpretation evaluation harness (corpora, capability profiles, few-shot, model comparison)

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

# Why Fluxio Exists

Fluxio started as an exploration of whether business software could evolve beyond:
- CRUD-heavy workflows
- dashboard-first ERP interfaces
- generic AI chat experiences

The project focuses on:
- proposal-driven operational interaction
- deterministic execution
- ambiguity-aware workflows
- explainable proposal lifecycle semantics

The goal is not to automate human decisions away.

The goal is to explore software that helps operators work through:
- structured proposals
- controlled refinements
- explicit confirmations
- safe operational execution

while preserving:
- clarity
- control
- explainability
- operational trust

---

# Development Philosophy

Fluxio evolves through:
- small steps
- deterministic workflows
- test-driven iterations
- operational UX experiments
- explainable proposal semantics

The project intentionally prioritizes:
- maintainability
- explicit behavior
- proposal transparency
- execution safety
- architectural clarity

Avoided intentionally:
- hidden AI behavior
- opaque automation
- giant assistant abstractions
- premature orchestration complexity

---

# Project Goal

Fluxio is currently NOT production-ready.

The project exists to explore:
- proposal-driven operational UX
- ambiguity-aware workflows
- explainable proposal interaction
- deterministic proposal mutation semantics
- future enterprise interaction models

while demonstrating:
- modular backend architecture
- scalable frontend structure
- proposal-centric UX
- maintainable domain separation
- controlled operational workflows

---

# Author

Fluxio is designed and developed by Paolo Servilio.

GitHub:
https://github.com/anfibes

---

# License

MIT