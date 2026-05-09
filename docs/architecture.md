# Fluxio Architecture

---

# Purpose

Fluxio is an open-source CRM/ERP prototype focused on AI-first, command-driven business interactions.

The project explores how business systems can evolve from:
- forms
- dashboards
- manual workflows

toward:
- natural-language intent
- structured action proposals
- proposal refinement
- validation-first execution
- controlled business automation

Fluxio is intentionally:
- architecture-first
- proposal-centric
- command-first
- operational

The project demonstrates:
- modular backend architecture
- API consistency
- domain separation
- event-driven communication
- proposal-driven workflows
- proposal continuity
- safe natural-language execution
- proposal mutation transparency
- modern AI-first frontend interaction patterns

Fluxio is NOT intended to become a traditional CRUD-heavy CRM.

The proposal lifecycle is the central architectural concept.

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

Natural language NEVER executes business actions directly.

The system always creates an intermediate `ActionProposal` object that can be:
- reviewed
- edited
- refined
- confirmed
- executed

This proposal-first approach is the architectural core of Fluxio.

---

# Proposal-Driven Philosophy

Fluxio is NOT:
- a generic AI chatbot
- a dashboard-first ERP
- an autonomous agent system

Fluxio is:
- proposal-centric
- operational
- validation-first
- execution-controlled
- deterministic-first

The proposal is more important than the conversation.

Conversation exists ONLY to:
- refine proposals
- resolve ambiguity
- improve confidence
- gather missing information

Conversation does NOT exist to:
- simulate assistant personalities
- maintain infinite chat timelines
- generate conversational clutter

Fluxio intentionally avoids:
- chatbot interaction patterns
- assistant prose
- timeline-based UX
- autonomous execution

---

# Proposal Continuity

Fluxio supports stateful proposal continuity.

The proposal evolves over time instead of being recreated for every command.

Example:

User:

```text
Schedule a call with Rossini
```

System:
- detects partial intent
- creates draft proposal
- identifies missing information

User:

```text
Tomorrow morning
```

Expected behavior:
- refine the EXISTING proposal
- improve confidence
- reduce missing fields
- move proposal toward `ready`
- preserve proposal identity

This proposal continuity behavior is one of the core differentiators of Fluxio.

---

# Proposal Mutation Transparency

Fluxio intentionally surfaces proposal mutations.

The UI should communicate:
- proposal changes
- refinement effects
- lifecycle transitions
- execution state
- confidence evolution

Example:

```text
Last update
"Tomorrow morning"

Date → 2026-05-10
Time → 09:00
Status → ready
```

This is intentionally:
- compact
- operational
- deterministic
- explainable

NOT:
- conversational
- assistant-like
- chat-oriented

The goal is:
- operational trust
- explainability
- proposal visibility
- mutation transparency

---

# Modular Monolith

Fluxio is designed as a modular monolith.

The goal is:
- simple deployment
- explicit internal boundaries
- isolated domains
- future extraction capability

Project structure:

```text
fluxio/
  apps/
    api/
    web/

  packages/
    Core/          (implemented)
    Identity/      (implemented)
    Leads/         (implemented)
    Tasks/         (implemented)
    Actions/       (implemented)
    Calendar/      (minimal foundation)
    Analytics/     (placeholder)
    Notifications/ (placeholder)
```

Principle:

