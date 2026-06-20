<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\Ambiguity\AmbiguitySelector;
use Fluxio\Actions\DTO\Ambiguity\AttributeSelector;
use Fluxio\Actions\DTO\Ambiguity\SemanticAmbiguityClarification;
use Fluxio\Actions\DTO\EditableFieldExplanation;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\ProposalRuntimeContext;
use Fluxio\Actions\DTO\RefinementAmbiguityOutcome;
use Fluxio\Actions\DTO\SemanticOperation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Registry\IntentCapabilityRegistry;
use Fluxio\Actions\Support\AmbiguityDirectiveLowerer;
use Fluxio\Actions\Support\AmbiguityResolver;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Fluxio\Actions\Support\ProposalRuntimeContextFactory;
use Fluxio\Actions\Support\SemanticRefinementLowerer;
use Fluxio\Actions\Support\TemporalParseResult;
use Illuminate\Validation\ValidationException;

class ActionProposalRefinementService
{
    private const REFINABLE_STATUSES = ['draft', 'ready'];

    public function __construct(
        private readonly RefinementInterpreterInterface $interpreter,
        private readonly IntentCapabilityRegistry $capabilityRegistry,
        private readonly ProposalRuntimeContextFactory $contextFactory,
        private readonly DateTimeExpressionParser $parser,
        private readonly SemanticRefinementLowerer $lowerer,
        private readonly AmbiguityDirectiveLowerer $ambiguityLowerer,
        private readonly AmbiguityResolver $ambiguityResolver,
    ) {}

    public function refine(ActionProposal $proposal, string $text): ActionProposal
    {
        if (! in_array($proposal->status, self::REFINABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'proposal' => [__('actions::actions.cannot_refine')],
            ]);
        }

        // Phase 7A: deterministic, proposal-scoped runtime context derived from the
        // current proposal state. Read-only and non-persisted; used internally below
        // (e.g. blocking-ambiguity detection) and available for future context-aware
        // refinement. It does not change public behavior or the proposal payload.
        $context = $this->contextFactory->fromProposal($proposal);

        $effectiveText = $this->effectiveText($text, $proposal->source_text);

        // Delegate NL → semantic interpretation to the interpreter, then partition
        // the semantic operation stream: field mutations are lowered into structural
        // NormalizedMutation (Phase 8D.2); the at-most-one ambiguity clarification is
        // routed through the AmbiguityResolver authority below (Phase 8D.3).
        [$fieldMutations, $clarification] = $this->partitionSemanticOperations(
            $this->interpreter->interpret($effectiveText)
        );

        // Capability validation: filter out mutations that are not permitted for this intent.
        // Disallowed mutations produce a warning; the proposal is left unchanged for that field.
        [$fieldMutations, $rejectedMutations] = $this->partitionByCapability($proposal->intent, $fieldMutations);

        if (! empty($rejectedMutations)) {
            $proposal->warnings = $this->addWarning(
                $proposal->warnings ?? [],
                __('actions::actions.mutation_not_allowed'),
            );
        }

        // If ALL mutations were rejected and there is nothing else to process, bail early.
        // No ambiguity clarification is driven on this path (the interpreter emits either
        // field mutations or a clarification, never both), so ambiguity_outcome is null.
        if (! empty($rejectedMutations) && empty($fieldMutations)) {
            $proposal->last_refinement = $this->buildLastRefinement($text, $effectiveText, 'No changes applied.', []);
            $proposal->save();

            return $proposal;
        }

        // Phase 7B: resolve contextual mutations (e.g. relative temporal shifts)
        // against the read-only ProposalRuntimeContext, turning them into concrete
        // mutations before application. Unresolvable ones are dropped with a warning.
        [$fieldMutations, $contextualWarnings] = $this->resolveContextualMutations($fieldMutations, $context);
        $hadContextualWarning = ! empty($contextualWarnings);

        foreach ($contextualWarnings as $warning) {
            $proposal->warnings = $this->addWarning($proposal->warnings ?? [], $warning);
        }

