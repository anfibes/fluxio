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

## Current Results

Latest recorded run (small local reference model, 50-case corpus):

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

## Future Evaluation Work

Possible next steps for the evaluation itself — all diagnostic, none implying a
change to runtime authority:

- **Additional corpus coverage** — more phrasings per intent, more adversarial
  and out-of-domain inputs, and clearer expectations for cases that currently
  assert only an intent.
- **Provider comparisons** — evaluating additional local models, or larger
  variants, against the same corpus and baseline.
- **Prompt experiments** — measuring whether prompt changes reduce the
  placeholder-on-optional-fields failure pattern, re-checked through the same
  diagnostic so any change is a measured delta rather than an assertion.
- **Diagnostics improvements** — richer per-case reporting (including the raw
  rejected output) and failure-mode tagging, so future runs are easier to read
  and trend.

These are evaluation activities, not committed features. Nothing here describes a
future runtime architecture or a planned change to how Fluxio executes
operations; the deterministic-first, proposal-centric model described in the
architecture and proposal-lifecycle documents continues to hold.
