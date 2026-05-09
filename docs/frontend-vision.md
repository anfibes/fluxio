# Fluxio Frontend Vision

---

# Purpose

The Fluxio frontend is NOT designed as a traditional CRM dashboard.

Its purpose is to explore a new interaction model for business software:
- command-first
- proposal-driven
- AI-assisted
- validation-centric
- continuity-aware
- operational

The frontend exists to support:
- natural-language operational workflows
- proposal refinement
- proposal continuity
- explicit execution control
- confidence-aware business interaction
- proposal mutation transparency

The UI is intentionally designed to feel closer to:
- a business copilot
- an operational workspace
- a structured execution environment

than to:
- a classic ERP
- a form-heavy CRM
- a chatbot interface

---

# Core Philosophy

Traditional CRM systems are typically:
- CRUD-first
- navigation-heavy
- form-centric
- dashboard-dominated

Fluxio intentionally moves toward:

```text
Command
→ Proposal
→ Refinement
→ Validation
→ Confirmation
→ Execution
```

The proposal is the central UI object.

Conversation exists ONLY to:
- refine proposals
- resolve ambiguity
- gather missing information
- improve confidence

Conversation does NOT exist to:
- simulate AI personalities
- maintain infinite timelines
- generate conversational clutter

Fluxio is NOT:
- a generic assistant
- a chat wrapper
- an autonomous AI agent

---

# Design Principles

The frontend should always feel:

- calm
- operational
- explicit
- trustworthy
- structured
- confidence-aware
- deterministic

The interface should avoid:
- visual noise
- aggressive animations
- consumer-chat aesthetics
- dashboard overload
- excessive color usage
- “magic AI” feeling

Fluxio should communicate:
- control
- transparency
- explainability
- operational clarity
- proposal continuity

---

# UX Direction

Fluxio is intentionally:
- AI-first
- proposal-centric
- execution-oriented
- operational

The system should feel like:
- an operator console
- a command workspace
- an execution copilot

NOT:
- a chat app
- a generic assistant
- an autonomous agent

The user must always understand:
- what the system detected
- what the system inferred
- what is still missing
- what changed after refinement
- what will happen after execution

---

# Main Interaction Flow

Current frontend flow:

```text
Login
→ Command input
→ Proposal creation
→ Proposal continuity
→ Proposal refinement
→ Validation
→ Confirmation
→ Execution
→ Execution result
```

The frontend must make this lifecycle explicit at every step.

---

# Proposal Continuity UX

Proposal continuity is one of the core UX principles of Fluxio.

The same proposal evolves over time.

Example:

User:

```text
Schedule a call with Rossini
```

System:
- creates draft proposal
- detects missing fields
- blocks execution

User:

```text
Tomorrow morning
```

System:
- updates SAME proposal
- fills missing fields
- improves confidence
- transitions proposal toward `ready`

The frontend must clearly communicate:
- proposal continuity
- proposal mutation
- proposal identity stability
- operational progression

The UX should never feel like:
- disconnected chat messages
- multiple isolated assistant replies
- fragmented operations

---

# Core Layout

Current layout structure:

```text
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
│ operational items   │ refinement metadata                │
│                     │ execution changes                  │
│                     │ confirm & execute                  │
└─────────────────────┴────────────────────────────────────┘
```

The proposal rail is the most important UI area.

---

# Sidebar Philosophy

The sidebar should remain:
- compact
- secondary
- operational

It should provide:
- navigation
- module access
- contextual shortcuts

But it must NOT dominate the interface.

Fluxio is not dashboard-first.

---

# Command Composer

The command composer is the primary interaction point.

It should feel:
- fast
- lightweight
- focused
- operational

The user should immediately understand:

```text
"I can operate the system from here."
```

The composer should support:
- multiline input
- keyboard-first interaction
- proposal refinement
- proposal continuation
- future autocomplete
- future context suggestions

An important UX insight:

Users naturally resend full commands during refinement.

Example:

Original proposal:

```text
Schedule a call with Rossini
```

User refinement:

```text
Schedule a call with Rossini Tomorrow morning
```

