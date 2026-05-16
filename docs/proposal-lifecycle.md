# Proposal Lifecycle

---

# Overview

The `ActionProposal` is the central architectural object of Fluxio.

Fluxio does NOT execute business operations directly from natural language.

Instead, every command becomes:
- structured
- reviewable
- refinable
- explainable
- confirmable

before execution.

Core operational flow:

```text id="4m2bqz"
Natural language
→ Intent interpretation
→ Entity extraction
→ Entity resolution
→ Action Proposal
→ Proposal refinement
→ Validation
→ Confirmation
→ Execution
```

The proposal lifecycle exists to provide:
- deterministic execution
- operational transparency
- explainable AI assistance
- proposal continuity
- ambiguity-aware workflows
- controlled business interaction

The proposal itself is authoritative.

Conversation exists only to improve proposal state.

---

# Lifecycle Invariants

These rules should never be violated.

- Natural language never executes business operations directly
- Proposal IDs remain stable
- Refinements mutate existing proposals
- Refinement never creates disconnected proposal state
- Ambiguities remain explicit
- Ambiguities may block readiness
- Execution always requires confirmation
- Proposal mutations remain explainable
- Proposal state remains inspectable
- AI output remains advisory and structured

These invariants define Fluxio’s operational model.

---

# Why Proposal-Driven Workflows

Traditional systems usually follow:

```text id="ag5g8g"
User
→ Form
→ Submit
→ Execute
```

Many AI systems follow:

```text id="q3u3tb"
User
→ AI
→ Execute
```

Fluxio introduces a different model:

```text id="pmv4z6"
User
→ Interpretation
→ Structured proposal
→ Proposal refinement
→ Validation
→ Confirmation
→ Execute
```

This enables:
- controlled execution
- proposal continuity
- ambiguity visibility
- confidence-aware UX
- deterministic workflows
- explainable operational AI

The proposal is more important than the conversation itself.

---

# ActionProposal Structure

Every interpretation produces an `ActionProposal`.

Example:

```json id="p1o1h8"
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
    "execution_result": null,
    "last_refinement": null
}
```

The proposal acts as:
- execution contract
- refinement target
- operational state container
- frontend rendering source

---

# Proposal Lifecycle States

Current lifecycle:

```text id="qfdz45"
draft
→ ready
→ confirmed
→ executed / failed
```

The SAME proposal evolves over time.

Refinement never creates disconnected proposals.

---

# State: `draft`

A proposal remains in `draft` when:
- required information is missing
- ambiguities remain unresolved
- confidence is insufficient
- validation is incomplete

Example:

```text id="mhn59z"
Schedule a meeting with Rossi
```

Possible state:
- date missing
- ambiguity unresolved
- proposal not executable

Expected behavior:
- proposal continuity preserved
- execution blocked
- refinement required

Draft proposals may evolve through multiple mutations.

---

# State: `ready`

A proposal becomes `ready` when:
- required fields are resolved
- ambiguities are resolved
- validation passes
- execution becomes possible

Example:

```text id="w9nqbd"
Schedule a meeting with Rossi tomorrow at 10:30
```

Ready proposals remain refinable until confirmation.

---

# State: `confirmed`

A proposal becomes `confirmed` after explicit user approval.

Confirmation intentionally remains separate from execution.

This supports future workflows such as:
- async execution
- approval chains
- delayed execution
- scheduling
- auditability

---

# State: `executed`

A proposal becomes `executed` after successful business execution.

At this point:
- business mutation completed
- execution metadata persisted
- execution result exposed

Executed proposals should remain immutable.

---

# State: `failed`

A proposal becomes `failed` when execution cannot complete.

Examples:
- validation failures
- permission issues
- business constraints
- unresolved ambiguity
- execution exceptions

Failures must remain:
- visible
- explicit
- operational
- explainable

Fluxio intentionally avoids hiding:
- uncertainty
- ambiguity
- execution problems

---

# Proposal Semantics

## Confidence

Each proposal exposes confidence metadata.

Example:

```json id="n7a4n2"
{
    "confidence": 0.91
}
```

