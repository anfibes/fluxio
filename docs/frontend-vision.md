# Fluxio Frontend Vision

---

# Purpose

The Fluxio frontend is designed to explore a proposal-driven interaction model for business software.

Fluxio intentionally moves away from:
- dashboard-first UX
- CRUD-heavy workflows
- form-centric interaction
- conversational AI wrappers

toward:
- command-first workflows
- proposal continuity
- operational refinement
- explicit execution control
- ambiguity-aware interaction
- deterministic operational AI

The frontend exists to support:
- structured operational workflows
- proposal evolution
- controlled execution
- explainable AI assistance

The interface should feel closer to:
- an operational workspace
- a business copilot
- a structured execution environment

than to:
- a traditional CRM
- a generic assistant
- a chatbot UI

---

# Core Interaction Model

Fluxio frontend revolves around:

```text id="9t3qjv"
Command
→ Proposal
→ Refinement
→ Validation
→ Confirmation
→ Execution
```

The proposal is the central UI object.

Natural language exists only to:
- create proposals
- refine proposals
- resolve ambiguities
- complete missing information
- improve confidence

Execution always remains:
- explicit
- reviewable
- controlled

---

# Frontend Philosophy

The frontend should always feel:
- calm
- operational
- explicit
- trustworthy
- structured
- confidence-aware

The system should communicate:
- control
- transparency
- explainability
- operational clarity
- proposal continuity

The frontend intentionally avoids:
- assistant personalities
- infinite chat timelines
- conversational clutter
- fake certainty
- “magic AI” behavior

Fluxio is NOT designed as:
- a chat wrapper
- a generic AI interface
- an autonomous agent system

---

# UX Direction

Fluxio is intentionally:
- AI-first
- proposal-centric
- operational
- execution-oriented

The interface should feel like:
- an operator console
- a command workspace
- a controlled execution environment

The user should always understand:
- what the system detected
- what the system inferred
- what remains unresolved
- what changed after refinement
- what execution will do

Operational clarity is more important than conversational realism.

---

# Current Interaction Flow

Current frontend lifecycle:

```text id="qvn5jv"
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

The frontend must keep this lifecycle visible at every stage.

---

# Current Vertical Slice

Current implemented flow:

1. User logs in
2. User writes:

```text id="fwwd4r"
Schedule a call with Rossini
```

3. Frontend calls:

```text id="zcc65r"
/api/actions/interpret
```

4. Proposal rail renders:
- fields
- confidence
- missing information
- ambiguities
- proposal changes

5. Proposal remains in `draft`

6. User refines:

```text id="9hnqzb"
Tomorrow morning
```

7. Frontend calls:

```text id="6jlwmr"
/api/actions/{proposal}/refine
```

8. SAME proposal is updated

9. Proposal transitions toward `ready`

10. User confirms

11. Frontend calls:
- `/confirm`
- `/execute`

12. Execution result is rendered

This vertical slice already validates:
- proposal continuity
- operational refinement
- ambiguity-aware UX
- mutation transparency
- deterministic execution workflows

---

# Proposal Evolution

## Proposal Continuity

The same proposal evolves over time.

Refinements must:
- preserve proposal identity
- mutate proposal state
- improve confidence
- reduce ambiguity
- complete missing fields

The experience should never feel like:
- disconnected assistant replies
- multiple isolated operations
- duplicated conversational threads

---

## Conversational Refinement

Users naturally refine proposals incrementally.

Example:

```text id="84c5jk"
Schedule a call with Rossini
```

Then:

```text id="wxvwqn"
Tomorrow morning
```

Fluxio supports:
- contextual refinement
- proposal-scoped updates
- operational continuity

The frontend should make this evolution feel:
- progressive
- stateful
- operational

NOT:
- chat-oriented
- conversationally noisy

---

## Full-Command Refinement

Users often resend complete commands during refinement.

Example:

Original proposal:

```text id="3l6zj9"
Schedule a call with Rossini
```

Refinement:

```text id="61q2u8"
Schedule a call with Rossini tomorrow morning
```

The frontend should support this behavior naturally without:
- breaking proposal continuity
- creating duplicate proposals
- producing conversational duplication

---

## Proposal Mutation Transparency

Fluxio intentionally exposes proposal mutations.

The UI should clearly render:
- latest refinement
- changed fields
- proposal state transitions
- confidence evolution

Example:

```text id="k8fqrz"
Last update
"Tomorrow morning"

