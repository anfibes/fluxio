<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\DTO\ActionProposalData;
use Fluxio\Actions\DTO\EditableField;
use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\DTO\MissingField;
use Fluxio\Actions\DTO\ProposedChange;
use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\Registry\IntentRegistry;
use Illuminate\Support\Str;

class ActionInterpreterService
{
    public function __construct(
        private readonly CommandInterpreterInterface $interpreter,
        private readonly IntentRegistry             $registry,
        private readonly EntityResolverRegistry     $resolverRegistry,
    ) {}

    public function interpret(string $text): ActionProposalData
    {
        $command    = $this->interpreter->interpret($text);
        $definition = $this->registry->find($command->intent);

        // Pre-resolve ambiguous entity queries before dispatching to intent builders.
        // Auto-resolved queries promote to detected entities; unresolved ones carry
        // pre-built ambiguity objects so builders never touch hardcoded candidate data.
        $entities            = $command->entities;
        $prebuiltAmbiguities = [];

        if (isset($entities['lead_query'])) {
            [$entities, $prebuiltAmbiguities] = $this->resolveEntityQuery('lead_query', $entities);
        }

        [$status, $missing, $editableFields, $changes, $ambiguities] = match ($command->intent) {
            'create_task'   => $this->buildCreateTask($entities, $prebuiltAmbiguities),
            'schedule_call' => $this->buildScheduleCall($entities, $prebuiltAmbiguities),
            default         => $definition !== null
                ? $this->buildFromDefinition($definition, $entities, $prebuiltAmbiguities)
                : ['draft', [], [], [], []],
        };

        return new ActionProposalData(
            id:                 (string) Str::uuid(),
            intent:             $command->intent,
            status:             $status,
            confidence:         $command->confidence,
            source_text:        $command->sourceText,
            entities:           $entities,
            missing:            $missing,
            editable_fields:    $editableFields,
            changes:            $changes,
            needs_confirmation: true,
            ambiguities:        $ambiguities,
        );
    }

    // ── Entity resolution ────────────────────────────────────────────────────

    /**
     * Run entity resolution for a query key, returning the updated entities array
     * and any pre-built ambiguity objects for unresolved multi-match cases.
     *
     * @return array{array, array}
     */
    private function resolveEntityQuery(string $queryKey, array $entities): array
    {
        $query = $entities[$queryKey];
        unset($entities[$queryKey]);

        $result      = $this->resolverRegistry->resolve($query, new ResolutionContext($queryKey));
        $ambiguities = [];

        if ($result->resolved) {
            // Single strong match — promote to a concrete entity value.
            $entities['lead'] = $result->resolvedCandidate->label;
        } elseif (! empty($result->candidates)) {
            // Multiple matches — emit a blocking ambiguity; the builder adds no missing field.
            $ambiguities[] = [
                'key'                   => 'lead',
                'label'                 => 'Lead',
                'reason'                => 'multiple_matches',
                'blocking'              => true,
                'query'                 => $query,
                'selected_candidate_id' => null,
                'candidates'            => array_map(
                    fn (ResolutionCandidate $c) => $c->toArray(),
                    $result->candidates,
                ),
            ];
        }
        // else: no match → entities['lead'] remains absent → builder treats as missing

        return [$entities, $ambiguities];
    }

    // ── Intent builders ──────────────────────────────────────────────────────

    private function buildCreateTask(array $entities, array $prebuiltAmbiguities = []): array
    {
        $hasLead              = isset($entities['lead']);
        $hasBlockingAmbiguity = collect($prebuiltAmbiguities)->contains(fn ($a) => $a['blocking'] ?? false);
        $status               = $hasBlockingAmbiguity ? 'draft' : 'ready';

        $editableFields = [
            new EditableField(
                key:      'title',
                label:    'Title',
                value:    'Follow-up task',
                source:   'inferred',
                required: true,
            ),
        ];

        if ($hasLead) {
            $editableFields[] = new EditableField(
                key:      'lead',
                label:    'Lead',
                value:    $entities['lead'],
                source:   'detected',
                required: false,
            );
        }

        $payload = ['title' => 'Follow-up task'];
        if ($hasLead) {
            $payload['lead'] = $entities['lead'];
        }

        $changes = [
            new ProposedChange(
                type:    'create',
                label:   'Create task',
                module:  'tasks',
                payload: $payload,
            ),
        ];

        return [$status, [], $editableFields, $changes, $prebuiltAmbiguities];
    }

    private function buildScheduleCall(array $entities, array $prebuiltAmbiguities = []): array
    {
        $missing = [
            new MissingField(
                key:      'date',
                label:    'Date',
                reason:   'The command does not specify a date.',
                required: true,
            ),
            new MissingField(
                key:      'time',
                label:    'Time',
                reason:   'The command does not specify a time.',
                required: true,
            ),
        ];

        $editableFields = [];
        $ambiguities    = $prebuiltAmbiguities;

        if (isset($entities['lead'])) {
            $editableFields[] = new EditableField(
                key:      'lead',
                label:    'Lead',
                value:    $entities['lead'],
                source:   'detected',
                required: true,
            );
        }

        $editableFields[] = new EditableField(
            key:      'date',
            label:    'Date',
            value:    null,
            source:   'missing',
            required: true,
        );

        $editableFields[] = new EditableField(
            key:      'time',
            label:    'Time',
            value:    null,
            source:   'missing',
            required: true,
        );

        $changes = [
            new ProposedChange(
                type:    'schedule',
                label:   'Schedule call',
                module:  'calendar',
                payload: [],
            ),
        ];

        return ['draft', $missing, $editableFields, $changes, $ambiguities];
    }

    private function buildFromDefinition(IntentDefinition $definition, array $entities, array $prebuiltAmbiguities = []): array
    {
        $missing        = [];
        $editableFields = [];
        $ambiguities    = $prebuiltAmbiguities;

        // Keys already handled by pre-built ambiguities must not appear in missing.
        $ambiguousKeys = array_column($prebuiltAmbiguities, 'key');

        foreach ($definition->requirements as $req) {
            if (isset($entities[$req->key])) {
                $editableFields[] = new EditableField(
                    key:      $req->key,
                    label:    $req->label,
                    value:    $entities[$req->key],
                    source:   'detected',
                    required: $req->required,
                );
            } elseif ($req->required && in_array($req->key, $ambiguousKeys, true)) {
                // Handled by a pre-built blocking ambiguity — skip missing and editable field.
            } elseif ($req->required) {
                $missing[] = new MissingField(
                    key:      $req->key,
                    label:    $req->label,
                    reason:   "The command does not specify a {$req->label}.",
                    required: true,
                );
                $editableFields[] = new EditableField(
                    key:      $req->key,
                    label:    $req->label,
                    value:    null,
                    source:   'missing',
                    required: true,
                );
            }
            // optional and not present — skip
        }

        $hasBlockingAmbiguity = collect($ambiguities)->contains(fn ($a) => $a['blocking'] ?? false);
        $status               = (empty($missing) && ! $hasBlockingAmbiguity) ? 'ready' : 'draft';

        $changes = [
            new ProposedChange(
                type:    $definition->operation,
                label:   $definition->label,
                module:  $definition->module,
                payload: $entities,
            ),
        ];

        return [$status, $missing, $editableFields, $changes, $ambiguities];
    }
}
