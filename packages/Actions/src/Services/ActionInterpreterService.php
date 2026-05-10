<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\ActionProposalData;
use Fluxio\Actions\DTO\EditableField;
use Fluxio\Actions\DTO\MissingField;
use Fluxio\Actions\DTO\ProposedChange;
use Illuminate\Support\Str;

class ActionInterpreterService
{
    public function __construct(private readonly IntentResolverInterface $resolver) {}

    public function interpret(string $text): ActionProposalData
    {
        $parsed = $this->resolver->resolve($text);

        $confidence = match ($parsed->intent) {
            'create_task' => 0.9,
            'schedule_call' => 0.7,
            default => 0.3,
        };

        [$status, $missing, $editableFields, $changes, $ambiguities] = match ($parsed->intent) {
            'create_task' => $this->buildCreateTask($parsed->entities),
            'schedule_call' => $this->buildScheduleCall($parsed->entities),
            default => ['draft', [], [], [], []],
        };

        return new ActionProposalData(
            id: (string) Str::uuid(),
            intent: $parsed->intent,
            status: $status,
            confidence: $confidence,
            source_text: $text,
            entities: $parsed->entities,
            missing: $missing,
            editable_fields: $editableFields,
            changes: $changes,
            needs_confirmation: true,
            ambiguities: $ambiguities,
        );
    }

    private function buildCreateTask(array $entities): array
    {
        $hasLead = isset($entities['lead']);
        $status = $hasLead ? 'ready' : 'draft';

        $editableFields = [
            new EditableField(
                key: 'title',
                label: 'Title',
                value: 'Follow-up task',
                source: 'inferred',
                required: true,
            ),
        ];

        if ($hasLead) {
            $editableFields[] = new EditableField(
                key: 'lead',
                label: 'Lead',
                value: $entities['lead'],
                source: 'detected',
                required: true,
            );
        }

        $payload = ['title' => 'Follow-up task'];
        if ($hasLead) {
            $payload['lead'] = $entities['lead'];
        }

        $changes = [
            new ProposedChange(
                type: 'create',
                label: 'Create task',
                module: 'tasks',
                payload: $payload,
            ),
        ];

        return [$status, [], $editableFields, $changes, []];
    }

    private const ROSSI_CANDIDATES = [
        ['id' => 1,  'type' => 'person',  'label' => 'Mario Rossi',   'description' => 'Individual lead',     'confidence' => 0.72],
        ['id' => 7,  'type' => 'company', 'label' => 'Rossi SRL',     'description' => 'Company lead',         'confidence' => 0.68],
        ['id' => 12, 'type' => 'company', 'label' => 'Studio Rossi',  'description' => 'Professional studio',  'confidence' => 0.61],
    ];

    private function buildScheduleCall(array $entities): array
    {
        $missing = [
            new MissingField(
                key: 'date',
                label: 'Date',
                reason: 'The command does not specify a date.',
                required: true,
            ),
            new MissingField(
                key: 'time',
                label: 'Time',
                reason: 'The command does not specify a time.',
                required: true,
            ),
        ];

        $editableFields = [];
        $ambiguities = [];

        if (isset($entities['lead'])) {
            $editableFields[] = new EditableField(
                key: 'lead',
                label: 'Lead',
                value: $entities['lead'],
                source: 'detected',
                required: true,
            );
        } elseif (isset($entities['lead_query'])) {
            $ambiguities[] = [
                'key' => 'lead',
                'label' => 'Lead',
                'reason' => 'multiple_matches',
                'blocking' => true,
                'query' => $entities['lead_query'],
                'selected_candidate_id' => null,
                'candidates' => self::ROSSI_CANDIDATES,
            ];
        }

        $editableFields[] = new EditableField(
            key: 'date',
            label: 'Date',
            value: null,
            source: 'missing',
            required: true,
        );

        $editableFields[] = new EditableField(
            key: 'time',
            label: 'Time',
            value: null,
            source: 'missing',
            required: true,
        );

        $changes = [
            new ProposedChange(
                type: 'schedule',
                label: 'Schedule call',
                module: 'calendar',
                payload: [],
            ),
        ];

        return ['draft', $missing, $editableFields, $changes, $ambiguities];
    }
}
