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

        // Refinement not recognized — add warning, leave proposal unchanged.
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

        $requiredMissingRemain = collect($missing)->contains('required', true);
        $status = $requiredMissingRemain ? $proposal->status : 'ready';
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
