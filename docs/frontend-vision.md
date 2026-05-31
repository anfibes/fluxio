# Fluxio Frontend Vision

---

# Purpose

Fluxio frontend explores:

```text id="r0k2mq"
proposal-driven operational UX
```

instead of:
- dashboard-first ERP UX
- CRUD-heavy interaction
- conversational assistant interfaces
- autonomous AI workflows

Core hypothesis:

```text id="9g8v2t"
Business software can evolve from forms and dashboards
toward proposal-centric operational workflows.
```

The frontend exists to:
- operationalize proposal workflows
- preserve execution control
- expose proposal state clearly
- support ambiguity-aware interaction
- visualize proposal mutations

The experience should feel closer to:
- an operational workspace
- a structured execution environment
- a business copilot

than to:
- a chatbot
- a generic AI assistant
- a traditional CRM dashboard

---

# Frontend Architectural Invariants

These rules should never be violated.

- Proposal rail remains authoritative
- Proposal continuity is always preserved
- Refinements mutate existing proposals
- Ambiguities remain explicit
- Execution remains explicit
- Mutation visibility is preserved
- Conversational timelines are avoided
- Proposal state remains inspectable
- Operational clarity is prioritized over conversational realism

These invariants define the frontend identity of Fluxio.

---

# Core Interaction Model

Fluxio revolves around:

```text id="s4r8yk"
Command
→ Proposal
→ Refinement
→ Validation
→ Confirmation
→ Execution
```

Natural language exists ONLY to:
- create proposals
- refine proposals
- resolve ambiguities
- complete missing information
- mutate proposal state

Execution always remains:
- explicit
- reviewable
- controlled
- deterministic

The proposal is the central UI object.

---

# Operational UX Direction

Fluxio frontend is intentionally:
- proposal-centric
- operational
- execution-oriented
- refinement-oriented
- deterministic-first

The interface should communicate:
- control
- transparency
- explainability
- operational trust
- proposal continuity

The UI intentionally avoids:
- assistant personalities
- conversational clutter
- infinite chat timelines
- fake certainty
- “magic AI” behavior

Operational clarity is more important than conversational realism.

---

# Current Interaction Lifecycle

Current frontend flow:

```text id="5d7t6x"
Login
→ Command input
→ Proposal creation
→ Proposal refinement
→ Ambiguity resolution
→ Validation
→ Confirmation
→ Execution
→ Execution result
```

The lifecycle must remain visible at every stage.

Fluxio intentionally exposes:
- proposal state
- confidence
- ambiguities
- mutations
- execution consequences
- execution results

---

# Current Vertical Slice

Current implemented capabilities:
- proposal interpretation
- proposal continuity
- contextual refinements
- ambiguity-aware UX
- mutation transparency
- collection mutations
- operational execution workflows
- refinement tracking
- execution rendering
- confidence-aware UX
- mobile-safe operational shell
- dark/light/system themes

Current frontend already validates:
- proposal-centric interaction
- operational refinement workflows
- deterministic proposal execution
- explainable proposal-driven UX

The project has moved beyond:

```text id="j3f1qe"
simple parser demo territory
```

---

# Proposal Mutation UX

Fluxio frontend communicates:
- what changed
- what remains unresolved
- what became executable
- what confidence changed
- which ambiguities remain
- which entities mutated

The UI should communicate:

```text id="0j9g5s"
"The proposal changed."
```

NOT:

```text id="u2f7cn"
"The assistant replied."
```

This distinction is fundamental to Fluxio.

---

# Proposal Continuity

The same proposal evolves over time.

Refinements must:
- preserve proposal identity
- mutate proposal state
- improve readiness
- reduce ambiguity
- preserve unaffected fields

Example:

```text id="l4x9bd"
Schedule a meeting with Rossi
```

Refinement:

```text id="n2k8yv"
Tomorrow morning
```

Expected behavior:
- SAME proposal updated
- proposal continuity preserved
- proposal readiness improved

The experience should never feel like:
- disconnected assistant replies
- duplicated conversational threads
- isolated operations

