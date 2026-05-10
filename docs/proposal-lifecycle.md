# Proposal Lifecycle

---

# Overview

The `ActionProposal` is the core architectural concept of Fluxio.

Fluxio does NOT execute business actions directly from natural language.

Instead, every user command becomes:
- structured
- reviewable
- refinable
- confirmable
- explainable

before execution.

Core flow:

```text id="qzxtm8"
Natural language
→ Intent resolution
→ Entity extraction
→ Action Proposal
→ Proposal refinement
→ Validation
→ User confirmation
→ Execution
```

The proposal lifecycle exists to ensure:
- deterministic execution
- explicit user control
- operational transparency
- explainable AI assistance
- proposal continuity
- ambiguity-aware workflows

Conversation exists only to refine proposals.

The proposal itself is the authoritative operational object.

---

# Why Proposal-Driven Workflows

Traditional systems usually follow:

```text id="5kn3eu"
User → Form → Submit → Execute
```

Many AI systems follow:

```text id="3jlwmu"
User → AI → Execute
```

Fluxio introduces a different model:

```text id="jlwmr3"
User
→ Interpretation
→ Structured proposal
→ Proposal refinement
→ Confirmation
→ Execute
```

This enables:
- natural-language interaction
- controlled execution
- explicit validation
- confidence-aware UX
- ambiguity visibility
- operational explainability

The proposal is more important than the conversation itself.

---

# Core Lifecycle Invariants

The proposal lifecycle follows strict invariants.

Rules:
- execution always requires confirmation
- refinement preserves proposal identity
- refinement never creates new proposals
- proposal IDs remain stable
- ambiguities block execution
- proposal state transitions remain explicit
- AI output remains advisory and structured

Natural language never directly mutates business data.

---

# ActionProposal Structure

Every interpretation produces an `ActionProposal`.

Example:

```json id="5h9jln"
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

The proposal acts as:
- execution contract
- refinement target
- operational state container
- frontend rendering source

---

# Proposal States

Current proposal lifecycle:

```text id="zmf91y"
draft
→ ready
→ confirmed
→ executed / failed
```

The SAME proposal evolves over time.

Refinement never creates disconnected proposals.

---

# State: `draft`

A proposal is in `draft` state when:
- required information is missing
- confidence is insufficient
- ambiguities remain unresolved

Example:

```text id="5e6fj9"
Schedule a call with Rossini
```

The system may detect:
- intent
- possible entities

but still miss:
- date
- time

or detect:
- multiple possible entities

Result:
- proposal remains incomplete
- execution remains blocked
- refinement becomes necessary

Example response:

```json id="7dq2fx"
{
    "status": "draft",
    "missing": [
        {
            "key": "date",
            "label": "Date",
            "required": true
        }
    ]
}
```

Draft proposals may evolve through multiple refinements.

---

# State: `ready`

A proposal becomes `ready` when:
- required fields are resolved
- ambiguities are resolved
- validation passes
- execution becomes possible

Example:

```text id="wsh2qt"
Create a follow-up task for Rossini tomorrow at 10am
```

The proposal is now:
- executable
- confirmable
- operationally complete

Ready proposals may still be refined before confirmation.

---

# State: `confirmed`

A proposal enters `confirmed` state after explicit user approval.

Confirmation is intentionally separate from execution.

This supports future workflows such as:
- async execution
- approval chains
- delayed execution
- scheduling
- auditability

Example:

```text id="u4z1i4"
Interpret
→ Ready
→ Confirmed
→ Execute
```

---

# State: `executed`

A proposal becomes `executed` after successful business execution.

At this point:
- business mutation completed
- execution metadata is persisted
- execution result becomes visible

Example:

```json id="jtt9hv"
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
- validation failures
- permission errors
- unresolved ambiguities
- execution exceptions
- business constraints

Example:

```json id="v57znr"
{
    "status": "failed",
    "failure_reason": "Multiple leads matched 'Rossini'."
}
```

Failures must remain:
- explicit
- visible
- explainable

Fluxio intentionally avoids hiding:
- uncertainty
- ambiguity
- execution problems

---

# Proposal Semantics

## Confidence

Each proposal exposes a confidence score.

Example:

```json id="rgwfrd"
{
    "confidence": 0.91
}
```

Confidence represents:
- parser certainty
- extraction quality
- contextual completeness
- entity certainty

Confidence is NOT:
- execution safety
- business correctness
- guaranteed intent accuracy

The frontend should expose uncertainty explicitly.

---

## Missing Fields

Incomplete proposals expose missing information.

Example:

```json id="d4t8bk"
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
- proposal continuation
- progressive completion
- deterministic refinement

---

## Editable Fields

Proposals expose editable fields to the frontend.

Example:

```json id="yavrf7"
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
- safely mutate proposal state

---

## Field Provenance

Editable fields expose provenance metadata.

Allowed sources:

| Source | Meaning |
|---|---|
| `detected` | explicitly detected |
| `inferred` | inferred from context |
| `guessed` | low-confidence assumption |
| `computed` | derived from business logic |
| `edited` | modified by user |
| `missing` | unresolved value |