        // Try ambiguity resolution when a blocking ambiguity exists.
        // Check capability first — if the intent does not support ambiguity resolution,
        // skip the attempt entirely.
        //
        // Phase 8D.3: resolution is selector-driven. The interpreter extracted a
        // stateless ambiguity selector; here the service binds it to the first
        // unresolved blocking ambiguity and delegates matching/narrowing/selection
        // to the deterministic AmbiguityResolver. The service no longer matches
        // candidates, parses ordinals, or classifies company/person itself.
        // Phase 8E.1: the ambiguity-state authority's outcome, projected as an inert
        // reporting facet of last_refinement. It is derived from the resolver's
        // ResolutionOutcome (and orchestration state), written for the response only,
        // and never read back by the runtime. The authoritative candidate state stays
        // in $proposal->ambiguities.
        $ambiguityResolution = null;
        $ambiguityOutcome = null;
        if ($this->hasUnresolvedBlockingAmbiguity($context)) {
            if ($this->intentSupportsAmbiguityResolution($proposal->intent)) {
                $targetAmbiguity = null;
                $outcome = null;

                if ($clarification !== null) {
                    [$targetAmbiguity, $outcome] = $this->resolveAgainstFirstBlockingAmbiguity(
                        $proposal,
                        $clarification->selector,
                    );

                    if ($outcome !== null && $outcome->isResolved()) {
                        $ambiguityResolution = [
                            'ambiguity_key' => $targetAmbiguity['key'],
                            'ambiguity_label' => $targetAmbiguity['label'],
                            'candidate' => $outcome->selectedCandidate,
                        ];
                        // Inert report only: the resolution is applied in applyAll().
                        $ambiguityOutcome = RefinementAmbiguityOutcome::resolved(
                            $targetAmbiguity['key'],
                            $targetAmbiguity['label'],
                        );
                    }
                }

                if ($ambiguityResolution === null && empty($fieldMutations)) {
                    // A type clarification (e.g. "the company") that still matches
                    // multiple candidates progressively narrows the active candidate
                    // set while keeping the ambiguity blocking; the warning names the
                    // remaining candidates. Otherwise the generic "be more specific"
                    // warning is used. (Phase 7C.3 behaviour, now resolver-driven.)
                    if ($outcome !== null && $outcome->isNarrowed()) {
                        $fromCount = count($targetAmbiguity['candidates'] ?? []);
                        $proposal->ambiguities = $this->applyNarrowedCandidates(
                            $proposal->ambiguities ?? [],
                            $targetAmbiguity['key'],
                            $outcome->narrowedCandidates,
                        );
                        $warning = $this->narrowedWarningMessage(
                            $clarification->selector instanceof AttributeSelector ? $clarification->selector->value : '',
                            $outcome->narrowedCandidates,
                        );
                        // Inert report: counts only, never the narrowed candidate array
                        // (authoritative set is proposal->ambiguities).
                        $ambiguityOutcome = RefinementAmbiguityOutcome::narrowed(
                            $targetAmbiguity['key'],
                            $targetAmbiguity['label'],
                            $fromCount,
                            count($outcome->narrowedCandidates),
                        );
                        $summary = 'Ambiguity narrowed.';
                    } else {
                        $warning = __('actions::actions.ambiguity_still_unresolved');
                        $summary = 'No changes applied.';
                        // A selector that matched zero / was out of range is a
                        // resolver-level unresolved outcome. (A turn that extracted no
                        // selector drove nothing — $outcome is null — and is left as a
                        // null facet, not reported as a resolver outcome.)
                        if ($outcome !== null && $outcome->isUnresolved()) {
                            $ambiguityOutcome = RefinementAmbiguityOutcome::unresolved(
                                $targetAmbiguity['key'],
                                $targetAmbiguity['label'],
                            );
                        }
                    }

                    $proposal->warnings = $this->addWarning(
                        $proposal->warnings ?? [],
                        $warning,
                    );
                    $proposal->last_refinement = $this->buildLastRefinement(
                        $text,
                        $effectiveText,
                        $summary,
                        [],
                        $ambiguityOutcome,
                    );
                    $proposal->save();

                    return $proposal;
                }
            } else {
                // Ambiguity exists but this intent cannot resolve it via refinement.
                // Emit a warning so the caller knows why resolution was not attempted,
                // then fall through to apply any field mutations that were provided.
                $proposal->warnings = $this->addWarning(
                    $proposal->warnings ?? [],
                    __('actions::actions.ambiguity_resolution_not_supported'),
                );
            }
        }

        // Orchestration-level inapplicable: a clarification was understood, but it was
        // not driven into a resolver outcome — either no blocking ambiguity existed, or
        // the intent does not support ambiguity resolution. Reporting only.
        if ($clarification !== null && $ambiguityOutcome === null && $ambiguityResolution === null) {
            $ambiguityOutcome = RefinementAmbiguityOutcome::inapplicable();
        }

        // Nothing recognized at all
        if (empty($fieldMutations) && $ambiguityResolution === null) {
            // A contextual mutation that was understood but could not be resolved
            // (e.g. a temporal shift with no current time) already produced its own
            // specific warning — don't add the generic "not recognized" on top.
            if (! $hadContextualWarning) {
                $proposal->warnings = $this->addWarning(
                    $proposal->warnings ?? [],
                    __('actions::actions.refinement_not_recognized'),
                );
            }
            $proposal->last_refinement = $this->buildLastRefinement(
                $text,
                $effectiveText,
                'No changes applied.',
                [],
                $ambiguityOutcome,
            );
            $proposal->save();

            return $proposal;
        }