---

# Contextual Mutation UX

Fluxio supports contextual proposal mutations.

Examples:

```text id="k5v1jt"
At 10:30
Friday instead
Add Mario too
Remove Luca
Replace Mario with Marco
```

Current mutation semantics:
- replace
- append
- remove
- clear
- collection replacement

The frontend must expose:
- latest refinement
- changed fields
- mutation summaries
- readiness transitions
- operational consequences

Mutation visibility is critical for:
- trust
- explainability
- operational clarity

### Semantic refinement rendering

When the backend tags a change with a `semantic_type`
(`shift_time`, `replace_time`, `replace_date`, `add_participant`,
`remove_participant`, `replace_participant`), `LastRefinementPanel` renders an
operationally-meaningful summary instead of a raw field → value pair — e.g.
"Time shifted from 09:00 to 10:30", "Participant added: Marco",
"Participant replaced: Marco → Mario". A subtle category badge accompanies the
line. Changes without a recognized `semantic_type` fall back to the original
field → value rendering, so older payloads stay stable.

Temporal editable fields additionally render their `explanation.message` (when
present) as a subtle note under the field via `EditableProposalField`, so the
user can see HOW a date/time value was derived. This is frontend rendering of
backend-provided metadata only — the frontend adds no interpretation of its own.

---

# Ambiguity-Aware UX

Ambiguity is a structured operational state.

Fluxio must NEVER:
- silently choose entities
- fake certainty
- auto-resolve dangerous operations

Example:

```text id="d4n0zc"
Call Rossi
```

Possible matches:
- Mario Rossi
- Rossi SRL
- Studio Rossi

Expected UX:
- ambiguity becomes explicit
- execution remains blocked
- proposal continuity is preserved
- refinement updates the SAME proposal

The ambiguity workflow should feel:
- operational
- structured
- confidence-aware

NOT conversational.

---

# Entity Resolution UX

Fluxio now includes a resolver-driven proposal workflow.

Current frontend responsibilities:
- candidate rendering
- ambiguity visualization
- proposal-scoped resolution
- confidence-aware selection flows

Current entity resolution flow:

```text id="n6q8tm"
Intent interpretation
→ Entity extraction
→ Entity resolution
→ Proposal update
```

Current implemented resolver UX:
- lead ambiguity rendering
- candidate selection
- ordinal refinements
- blocking ambiguity states

Future expansion may include:
- product suggestions
- calendar participant resolution
- user assignment resolution
- semantic search assistance

without changing the proposal lifecycle.

---

# Confidence UX

Confidence is a core UX concept.

The frontend should:
- expose low confidence
- encourage review
- reduce blind trust
- preserve explainability

Low confidence is NOT considered failure.

It is part of:
- operational transparency
- deterministic workflows
- explainable interaction

The system should never pretend certainty it does not have.

---

# Missing Information UX

Incomplete proposals are a core interaction pattern.

Example:

```text id="r6g3kp"
Schedule a meeting with Rossi
```

Expected UX:
- proposal enters `draft`
- missing fields become explicit
- execution remains blocked
- refinement guidance appears

This interaction pattern is central to Fluxio.

---

# Execution UX

Execution must feel:
- explicit
- intentional
- operational
- controlled

Before execution, the frontend should clearly communicate:
- what will happen
- which entities are affected
- which modules are involved
- what mutations will occur

The UI must never:
- auto-execute silently
- hide operational side effects
- obscure consequences

Execution remains:
- deterministic
- reviewable
- explainable

---

# Execution Result UX

After execution, the interface should expose:
- execution state
- resulting entities
- execution metadata
- operation summaries
- failures explicitly

Failures should remain:
- factual
- visible
- operational
- explainable

Execution result UX should feel:
- structured
- deterministic
- operational

NOT conversational.

---

# Provider Validation Boundary

Fluxio frontend intentionally receives only:
- proposal-safe payloads
- validated proposal state
- deterministic lifecycle data

Malformed provider outputs are rejected before entering the proposal lifecycle.

Current flow:

