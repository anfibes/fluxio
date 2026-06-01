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
    "failure_reason_code": null,
    "execution_failure": null,
    "execution_result": null,
    "last_refinement": null
}
```

(The serialized payload also carries a read-only `capabilities` block; see the
Architecture doc's Intent Capability Layer.)

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

A proposal becomes `executed` after a successful, atomic, at-most-once execution.

At this point:
- the business side effect completed inside a transaction
- `executed_at` is persisted
- `execution_result` is exposed as a typed, consistent shape:
  `{ "summary": <human one-liner>, "details": { … } }` — the same envelope for
  every intent (e.g. a created-resource reference, or the scheduled lead/date/time)

Executed proposals are terminal and immutable (re-executing returns the stored
result; refinement and confirmation are rejected).

---

# State: `failed`

A proposal becomes `failed` only when an **execution attempt** raises an unexpected
error, or the intent has no registered executor. The failure is recorded as a typed,
sanitized outcome:

- `failed_at` is persisted
- `failure_reason_code` ∈ `{ unsupported_intent, execution_failed }` (closed taxonomy)
- `failure_reason` / `execution_failure.message` carry a sanitized, localized
  message — raw exception text is never persisted or exposed
- any partial side effects from the failing executor are rolled back

Important distinction (grounded in `ActionExecutionService`): a *validation* condition
surfaced at execution time — e.g. an ambiguous lead detected inside the executor — is
returned as a `422` and leaves the proposal `confirmed`. It is **not** a `failed`
terminal state. Likewise, an unresolved blocking ambiguity keeps a proposal in
`draft` and never reaches execution at all. `failed` is reserved for genuine
execution-time errors.

Failures remain visible, explicit, operational, and explainable; Fluxio intentionally
avoids hiding uncertainty, ambiguity, or execution problems.

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

### Temporal field explanations

Date and time editable fields may carry an optional `explanation` object that
records HOW the value entered the proposal — e.g. a date resolved from
"tomorrow" or a time set from "at 10". This is parser-local explainability
metadata only:

```json id="e3v7tq"
{
    "key": "time",
    "label": "Time",
    "value": "10:30",
    "source": "computed",
    "explanation": {
        "source": "computed",
        "expression": "Push it by 30 minutes",
        "confidence": 1.0,
        "message": "Time shifted later by 30 minutes to 10:30."
    }
}
```

`explanation.confidence` is temporal parse confidence only — it is not proposal
confidence and does not imply execution safety. After a refinement that changes
a date/time value, the explanation is refreshed to describe the new value (or
dropped if a coherent one cannot be built), so it never references a stale
command.

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

The `replace_target` row is not a distinct operation at the data level: it is a
`replace` carrying a `target` (the collection item to swap). The operation set
stored on a mutation is `replace | append | remove | clear`.

## Semantic mutation types

Alongside the structural operation, each recorded change can carry a descriptive
`semantic_type` that names the operational meaning of the mutation. This is
explainability metadata only — it never changes lifecycle, execution, capability
legality, or persistence; it makes a structurally-described mutation easier to
render, audit, and reason about.

Current semantic types:

| Semantic type | Meaning |
|---|---|
| `replace_time` | a direct time replace (e.g. "at 11:00") |
| `replace_date` | a direct date replace (e.g. "move it to Friday") |
| `shift_time` | a relative time shift resolved against the proposal's current time (e.g. "push it by 30 minutes") |
| `add_participant` | a participant appended to the collection |
| `remove_participant` | a participant removed from the collection |
| `replace_participant` | a targeted participant swap |
| `replace_priority` | a priority replace (e.g. "high priority") |
| `clear_priority` | a priority cleared |
| `unknown` | no recognized semantic meaning (default) |

A `shift_time` is still a concrete `replace` on the `time` field — the semantic
type preserves the fact that it originated from a relative shift rather than an
absolute value. The type is surfaced in `last_refinement.changes[].semantic_type`
(see Proposal Mutation Transparency below).

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
Push it by 30 minutes
Move it one hour earlier
```

These references operate ONLY within proposal scope.

Relative temporal refinements ("push it by 30 minutes", "one hour earlier") are
resolved deterministically against the proposal's current time. The refinement
service derives a read-only, proposal-scoped `ProposalRuntimeContext` from the
current proposal state (entities, editable fields, temporal values, blocking
ambiguities) and uses it to turn a relative shift into a concrete time replace.
If the proposal has no current time, the shift is rejected with a warning — the
system never invents a value.

`ProposalRuntimeContext` is explicitly NOT conversational or assistant memory:
it is a deterministic snapshot derived from the single proposal under refinement,
never persisted separately, and it never queries the database, parses natural
language, or resolves ambiguities on its own.

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
                "to": "10:30",
                "semantic_type": "replace_time"
            }
        ]
    }
}
```

Each change may carry the optional `semantic_type` described above
(temporal and participant mutations populate it; other changes default to
`unknown` or omit it). The frontend's `LastRefinementPanel` uses it to render
operationally-meaningful summaries (e.g. "Time shifted from 09:00 to 10:30",
"Participant added: Marco") and falls back to the plain field → value rendering
when no recognized type is present.

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
            "selected_candidate_id": null,
            "candidates": [
                {
                    "id": 1,
                    "label": "Mario Rossi",
                    "type": "person"
                },
                {
                    "id": 7,
                    "label": "Rossi SRL",
                    "type": "company"
                }
            ]
        }
    ]
}
```

