# LLM Interpretation Evaluation

This document reports how Fluxio evaluated a local LLM as an *interpretation*
component and what that evaluation showed. It is an engineering report, not a
roadmap and not a product claim.

It assumes the reader already knows the proposal-driven model. It does not
re-explain the runtime, the proposal lifecycle, the modular architecture, or the
frontend philosophy — those live in
[`docs/architecture.md`](architecture.md),
[`docs/proposal-lifecycle.md`](proposal-lifecycle.md), and
[`docs/frontend-vision.md`](frontend-vision.md). Where this report touches those
topics it links to them rather than restating them.

---

## Purpose

Fluxio executes operations through validated Action Proposals, never directly
from natural language. Interpretation — turning a sentence into an intent and a
set of entities — is the one step where a language model could plausibly help.

The open question was narrow and testable:

> Can a small, local language model produce interpretations good enough to be
> useful, *without* being given any authority over execution?

To answer it without disturbing the runtime, Fluxio introduced an
**interpretation provider abstraction**: a single seam
(`InterpretationProviderInterface`) behind which the interpretation step can be
swapped. The rule-based parser implements it; an experimental local-LLM provider
also implements it. Everything downstream of that seam — validation, proposal
construction, ambiguity handling, confirmation, execution — is identical
regardless of which provider ran.