The frontend should support this behavior naturally without:
- creating new proposals
- breaking proposal continuity
- creating chat-like duplication

---

# Live Parsing Feedback

Fluxio exposes interpretation in real time.

The UI should show:
- detected intent
- detected entities
- confidence level
- proposal readiness

This creates:
- transparency
- explainability
- trust

The system should never appear mysterious or opaque.

---

# Action Proposal Rail

The proposal rail is the core UX object.

It represents:
- interpretation
- validation
- execution intent
- operational consequence
- proposal continuity
- refinement evolution

The rail should clearly display:
- proposal state
- editable fields
- missing information
- execution changes
- warnings
- refinement metadata
- execution results

The rail should feel:
- structured
- stable
- explicit
- operational

The proposal rail acts as:
- operational truth panel
- proposal inspector
- mutation summary surface
- execution visibility layer

---

# Proposal States

The frontend must visually distinguish proposal states:

| State | Meaning |
|---|---|
| `draft` | incomplete proposal |
| `ready` | executable proposal |
| `confirmed` | approved proposal |
| `executed` | successful execution |
| `failed` | execution failure |

The UI should expose lifecycle state clearly and honestly.

---

# Confidence UX

Fluxio intentionally surfaces uncertainty.

Confidence is a core UX concept.

The frontend should:
- expose low confidence
- encourage review
- avoid fake certainty
- reduce blind trust

Low confidence is NOT considered failure.

It is part of:
- explainable AI interaction
- operational transparency
- safe execution workflows

The UI should never pretend certainty when ambiguity exists.

---

# Missing Information UX

One of Fluxio's strongest interaction patterns is:
- incomplete proposals
- progressive refinement
- operational completion

Example:

User:

```text
Schedule a call with Rossini
```

The system should:
- create a draft proposal
- expose missing fields
- disable execution
- guide refinement

This UX pattern is central to Fluxio.

---

# Conversational Refinement

Fluxio supports iterative proposal refinement.

Example:

```text
Schedule a call with Rossini
```

Then:

```text
Tomorrow morning
```

The frontend should:
- preserve proposal continuity
- update the existing proposal
- expose proposal mutations
- avoid creating disconnected interactions

The experience should feel:
- progressive
- contextual
- operational
- stateful

NOT:
- chat-oriented
- conversationally noisy
- assistant-like

---

# Proposal Mutation Transparency

Fluxio intentionally surfaces proposal mutations.

The frontend should clearly render:
- latest refinement
- changed fields
- proposal state transitions
- execution state changes

Example:

```text
Last update
"Tomorrow morning"

Date → 2026-05-10
Time → 09:00
Status → ready
```

This UX exists to create:
- operational trust
- explainability
- deterministic visibility
- proposal transparency

The system should communicate:

```text
"The proposal changed."
```

NOT:

```text
"The assistant replied."
```

This distinction is extremely important.

---

# Last Refinement UX

Fluxio currently exposes:
- raw refinement text
- effective refinement text
- refinement summary
- field mutations

The frontend should prefer rendering:
- effective refinement text

instead of:
- duplicated full commands

Example:

Avoid:

```text
Schedule a call with Rossini Tomorrow morning
```

Prefer:

```text
Tomorrow morning
```

This creates:
- cleaner operational UX
- less conversational clutter
- stronger proposal continuity perception

---

# Execution UX

Execution must feel:
- explicit
- intentional
- controlled

The frontend must never:
- auto-execute silently
- hide side effects
- obscure business mutations

Before execution, the UI should communicate:
- what will happen
- which module is affected
- which entity will change

Execution is always:
- deliberate
- reviewable
- operational

---

# Execution Result UX

After execution, the interface should show:
- success state
- execution metadata
- created resource references
- execution outcome

Failures should remain visible and understandable.

The system should not hide operational failures.

Execution results should feel:
- factual
- operational
- structured

NOT conversational.

---

# Visual Direction

Current visual direction:
- dark enterprise SaaS
- restrained color palette
- compact density
- operational typography
- minimal chrome
- subtle depth
- clean spacing