Ambiguity resolution is part of the proposal lifecycle itself.

## Progressive narrowing

A refinement against a blocking ambiguity has three deterministic outcomes:

1. **Resolve** — the clarification matches exactly one candidate (an exact label,
   an ordinal like "the first one", or a type clarification that leaves a single
   match). The authoritative ambiguity state is made self-consistent in one update:
   `selected_candidate_id` is set **and** `blocking` is cleared to `false`. The
   payload therefore never shows a resolved-but-blocking ambiguity, so consumers can
   trust `blocking` directly rather than the compound
   `blocking && selected_candidate_id === null` rule.
2. **Narrow but stay blocking** — a type clarification ("the company") matches
   more than one candidate. The active `candidates` list is replaced with the
   matching subset (order preserved), `selected_candidate_id` stays `null`, the
   ambiguity stays blocking, and a warning names the remaining candidates
   (e.g. "Multiple company candidates still match: Rossi SRL, Studio Rossi.").
   A later ordinal/exact refinement then operates on the narrowed set.
3. **No change** — the clarification names nothing recognizable; the candidate
   list is left untouched and a generic "be more specific" warning is added.

Narrowing is deterministic and idempotent (re-narrowing an already-narrowed set
yields the same candidates and a single deduplicated warning). It changes only
the active candidate set and the warning — it never alters resolver scoring,
candidate generation, or the ambiguity payload shape.

## Capability gating

Ambiguity resolution is capability-scoped. The refinement service consults
`IntentCapabilityRegistry` before attempting resolution: if the current
intent does not declare `supportsAmbiguityResolution = true`, the attempt is
skipped, a warning is added, and the proposal remains continuable. The
mutation engine applies the same gate to detected refinement mutations
through `IntentCapabilityRegistry::allowsMutation()`.

This gate is paired with a hard invariant: any intent that can *generate* a
resolver-backed blocking ambiguity must also support resolving it — otherwise a
proposal could become structurally blocked yet operationally unresolvable. So the
skipped-resolution path applies only to intents that cannot emit such an ambiguity.
The invariant is enforced by a runtime guardrail test
(`AmbiguityCapabilityInvariantTest`) derived from the live `IntentRegistry` and
`EntityResolverRegistry`, so a future intent that wires a resolver-backed entity
without resolution capability fails CI.

Warnings produced during refinement (unsupported mutation, ambiguity
resolution not supported, refinement not recognized) are deduplicated on the
proposal — repeated refinements that fail the same way do not accumulate
duplicate messages.

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

## Execution runtime

Execution is a deterministic runtime authority (`ActionExecutionService`) — the only
path that produces business side effects. Its guarantees:

- **Confirmed-only, at-most-once.** Execution runs only from a `confirmed` proposal.
  The service opens a transaction, locks the proposal row (`lockForUpdate`), and
  re-checks status under the lock before dispatching the executor, so two concurrent
  executes can never both run. A second execute observes `executed` and returns the
  stored result (idempotent).
- **Atomic outcome.** The executor runs inside an inner savepoint. On success its
  side effect and the `executed` transition commit together; on an unexpected error
  the partial side effect rolls back while the `failed` terminal state is still
  committed under the same lock (the throwable is re-raised only after commit).
- **Typed result / failure.** Every executor returns one consistent
  `ExecutionResult` (`{ summary, details }`); failures are a typed `ExecutionFailure`
  (`{ reason, message }`) drawn from a closed taxonomy, with a sanitized message.
- **Provider-blind and terminal.** Execution consumes committed proposal state only —
  no reinterpretation, no ambiguity reopening, no provider metadata. A failed proposal
  stays failed: there is no retry, recovery, or re-confirmation.

---

# Interpretation Strategy

Fluxio currently uses:
- deterministic parsing
- rule-based interpretation
- structured validation
- deterministic refinement semantics

Current interpretation architecture includes:
- interpretation provider abstraction (`InterpretationProviderInterface`)
- deterministic provider (`DeterministicInterpretationProvider`, default and
  authoritative)
- an opt-in Ollama sandbox provider (`OllamaInterpretationProvider`, selected
  via `ACTIONS_INTERPRETATION_PROVIDER=ollama`; not production-authoritative)
- a `FakeLlmInterpretationProvider` used for tests/provider-swap checks
- provider validation layer (`NormalizedCommandValidator`, plus a provider-level
  `LlmStructuredOutputValidator` for LLM output)

Exactly one provider is active per request — there is no runtime hybrid mode and
no fallback chain. Deterministic vs. Ollama comparison exists only as
development-only Artisan diagnostics.

Future providers may include:
- local LLMs
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