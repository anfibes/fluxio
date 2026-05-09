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
→ Proposal refinement
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
- proposal continuity
- proposal mutation visibility

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
User
→ AI interpretation
→ Structured proposal
→ Proposal refinement
→ Confirmation
→ Execute
```

This allows:
- natural-language interaction
- validation before execution
- explicit user approval
- auditability
- confidence-aware UX
- controlled refinement
- deterministic proposal evolution

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

Conversation exists ONLY to:
- refine proposals
- improve confidence
- gather missing information
- resolve ambiguity

Fluxio is NOT:
- a chatbot
- a conversational timeline
- an autonomous AI agent

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
    "execution_result": null,
    "last_refinement": {
        "text": "Tomorrow morning",
        "effective_text": "Tomorrow morning",
        "summary": "Date and time added.",
        "changes": [
            {
                "field": "date",
                "label": "Date",
                "from": null,
                "to": "2026-05-10"
            },
            {
                "field": "time",
                "label": "Time",
                "from": null,
                "to": "09:00"
            }
        ]
    }
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

Proposal refinement does NOT create new proposals.

The SAME proposal evolves over time.

Proposal identity must remain stable during refinement.

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

Draft proposals may still be refined multiple times.

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

Ready proposals may still be refined before confirmation.

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

Executed proposals should remain immutable.

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

Fluxio intentionally avoids hiding:
- uncertainty
- ambiguity
- execution errors

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

Low confidence is expected behavior.

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
- proposal refinement
- proposal continuation
- progressive completion
- deterministic proposal evolution

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
- mutate proposal data safely

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
- operational visibility

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

# Proposal Continuity

One of Fluxio's main architectural goals is proposal continuity.

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
- refine the EXISTING proposal
- preserve proposal identity
- improve confidence
- reduce missing fields
- transition proposal toward `ready`

The system should avoid:
- disconnected proposals
- chat-style duplication
- fragmented operational state

Proposal continuity is one of the main differentiators of Fluxio.

---

# Full-Command Refinement

Fluxio also supports full-command refinement behavior.

Example:

Original proposal:

```text
Schedule a call with Rossini
```

User refinement:

```text
Schedule a call with Rossini Tomorrow morning
```

Expected behavior:
- refinement still targets the SAME proposal
- proposal identity remains stable
- original `source_text` remains unchanged
- effective refinement is extracted internally
- proposal updates correctly

This supports natural user behavior while preserving:
- proposal continuity
- deterministic refinement
- operational clarity

---

# Proposal Mutation Transparency

Fluxio intentionally surfaces proposal mutations.

Proposal responses may include:

```json
{
    "last_refinement": {
        "text": "Tomorrow morning",
        "effective_text": "Tomorrow morning",
        "summary": "Date and time added.",
        "changes": [
            {
                "field": "date",
                "from": null,
                "to": "2026-05-10"
            }
        ]
    }
}
```

This metadata exists to support:
- explainability
- operational transparency
- proposal mutation visibility
- deterministic UX

The frontend should expose:
- latest refinement
- field changes
- lifecycle progression
- operational deltas

Fluxio intentionally avoids:
- assistant prose
- conversational timelines
- chatbot-style interactions

---

# Conversational Proposal Refinement

Fluxio supports iterative proposal refinement.

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
- update proposal state

The refinement lifecycle is:
- stateful
- deterministic
- operational

NOT:
- conversationally open-ended
- assistant-oriented
- freeform chat memory

---

# Execution Safety

Fluxio is designed around controlled execution.

Important rules:
- proposals must validate before execution
- confirmation is mandatory
- execution must be deterministic
- AI output must remain structured
- execution should be idempotent where possible

Execution must remain:
- explicit
- reviewable
- auditable

AI must NEVER directly mutate business data.

---

# AI Strategy

Fluxio currently uses:
- deterministic parsing
- rule-based interpretation
- structured validation
- deterministic refinement rules

Future versions may optionally integrate:
- local LLMs
- Ollama
- Qwen
- provider abstractions

However:
- AI remains assistive
- proposals remain authoritative
- confirmation remains mandatory
- refinement remains structured

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

The frontend should clearly expose:
- proposal state
- confidence
- missing fields
- proposal mutations
- refinement effects
- execution results

---

# Current Implemented Flow

Current working vertical slice:

1. User logs in
2. User writes command:

```text
Schedule a call with Rossini
```

3. Frontend calls `/api/actions/interpret`
4. Backend returns draft `ActionProposal`
5. Proposal rail renders:
   - fields
   - changes
   - confidence
   - missing information

6. User refines:

```text
Tomorrow morning
```

7. Frontend calls:

```text
/api/actions/{proposal}/refine
```

8. SAME proposal is updated
9. Proposal transitions toward `ready`
10. User confirms
11. Frontend calls:
   - `/confirm`
   - `/execute`
12. Backend executes business action
13. Execution result is rendered

This vertical slice now demonstrates:
- proposal continuity
- operational refinement
- deterministic execution
- proposal mutation transparency
- AI-first operational UX

---

# Future Direction

The next major evolution areas are:
- ambiguity resolution workflows
- candidate entity UX
- contextual refinement semantics
- multi-step proposals
- operational orchestration flows

Future examples:

```text
Call Rossi
```

when multiple entities exist:
- ambiguity must remain visible
- execution must remain blocked
- clarification must refine the SAME proposal

Fluxio should NEVER:
- guess dangerous actions
- hide ambiguity
- fake confidence

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
- ambiguity-aware operational UX

The proposal lifecycle is the foundation of that vision.

The ultimate goal is NOT:
- autonomous AI execution

The ultimate goal is:

```text
Validated, explainable and controllable
AI-assisted business execution through structured Action Proposals.
```