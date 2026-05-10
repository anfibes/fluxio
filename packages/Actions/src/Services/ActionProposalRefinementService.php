<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Validation\ValidationException;

class ActionProposalRefinementService
{
    private const REFINABLE_STATUSES = ['draft', 'ready'];

    public function refine(ActionProposal $proposal, string $text): ActionProposal
    {
        if (! in_array($proposal->status, self::REFINABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'proposal' => [__('actions::actions.cannot_refine')],
            ]);
        }

        $effectiveText = $this->effectiveText($text, $proposal->source_text);

        if ($proposal->intent === 'schedule_call' && $this->mentionsTomorrowMorning($effectiveText)) {
            return $this->applyTomorrowMorning($proposal, $text, $effectiveText);
        }

        if ($this->hasUnresolvedBlockingAmbiguity($proposal)) {
            return $this->tryResolveAmbiguity($proposal, $text, $effectiveText);
        }

        $warnings = $proposal->warnings ?? [];
        $warnings[] = __('actions::actions.refinement_not_recognized');
        $proposal->warnings = $warnings;
        $proposal->last_refinement = [
            'text' => $text,
            'effective_text' => $effectiveText,
            'summary' => 'No changes applied.',
            'changes' => [],
        ];
        $proposal->save();

        return $proposal;
    }

    private function effectiveText(string $text, string $sourceText): string
    {
        $text = trim($text);
        $sourceText = trim($sourceText);

        if ($sourceText !== '' && str_starts_with($text, $sourceText)) {
            return trim(substr($text, strlen($sourceText)));
        }

        return $text;
    }

    private function mentionsTomorrowMorning(string $text): bool
    {
        return (bool) preg_match('/tomorrow\s+morning/i', $text);
    }

    private function hasUnresolvedBlockingAmbiguity(ActionProposal $proposal): bool
    {
        return collect($proposal->ambiguities ?? [])
            ->contains(fn (array $a) => ($a['blocking'] ?? false) && $a['selected_candidate_id'] === null);
    }

    private function hasBlockingConditions(ActionProposal $proposal, array $updatedMissing, array $updatedAmbiguities): bool
    {
        $requiredMissingRemain = collect($updatedMissing)->contains('required', true);
        $blockingAmbiguityRemains = collect($updatedAmbiguities)
            ->contains(fn (array $a) => ($a['blocking'] ?? false) && $a['selected_candidate_id'] === null);

        return $requiredMissingRemain || $blockingAmbiguityRemains;
    }

    private function tryResolveAmbiguity(ActionProposal $proposal, string $text, string $effectiveText): ActionProposal
    {
        $ambiguities = $proposal->ambiguities ?? [];
        $resolvedField = null;

        foreach ($ambiguities as &$ambiguity) {
            if (! ($ambiguity['blocking'] ?? false) || $ambiguity['selected_candidate_id'] !== null) {
                continue;
            }

            $candidate = $this->resolveCandidate($ambiguity, $effectiveText);

            if ($candidate === null) {
                $warnings = $proposal->warnings ?? [];
                $warnings[] = __('actions::actions.ambiguity_still_unresolved');
                $proposal->warnings = $warnings;
                $proposal->last_refinement = [
                    'text' => $text,
                    'effective_text' => $effectiveText,
                    'summary' => 'No changes applied.',
                    'changes' => [],
                ];
                $proposal->save();

                return $proposal;
            }

            $ambiguity['selected_candidate_id'] = $candidate['id'];
            $resolvedField = ['key' => $ambiguity['key'], 'label' => $ambiguity['label'], 'candidate' => $candidate];
        }
        unset($ambiguity);

        if ($resolvedField === null) {
            return $proposal;
        }

        $candidate = $resolvedField['candidate'];
        $fieldKey = $resolvedField['key'];
        $fieldLabel = $resolvedField['label'];

        $entities = $proposal->entities ?? [];
        $previousLead = $entities[$fieldKey] ?? null;
        $entities[$fieldKey] = $candidate['label'];

        $editableFields = $proposal->editable_fields ?? [];
        $hasLeadField = collect($editableFields)->contains('key', $fieldKey);
        if (! $hasLeadField) {
            $editableFields[] = ['key' => $fieldKey, 'label' => $fieldLabel, 'value' => $candidate['label'], 'source' => 'detected', 'required' => true];
        } else {
            $editableFields = array_map(function (array $field) use ($fieldKey, $candidate): array {
                if ($field['key'] === $fieldKey) {
                    $field['value'] = $candidate['label'];
                    $field['source'] = 'detected';
                }

                return $field;
            }, $editableFields);
        }

        $missing = $proposal->missing ?? [];
        $previousStatus = $proposal->status;
        $status = $this->hasBlockingConditions($proposal, $missing, $ambiguities) ? $previousStatus : 'ready';

        $changes = [
            ['field' => $fieldKey, 'label' => $fieldLabel, 'from' => $previousLead, 'to' => $candidate['label']],
        ];

        if ($status !== $previousStatus) {
            $changes[] = ['field' => 'status', 'label' => 'Status', 'from' => $previousStatus, 'to' => $status];
        }

        $proposal->ambiguities = $ambiguities;
        $proposal->entities = $entities;
        $proposal->editable_fields = $editableFields;
        $proposal->status = $status;
        $proposal->last_refinement = [
            'text' => $text,
            'effective_text' => $effectiveText,
            'summary' => ucfirst($fieldLabel).' resolved.',
            'changes' => $changes,
        ];
        $proposal->save();

        return $proposal;
    }

    private function resolveCandidate(array $ambiguity, string $text): ?array
    {
        $candidates = $ambiguity['candidates'] ?? [];
        $lower = mb_strtolower(trim($text));

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

    private function applyTomorrowMorning(ActionProposal $proposal, string $text, string $effectiveText): ActionProposal
    {
        $tomorrow = now()->addDay()->toDateString();

        $previousStatus = $proposal->status;
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');
        $previousDate = $fields->get('date')['value'] ?? null;
        $previousTime = $fields->get('time')['value'] ?? null;

        $editableFields = array_map(function (array $field) use ($tomorrow): array {
            if ($field['key'] === 'date') {
                $field['value'] = $tomorrow;
                $field['source'] = 'detected';
            } elseif ($field['key'] === 'time') {
                $field['value'] = '09:00';
                $field['source'] = 'detected';
            }

            return $field;
        }, $proposal->editable_fields ?? []);

        $missing = array_values(
            array_filter(
                $proposal->missing ?? [],
                fn (array $f) => ! in_array($f['key'], ['date', 'time'], true),
            )
        );

        $status = $this->hasBlockingConditions($proposal, $missing, $proposal->ambiguities ?? [])
            ? $proposal->status
            : 'ready';
        $confidence = max($proposal->confidence, 0.85);

        $changes = [
            ['field' => 'date', 'label' => 'Date', 'from' => $previousDate, 'to' => $tomorrow],
            ['field' => 'time', 'label' => 'Time', 'from' => $previousTime, 'to' => '09:00'],
        ];

        if ($status !== $previousStatus) {
            $changes[] = ['field' => 'status', 'label' => 'Status', 'from' => $previousStatus, 'to' => $status];
        }

        $proposal->editable_fields = $editableFields;
        $proposal->missing = $missing;
        $proposal->status = $status;
        $proposal->confidence = $confidence;
        $proposal->last_refinement = [
            'text' => $text,
            'effective_text' => $effectiveText,
            'summary' => 'Date and time added.',
            'changes' => $changes,
        ];
        $proposal->save();

        return $proposal;
    }
}