```text
Modularize first, microservice later.
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

# Application Layers

## apps/api

Laravel backend application.

Responsibilities:
- expose APIs
- register modules
- handle authentication
- coordinate business workflows
- expose proposal lifecycle endpoints
- expose proposal refinement endpoints
- provide standardized API responses
- orchestrate module interaction

Controllers must remain thin.

Business logic must live in:
- services
- actions
- jobs
- domain-specific classes

---

## apps/web

Nuxt frontend application.

Responsibilities:
- command-first UI
- proposal rendering
- proposal refinement UX
- proposal continuity UX
- proposal mutation visibility
- proposal confirmation UX
- execution state rendering
- confidence visualization
- API communication
- i18n support

The frontend is intentionally:
- AI-first
- proposal-centric
- operational

NOT dashboard-first.

---

# Frontend Interaction Architecture

Current frontend flow:

```text
Login
→ Command input
→ Interpret
→ Action Proposal
→ Proposal refinement
→ Confirm
→ Execute
→ Execution Result
```

The UI is centered around:
- command input
- proposal review
- refinement
- proposal continuity
- confirmation
- execution visibility

CRM data supports the proposal flow.
It must not dominate the experience.

---

# Frontend Design Principles

The UI should feel like:
- a business copilot
- a structured operational workspace
- a controlled execution environment

NOT:
- a ChatGPT clone
- a consumer AI tool
- a classic ERP full of forms

Current visual direction:
- dark enterprise SaaS
- proposal-centric layout
- compact typography
- restrained motion
- minimal chrome
- operational feel

Inspirations:
- Linear
- Notion
- modern European B2B SaaS

---

# Frontend Architecture Rules

Frontend principles:
- composables orchestrate logic
- visual components remain presentational
- API calls stay outside UI components
- proposal lifecycle remains centralized
- avoid giant page components
- avoid premature global state management

Current orchestration composables:
- `useAuth()`
- `useApi()`
- `useActionProposal()`

Current `useActionProposal()` responsibilities:
- interpret proposals
- refine proposals
- confirm proposals
- execute proposals
- maintain proposal continuity
- centralize proposal lifecycle orchestration

The frontend intentionally avoids:
- large global stores
- unnecessary abstractions
- complex state machines

---

# Current Frontend Components

Main UI components:

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
- lifecycle state viewer
- mutation summary panel
- operational truth surface

---

# Packages

Each package owns a specific domain or infrastructure responsibility.

Typical structure:

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

Avoid empty architectural noise.

---

# Modules

## Core

Shared infrastructure module.

Responsibilities:
- base API controller
- API response standard
- exception rendering
- shared contracts
- shared DTOs
- common helpers
- translation keys

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

Frontend:
- token persistence
- auth composable
- protected API flow

Planned:
- password reset
- OTP
- email verification
- user profile management

Identity should expose:
- services
- contracts
- events

instead of leaking implementation details.

---

## Leads

Basic CRM lead management.

Implemented:
- CRUD
- pagination
- filtering
- search
- protected endpoints

Fields:
- id
- name
- company
- email
- phone
- status
- notes
- timestamps

Statuses:
- new
- contacted
- qualified
- lost
- won

Future:
- proposal integration
- contextual refinement support
- ambiguity-aware entity workflows

---

## Tasks

Execution layer for actionable work.

Implemented:
- CRUD
- pagination
- filters
- optional lead relation
- protected endpoints

Fields:
- title
- description
- status
- priority
- due_at
- lead_id

Statuses:
- pending
- in_progress
- completed
- cancelled

Priorities:
- low
- normal
- high

Tasks is currently the primary executable domain used by the Actions module.

---

## Actions

The most distinctive module of Fluxio.

Responsibilities:
- parse natural language
- detect intent
- extract entities
- calculate confidence
- detect missing information
- create ActionProposal objects
- persist proposals
- refine existing proposals
- preserve proposal continuity
- track proposal mutations
- validate lifecycle transitions
- execute confirmed actions

Implemented:
- rule-based intent resolver
- proposal persistence
- proposal refinement
- proposal lifecycle guards
- refinement metadata tracking
- confirmation flow
- execution flow
- idempotent execution
- task creation executor
- execution result metadata

Current intents:
- `create_task`
- `schedule_call`
- `unknown`

Execution happens ONLY after explicit confirmation.

Ambiguity-aware workflows are the next planned milestone.

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
  user-approved proposal awaiting execution

- `executed`
  successful execution

- `failed`
  execution failure

Lifecycle rules:
- refinement does NOT create new proposals
- proposal IDs remain stable
- `draft` proposals may be refined
- `ready` proposals may still be refined
- execution must remain explicit
- proposal continuity must remain visible

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
    "status": "ready",
    "confidence": 0.91,
    "source_text": "Create a follow-up task for Rossini tomorrow at 10am",
    "entities": {
        "lead": "Rossini"
    },
    "missing": [],
    "warnings": [],
    "editable_fields": [
        {
            "key": "title",
            "label": "Title",
            "value": "Follow-up task",
            "source": "detected",
            "required": true
        }
    ],
    "changes": [
        {
            "type": "create",
            "label": "Create task",
            "module": "tasks",
            "payload": {
                "title": "Follow-up task"
            }
        }
    ],
    "needs_confirmation": true,
    "confirmed_at": null,
    "executed_at": null,
    "failed_at": null,
    "failure_reason": null,
    "execution_result": null,
    "last_refinement": {
        "text": "Tomorrow morning",
        "effective_text": "Tomorrow morning",
        "summary": "Date and time added.",
        "changes": [
            {
                "field": "date",
                "from": null,
                "to": "2026-05-10"
            },
            {
                "field": "time",
                "from": null,
                "to": "09:00"
            }
        ]
    }
}
```