Primary inspirations:
- Linear
- Notion
- European B2B SaaS products
- operational tooling interfaces

Fluxio should avoid:
- flashy AI aesthetics
- excessive gradients
- consumer-style chat visuals
- marketing-heavy UI patterns

---

# Motion and Animation

Animations should remain:
- subtle
- purposeful
- restrained

Allowed:
- proposal transitions
- refinement transitions
- execution state transitions
- fade states
- lightweight hover feedback

Avoid:
- excessive motion
- playful transitions
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
- presentational UI components
- explicit data flow
- isolated concerns

Avoid:
- giant page components
- implicit state mutation
- excessive global stores
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

Current `useActionProposal()` responsibilities:
- interpret proposals
- refine proposals
- confirm proposals
- execute proposals
- preserve proposal continuity
- orchestrate lifecycle state

---

# Tailwind Philosophy

Tailwind is used as:
- a design implementation tool
- a token-driven utility layer

Fluxio should progressively standardize:
- semantic utility classes
- design tokens
- shared spacing rules
- typography rules
- state colors

Avoid uncontrolled inline utility chaos.

Current direction:
- `bg-surface`
- `text-muted`
- `border-border`
- `bg-accent`

instead of repeated raw CSS variables.

---

# AI-First Principle

The frontend is NOT an “AI wrapper.”

The frontend exists to:
- operationalize AI assistance
- structure execution
- expose proposal lifecycle
- expose proposal continuity
- expose proposal mutations
- maintain human control

AI must remain:
- assistive
- explainable
- controllable
- deterministic-first

The frontend should reinforce this philosophy visually.

---

# Ambiguity UX Direction

The next major UX milestone is:
- ambiguity-aware operational workflows

Example:

```text
Call Rossi
```

When multiple Rossi entities exist:
- ambiguity must become visible
- execution must remain blocked
- the proposal must remain active
- clarification must refine the SAME proposal

Fluxio must NEVER:
- choose arbitrarily
- fake confidence
- silently infer dangerous actions

The ambiguity workflow should feel:
- operational
- structured
- confidence-aware

NOT conversational.

---

# Future Frontend Goals

Planned directions:

- ambiguity resolution UX
- candidate entity workflows
- contextual refinement semantics
- keyboard-first workflows
- command history
- contextual entity suggestions
- inline proposal editing
- proposal comparison
- optimistic refinement
- streaming interpretation feedback
- AI provider abstraction support

Long-term:
- multi-step operational proposals
- chained proposal execution
- operational AI workspaces

---

# What Fluxio Should NOT Become

Fluxio should NOT evolve into:
- another dashboard CRM
- a generic AI chat clone
- an autonomous AI agent
- a form-heavy ERP
- a “chat with your database” gimmick

The core value is:
- proposal-driven business interaction
- explicit operational control
- explainable AI-assisted workflows
- proposal continuity
- deterministic refinement

---

# Current Vertical Slice

Currently implemented frontend flow:

1. User logs in
2. User writes command:

```text
Schedule a call with Rossini
```

3. Frontend calls `/api/actions/interpret`
4. Proposal rail renders:
   - fields
   - missing information
   - changes
   - confidence
5. Proposal remains in `draft`
6. User refines:

```text
Tomorrow morning
```

7. Frontend calls `/api/actions/{proposal}/refine`
8. SAME proposal is updated
9. Proposal transitions toward `ready`
10. User confirms
11. Frontend calls:
   - `/confirm`
   - `/execute`
12. Execution result is rendered

This vertical slice now demonstrates:
- proposal continuity
- operational refinement
- AI-first operational UX
- proposal mutation transparency
- deterministic execution workflows

---

# Long-Term Vision

Fluxio aims to explore a future where business software becomes:
- conversational
- operational
- proposal-centric
- validation-first
- confidence-aware
- ambiguity-aware

without sacrificing:
- control
- predictability
- explainability
- operational safety

The frontend is the visible manifestation of that vision.

The long-term goal is NOT:
- replacing operators with AI

The long-term goal is:

```text
Building operational software where AI assists structured human decision-making through validated Action Proposals.
```