# Proposal Lifecycle

---

# Overview

The `ActionProposal` is the central architectural object of Fluxio.

Fluxio never executes business operations directly from natural language.

Every command becomes:
- structured
- reviewable
- refinable
- explainable
- confirmable

before execution.

Current operational flow:

```text id="j7m2xq"
Natural language
→ Intent interpretation
→ Entity extraction
→ Entity resolution
→ Proposal creation
→ Proposal refinement
→ Validation
→ Confirmation
→ Execution
```

The proposal lifecycle exists to provide:
- deterministic execution
- proposal continuity
- ambiguity-aware workflows
- explainable operational interaction
- controlled business execution

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

```text id="x8k5tp"
User
→ Form
→ Submit
→ Execute
```

Many AI systems follow:

```text id="v9t3gr"
User
→ AI
→ Execute
```

Fluxio introduces:

```text id="r4m6bn"
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
- deterministic workflows
- explainable proposal state

The proposal is more important than the conversation itself.

---

# ActionProposal Structure

Every interpretation produces an `ActionProposal`.

Example:

```json id="n2w6zc"
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

```text id="s6p9tv"
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
- validation is incomplete
- execution is not yet safe

Example:

```text id="f7g2qy"
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

```text id="a1v8zk"
Schedule a meeting with Rossi tomorrow at 10:30
```

Ready proposals remain refinable until confirmation.

---

# State: `confirmed`

A proposal becomes `confirmed` after explicit user approval.

Confirmation intentionally remains separate from execution.

This prepares future support for:
- async execution
- approval chains
- delayed execution
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

```json id="g9m3xt"
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

```json id="h8x2rq"
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

```json id="m5q8cv"
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

```json id="x7c5pv"
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

# Proposal Mutation Semantics

Fluxio supports:

```text id="b6n1wy"
controlled proposal mutation semantics
```

The system tracks:
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

```text id="u3r8kf"
deterministic proposal mutation semantics
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

```text id="n4t7gs"
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

```text id="d8m1rv"
Schedule a meeting with Rossi
```

Refinement:

```text id="f4z9yb"
Tomorrow morning
```

Then:

```text id="q6s3pk"
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

```text id="z9x2gh"
Schedule a meeting with Rossi
```

Refinement:

```text id="j5r7vn"
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

```text id="t4k8wf"
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

```json id="w2n9qs"
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

```text id="r7f2mg"
"The proposal evolved."
```

NOT:

```text id="k9x1qt"
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

```text id="h3v8pc"
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

```json id="y5m7zd"
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

# Entity Resolution Layer

Fluxio now includes a dedicated entity resolution architecture.

Current structure:

```text id="n8f1wr"
EntityResolverInterface
→ EntityResolverRegistry
→ Resolver implementations
→ ResolutionResult
```

Current implemented resolver:
- `LeadEntityResolver`

Current implemented behaviors:
- exact match scoring
- starts-with scoring
- contains scoring
- confidence ordering
- ambiguity generation
- auto-resolution threshold

Current ambiguity flows already support:
- proposal-local resolution
- ordinal refinements
- SAME proposal continuity

Example:

```text id="q1v4nk"
The second one
```

updates the SAME proposal instead of generating conversational state.

---

# Provider Validation Boundary

Fluxio validates every `NormalizedCommand` before it enters the proposal lifecycle.

Current flow:

```text id="s5x8zd"
Interpretation Provider
→ NormalizedCommand
→ Validation Layer
→ Proposal Lifecycle
```

Validation currently checks:
- valid intent
- valid confidence range
- non-empty entities
- compatible entity keys

Invalid provider output throws:

```text id="g6k3pm"
InvalidNormalizedCommandException
```

which becomes a safe `422` response.

This boundary protects:
- proposal integrity
- frontend stability
- deterministic lifecycle semantics

The proposal lifecycle never trusts raw provider output directly.

---

# Execution Safety

Fluxio is designed around:

```text id="m2t9rq"
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

# Interpretation Strategy

Fluxio currently uses:
- deterministic parsing
- rule-based interpretation
- structured validation
- deterministic refinement semantics

Current interpretation architecture includes:
- interpretation provider abstraction
- deterministic provider
- fake LLM provider sandbox
- provider validation layer

Future providers may include:
- local LLMs
- Ollama
- Qwen
- semantic interpretation providers

However:
- providers remain assistive
- proposals remain authoritative
- confirmation remains mandatory
- execution remains controlled

Core principle:

```text id="z4q7fx"
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

The frontend exposes:
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

```text id="w8m4pc"
Schedule a meeting with Rossi tomorrow morning
```

2. Frontend calls:

```text id="n5x2qb"
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

```text id="j7k9zs"
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
- explainable operational interaction
- controlled business execution

The proposal lifecycle is the foundation of that vision.

The goal is NOT autonomous AI execution.

The goal is:

```text id="c7m2vw"
Validated, explainable and controllable
proposal-driven business execution
through structured Action Proposals.
```