Confidence represents:
- interpretation certainty
- extraction quality
- contextual completeness
- entity certainty

Confidence is NOT:
- execution safety
- business correctness
- guaranteed intent accuracy

Low confidence is part of healthy operational UX.

---

## Missing Fields

Incomplete proposals expose missing information.

Example:

```json id="sv6i95"
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

This supports:
- progressive completion
- deterministic refinement
- proposal continuity

---

## Editable Fields

Proposals expose editable operational state.

Example:

```json id="n5hqvx"
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

Editable fields support:
- proposal inspection
- safe refinement
- explainability
- operational transparency

---

## Field Provenance

Editable fields expose provenance metadata.

Allowed sources:

| Source | Meaning |
|---|---|
| `detected` | explicitly detected |
| `inferred` | inferred from context |
| `guessed` | low-confidence assumption |
| `computed` | derived from logic |
| `edited` | modified by user |
| `missing` | unresolved value |

Field provenance improves:
- trust
- explainability
- operational clarity

---

## Proposed Changes

Proposals expose intended business mutations before execution.

Example:

```json id="4lfjlwm"
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

This allows the frontend to render:
- execution intent
- affected modules
- operational consequences

---

# Proposal Mutation Intelligence

Fluxio supports:

```text id="yljlwm"
controlled proposal mutation semantics
```

The system must understand:
- what changed
- what remains unchanged
- whether readiness changed
- whether ambiguity changed
- which fields mutated

WITHOUT introducing:
- hidden conversational memory
- autonomous agents
- opaque AI reasoning

Proposal continuity alone is not enough.

Fluxio also requires:
```text id="kq7f0z"
proposal mutation semantics
```

---

# Supported Mutation Operations

Current mutation operations:

| Operation | Meaning |
|---|---|
| `replace` | replace existing value |
| `append` | append collection item |
| `remove` | remove collection item |
| `clear` | remove existing value |
| `replace_target` | replace collection item with another |

Examples:

```text id="m3lnby"
Tomorrow instead
At 10:30
Add Mario too
Remove Luca
Replace Mario with Marco
```

These operations remain:
- deterministic
- proposal-scoped
- explainable
- inspectable

---

# Proposal Continuity & Refinement

Fluxio supports:
- incremental refinements
- contextual refinements
- full-command refinements
- collection mutations
- proposal-local references

Example:

Initial proposal:

```text id="s7t6kz"
Schedule a meeting with Rossi
```

Refinement:

```text id="n9gf4v"
Tomorrow morning
```

Then:

```text id="rujlwm"
At 10:30
```

Expected behavior:
- SAME proposal updated
- date preserved
- time replaced
- lead preserved
- readiness recomputed

The system intentionally avoids:
- fragmented conversational state
- duplicated proposals
- hidden assistant memory

Conversation exists ONLY to improve proposal state.

---

# Full-Command Refinement

Users may resend complete commands during refinement.

Example:

Initial proposal:

```text id="egvjlwm"
Schedule a meeting with Rossi
```

Refinement:

```text id="g9jjlwm"
Schedule a meeting with Rossi tomorrow morning
```

Expected behavior:
- refinement still targets SAME proposal
- proposal identity preserved
- proposal evolves safely

This supports natural user behavior while preserving:
- continuity
- operational clarity
- deterministic refinement

---

# Contextual References

Fluxio supports proposal-local contextual references.

Examples:

```text id="tjlwm1"
Move it to Friday
The second one
Add Mario too
```

These references operate ONLY within proposal scope.

Fluxio intentionally avoids:
- hidden global memory
- assistant conversation history
- autonomous contextual reasoning

Proposal state remains the only operational context.

---

# Proposal Mutation Transparency

Fluxio intentionally exposes proposal mutations.

Example:

```json id="jlwmt8"
{
    "last_refinement": {
        "text": "At 10:30",
        "summary": "Time updated.",
        "changes": [
            {
                "field": "time",
                "from": "09:00",
                "to": "10:30"
            }
        ]
    }
}
```

Purpose:
- explainability
- operational visibility
- proposal evolution tracking
- mutation clarity

The frontend should expose:
- latest refinement
- changed fields
- lifecycle progression
- operational deltas

The UX should communicate:

```text id="jlwmf4"
"The proposal evolved."
```

NOT:

```text id="jlwmw9"
"The assistant replied."
```

This distinction is foundational to Fluxio.

---

# Ambiguity Lifecycle

Ambiguity is a first-class operational concept.

Fluxio must NEVER:
- silently choose entities
- hallucinate certainty
- auto-resolve dangerous ambiguity

Example:

```text id="jlwmc2"
Call Rossi
```

Possible matches:
- Mario Rossi
- Rossi SRL
- Studio Rossi

Expected behavior:
- proposal remains incomplete
- ambiguity becomes explicit
- execution remains blocked
- refinement updates SAME proposal

Example ambiguity structure:

```json id="6pjlwm"
{
    "ambiguities": [
        {
            "key": "lead",
            "label": "Lead",
            "reason": "multiple_matches",
            "blocking": true,
            "candidates": [
                {
                    "id": 1,
                    "label": "Mario Rossi"
                },
                {
                    "id": 7,
                    "label": "Rossi SRL"
                }
            ]
        }
    ]
}
```

Ambiguity resolution is part of the proposal lifecycle itself.

---

# Execution Safety

Fluxio is designed around:
```text id="jlwm92"
controlled execution
```

Execution rules:
- proposals must validate before execution
- ambiguities block execution
- confirmation is mandatory
- execution remains deterministic
- AI output remains structured

Execution must remain:
- explicit
- reviewable
- auditable

AI never directly mutates business data.

---

# AI Strategy

Fluxio currently uses:
- deterministic parsing
- rule-based interpretation
- structured validation
- deterministic refinement semantics

Future versions may integrate:
- local LLMs
- Ollama
- Qwen
- provider abstractions

However:
- AI remains assistive
- proposals remain authoritative
- confirmation remains mandatory
- execution remains controlled

Core principle:

```text id="jlwm6v"
LLM assists interpretation.
Fluxio controls execution.
```

---

# Frontend Lifecycle UX

The frontend is intentionally proposal-centric.

The proposal rail acts as:
- lifecycle viewer
- proposal inspector
- mutation renderer
- ambiguity resolver
- operational truth surface

The frontend should expose:
- proposal state
- confidence
- ambiguities
- missing information
- mutation summaries
- refinement metadata
- execution results

Current UX direction:
- operational
- structured
- deterministic-first
- confidence-aware

---

# Current Implemented Vertical Slice

Current implemented flow:

1. User writes:

```text id="jlwmx7"
Schedule a meeting with Rossi tomorrow morning
```

2. Frontend calls:

```text id="9jlwm0"
/api/actions/interpret
```

3. Backend returns:
- draft proposal
- ambiguity candidates
- detected date/time

4. Proposal rail renders:
- editable fields
- ambiguities
- confidence
- mutation state
- missing information

5. User refines:

```text id="jlwm3q"
The second one
```

6. SAME proposal is updated

7. Proposal becomes `ready`

8. User confirms

9. Frontend calls:
- `/confirm`
- `/execute`

10. Backend executes operation

11. Execution result is rendered

Current implemented behaviors include:
- proposal continuity
- ambiguity-aware refinement
- contextual refinements
- collection mutations
- mutation transparency
- deterministic execution

---

# Future Direction

Next evolution areas:
- richer ambiguity workflows
- resolver-driven entity resolution
- multilingual parsing
- confidence scoring evolution
- multi-step proposals
- orchestration flows
- semantic entity search
- local inference support

while preserving:
- proposal continuity
- deterministic execution
- explicit control
- explainability

---

# Long-Term Vision

Fluxio explores how enterprise software can evolve from:
- forms
- dashboards
- CRUD workflows

toward:
- proposal-driven workflows
- ambiguity-aware systems
- explainable operational AI
- controlled business execution

The proposal lifecycle is the foundation of that vision.

The goal is NOT autonomous AI execution.

The goal is:

```text id="jlwmk8"
Validated, explainable and controllable
AI-assisted business execution
through structured Action Proposals.
```