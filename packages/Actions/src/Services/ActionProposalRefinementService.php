<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Validation\ValidationException;

class ActionProposalRefinementService
{
    private const REFINABLE_STATUSES = ['draft', 'ready'];

    public function __construct(private readonly RefinementInterpreterInterface $interpreter) {}

    public function refine(ActionProposal $proposal, string $text): ActionProposal
    {
        if (! in_array($proposal->status, self::REFINABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'proposal' => [__('actions::actions.cannot_refine')],
            ]);
        }

        $effectiveText = $this->effectiveText($text, $proposal->source_text);

        // Delegate NL → mutation extraction to the interpreter
        $fieldMutations = $this->interpreter->interpret($effectiveText);

        // Try ambiguity resolution when a blocking ambiguity exists
        $ambiguityResolution = null;
        if ($this->hasUnresolvedBlockingAmbiguity($proposal)) {
            $ambiguityResolution = $this->tryClassifyAmbiguityResolution($proposal, $effectiveText);

            if ($ambiguityResolution === null && empty($fieldMutations)) {
                $warnings   = $proposal->warnings ?? [];
                $warnings[] = __('actions::actions.ambiguity_still_unresolved');
                $proposal->warnings        = $warnings;
                $proposal->last_refinement = [
                    'text'           => $text,
                    'effective_text' => $effectiveText,
                    'summary'        => 'No changes applied.',
                    'changes'        => [],
                ];
                $proposal->save();

                return $proposal;
            }
        }

        // Nothing recognized at all
        if (empty($fieldMutations) && $ambiguityResolution === null) {
            $warnings   = $proposal->warnings ?? [];
            $warnings[] = __('actions::actions.refinement_not_recognized');
            $proposal->warnings        = $warnings;
            $proposal->last_refinement = [
                'text'           => $text,
                'effective_text' => $effectiveText,
                'summary'        => 'No changes applied.',
                'changes'        => [],
            ];
            $proposal->save();

            return $proposal;
        }

