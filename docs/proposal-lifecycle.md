# Proposal Lifecycle

---

# Overview

The `ActionProposal` is the core architectural concept of Fluxio.

Fluxio does NOT execute business actions directly from natural language.

Instead, every user command is transformed into a structured, reviewable and confirmable proposal before execution.

Core flow:

```text
Natural language
→ Intent resolution
→ Entity extraction
→ Action Proposal
→ Validation
→ User confirmation
→ Execution
```

The proposal lifecycle is designed to ensure:
- deterministic execution
- explicit user control
- operational transparency
- safe AI-assisted workflows
- explainable business automation

---

# Why Proposal-Driven Workflows

Traditional systems usually follow:

```text
User → Form → Submit → Execute
```

AI chat systems often follow:

```text
User → AI → Execute
```

Fluxio introduces a different approach:

```text
User → AI interpretation → Structured proposal → Confirmation → Execute
```

This allows:
- natural-language interaction
- validation before execution
- explicit user approval
- auditability
- confidence-aware UX

The proposal is more important than the conversation itself.

---

# Core Principle

Natural language must NEVER directly mutate business data.

AI interpretation is:
- advisory
- assistive
- structured

Execution always requires:
- validation
- lifecycle checks
- explicit confirmation

---

# ActionProposal Structure

Every interpretation produces an `ActionProposal`.

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
    "execution_result": null
}
```

---

# Proposal Lifecycle States

Fluxio currently supports the following proposal states:

```text
draft
→ ready
→ confirmed
→ executed / failed
```

---

# State: `draft`

A proposal is in `draft` state when:
- required information is missing
- confidence is too low
- ambiguity remains unresolved

Example:

```text
Schedule a call with Rossini
```

The system may detect:
- intent: `schedule_call`
- entity: `Rossini`

but still miss:
- date
- time

Result:
- proposal remains incomplete
- execution is disabled
- UI shows missing information

Example response:

```json
{
    "status": "draft",
    "missing": [
        {
            "key": "date",
            "label": "Date",
            "required": true
        },
        {
            "key": "time",
            "label": "Time",
            "required": true
        }
    ]
}
```

---

# State: `ready`

A proposal becomes `ready` when:
- required fields are resolved
- validation passes
- execution becomes possible

Example:

```text
Create a follow-up task for Rossini tomorrow at 10am
```

The system can:
- detect intent
- extract entities
- build executable payload
- calculate sufficient confidence

Result:
- proposal is confirmable
- execution button becomes enabled

---

# State: `confirmed`

A proposal enters `confirmed` state after explicit user approval.

Confirmation is separate from execution.

This distinction allows:
- future async execution
- delayed workflows
- approval chains
- scheduled execution
- auditability

Example flow:

```text
Interpret
→ Ready
→ User confirms
→ Confirmed
→ Execute
```

---

# State: `executed`

A proposal becomes `executed` after successful business execution.

At this point:
- the business action has completed
- execution metadata is persisted
- UI shows execution results

Example execution result:

```json
{
    "execution_result": {
        "task_id": 42,
        "status": "created"
    }
}
```

---

# State: `failed`

A proposal becomes `failed` when execution cannot complete.

Examples:
- validation failure
- ambiguous entity resolution
- database constraints
- permission errors
- execution exceptions

Example:

```json
{
    "status": "failed",
    "failure_reason": "Multiple leads matched 'Rossini'."
}
```

The frontend should surface failures clearly and explicitly.

Fluxio intentionally avoids hiding uncertainty or execution errors.

---

# Confidence

Each proposal includes a confidence score.

Example:

```json
{
    "confidence": 0.91
}
```

Confidence represents:
- parser certainty
- entity certainty
- extraction quality
- contextual completeness

Confidence is NOT:
- a guarantee
- execution safety
- business correctness

The UI should:
- expose uncertainty
- encourage review
- reduce blind trust

---

# Missing Information

Incomplete proposals expose missing fields explicitly.

Example:

```json
{
    "missing": [
        {
            "key": "date",
            "label": "Date",
            "required": true
        }
    ]
}
```

This enables:
- conversational refinement
- proposal continuation
- progressive completion

---

# Editable Fields

Proposals expose editable fields to the frontend.

Example:

```json
{
    "editable_fields": [
        {
            "key": "priority",
            "label": "Priority",
            "value": "high",
            "source": "inferred"
        }
    ]
}
```

This allows users to:
- review inferred values
- refine execution
- correct AI assumptions

---

# Field Sources

Each editable field exposes its provenance.

Allowed sources:

| Source | Meaning |
|---|---|
| `detected` | explicitly detected in user input |
| `inferred` | inferred from context |
| `guessed` | weak confidence assumption |
| `computed` | derived from business rules |
| `edited` | modified by user |
| `missing` | unresolved required field |

Field provenance is important for:
- explainability
- trust
- AI transparency

---

# Proposed Changes

Proposals expose intended business mutations before execution.

Example:

```json
{
    "changes": [
        {
            "type": "create",
            "module": "tasks",
            "label": "Create task"
        }
    ]
}
```

This allows the frontend to show:
- what will happen
- which module is involved
- what business entity will change

Fluxio intentionally surfaces execution intent explicitly.

---

# Conversational Proposal Refinement

One of Fluxio's main goals is conversational proposal continuation.

Example:

User:

```text
Schedule a call with Rossini
```

System:
- creates draft proposal
- detects missing fields

User:

```text
Tomorrow morning
```

Expected behavior:
- refine the existing proposal
- preserve proposal continuity
- improve confidence
- reduce ambiguity

The system should avoid creating disconnected proposals when refinement context exists.

---

# Execution Safety

Fluxio is designed around controlled execution.

Important rules:
- proposals must validate before execution
- confirmation is mandatory
- execution must be deterministic
- AI output must remain structured
- execution should be idempotent where possible

---

# AI Strategy

Fluxio currently uses:
- deterministic parsing
- rule-based interpretation
- structured validation

Future versions may optionally integrate:
- local LLMs
- Ollama
- Qwen
- provider abstractions

However:
- AI remains assistive
- proposals remain authoritative
- confirmation remains mandatory

LLMs must NEVER directly execute business actions.

---

# Frontend UX Principles

The frontend is proposal-centric.

The UI should feel like:
- a structured operational copilot
- a business execution workspace
- a controlled AI-assisted system

NOT:
- a generic chatbot
- a dashboard-heavy ERP
- a consumer AI interface

The proposal rail is the primary interaction object.

---

# Current Implemented Flow

Current working vertical slice:

1. User logs in
2. User writes command
3. Frontend calls `/api/actions/interpret`
4. Backend returns `ActionProposal`
5. Proposal rail renders:
   - fields
   - changes
   - confidence
   - missing information
6. User confirms
7. Frontend calls:
   - `/confirm`
   - `/execute`
8. Backend executes business action
9. Execution result is rendered

---

# Long-Term Vision

Fluxio aims to demonstrate that business systems can evolve from:
- form-driven interaction
- CRUD-first workflows
- dashboard-centric UX

toward:
- proposal-driven workflows
- AI-assisted operational interaction
- confidence-aware execution systems
- conversational refinement flows

The proposal lifecycle is the foundation of that vision.