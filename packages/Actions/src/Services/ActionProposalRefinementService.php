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

        if ($proposal->intent === 'schedule_call' && $this->mentionsTomorrowMorning($text)) {
            return $this->applyTomorrowMorning($proposal);
        }

        // Refinement not recognized — add warning, leave proposal unchanged.
        $warnings = $proposal->warnings ?? [];
        $warnings[] = __('actions::actions.refinement_not_recognized');
        $proposal->warnings = $warnings;
        $proposal->save();

        return $proposal;
    }

    private function mentionsTomorrowMorning(string $text): bool
    {
        return (bool) preg_match('/tomorrow\s+morning/i', $text);
    }

    private function applyTomorrowMorning(ActionProposal $proposal): ActionProposal
    {
        $tomorrow = now()->addDay()->toDateString();

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

        $proposal->editable_fields = $editableFields;
        $proposal->missing = $missing;
        $proposal->status = $status;
        $proposal->confidence = $confidence;
        $proposal->save();

        return $proposal;
    }
}