The abstraction exists so that a model can be *measured against* the
deterministic baseline as a peer, while staying structurally incapable of doing
anything except proposing a candidate interpretation. The architecture of that
seam is described in
[`docs/architecture.md` → Interpretation Sandbox Layer](architecture.md#interpretation-sandbox-layer);
this document is about what happened when it was exercised.

---

## Architectural Position

Interpretation is the first stage of a longer, deterministic pipeline:

```text
Natural language
→ interpretation            (provider: deterministic OR local-LLM sandbox)
→ Action Proposal
→ validation
→ confirmation
→ execution                 (deterministic authority)
```

Only the first arrow is a provider concern. A provider's entire output is a
*candidate* normalized command (an intent, a confidence value, and labelled
entities). It is consumed, validated, and — if it survives validation — turned
into a proposal by code the provider does not touch.

A provider, LLM-backed or otherwise, never:

- executes operations,
- owns or mutates proposal state,
- owns ambiguity state or resolves entities,
- decides readiness, confirmation, or execution.

Those are deterministic runtime authorities and remain so. The detailed
ownership boundaries (entity resolution, ambiguity lifecycle, capability gating,
execution safety) are documented in
[`docs/proposal-lifecycle.md`](proposal-lifecycle.md) and are out of scope here.

The practical consequence for evaluation: a model can be *wrong* — wrong intent,
missing entity, malformed output — and the worst case is a rejected candidate, never
an unsafe operation. That property is what made it safe to evaluate a model at all.

---

## Evaluation Approach

The evaluation compares two providers over a fixed corpus and records, per case,
how each one did. It is **development-only**: it runs through Artisan
diagnostics, is blocked in production, and never executes during a real proposal
request.

The pieces:

- **Deterministic baseline.** The rule-based provider is the reference oracle.
  It is the authoritative runtime path, so "how does the model compare to what
  ships today" is the question that matters, not "how does the model do in the
  abstract."

- **Local LLM sandbox.** An opt-in provider backed by a local Ollama runtime
  (reference model: a small ~0.6B-parameter local model, run at temperature 0
  for repeatability). It is never the default and is not production-authoritative.

- **Structured-output validation.** Before any model output becomes a candidate
  command, it must pass a fail-closed structured-output contract (allowed
  fields, allowed intents, allowed per-intent entity keys, value shapes, no
  identifiers or lifecycle fields). Output that violates the contract is
  rejected, not repaired. This is the same boundary the runtime would apply if
  the provider were ever enabled, so the evaluation measures the model against
  the real contract rather than a lenient one.

- **Interpretation corpus.** A fixed set of 50 English cases covering every
  implemented intent (`create_task`, `schedule_call`, `schedule_meeting`,
  `assign_lead`, `prepare_contract_from_quote`) plus out-of-domain inputs that
  should resolve to `unknown`. Each case asserts an expected intent and, where
  stable, expected entities. Run-relative values (e.g. "tomorrow", "next
  Friday") are deliberately not asserted, because they depend on when the
  evaluation runs. A corpus-integrity test keeps the cases well-formed and
  aligned with the registered intents.

- **Regression-oriented reading.** Results are read *relative to the
  deterministic baseline*, not as an absolute score. The diagnostic flags any
  case where enabling the model would have produced a worse outcome than the
  baseline already gives. The goal of the exercise is a comparison signal, not a
  leaderboard number.

Matching is exact and conservative: an intent matches only if it equals the
expected intent; entities match only if every asserted key is present with an
identical value. There is no fuzzy or semantic credit. A "match" therefore means
"agrees with the corpus," not "is semantically correct in general."

---

## Results — original English run

The first evaluation used the small reference model on the 50-case English corpus. It established the
deterministic-boundary findings below and remains the baseline this work grew from; the diagnostics
have since been extended to a held-out Italian corpus and multiple driving strategies (see
*Multilingual diagnostics* below).

Recorded run (small local reference model, 50-case English corpus):

| Metric | Value | What it means |
|---|---|---|
| Corpus size | 50 | Cases evaluated, covering all intents + out-of-domain inputs. |
| Success rate | 92% | Share of cases where the model produced output that passed the structured-output contract and yielded a candidate command. **Success ≠ correctness** — it only means the output was well-formed and contract-valid. |
| Intent match rate | 76% | Share where the produced intent equalled the expected intent. |
| Entity match rate | 66% | Share where every asserted entity matched exactly (key and value). |
| Provider failures | 4 | Cases where the model's output was rejected by the structured-output contract (the complement of the 92% success rate). |
| Regressions | 0 | Cases where the model would have produced a worse outcome than the deterministic baseline, as flagged by the diagnostic. |

How to read these honestly:

- The three rates measure **different things** and should not be collapsed into
  one figure. A case can succeed (well-formed output) while still mismatching
  the expected intent or entities.
- These are corpus-relative numbers from one model on a 50-case set. They
  describe behaviour on this corpus, not general interpretation quality, and
  local-model output is not guaranteed identical across model or runtime
  upgrades.
- The numbers are a **comparison and diagnostic signal**. They are not a quality
  bar the project has committed to, and they do not imply the model is ready for
  any runtime role.

---

## What Failed

The four provider failures shared a single pattern: they were
**structured-output contract violations, not misunderstandings of the request.**

In each failing case the model chose a reasonable intent but populated
*optional* entity fields with **empty placeholder values** — for example an empty
list, or a key set to null — instead of omitting fields it had no value for. The
contract requires absent information to be *omitted*, not represented as an empty
placeholder, so the structured-output validator rejected the output.

The important part is what *didn't* happen as a result:

- The rejected outputs **never entered the proposal lifecycle.** Validation
  fail-closed at the provider boundary, exactly as designed.
- **Runtime behaviour was unaffected.** These failures were observed in
  development diagnostics, against the sandbox provider. The deterministic
  provider remained the runtime authority throughout; no proposal, execution, or
  user-facing path was involved.

The full per-case failure analysis is internal engineering detail and is not
reproduced here; the relevant public takeaway is the pattern (placeholder values
on optional fields) and the outcome (rejected before the lifecycle, no runtime
impact).

---

## Key Lessons

The evaluation supports a few architectural conclusions:

- **Interpretation quality and execution safety are separate concerns.** A model
  can be a mediocre interpreter and still be perfectly safe, because safety comes
  from the validation-and-proposal boundary, not from the model's accuracy. The
  two were measured independently and behaved independently.

- **Small local models can be useful interpreters.** On this corpus a small
  local model produced contract-valid output most of the time and matched the
  baseline's intent on most cases. That is a meaningful signal for a component
  that is only ever advisory — without implying it is ready to be authoritative.

- **Deterministic validation remains essential.** Every failure observed was
  caught by structured-output validation and stopped before it could affect a
  proposal. Removing or relaxing that boundary to make a model "look better"
  would trade a measurable safety property for a cosmetic metric. The boundary is
  the point.

- **A proposal-centric workflow is a safer integration boundary than direct
  execution.** Because a model only ever produces a candidate that is validated
  and turned into a reviewable proposal, the cost of a bad interpretation is a
  rejected or refinable proposal — never an incorrect operation. This is the same
  invariant the rest of Fluxio is built on, observed here specifically at the
  model boundary.

---

## Current Status

To state it plainly, with no ambiguity:

- The **deterministic provider remains authoritative** and is the default
  runtime interpretation path.
- The **local LLM provider is sandbox-only** and opt-in. It is not
  production-authoritative.
- **No hybrid runtime exists.** Exactly one provider is active per request;
  there is no fallback chain and no merging of provider outputs. The deterministic
  vs. LLM comparison exists only as development diagnostics.
- **No provider owns runtime state** — not proposal state, ambiguity state,
  readiness, confirmation, or execution.
- **No provider bypasses validation.** All provider output passes the same
  fail-closed validation before it can become a proposal.

---

## Multilingual diagnostics (since this report)

The evaluation steps this report originally listed as "future work" have since been built — still
**development-only diagnostics**, with no change to runtime authority. The internal architecture and
slice-by-slice findings live in
[`.docs/diagnostics-architecture.md`](../.docs/diagnostics-architecture.md); the summary:

- **Held-out Italian corpus.** A 93-case Italian evaluation corpus (all implemented intents +
  out-of-domain `unknown`), authored idiomatically and held out from the few-shot example library, now
  measures multilingual interpretation. Entity expectations are conservative and exact-match, as in the
  English set.
- **Capability-class driving.** Models are driven by *how they must be driven*, not their size:
  an `instruction_following` class uses the forced-JSON contract directly; a `reasoning` class is
  driven with thinking disabled (a thinking-mode × forced-JSON interaction otherwise produced empty
  output). This mapping and its transport knobs are diagnostics-only profiles.
- **Few-shot exemplars from the example library.** Locale-appropriate exemplars can be prepended to the
  prompt as an opt-in, append-only block (the no-exemplar prompt stays byte-identical to runtime).
  Exemplar choice is deterministic and metadata-driven (same intent, slot overlap) — no embeddings or
  retrieval. Few-shot is enabled by capability class: off for the small model (it regressed), on for
  the reasoning model (it helped).
- **Model comparison.** The same corpus runs across multiple local models side by side, with per-model
  metrics and per-intent breakdowns.
- **Prompt experiments.** Targeted, diagnostics-only prompt-guidance variants can be appended and
  measured as a delta; they are kept experiment-only unless a benchmark shows a net improvement with no
  guard regressions (none has been promoted to a default).
- **Richer reporting.** Per-case output now separates produced / contract-valid / intent-match /
  entity-agreement, with per-intent breakdowns, raw provider body capture, and strategy comparison
  deltas.

Current strongest *diagnostic* result on the 93-case Italian corpus: a small reasoning model
(qwen3:1.7b), driven reasoning-aware with selected few-shot, reaches ~91% intent-match. This is a
corpus-relative, directional signal — not a committed quality bar, and not a step toward making any
model runtime-authoritative.

## Still future

Genuinely not built, and not presented as such anywhere:

- **Runtime LLM adoption or hybrid interpretation** — the runtime stays single-provider with
  deterministic as the default and authoritative path.
- **Retrieval / embeddings / interpretation memory** — out of scope for the current diagnostics.
- **Promoting any prompt variant or larger-accuracy profile to a default** — gated on a clean,
  regression-free benchmark.

These are evaluation activities, not committed features. Nothing here describes a future runtime
architecture or a planned change to how Fluxio executes operations; the deterministic-first,
proposal-centric model described in the architecture and proposal-lifecycle documents continues to
hold.
