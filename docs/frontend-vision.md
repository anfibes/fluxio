# Fluxio Frontend Vision

---

# Purpose

The Fluxio frontend is NOT designed as a traditional CRM dashboard.

Its purpose is to explore a new interaction model for business software:
- command-first
- proposal-driven
- AI-assisted
- validation-centric

The frontend exists to support:
- natural-language operational workflows
- proposal refinement
- explicit execution control
- confidence-aware business interaction

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
→ Validation
→ Confirmation
→ Execution
```

The proposal is the central UI object.

The conversation exists only to support proposal refinement.

---

# Design Principles

The frontend should always feel:

- calm
- operational
- explicit
- trustworthy
- structured
- confidence-aware

The interface should avoid:
- visual noise
- aggressive animations
- consumer-chat aesthetics
- dashboard overload
- excessive color usage
- "magic AI" feeling

Fluxio should communicate:
- control
- transparency
- explainability
- operational clarity

---

# UX Direction

Fluxio is intentionally:
- AI-first
- proposal-centric
- execution-oriented

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
- what will happen after execution

---

# Main Interaction Flow

Current frontend flow:

```text
Login
→ Command input
→ Live interpretation
→ Action Proposal
→ Missing information detection
→ Proposal refinement
→ Confirmation
→ Execution
→ Execution result
```

The frontend must make this lifecycle explicit at every step.

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
│ operational items   │ execution changes                  │
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
- command refinement
- conversational continuation
- future autocomplete
- future context suggestions

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

The rail should clearly display:
- proposal state
- editable fields
- missing information
- execution changes
- warnings
- execution results

The rail should feel:
- structured
- stable
- explicit
- operational

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

---

# Missing Information UX

One of Fluxio's strongest interaction patterns is:
- incomplete proposals
- progressive refinement
- conversational completion

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

Fluxio should support iterative refinement of proposals.

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
- avoid creating disconnected interactions

The experience should feel:
- progressive
- contextual
- operational

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

---

# Execution Result UX

After execution, the interface should show:
- success state
- execution metadata
- created resource references
- execution outcome

Failures should remain visible and understandable.

The system should not hide operational failures.

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
- fade states
- execution state transitions
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

The frontend is NOT an "AI wrapper."

The frontend exists to:
- operationalize AI assistance
- structure execution
- expose proposal lifecycle
- maintain human control

AI must remain:
- assistive
- explainable
- controllable

The frontend should reinforce this philosophy visually.

---

# Future Frontend Goals

Planned directions:

- conversational proposal continuation
- contextual refinement memory
- keyboard-first workflows
- command history
- operational timeline
- contextual entity suggestions
- inline proposal editing
- proposal comparison
- optimistic refinement
- streaming interpretation feedback
- AI provider abstraction support

Long-term:
- multi-proposal workflows
- chained proposal execution
- operational AI workspaces

---

# What Fluxio Should NOT Become

Fluxio should NOT evolve into:
- another dashboard CRM
- a generic AI chat clone
- an autonomous AI agent
- a form-heavy ERP
- a "chat with your database" gimmick

The core value is:
- proposal-driven business interaction
- explicit operational control
- explainable AI-assisted workflows

---

# Current Vertical Slice

Currently implemented frontend flow:

1. User logs in
2. User writes command
3. Frontend calls `/api/actions/interpret`
4. Proposal rail renders:
   - fields
   - missing information
   - changes
   - confidence
5. User confirms
6. Frontend calls:
   - `/confirm`
   - `/execute`
7. Execution result is rendered

This vertical slice already demonstrates the central Fluxio philosophy.

---

# Long-Term Vision

Fluxio aims to explore a future where business software becomes:
- conversational
- operational
- proposal-centric
- validation-first
- confidence-aware

without sacrificing:
- control
- predictability
- explainability
- operational safety

The frontend is the visible manifestation of that vision.