The proposal contract must remain:
- stable
- deterministic
- frontend-friendly
- explainable

---

# Confidence UX

Fluxio never pretends certainty.

Low-confidence proposals are expected behavior.

Examples:
- ambiguous entities
- incomplete commands
- weak context
- unknown intent

The UI should:
- expose uncertainty explicitly
- encourage review
- avoid hallucinated confidence

Confidence UX is considered:
- architectural
- operational
- product-critical

NOT cosmetic.

---

# API Response Standard

All API responses must be standardized.

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
        "total": 100,
        "last_page": 7
    }
}
```

The frontend relies heavily on stable response contracts.

Proposal payloads are especially sensitive because they drive:
- proposal rendering
- refinement rendering
- execution state
- mutation visibility
- confidence UX
- proposal continuity

---

# Exception Handling

Exception rendering is centralized.

Mappings:
- ValidationException → 422
- AuthenticationException → 401
- AuthorizationException → 403
- ModelNotFoundException → 404
- NotFoundHttpException → 404
- Generic Throwable → 500

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
- clear technical language
- suitable for European/German audience

---

# Controller Rules

Controllers must remain thin.

Allowed:
- receive request
- delegate orchestration
- return standardized responses

Avoid:
- business logic
- manual JSON construction
- cross-domain orchestration
- large methods

Example:

```php
public function store(StoreTaskRequest $request): JsonResponse
{
    $task = $this->taskService->create($request->validated());

    return ApiResponse::success(
        (new TaskResource($task))->resolve(),
        'tasks::tasks.created',
        Response::HTTP_CREATED
    );
}
```

---

# Services and Action Classes

Business logic belongs in:
- services
- action classes
- jobs
- domain-specific orchestration

Guidelines:
- services → orchestration
- action classes → focused operations
- jobs → async execution

Avoid:
- god services
- multi-domain classes
- hidden side effects

---

# Cross-Module Communication

Prefer:
- events
- listeners
- contracts
- DTOs

Avoid direct domain coupling.

Incorrect:

```php
Lead::createTaskDirectly();
```

Preferred:

```php
event(new LeadFollowUpRequested($leadId, $dueAt));
```

or a dedicated orchestration service.

---

# LLM Strategy

Fluxio is deterministic-first.

The MVP uses:
- rule-based parsing
- explicit validation
- predictable flows

Future LLM support may assist:
- intent detection
- entity extraction
- proposal refinement
- ambiguity resolution

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

Testing priority:
- business-critical behavior
- proposal integrity
- proposal continuity
- refinement consistency
- lifecycle consistency
- API contracts

Backend priorities:
- response standard
- exception rendering
- Actions parser
- confidence calculation
- proposal lifecycle
- proposal refinement
- mutation tracking
- effective refinement extraction
- idempotent execution
- auth flows

Frontend priorities:
- command UX
- proposal rendering
- refinement rendering
- confidence rendering
- missing information UX
- proposal mutation visibility
- confirmation UX
- execution state rendering
- API error handling

---

# Current Vertical Slice

Implemented end-to-end flow:

```text
Schedule a call with Rossini
```

Current behavior:
1. User enters command
2. System interprets intent
3. Draft proposal is generated
4. Proposal is rendered in the rail
5. Missing date/time is detected
6. User refines:

```text
Tomorrow morning
```

7. SAME proposal is updated
8. Proposal transitions toward `ready`
9. User confirms
10. Proposal executes
11. Execution result is rendered

This vertical slice now validates:
- proposal continuity
- operational refinement
- deterministic execution
- AI-first operational UX

---

# Current Strategic Direction

The current focus is:
- proposal continuity
- ambiguity-aware workflows
- operational refinement UX
- mutation transparency
- contextual proposal handling
- confidence UX
- controlled execution

NOT:
- CRUD expansion
- dashboard complexity
- generic AI chat experiences

---

# Main Risk

The main risk is rebuilding a traditional CRM before validating the proposal-first interaction model.

Rule:

```text
Build the smallest working product that demonstrates the strongest architectural idea.
```

The strongest architectural idea is:

```text
Validated natural-language action proposals for business systems.
```

---

# Long-Term Vision

Fluxio aims to demonstrate:
- proposal-driven enterprise UX
- AI-assisted operational workflows
- ambiguity-aware business interaction
- controlled natural-language execution
- deterministic operational AI systems

The strongest possible outcome is NOT:
- becoming a massive CRM

The strongest possible outcome is:

```text
Proving that proposal-centric operational UX is a viable future paradigm for enterprise software.
```