        return $this->applyAll($proposal, $fieldMutations, $ambiguityResolution, $text, $effectiveText);
    }

    // ── Ambiguity resolution ─────────────────────────────────────────────────
    // Candidate matching requires proposal state (the candidates list) so it
    // stays here rather than in the interpreter.

    private function tryClassifyAmbiguityResolution(ActionProposal $proposal, string $effectiveText): ?array
    {
        foreach ($proposal->ambiguities ?? [] as $ambiguity) {
            if (! ($ambiguity['blocking'] ?? false) || $ambiguity['selected_candidate_id'] !== null) {
                continue;
            }

            $candidate = $this->resolveCandidate($ambiguity, $effectiveText);

            if ($candidate !== null) {
                return [
                    'ambiguity_key'   => $ambiguity['key'],
                    'ambiguity_label' => $ambiguity['label'],
                    'candidate'       => $candidate,
                ];
            }

            // Process only the first unresolved blocking ambiguity per call
            return null;
        }

        return null;
    }

    // ── Unified mutation application ─────────────────────────────────────────

    /**
     * @param NormalizedMutation[] $fieldMutations
     */
    private function applyAll(
        ActionProposal $proposal,
        array $fieldMutations,
        ?array $ambiguityResolution,
        string $text,
        string $effectiveText,
    ): ActionProposal {
        $editableFields = $proposal->editable_fields ?? [];
        $missing        = $proposal->missing ?? [];
        $ambiguities    = $proposal->ambiguities ?? [];
        $entities       = $proposal->entities ?? [];
        $previousStatus = $proposal->status;
        $confidence     = $proposal->confidence;
        $changes        = [];

        $currentFieldValues = collect($editableFields)
            ->keyBy('key')
            ->map(fn (array $f) => $f['value'])
            ->all();

        // Apply field mutations (date, time, priority, …)
        $mutatedFieldKeys = [];

        foreach ($fieldMutations as $mutation) {
            $field    = $mutation->field;
            $label    = $mutation->label;
            $newValue = $mutation->value;
            $prevValue = $currentFieldValues[$field] ?? null;

            if (collect($editableFields)->contains('key', $field)) {
                $editableFields = array_map(function (array $f) use ($field, $newValue, $mutation): array {
                    if ($f['key'] === $field) {
                        $f['value']  = $newValue;
                        $f['source'] = $mutation->source;
                    }

                    return $f;
                }, $editableFields);
            } else {
                $editableFields[] = [
                    'key'      => $field,
                    'label'    => $label,
                    'value'    => $newValue,
                    'source'   => $mutation->source,
                    'required' => in_array($field, ['date', 'time'], true),
                ];
            }

            if ($prevValue !== $newValue) {
                $changes[] = ['field' => $field, 'label' => $label, 'from' => $prevValue, 'to' => $newValue];
            }

            $mutatedFieldKeys[] = $field;

            if (in_array($field, ['date', 'time'], true)) {
                $confidence = max($confidence, 0.85);
            }
        }

        // Remove resolved field keys from missing
        if (! empty($mutatedFieldKeys)) {
            $missing = array_values(
                array_filter($missing, fn (array $f) => ! in_array($f['key'], $mutatedFieldKeys, true))
            );
        }

        // Apply ambiguity resolution
        if ($ambiguityResolution !== null) {
            $fieldKey   = $ambiguityResolution['ambiguity_key'];
            $fieldLabel = $ambiguityResolution['ambiguity_label'];
            $candidate  = $ambiguityResolution['candidate'];
            $prevValue  = $currentFieldValues[$fieldKey] ?? null;

            $ambiguities = array_map(function (array $a) use ($fieldKey, $candidate): array {
                if ($a['key'] === $fieldKey && $a['selected_candidate_id'] === null) {
                    $a['selected_candidate_id'] = $candidate['id'];
                }

                return $a;
            }, $ambiguities);

            $entities[$fieldKey] = $candidate['label'];

            if (collect($editableFields)->contains('key', $fieldKey)) {
                $editableFields = array_map(function (array $f) use ($fieldKey, $candidate): array {
                    if ($f['key'] === $fieldKey) {
                        $f['value']  = $candidate['label'];
                        $f['source'] = 'detected';
                    }

                    return $f;
                }, $editableFields);
            } else {
                $editableFields[] = [
                    'key'      => $fieldKey,
                    'label'    => $fieldLabel,
                    'value'    => $candidate['label'],
                    'source'   => 'detected',
                    'required' => true,
                ];
            }

            if ($prevValue !== $candidate['label']) {
                $changes[] = [
                    'field' => $fieldKey,
                    'label' => $fieldLabel,
                    'from'  => $prevValue,
                    'to'    => $candidate['label'],
                ];
            }
        }

        // Recompute status
        $status = $this->hasBlockingConditions($proposal, $missing, $ambiguities)
            ? $previousStatus
            : 'ready';

        if ($status !== $previousStatus) {
            $changes[] = ['field' => 'status', 'label' => 'Status', 'from' => $previousStatus, 'to' => $status];
        }

        $proposal->editable_fields = $editableFields;
        $proposal->missing         = $missing;
        $proposal->ambiguities     = $ambiguities;
        $proposal->entities        = $entities;
        $proposal->status          = $status;
        $proposal->confidence      = $confidence;
        $proposal->last_refinement = [
            'text'           => $text,
            'effective_text' => $effectiveText,
            'summary'        => $this->buildSummary($changes),
            'changes'        => $changes,
        ];
        $proposal->save();

        return $proposal;
    }

    private function buildSummary(array $changes): string
    {
        if (empty($changes)) {
            return 'No changes applied.';
        }

        $byField    = collect($changes)->keyBy('field');
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
            return 'Priority set.';
        }

        return 'Proposal updated.';
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    private function effectiveText(string $text, string $sourceText): string
    {
        $text       = trim($text);
        $sourceText = trim($sourceText);

        if ($sourceText !== '' && str_starts_with($text, $sourceText)) {
            return trim(substr($text, strlen($sourceText)));
        }

        return $text;
    }

    private function hasUnresolvedBlockingAmbiguity(ActionProposal $proposal): bool
    {
        return collect($proposal->ambiguities ?? [])
            ->contains(fn (array $a) => ($a['blocking'] ?? false) && $a['selected_candidate_id'] === null);
    }

    private function hasBlockingConditions(ActionProposal $proposal, array $updatedMissing, array $updatedAmbiguities): bool
    {
        $requiredMissingRemain    = collect($updatedMissing)->contains('required', true);
        $blockingAmbiguityRemains = collect($updatedAmbiguities)
            ->contains(fn (array $a) => ($a['blocking'] ?? false) && $a['selected_candidate_id'] === null);

        return $requiredMissingRemain || $blockingAmbiguityRemains;
    }

    private function resolveCandidate(array $ambiguity, string $text): ?array
    {
        $candidates = $ambiguity['candidates'] ?? [];
        $lower      = mb_strtolower(trim($text));

        $ordinal = $this->parseOrdinal($lower);
        if ($ordinal !== null && isset($candidates[$ordinal])) {
            return $candidates[$ordinal];
        }

        foreach ($candidates as $candidate) {
            if (mb_strtolower($candidate['label']) === $lower) {
                return $candidate;
            }
        }

        $partialMatches = array_values(array_filter(
            $candidates,
            fn (array $c) => str_contains(mb_strtolower($c['label']), $lower)
        ));

        if (count($partialMatches) === 1) {
            return $partialMatches[0];
        }

        if ((bool) preg_match('/\bcompan(y|ies)\b/i', $text)) {
            $typeMatches = array_values(array_filter($candidates, fn (array $c) => $c['type'] === 'company'));
            if (count($typeMatches) === 1) {
                return $typeMatches[0];
            }

            return null;
        }

        if ((bool) preg_match('/\bperson\b|\bindividual\b/i', $text)) {
            $typeMatches = array_values(array_filter($candidates, fn (array $c) => $c['type'] === 'person'));
            if (count($typeMatches) === 1) {
                return $typeMatches[0];
            }

            return null;
        }

        return null;
    }

    private function parseOrdinal(string $text): ?int
    {
        return match (true) {
            str_contains($text, 'first')  || str_contains($text, '1st') => 0,
            str_contains($text, 'second') || str_contains($text, '2nd') => 1,
            str_contains($text, 'third')  || str_contains($text, '3rd') => 2,
            default => null,
        };
    }
}