```text id="u9v4qh"
Interpretation Provider
→ Validation Layer
→ Proposal Lifecycle
→ Frontend
```

This allows the frontend to remain:
- deterministic
- proposal-centric
- operationally stable

without depending on provider internals.

---

# Core Layout

Current layout:

```text id="m1t7vk"
┌──────────────────────────────────────────────────────────┐
│ Sidebar                                                  │
├──────────────────────────────────────────────────────────┤
│ Command composer                                         │
│ Live parsing feedback                                    │
│ Context tabs                                             │
│ Recent operational context                               │
└──────────────────────────────────────────────────────────┘

┌─────────────────────┬────────────────────────────────────┐
│ Main workspace      │ Action Proposal Rail              │
│                     │                                    │
│ command input       │ proposal state                     │
│ quick actions       │ editable fields                    │
│ operational items   │ ambiguities                        │
│ context lists       │ missing information                │
│                     │ refinement metadata                │
│                     │ execution changes                  │
│                     │ execution results                  │
│                     │ confirm & execute                  │
└─────────────────────┴────────────────────────────────────┘
```

The proposal rail is the central UX surface.

---

# Proposal Rail

The proposal rail acts as:
- operational truth panel
- proposal inspector
- ambiguity resolver
- mutation viewer
- lifecycle surface
- execution visibility layer

The rail should expose:
- editable fields
- missing information
- ambiguities
- confidence
- refinement metadata
- mutation summaries
- execution changes
- warnings
- execution results
- intent capabilities (read-only operational metadata)
- contextual refinement hints (capability- and state-gated)

The proposal rail is the operational center of the frontend.

---

# Capability-Aware UX

The proposal payload carries intent capabilities — both technical keys and
localized human-readable labels. The frontend renders this metadata directly
through two read-only components:

- `ProposalCapabilitiesPanel` — shows the boolean flags
  (`supports_ambiguity_resolution`, `supports_contextual_references`,
  `supports_collection_mutations`) plus the supported refinement labels and
  allowed mutation field labels.
- `ProposalRefinementHints` — surfaces compact, contextually-gated guidance
  derived from `proposal.missing`, `proposal.ambiguities`, and
  `proposal.capabilities`. Hints are hidden once the proposal is ready and
  has no resolvable blocking context.

Architectural rule: the backend owns operational semantics. The frontend
consumes capability flags and labels but never branches on
`proposal.intent`. Labels are localized server-side; the active i18n locale
is sent through `useApi` as `Accept-Language`.

---

# Proposal States

Current proposal lifecycle:

| State | Meaning |
|---|---|
| `draft` | incomplete proposal |
| `ready` | executable proposal |
| `confirmed` | approved proposal |
| `executed` | successful execution |
| `failed` | execution failure |

The frontend must render lifecycle state clearly and honestly.

---

# Sidebar Philosophy

The sidebar remains:
- compact
- secondary
- operational

Responsibilities:
- navigation
- module access
- contextual shortcuts

But it must never dominate the experience.

Fluxio is NOT dashboard-first.

---

# Command Composer

The command composer is the primary interaction surface.

It should feel:
- lightweight
- operational
- keyboard-first
- fast
- focused

The user should immediately understand:

```text id="x7n0vz"
"I can operate the system from here."
```

Current capabilities:
- multiline input
- proposal refinement
- proposal continuity
- contextual placeholders
- command history
- operational reset flows

Future direction:
- contextual suggestions
- autocomplete
- voice-oriented workflows
- resolver-assisted suggestions

---

# Live Parsing Feedback

Fluxio intentionally exposes interpretation in real time.

The frontend renders:
- detected intent
- detected entities
- confidence
- proposal readiness
- ambiguity state

Purpose:
- transparency
- explainability
- operational trust

The system should never feel opaque.

---

# Mobile Operational UX

Fluxio mobile is NOT:
- miniature ERP
- mobile dashboard
- chat application

Mobile direction:
- command-first
- proposal-centric
- operational

Target scenarios:
- commercial agents
- field operations
- rapid refinement workflows
- future voice interaction