Field provenance improves:
- explainability
- trust
- operational transparency

---

## Proposed Changes

Proposals expose intended business mutations before execution.

Example:

```json id="r4k6pa"
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

# Proposal Evolution

## Proposal Continuity

Proposal identity must remain stable during refinement.

Example:

User:

```text id="3v72yj"
Schedule a call with Rossini
```

System:
- creates draft proposal
- detects missing information

User:

```text id="5zj81x"
Tomorrow morning
```

Expected behavior:
- refine SAME proposal
- preserve proposal identity
- improve confidence
- reduce missing fields
- transition toward `ready`

Fluxio intentionally avoids:
- fragmented proposal history
- disconnected operations
- conversational duplication

Proposal continuity is one of the main architectural differentiators of Fluxio.

---

## Incremental Refinement

Users naturally refine proposals step-by-step.

Example:

```text id="ykhnlr"
Schedule a call with Rossini
```

Then:

```text id="7svxrr"
Tomorrow morning
```

Refinement should:
- remain proposal-scoped
- mutate proposal state
- preserve operational continuity

Conversation exists only to improve the proposal.

---

## Full-Command Refinement

Users may resend complete commands during refinement.

Example:

Original proposal:

```text id="mmskxb"
Schedule a call with Rossini
```

Refinement:

```text id="d74p2j"
Schedule a call with Rossini tomorrow morning
```

Expected behavior:
- refinement still targets SAME proposal
- proposal identity remains stable
- proposal evolves correctly

This supports natural user behavior while preserving:
- continuity
- deterministic refinement
- operational clarity

---

## Proposal Mutation Transparency

Fluxio intentionally exposes proposal mutations.

Proposal responses may include:

```json id="gdnv9r"
{
    "last_refinement": {
        "text": "Tomorrow morning",
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

This supports:
- explainability
- operational visibility
- proposal evolution tracking

The frontend should expose:
- latest refinement
- changed fields
- lifecycle progression
- operational deltas

The UX should communicate:

```text id="h7vnh0"
"The proposal evolved."
```

NOT:

```text id="9n2xrc"
"The assistant replied."
```

---

# Ambiguity Lifecycle

Ambiguity is a first-class operational concept.

Fluxio must NEVER:
- silently choose entities
- hallucinate certainty
- auto-resolve dangerous ambiguity

Example:

```text id="m1l4zb"
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

```json id="l5wx3i"
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

Fluxio is designed around controlled execution.

Execution rules:
- proposals must validate before execution
- ambiguities block execution
- confirmation is mandatory
- execution should remain deterministic
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
- deterministic refinement rules

Future versions may integrate:
- local LLMs
- Ollama
- Qwen
- provider abstractions

However:
- AI remains assistive
- proposals remain authoritative
- refinement remains structured
- confirmation remains mandatory

LLMs may help generate proposals.

They never directly execute business actions.

---

# Frontend Lifecycle UX

The frontend is proposal-centric.

The proposal rail acts as:
- lifecycle viewer
- proposal inspector
- mutation renderer
- ambiguity resolver
- operational truth surface

The frontend should clearly expose:
- proposal state
- confidence
- ambiguities
- missing information
- refinement effects
- execution results

Current UX direction:
- operational
- structured
- confidence-aware
- deterministic-first

---

# Current Implemented Flow

Current working vertical slice:

1. User writes:

```text id="q1xkg5"
Schedule a call with Rossini
```

2. Frontend calls:

```text id="ypwzfr"
/api/actions/interpret
```

3. Backend returns draft proposal

4. Proposal rail renders:
- fields
- confidence
- ambiguities
- missing information
- changes

5. User refines:

```text id="6lcxqj"
Tomorrow morning
```

6. Frontend calls:

```text id="jv8xeh"
/api/actions/{proposal}/refine
```

7. SAME proposal is updated

8. Proposal transitions toward `ready`

9. User confirms

10. Frontend calls:
- `/confirm`
- `/execute`

11. Backend executes operation

12. Execution result is rendered

This vertical slice demonstrates:
- proposal continuity
- ambiguity-aware refinement
- mutation transparency
- deterministic execution
- operational AI UX

---

# Future Direction

Next evolution areas:
- richer ambiguity workflows
- contextual refinement semantics
- multi-step proposals
- orchestration flows
- proposal mutation intelligence
- contextual entity memory

Future workflows may support:
- progressive proposal composition
- orchestration chains
- operational timelines
- local AI inference

while preserving:
- deterministic execution
- proposal continuity
- explicit control

---

# Long-Term Vision

Fluxio explores how business software can evolve from:
- forms
- dashboards
- CRUD workflows

toward:
- proposal-driven workflows
- ambiguity-aware systems
- explainable operational AI
- controlled business execution

The proposal lifecycle is the foundation of that vision.

The long-term goal is not autonomous AI execution.

The goal is:

```text id="ym83fk"
Validated, explainable and controllable
AI-assisted business execution
through structured Action Proposals.
```