Date → 2026-05-10
Time → 09:00
Status → ready
```

The UX should communicate:

```text id="k38ylz"
"The proposal changed."
```

NOT:

```text id="7ohz5n"
"The assistant replied."
```

This distinction is fundamental to Fluxio.

---

# Ambiguity-Aware UX

Ambiguity is a core operational concept.

Fluxio must NEVER:
- silently choose entities
- fake confidence
- auto-resolve dangerous actions

Example:

```text id="s9mu2d"
Call Rossi
```

Possible matches:
- Mario Rossi
- Rossi SRL
- Studio Rossi

Expected frontend behavior:
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

# Confidence UX

Fluxio intentionally surfaces uncertainty.

Confidence is a core UX concept.

The frontend should:
- expose low confidence
- encourage review
- reduce blind trust
- preserve explainability

Low confidence is NOT considered failure.

It is part of:
- operational transparency
- explainable AI interaction
- safe execution workflows

---

# Missing Information UX

One of Fluxio’s strongest interaction patterns is:
- incomplete proposals
- progressive refinement
- operational completion

Example:

```text id="7gj13w"
Schedule a call with Rossini
```

The frontend should:
- create a draft proposal
- expose missing fields
- block execution
- guide refinement

This pattern is central to the Fluxio experience.

---

# Execution UX

Execution must feel:
- explicit
- intentional
- controlled

Before execution, the frontend should communicate:
- what will happen
- which module is affected
- which entities will change

The UI must never:
- auto-execute silently
- obscure side effects
- hide operational consequences

Execution is always:
- reviewable
- deterministic
- operational

---

# Execution Result UX

After execution, the interface should expose:
- execution status
- resulting entities
- operation metadata
- execution outcome

Failures should remain:
- visible
- factual
- explainable

Execution results should feel:
- operational
- structured
- deterministic

NOT conversational.

---

# Core Layout

Current layout structure:

```text id="7i1v5r"
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
│ context lists       │ missing information                │
│ operational items   │ ambiguities                        │
│                     │ refinement metadata                │
│                     │ execution changes                  │
│                     │ confirm & execute                  │
└─────────────────────┴────────────────────────────────────┘
```

The proposal rail is the central UX surface.

---

# Proposal Rail

The proposal rail represents:
- proposal state
- validation state
- execution intent
- proposal continuity
- mutation evolution
- operational consequence

The rail should clearly display:
- editable fields
- missing information
- ambiguities
- refinement metadata
- execution changes
- warnings
- execution results

The rail acts as:
- operational truth panel
- proposal inspector
- mutation summary surface
- execution visibility layer

---

# Proposal States

Current proposal states:

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

The sidebar should remain:
- compact
- secondary
- operational

It provides:
- navigation
- module access
- contextual shortcuts

But it must NOT dominate the experience.

Fluxio is not dashboard-first.

---

# Command Composer

The command composer is the primary interaction point.

It should feel:
- lightweight
- fast
- operational
- keyboard-first

The user should immediately understand:

```text id="c06z04"
"I can operate the system from here."
```

The composer currently supports:
- multiline input
- proposal refinement
- proposal continuity
- command history
- contextual placeholders
- operational reset flows

Future direction:
- autocomplete
- contextual suggestions
- voice workflows

---

# Live Parsing Feedback

Fluxio intentionally exposes interpretation in real time.

The frontend should render:
- detected intent
- detected entities
- confidence level
- proposal readiness

This creates:
- transparency
- explainability
- operational trust

The system should never feel opaque.

---

# Mobile Operational UX

Fluxio mobile is NOT intended to become:
- a miniature ERP
- a mobile dashboard
- a chat application

The mobile direction is:
- command-first
- proposal-centric
- operational

Target scenarios:
- commercial agents
- field operations
- mobile workflows
- future voice interaction

Current mobile work:
- responsive operational shell
- compact proposal rail
- mobile-safe command composer
- operational spacing system

Voice support is intentionally postponed until proposal semantics mature further.

---

# Theme & Visual Consistency

Fluxio currently supports:
- dark theme
- light theme
- system theme detection

The visual direction prioritizes:
- operational readability
- semantic contrast
- restrained color usage
- calm enterprise aesthetics

Current design direction:
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
- excessive gradients
- consumer-chat styling
- marketing-heavy UI patterns

---

# Motion & Animation

Motion should reinforce operational clarity, never distract from it.

Allowed:
- proposal transitions
- refinement transitions
- execution state transitions
- lightweight hover feedback

Avoid:
- excessive motion
- playful animations
- distracting microinteractions

The product should feel operational, not playful.

---

# Frontend Architecture Principles

Frontend architecture should remain:
- composable-driven
- modular
- predictable
- lightweight
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
- chat timelines
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

Tailwind is used as:
- a design implementation layer
- a token-driven utility system

Fluxio should progressively standardize:
- semantic utility classes
- spacing rules
- typography rules
- state colors
- shared layout patterns

Preferred semantic utilities:

```text id="l65jsr"
bg-surface
text-muted
border-border
bg-accent
```

instead of repeated raw CSS variables.

Avoid uncontrolled utility chaos.

---

# AI-First Principle

Fluxio is NOT an “AI wrapper.”

The frontend exists to:
- operationalize AI assistance
- expose proposal lifecycle
- preserve human control
- maintain explainability
- structure execution

AI should remain:
- assistive
- explainable
- deterministic-first
- operationally constrained

The frontend should reinforce this visually and behaviorally.

---

# Future Frontend Direction

## Proposal Intelligence

Planned work:
- richer ambiguity workflows
- mutation intelligence UX
- contextual refinement semantics
- proposal comparison
- inline proposal editing

---

## Operational UX

Planned work:
- keyboard-first workflows
- contextual entity suggestions
- operational shortcuts
- mobile-first execution flows
- voice-oriented operational UX

---

## AI Infrastructure

Planned work:
- provider abstraction
- streaming interpretation feedback
- local inference support
- lightweight model integration
- deterministic + LLM hybrid workflows

---

# What Fluxio Should NOT Become

Fluxio should NOT evolve into:
- another dashboard CRM
- a generic AI chat clone
- an autonomous AI agent
- a form-heavy ERP
- “chat with your database”

The core value is:
- proposal-driven interaction
- controlled execution
- operational clarity
- ambiguity-aware workflows
- explainable AI assistance
- deterministic refinement

---

# Long-Term Vision

Fluxio aims to explore a future where business software becomes:
- proposal-centric
- operational
- ambiguity-aware
- validation-first
- confidence-aware
- AI-assisted

without sacrificing:
- control
- predictability
- explainability
- operational safety

The frontend is the visible manifestation of that vision.

The long-term goal is NOT:
- replacing operators with AI

The long-term goal is:

```text id="ojitn9"
Building operational software where AI assists structured human decision-making through validated Action Proposals.
```