Current mobile implementation:
- responsive operational shell
- compact proposal rail
- mobile-safe command composer
- operational spacing system

Future direction:
- voice-driven proposal refinement
- rapid mutation workflows
- proposal snapshots
- field-operation execution flows

Voice support remains intentionally postponed until proposal semantics mature further.

---

# Theme & Visual Direction

Current supported themes:
- dark
- light
- system

Visual direction prioritizes:
- operational readability
- semantic contrast
- restrained color usage
- calm enterprise aesthetics

Current visual style:
- dark enterprise SaaS
- compact density
- minimal chrome
- operational typography
- subtle depth

Inspirations:
- Linear
- Notion
- modern European B2B SaaS

Fluxio intentionally avoids:
- flashy AI aesthetics
- consumer-chat styling
- excessive gradients
- marketing-heavy UI patterns

---

# Motion & Animation

Motion should reinforce:
- operational clarity
- proposal continuity
- lifecycle visibility

Allowed:
- proposal transitions
- refinement transitions
- execution transitions
- lightweight hover feedback

Avoid:
- distracting motion
- playful animation
- excessive microinteractions

Fluxio should feel operational, not playful.

---

# Frontend Architecture Principles

Frontend architecture should remain:
- composable-driven
- modular
- lightweight
- predictable
- proposal-centric

Prefer:
- composables for orchestration
- presentational components
- explicit proposal state
- isolated concerns
- deterministic data flow

Avoid:
- giant page components
- implicit mutations
- excessive global stores
- assistant-style timelines
- premature abstractions

---

# Current Frontend Stack

Current stack:
- Nuxt
- Vue 3
- Composition API
- Tailwind CSS
- TypeScript
- i18n

Current orchestration composables:
- `useAuth()`
- `useApi()`
- `useActionProposal()`
- `useTheme()`

Current `useActionProposal()` responsibilities:
- interpret proposals
- refine proposals
- resolve ambiguities
- confirm proposals
- execute proposals
- preserve proposal continuity
- orchestrate lifecycle state

---

# Tailwind Philosophy

Tailwind acts as:
- implementation layer
- semantic utility system
- design token surface

Fluxio should progressively standardize:
- semantic utility classes
- spacing rules
- typography rules
- layout tokens
- state colors

Preferred semantic utilities:

```text id="a4v1zg"
bg-surface
text-muted
border-border
bg-accent
```

Avoid uncontrolled utility sprawl.

---

# Frontend Principles

Fluxio frontend is NOT:

```text id="f6x7dr"
an AI wrapper
```

The frontend exists to:
- operationalize proposal workflows
- preserve human control
- expose proposal lifecycle
- maintain explainability
- support deterministic workflows

AI remains:
- assistive
- constrained
- explainable
- operationally controlled

The frontend must reinforce this behavior visually and structurally.

---

# Future Frontend Direction

## Proposal Workflows

Planned:
- richer ambiguity workflows
- proposal comparison
- inline proposal editing
- contextual mutation UX
- proposal snapshots

---

## Resolver UX

Planned:
- candidate ranking
- contextual entity suggestions
- resolver confidence
- semantic entity search
- proposal-scoped entity previews

---

## Operational UX

Planned:
- keyboard-first workflows
- operational shortcuts
- mobile-first execution flows
- voice-oriented operational interaction

---

## AI Infrastructure

Planned:
- provider abstraction
- streaming interpretation feedback
- local inference support
- lightweight model integration
- deterministic + LLM hybrid interpretation

LLMs remain assistive infrastructure, not operational authority.

---

# Long-Term Vision

Fluxio aims to explore a future where business software becomes:
- proposal-centric
- operational
- ambiguity-aware
- confidence-aware
- validation-first
- AI-assisted

without sacrificing:
- control
- predictability
- explainability
- operational safety

The frontend is the visible manifestation of that direction.

The goal is NOT:

```text id="g1u9zt"
replacing operators with AI
```

The goal is:

```text id="q7m4vk"
building operational software where AI assists
structured human decision-making through
validated Action Proposals.
```