        return $this->applyAll($proposal, $fieldMutations, $ambiguityResolution, $ambiguityOutcome, $text, $effectiveText);
    }

    // ── Capability validation ────────────────────────────────────────────────

    /**
     * Split mutations into allowed and rejected lists based on intent capabilities.
     *
     * If no capability is registered for the intent, all mutations are rejected
     * (conservative default — unlisted intents have no explicit mutation contract).
     *
     * @param  NormalizedMutation[]  $mutations
     * @return array{0: NormalizedMutation[], 1: NormalizedMutation[]}
     */
    private function partitionByCapability(string $intent, array $mutations): array
    {
        $allowed = [];
        $rejected = [];

        foreach ($mutations as $mutation) {
            if ($this->capabilityRegistry->allowsMutation($intent, $mutation)) {
                $allowed[] = $mutation;
            } else {
                $rejected[] = $mutation;
            }
        }

        return [$allowed, $rejected];
    }

    /**
     * Lower the interpreter's Semantic Refinement IR field mutations into the
     * structural NormalizedMutation the rest of the pipeline (capability validation,
     * contextual resolution, applyAll) consumes unchanged.
     *
     * Phase 8D.4: every field mutation now arrives as SemanticRefinementMutation
     * (the interpreter emits no structural mutations), so there is no passthrough
     * branch — each carries its semantic type as input, with no reliance on
     * classify(). Structural NormalizedMutation exists only as lowering output.
     *
     * @param  SemanticRefinementMutation[]  $mutations
     * @return NormalizedMutation[]
     */
    private function lowerFieldMutations(array $mutations): array
    {
        return array_map(
            fn (SemanticRefinementMutation $mutation): NormalizedMutation => $this->lowerer->lower($mutation),
            $mutations,
        );
    }

    /**
     * Split the interpreter's semantic operation stream into lowered field
     * mutations and an at-most-one ambiguity clarification (coordinator step).
     * Field mutations (SemanticRefinementMutation) are lowered to NormalizedMutation;
     * the first ambiguity clarification (if any) is returned for resolver-driven
     * handling. Phase 8D.4: the stream is semantic-IR only — no structural
     * NormalizedMutation enters here.
     *
     * @param  array<SemanticOperation>  $operations
     * @return array{0: NormalizedMutation[], 1: SemanticAmbiguityClarification|null}
     */
    private function partitionSemanticOperations(array $operations): array
    {
        $fieldMutations = [];
        $clarification = null;

        foreach ($operations as $operation) {
            if ($operation instanceof SemanticAmbiguityClarification) {
                $clarification ??= $operation; // at most one per refinement; first wins
            } elseif ($operation instanceof SemanticRefinementMutation) {
                $fieldMutations[] = $operation;
            }
        }

        return [$this->lowerFieldMutations($fieldMutations), $clarification];
    }

    /**
     * Bind a stateless ambiguity selector to the first unresolved blocking
     * ambiguity on the proposal, lower it to an AmbiguityDirective, and delegate to
     * the deterministic AmbiguityResolver. Returns the targeted ambiguity and the
     * resolution outcome, or [null, null] when no unresolved blocking ambiguity
     * exists. The service performs NO candidate matching itself — it only binds the
     * key (proposal state) and applies the resolver's outcome.
     *
     * @return array{0: array<string, mixed>|null, 1: \Fluxio\Actions\DTO\Ambiguity\ResolutionOutcome|null}
     */
    private function resolveAgainstFirstBlockingAmbiguity(ActionProposal $proposal, AmbiguitySelector $selector): array
    {
        foreach ($proposal->ambiguities ?? [] as $ambiguity) {
            if (! ($ambiguity['blocking'] ?? false) || ($ambiguity['selected_candidate_id'] ?? null) !== null) {
                continue;
            }

            $directive = $this->ambiguityLowerer->lower(
                new SemanticAmbiguityClarification($ambiguity['key'], $selector),
            );

            $outcome = $this->ambiguityResolver->resolve($directive, $ambiguity['candidates'] ?? []);

            // Only the first unresolved blocking ambiguity is considered per call.
            return [$ambiguity, $outcome];
        }

        return [null, null];
    }

    /**
     * Append a warning to the list only if it is not already present.
     * Repeated refinements must not duplicate the same warning message.
     *
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function addWarning(array $warnings, string $warning): array
    {
        if (in_array($warning, $warnings, true)) {
            return array_values($warnings);
        }

        $warnings[] = $warning;

        return array_values($warnings);
    }

    // ── Contextual mutation resolution (Phase 7B) ───────────────────────────
    // Turns interpreter-detected contextual mutations into concrete ones using the
    // read-only ProposalRuntimeContext. Deterministic; no NL reasoning, no LLM.
    // Capability validation has already run, so a temporal_shift reaching here is
    // an allowed time replace for this intent.

    /**
     * Resolve contextual mutations against the proposal runtime context.
     *
     * Non-contextual mutations pass through unchanged. A contextual mutation that
     * cannot be resolved (e.g. a temporal shift with no current time) is dropped
     * and contributes a warning instead of being applied.
     *
     * @param  NormalizedMutation[]  $mutations
     * @return array{0: NormalizedMutation[], 1: list<string>}
     */
    private function resolveContextualMutations(array $mutations, ProposalRuntimeContext $context): array
    {
        $resolved = [];
        $warnings = [];

        foreach ($mutations as $mutation) {
            $operation = $mutation->metadata['contextual_operation'] ?? null;

            if ($operation !== 'temporal_shift') {
                $resolved[] = $mutation;

                continue;
            }

            $shifted = $this->resolveTemporalShift($mutation, $context);

            if ($shifted === null) {
                $warnings[] = __('actions::actions.cannot_shift_time_no_current_time');

                continue;
            }

            $resolved[] = $shifted;
        }

        return [$resolved, $warnings];
    }

    /**
     * Compute a concrete time-replace mutation by shifting the proposal's current
     * time by a signed minute offset. Returns null when there is no valid current
     * time (HH:MM) to shift — the caller must not invent a time. Time-of-day
     * arithmetic wraps within a single day and never touches the date field.
     */
    private function resolveTemporalShift(NormalizedMutation $mutation, ProposalRuntimeContext $context): ?NormalizedMutation
    {
        $current = $context->temporalValue('time');

        if (! is_string($current) || ! (bool) preg_match('/^(\d{1,2}):(\d{2})$/', $current, $m)) {
            return null;
        }

        $hours = (int) $m[1];
        $minutes = (int) $m[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        $amount = (int) ($mutation->metadata['amount'] ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $delta = ($mutation->metadata['direction'] ?? 'later') === 'earlier' ? -$amount : $amount;
        $total = ((($hours * 60 + $minutes + $delta) % 1440) + 1440) % 1440;

        return new NormalizedMutation(
            field: 'time',
            label: 'Time',
            value: sprintf('%02d:%02d', intdiv($total, 60), $total % 60),
            operation: 'replace',
            source: 'computed',
            metadata: $mutation->metadata,
            // Preserve the original semantic intent: this concrete time replace
            // came from a contextual shift, so it stays shift_time (not replace_time).
            semanticType: $mutation->semanticType,
        );
    }

    // ── Temporal explanation refresh (Phase 7C.1) ───────────────────────────
    // Keeps editable_fields[date|time].explanation coherent with the refined
    // value. Reuses the existing DateTimeExpressionParser provenance and the
    // EditableFieldExplanation DTO — no new explanation engine. Returns null when
    // a coherent explanation cannot be built, so the caller drops the stale one.

    private function refreshedTemporalExplanation(
        NormalizedMutation $mutation,
        TemporalParseResult $temporal,
        mixed $newValue,
        string $effectiveText,
    ): ?EditableFieldExplanation {
        if (! is_string($newValue) || $newValue === '') {
            return null;
        }

        return match ($mutation->semanticType()) {
            SemanticMutationType::ShiftTime => $this->shiftTimeExplanation($mutation, $newValue, $effectiveText),
            SemanticMutationType::ReplaceTime => $this->replaceTimeExplanation($temporal, $newValue, $effectiveText),
            SemanticMutationType::ReplaceDate => $this->replaceDateExplanation($temporal, $newValue, $effectiveText),
            default => null,
        };
    }

    private function shiftTimeExplanation(NormalizedMutation $mutation, string $newValue, string $effectiveText): EditableFieldExplanation
    {
        $amount = (int) ($mutation->metadata['amount'] ?? 0);
        $direction = ($mutation->metadata['direction'] ?? 'later') === 'earlier' ? 'earlier' : 'later';

        return new EditableFieldExplanation(
            source: 'computed',
            expression: trim($effectiveText),
            confidence: 1.0,
            message: "Time shifted {$direction} by {$amount} minutes to {$newValue}.",
        );
    }

    private function replaceTimeExplanation(TemporalParseResult $temporal, string $newValue, string $effectiveText): EditableFieldExplanation
    {
        return new EditableFieldExplanation(
            source: $temporal->timeSource !== 'none' ? $temporal->timeSource : 'explicit',
            expression: $temporal->timeExpression ?? trim($effectiveText),
            confidence: $temporal->timeConfidence > 0.0 ? $temporal->timeConfidence : 1.0,
            message: "Time updated from refinement to {$newValue}.",
        );
    }

    private function replaceDateExplanation(TemporalParseResult $temporal, string $newValue, string $effectiveText): EditableFieldExplanation
    {
        return new EditableFieldExplanation(
            source: $temporal->dateSource !== 'none' ? $temporal->dateSource : 'relative',
            expression: $temporal->dateExpression ?? trim($effectiveText),
            confidence: $temporal->dateConfidence > 0.0 ? $temporal->dateConfidence : 0.9,
            message: "Date updated from refinement to {$newValue}.",
        );
    }

    private function intentSupportsAmbiguityResolution(string $intent): bool
    {
        $capability = $this->capabilityRegistry->find($intent);

        // Conservative default: if no capability is registered, do not attempt resolution.
        if ($capability === null) {
            return false;
        }

        return $capability->supportsAmbiguityResolution;
    }

    // ── Ambiguity outcome application (Phase 8D.3) ───────────────────────────
    // Candidate matching, narrowing, and selection live in AmbiguityResolver. The
    // service only binds the selector to proposal state (resolveAgainstFirstBlockingAmbiguity)
    // and applies the resolver's outcome: warning composition and the narrowed
    // candidate-list write below, and the resolved-candidate application in applyAll().

    /**
     * Warning naming the candidates that still match after a type narrowing.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function narrowedWarningMessage(string $type, array $candidates): string
    {
        $labels = implode(', ', array_map(fn (array $c) => $c['label'], $candidates));

        return __('actions::actions.ambiguity_narrowed_type', [
            'type' => __('actions::actions.candidate_types.'.$type),
            'candidates' => $labels,
        ]);
    }

    /**
     * Replace one unresolved ambiguity's candidate list with the narrowed subset,
     * preserving order and every other ambiguity field (key/label/reason/query/
     * blocking/selected_candidate_id). The ambiguity stays blocking with
     * selected_candidate_id null — only the active candidate set shrinks.
     *
     * @param  array<int, array<string, mixed>>  $ambiguities
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function applyNarrowedCandidates(array $ambiguities, string $key, array $candidates): array
    {
        return array_map(function (array $a) use ($key, $candidates): array {
            if ($a['key'] === $key && ($a['selected_candidate_id'] ?? null) === null) {
                $a['candidates'] = array_values($candidates);
            }

            return $a;
        }, $ambiguities);
    }

    // ── Unified mutation application ─────────────────────────────────────────

    /**
     * @param  NormalizedMutation[]  $fieldMutations
     */
    private function applyAll(
        ActionProposal $proposal,
        array $fieldMutations,
        ?array $ambiguityResolution,
        ?RefinementAmbiguityOutcome $ambiguityOutcome,
        string $text,
        string $effectiveText,
    ): ActionProposal {
        $editableFields = $proposal->editable_fields ?? [];
        $missing = $proposal->missing ?? [];
        $ambiguities = $proposal->ambiguities ?? [];
        $entities = $proposal->entities ?? [];
        $proposedChanges = $proposal->changes ?? [];
        $previousStatus = $proposal->status;
        $confidence = $proposal->confidence;
        $changes = [];

        // Phase 7C.1: parser-local provenance of the refinement text, used to
        // refresh temporal field explanations so they describe the new value
        // rather than the original command. Deterministic; explainability only.
        $temporal = $this->parser->explain($effectiveText);

        $currentFieldValues = collect($editableFields)
            ->keyBy('key')
            ->map(fn (array $f) => $f['value'])
            ->all();

        // Apply field mutations — dispatch on semantic operation
        $mutatedFieldKeys = [];

        foreach ($fieldMutations as $mutation) {
            $field = $mutation->field;
            $label = $mutation->label;
            $prevValue = $currentFieldValues[$field] ?? null;

            if ($mutation->operation === 'replace' && $mutation->target !== null) {
                // Collection replace: swap a specific item within an array field.
                $current = is_array($prevValue) ? $prevValue : [];
                $newCollection = array_map(
                    fn ($item) => $item === $mutation->target ? $mutation->value : $item,
                    $current
                );

                if ($newCollection !== $current) {
                    $editableFields = $this->upsertCollectionField($editableFields, $field, $label, $newCollection, $mutation->source);
                    $changes[] = [
                        'field' => $field,
                        'label' => $label,
                        'operation' => 'replace',
                        'target' => $mutation->target,
                        'from' => $current,
                        'to' => $newCollection,
                        // Phase 7D: descriptive semantic type (e.g. replace_participant).
                        'semantic_type' => $mutation->semanticType()->value,
                    ];
                }

            } elseif ($mutation->operation === 'replace') {
                // Scalar replace: set or overwrite a scalar field value.
                $newValue = $mutation->value;

                // Phase 7C.1: for date/time fields, refresh the explanation so it
                // describes the new value. A null result means we cannot build a
                // coherent explanation → drop the stale one rather than keep it.
                $isTemporalField = in_array($field, ['date', 'time'], true);
                $refreshedExplanation = $isTemporalField
                    ? $this->refreshedTemporalExplanation($mutation, $temporal, $newValue, $effectiveText)
                    : null;

                if (collect($editableFields)->contains('key', $field)) {
                    $editableFields = array_map(
                        function (array $f) use ($field, $newValue, $mutation, $isTemporalField, $refreshedExplanation): array {
                            if ($f['key'] === $field) {
                                $f['value'] = $newValue;
                                $f['source'] = $mutation->source;

                                if ($isTemporalField) {
                                    if ($refreshedExplanation !== null) {
                                        $f['explanation'] = $refreshedExplanation->toArray();
                                    } else {
                                        unset($f['explanation']);
                                    }
                                }
                            }

                            return $f;
                        },
                        $editableFields
                    );
                } else {
                    $newField = [
                        'key' => $field,
                        'label' => $label,
                        'value' => $newValue,
                        'source' => $mutation->source,
                        'required' => $isTemporalField,
                    ];

                    if ($isTemporalField && $refreshedExplanation !== null) {
                        $newField['explanation'] = $refreshedExplanation->toArray();
                    }

                    $editableFields[] = $newField;
                }

                // Keep authoritative proposal state consistent with the edited
                // field: a scalar replace makes the field part of the operational
                // proposal, so mirror it into entities and into any existing change
                // payload that already declares the field. This applies to every
                // scalar replace (date, time, lead, priority, …), not just temporal.
                $entities[$field] = $newValue;
                $proposedChanges = $this->syncChangePayloads($proposedChanges, $field, $newValue);

                if ($prevValue !== $newValue) {
                    // Phase 7C: surface the descriptive semantic type alongside the
                    // existing change metadata (e.g. shift_time vs replace_time).
                    $changes[] = [
                        'field' => $field,
                        'label' => $label,
                        'from' => $prevValue,
                        'to' => $newValue,
                        'semantic_type' => $mutation->semanticType()->value,
                    ];
                }

                if (in_array($field, ['date', 'time'], true)) {
                    $confidence = max($confidence, 0.85);
                }

            } elseif ($mutation->operation === 'clear') {
                $editableFields = array_values(
                    array_filter($editableFields, fn (array $f) => $f['key'] !== $field)
                );

                if ($prevValue !== null) {
                    // Phase 8D.4: surface the descriptive semantic type, consistent
                    // with the replace/append/remove change entries.
                    $changes[] = [
                        'field' => $field,
                        'label' => $label,
                        'from' => $prevValue,
                        'to' => null,
                        'semantic_type' => $mutation->semanticType()->value,
                    ];
                }

            } elseif ($mutation->operation === 'append') {
                // Append a value to a collection field (no duplicates).
                $current = is_array($prevValue) ? $prevValue : ($prevValue !== null ? [$prevValue] : []);

                if (! in_array($mutation->value, $current, true)) {
                    $newCollection = [...$current, $mutation->value];
                    $editableFields = $this->upsertCollectionField($editableFields, $field, $label, $newCollection, $mutation->source);
                    $changes[] = [
                        'field' => $field,
                        'label' => $label,
                        'operation' => 'append',
                        'from' => $current,
                        'to' => $newCollection,
                        // Phase 7D: descriptive semantic type (e.g. add_participant).
                        'semantic_type' => $mutation->semanticType()->value,
                    ];
                }

            } elseif ($mutation->operation === 'remove') {
                // Remove a specific item from a collection field.
                $current = is_array($prevValue) ? $prevValue : [];
                $newCollection = array_values(array_filter($current, fn ($item) => $item !== $mutation->target));

                if (count($newCollection) !== count($current)) {
                    $editableFields = $this->upsertCollectionField($editableFields, $field, $label, $newCollection, $mutation->source);
                    $changes[] = [
                        'field' => $field,
                        'label' => $label,
                        'operation' => 'remove',
                        'from' => $current,
                        'to' => $newCollection,
                        // Phase 7D: descriptive semantic type (e.g. remove_participant).
                        'semantic_type' => $mutation->semanticType()->value,
                    ];
                }
            }

            $mutatedFieldKeys[] = $field;
        }

        // Remove resolved field keys from missing
        if (! empty($mutatedFieldKeys)) {
            $missing = array_values(
                array_filter($missing, fn (array $f) => ! in_array($f['key'], $mutatedFieldKeys, true))
            );
        }

        // Apply ambiguity resolution
        if ($ambiguityResolution !== null) {
            $fieldKey = $ambiguityResolution['ambiguity_key'];
            $fieldLabel = $ambiguityResolution['ambiguity_label'];
            $candidate = $ambiguityResolution['candidate'];
            $prevValue = $currentFieldValues[$fieldKey] ?? null;

            $ambiguities = array_map(function (array $a) use ($fieldKey, $candidate): array {
                if ($a['key'] === $fieldKey && $a['selected_candidate_id'] === null) {
                    $a['selected_candidate_id'] = $candidate['id'];
                    // Lifecycle coherence: a resolved ambiguity is no longer blocking.
                    // The authoritative state itself is made self-consistent here
                    // (blocking === false ⇔ selected_candidate_id !== null) so no
                    // consumer needs the hidden `blocking && id === null` rule.
                    $a['blocking'] = false;
                }

                return $a;
            }, $ambiguities);

            $entities[$fieldKey] = $candidate['label'];

            if (collect($editableFields)->contains('key', $fieldKey)) {
                $editableFields = array_map(function (array $f) use ($fieldKey, $candidate): array {
                    if ($f['key'] === $fieldKey) {
                        $f['value'] = $candidate['label'];
                        $f['source'] = 'detected';
                    }

                    return $f;
                }, $editableFields);
            } else {
                $editableFields[] = [
                    'key' => $fieldKey,
                    'label' => $fieldLabel,
                    'value' => $candidate['label'],
                    'source' => 'detected',
                    'required' => true,
                ];
            }

            if ($prevValue !== $candidate['label']) {
                $changes[] = [
                    'field' => $fieldKey,
                    'label' => $fieldLabel,
                    'from' => $prevValue,
                    'to' => $candidate['label'],
                ];
            }
        }

        // Sync due_at from date + time in entities for create_task proposals.
        // The executor reads due_at from changes.payload; refinements on date/time
        // update those keys in the payload via syncChangePayloads above, but due_at
        // must be re-derived to stay coherent after a date/time change.
        // When date is absent, stale due_at is removed from both payloads and
        // editable_fields so execution cannot persist an old value.
        $proposedChanges = $this->syncDueAtFromTemporalEntities($proposal, $entities, $proposedChanges);

        if ($proposal->intent === 'create_task') {
            $editableFields = $this->syncDueAtEditableField($editableFields, $entities);
        }

        // Recompute status
        $status = $this->hasBlockingConditions($proposal, $missing, $ambiguities)
            ? $previousStatus
            : 'ready';

        if ($status !== $previousStatus) {
            $changes[] = ['field' => 'status', 'label' => 'Status', 'from' => $previousStatus, 'to' => $status];
        }

        $proposal->editable_fields = $editableFields;
        $proposal->missing = $missing;
        $proposal->ambiguities = $ambiguities;
        $proposal->entities = $entities;
        $proposal->changes = $proposedChanges;
        $proposal->status = $status;
        $proposal->confidence = $confidence;
        $proposal->last_refinement = $this->buildLastRefinement(
            $text,
            $effectiveText,
            $this->buildSummary($changes),
            $changes,
            $ambiguityOutcome,
        );
        $proposal->save();

        return $proposal;
    }

    /**
     * Build the last_refinement report: the union projection of the two refinement
     * authorities. `changes[]` is the field-state authority facet; `ambiguity_outcome`
     * is the ambiguity-state authority facet (Phase 8E.1), null when no ambiguity
     * clarification was driven this turn. Both facets are inert reporting metadata —
     * never read back by the runtime.
     *
     * @param  array<int, array<string, mixed>>  $changes
     * @return array<string, mixed>
     */
    private function buildLastRefinement(
        string $text,
        string $effectiveText,
        string $summary,
        array $changes,
        ?RefinementAmbiguityOutcome $ambiguityOutcome = null,
    ): array {
        return [
            'text' => $text,
            'effective_text' => $effectiveText,
            'summary' => $summary,
            'changes' => $changes,
            'ambiguity_outcome' => $ambiguityOutcome?->toArray(),
        ];
    }

    /**
     * Sync a scalar value into any ProposedChange payload that already declares
     * the field, keeping change payloads aligned with entities/editable fields
     * after a scalar replace. Payloads that do not already contain the key are
     * left untouched — no unrelated keys are created. Collection/array payload
     * values for the key are skipped (this slice only syncs scalar replaces).
     *
     * @param  array<int, mixed>  $changes
     * @return array<int, mixed>
     */
    private function syncChangePayloads(array $changes, string $field, mixed $value): array
    {
        return array_map(function ($change) use ($field, $value) {
            if (
                is_array($change)
                && isset($change['payload'])
                && is_array($change['payload'])
                && array_key_exists($field, $change['payload'])
                && ! is_array($change['payload'][$field])
            ) {
                $change['payload'][$field] = $value;
            }

            return $change;
        }, $changes);
    }

    /**
     * Remove a key from all ProposedChange payloads that declare it.
     *
     * @param  array<int, mixed>  $changes
     * @return array<int, mixed>
     */
    private function removeFromChangePayloads(array $changes, string $field): array
    {
        return array_map(function ($change) use ($field) {
            if (
                is_array($change)
                && isset($change['payload'])
                && is_array($change['payload'])
                && array_key_exists($field, $change['payload'])
            ) {
                unset($change['payload'][$field]);
            }

            return $change;
        }, $changes);
    }

    /**
     * Re-derive due_at from date + time entities and sync it into change payloads
     * for create_task proposals. This runs after all field mutations are applied
     * so a refinement that changes date or time keeps due_at coherent.
     *
     * When date is present: derives due_at from date + time and syncs it into
     * any payload that already declares it. When date is absent: removes stale
     * due_at from all payloads so execution cannot persist an old value.
     *
     * Only operates when the proposal intent is create_task. Never injects due_at
     * into a payload that didn't already declare it.
     *
     * @param  array<int, mixed>  $changes
     * @return array<int, mixed>
     */
    private function syncDueAtFromTemporalEntities(ActionProposal $proposal, array $entities, array $changes): array
    {
        if ($proposal->intent !== 'create_task') {
            return $changes;
        }

        if (! isset($entities['date'])) {
            return $this->removeFromChangePayloads($changes, 'due_at');
        }

        $dueAt = $entities['date'];
        if (isset($entities['time'])) {
            $dueAt .= ' ' . $entities['time'];
        }

        // First pass: update due_at in payloads that already declare it.
        $changes = $this->syncChangePayloads($changes, 'due_at', $dueAt);

        // Second pass: if due_at still isn't in any payload (temporal info was
        // added via refinement to a proposal that originally had none), inject it
        // into the first create-type change payload.
        return $this->injectDueAtIntoCreatePayload($changes, $dueAt);
    }

    /**
     * Inject due_at into the first create-type change payload that does not
     * already contain it. Used when temporal information appears for the first
     * time via refinement on a proposal that originally had no date/time.
     *
     * @param  array<int, mixed>  $changes
     * @return array<int, mixed>
     */
    private function injectDueAtIntoCreatePayload(array $changes, string $dueAt): array
    {
        $injected = false;

        return array_map(function ($change) use ($dueAt, &$injected) {
            if (
                ! $injected
                && is_array($change)
                && isset($change['payload'])
                && is_array($change['payload'])
                && ! array_key_exists('due_at', $change['payload'])
                && ($change['type'] ?? '') === 'create'
            ) {
                $change['payload']['due_at'] = $dueAt;
                $injected = true;
            }

            return $change;
        }, $changes);
    }

    /**
     * Keep the due_at editable field coherent with the current entities after a
     * temporal refinement. When date is present, upsert due_at with the merged
     * date+time value. When date is absent, remove any stale due_at field.
     *
     * @param  array<int, array<string, mixed>>  $editableFields
     * @return array<int, array<string, mixed>>
     */
    private function syncDueAtEditableField(array $editableFields, array $entities): array
    {
        $hasDueAtField = collect($editableFields)->contains('key', 'due_at');

        if (! isset($entities['date'])) {
            if ($hasDueAtField) {
                return array_values(array_filter(
                    $editableFields,
                    fn (array $f) => $f['key'] !== 'due_at',
                ));
            }

            return $editableFields;
        }

        $dueAt = $entities['date'];
        if (isset($entities['time'])) {
            $dueAt .= ' ' . $entities['time'];
        }

        if ($hasDueAtField) {
            return array_map(
                function (array $f) use ($dueAt): array {
                    if ($f['key'] === 'due_at') {
                        $f['value'] = $dueAt;
                    }

                    return $f;
                },
                $editableFields,
            );
        }

        // due_at was not in editable_fields before (interpretation without date)
        // but a refinement added date — inject it.
        $editableFields[] = [
            'key' => 'due_at',
            'label' => 'Due',
            'value' => $dueAt,
            'source' => 'derived',
            'required' => false,
        ];

        return $editableFields;
    }

    /**
     * Upsert an array value into editable_fields for collection mutations.
     *
     * @param  array<int, array<string, mixed>>  $editableFields
     * @return array<int, array<string, mixed>>
     */
    private function upsertCollectionField(array $editableFields, string $field, string $label, array $value, string $source): array
    {
        if (collect($editableFields)->contains('key', $field)) {
            return array_map(
                function (array $f) use ($field, $value, $source): array {
                    if ($f['key'] === $field) {
                        $f['value'] = $value;
                        $f['source'] = $source;
                    }

                    return $f;
                },
                $editableFields
            );
        }

        $editableFields[] = [
            'key' => $field,
            'label' => $label,
            'value' => $value,
            'source' => $source,
            'required' => false,
        ];

        return $editableFields;
    }

    private function buildSummary(array $changes): string
    {
        if (empty($changes)) {
            return 'No changes applied.';
        }

        $byField = collect($changes)->keyBy('field');
        $dataFields = array_values(array_diff($byField->keys()->all(), ['status']));

        if (in_array('lead', $dataFields, true) && count($dataFields) === 1) {
            return 'Lead resolved.';
        }

        $hasDate = in_array('date', $dataFields, true);
        $hasTime = in_array('time', $dataFields, true);

        if ($hasDate && $hasTime) {
            $dateFrom = $byField->get('date')['from'];
            $timeFrom = $byField->get('time')['from'];

            return ($dateFrom === null && $timeFrom === null)
                ? 'Date and time added.'
                : 'Date and time updated.';
        }

        if ($hasDate) {
            return $byField->get('date')['from'] === null ? 'Date added.' : 'Date updated.';
        }

        if ($hasTime) {
            return $byField->get('time')['from'] === null ? 'Time added.' : 'Time updated.';
        }

        if (in_array('priority', $dataFields, true)) {
            return $byField->get('priority')['to'] === null ? 'Priority cleared.' : 'Priority set.';
        }

        if (in_array('participants', $dataFields, true)) {
            $from = $byField->get('participants')['from'] ?? [];
            $to = $byField->get('participants')['to'] ?? [];
            $fromCount = is_array($from) ? count($from) : 0;
            $toCount = is_array($to) ? count($to) : 0;

            if ($toCount > $fromCount) {
                return 'Participant added.';
            }

            if ($toCount < $fromCount) {
                return 'Participant removed.';
            }

            return 'Participants updated.';
        }

        return 'Proposal updated.';
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    private function effectiveText(string $text, string $sourceText): string
    {
        $text = trim($text);
        $sourceText = trim($sourceText);

        if ($sourceText !== '' && str_starts_with($text, $sourceText)) {
            return trim(substr($text, strlen($sourceText)));
        }

        return $text;
    }

    /**
     * Behaviour-identical to the previous proposal-based check, now reading the
     * runtime context's pre-derived blocking ambiguities (already filtered to
     * blocking === true) and keeping only those still unresolved.
     */
    private function hasUnresolvedBlockingAmbiguity(ProposalRuntimeContext $context): bool
    {
        return collect($context->blockingAmbiguities)
            ->contains(fn (array $a) => ($a['selected_candidate_id'] ?? null) === null);
    }

    private function hasBlockingConditions(ActionProposal $proposal, array $updatedMissing, array $updatedAmbiguities): bool
    {
        $requiredMissingRemain = collect($updatedMissing)->contains('required', true);
        $blockingAmbiguityRemains = collect($updatedAmbiguities)
            ->contains(fn (array $a) => ($a['blocking'] ?? false) && $a['selected_candidate_id'] === null);

        return $requiredMissingRemain || $blockingAmbiguityRemains;
    }
}
