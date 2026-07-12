<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\DTO\ActionProposalData;
use Fluxio\Actions\DTO\EditableField;
use Fluxio\Actions\DTO\EditableFieldExplanation;
use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\DTO\MissingField;
use Fluxio\Actions\DTO\ProposedChange;
use Fluxio\Actions\DTO\ResolvedEntity;
use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\Registry\IntentRegistry;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Fluxio\Actions\Support\TemporalParseResult;
use Illuminate\Support\Str;

class ActionInterpreterService
{
    public function __construct(
        private readonly CommandInterpreterInterface $interpreter,
        private readonly IntentRegistry $registry,
        private readonly EntityResolverRegistry $resolverRegistry,
        private readonly DateTimeExpressionParser $temporalParser,
    ) {}

    public function interpret(string $text): ActionProposalData
    {
        $command = $this->interpreter->interpret($text);
        $definition = $this->registry->find($command->intent);

        // Pre-resolve ambiguous entity queries before dispatching to intent builders.
        // Auto-resolved queries promote to detected entities; unresolved ones carry
        // pre-built ambiguity objects so builders never touch hardcoded candidate data.
        $entities = $command->entities;
        $prebuiltAmbiguities = [];

        // Identity continuity (slice 1): server-owned identities captured at
        // auto-resolution time, keyed by operational role. Persisted alongside the
        // label promotion below — the resolver's primary key is no longer discarded.
        $resolvedEntities = [];

        if (isset($entities['lead_query'])) {
            [$entities, $leadAmbiguities, $leadIdentity] = $this->resolveEntityQuery('lead_query', $entities);
            $prebuiltAmbiguities = array_merge($prebuiltAmbiguities, $leadAmbiguities);
            $resolvedEntities += $leadIdentity;
        }

        // Resolve the assignee for assign_lead at interpretation time so an unknown or
        // ambiguous assignee keeps the proposal draft rather than surfacing only at
        // execution. The provider emits assignee as a parsed scalar (not a _query span)
        // so resolution is triggered here rather than from a query key.
        if ($command->intent === 'assign_lead' && isset($entities['assignee'])) {
            [$entities, $assigneeAmbiguities, $assigneeIdentity] = $this->resolveAssignee($entities['assignee'], $entities);
            $prebuiltAmbiguities = array_merge($prebuiltAmbiguities, $assigneeAmbiguities);
            $resolvedEntities += $assigneeIdentity;
        }

        // Resolve the target task for update_task_status at interpretation time so an
        // unknown or ambiguous task keeps the proposal draft rather than surfacing only
        // at execution. The provider emits the task as a parsed scalar span (`task`),
        // not a _query key, so resolution is triggered here against TaskEntityResolver.
        if ($command->intent === 'update_task_status' && isset($entities['task'])) {
            [$entities, $taskAmbiguities, $taskIdentity] = $this->resolveTask($entities['task'], $entities);
            $prebuiltAmbiguities = array_merge($prebuiltAmbiguities, $taskAmbiguities);
            $resolvedEntities += $taskIdentity;
        }

        // Parser-local temporal provenance, derived once from the source text and
        // used only to attach explainability metadata to date/time editable fields.
        // It does not affect entities, status, readiness, confidence, or execution.
        $temporal = $this->temporalParser->explain($command->sourceText);

        [$status, $missing, $editableFields, $changes, $ambiguities] = match ($command->intent) {
            'create_task' => $this->buildCreateTask($entities, $prebuiltAmbiguities),
            'schedule_call' => $this->buildScheduleCall($entities, $temporal, $prebuiltAmbiguities),
            default => $definition !== null
                ? $this->buildFromDefinition($definition, $entities, $temporal, $prebuiltAmbiguities)
                : ['draft', [], [], [], []],
        };

        return new ActionProposalData(
            id: (string) Str::uuid(),
            intent: $command->intent,
            status: $status,
            confidence: $command->confidence,
            source_text: $command->sourceText,
            entities: $entities,
            missing: $missing,
            warnings: $command->warnings,
            editable_fields: $editableFields,
            changes: $changes,
            needs_confirmation: true,
            ambiguities: $ambiguities,
            resolved_entities: $resolvedEntities,
        );
    }

    // ── Entity resolution ────────────────────────────────────────────────────

    /**
     * Run entity resolution for a query key, returning the updated entities array,
     * any pre-built ambiguity objects for unresolved multi-match cases, and the
     * server-owned identity captured on auto-resolution (empty otherwise).
     *
     * @return array{array, array, array<string, ResolvedEntity>}
     */
    private function resolveEntityQuery(string $queryKey, array $entities): array
    {
        $query = $entities[$queryKey];
        unset($entities[$queryKey]);

        $result = $this->resolverRegistry->resolve($query, new ResolutionContext($queryKey));
        $ambiguities = [];
        $identity = [];

        if ($result->resolved) {
            // Single strong match — promote to a concrete entity value and keep
            // the resolver-produced identity (real primary key, never provider data).
            $entities['lead'] = $result->resolvedCandidate->label;
            $identity['lead'] = ResolvedEntity::fromCandidate($result->resolvedCandidate);
        } elseif (! empty($result->candidates)) {
            // Multiple matches — emit a blocking ambiguity; the builder adds no missing field.
            $ambiguities[] = [
                'key' => 'lead',
                'label' => 'Lead',
                'reason' => 'multiple_matches',
                'blocking' => true,
                'query' => $query,
                'selected_candidate_id' => null,
                'candidates' => array_map(
                    fn (ResolutionCandidate $c) => $c->toArray(),
                    $result->candidates,
                ),
            ];
        }
        // else: no match → entities['lead'] remains absent → builder treats as missing

        return [$entities, $ambiguities, $identity];
    }

    /**
     * Resolve the assignee scalar value for assign_lead via UserEntityResolver.
     *
     * The provider emits assignee as a parsed scalar, not a _query span, so this
     * method is intent-specific rather than key-driven. It mirrors resolveEntityQuery
     * in outcome: auto-resolved → entity kept as label; ambiguous → blocking ambiguity,
     * entity removed; no match → entity removed (builder treats as missing).
     *
     * @return array{array, array, array<string, ResolvedEntity>}
     */
    private function resolveAssignee(string $assigneeValue, array $entities): array
    {
        unset($entities['assignee']);

        $result = $this->resolverRegistry->resolve($assigneeValue, new ResolutionContext('user_query'));
        $ambiguities = [];
        $identity = [];

        if ($result->resolved) {
            $entities['assignee'] = $result->resolvedCandidate->label;
            $identity['assignee'] = ResolvedEntity::fromCandidate($result->resolvedCandidate);
        } elseif (! empty($result->candidates)) {
            $ambiguities[] = [
                'key' => 'assignee',
                'label' => 'Assignee',
                'reason' => 'multiple_matches',
                'blocking' => true,
                'query' => $assigneeValue,
                'selected_candidate_id' => null,
                'candidates' => array_map(
                    fn (ResolutionCandidate $c) => $c->toArray(),
                    $result->candidates,
                ),
            ];
        }
        // else: no match → assignee absent → builder treats it as a missing required field

        return [$entities, $ambiguities, $identity];
    }

    /**
     * Resolve the task span for update_task_status via TaskEntityResolver.
     *
     * Mirrors resolveAssignee: auto-resolved → entity kept as the resolved title;
     * ambiguous → blocking ambiguity, entity removed; no match → entity removed (the
     * builder then treats `task` as a missing required field). The resolver dispatch
     * key (`task_query`) lives only here, never in the provider's entity surface.
     *
     * @return array{array, array, array<string, ResolvedEntity>}
     */
    private function resolveTask(string $taskValue, array $entities): array
    {
        unset($entities['task']);

        $result = $this->resolverRegistry->resolve($taskValue, new ResolutionContext('task_query'));
        $ambiguities = [];
        $identity = [];

        if ($result->resolved) {
            $entities['task'] = $result->resolvedCandidate->label;
            $identity['task'] = ResolvedEntity::fromCandidate($result->resolvedCandidate);
        } elseif (! empty($result->candidates)) {
            $ambiguities[] = [
                'key' => 'task',
                'label' => 'Task',
                'reason' => 'multiple_matches',
                'blocking' => true,
                'query' => $taskValue,
                'selected_candidate_id' => null,
                'candidates' => array_map(
                    fn (ResolutionCandidate $c) => $c->toArray(),
                    $result->candidates,
                ),
            ];
        }
        // else: no match → task absent → builder treats it as a missing required field

        return [$entities, $ambiguities, $identity];
    }

    // ── Intent builders ──────────────────────────────────────────────────────

    private function buildCreateTask(array $entities, array $prebuiltAmbiguities = []): array
    {
        $hasLead = isset($entities['lead']);
        $hasBlockingAmbiguity = collect($prebuiltAmbiguities)->contains(fn ($a) => $a['blocking'] ?? false);
        $status = $hasBlockingAmbiguity ? 'draft' : 'ready';

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
                required: false,
            );
        }

        // Date / Time — extracted by the parser for all intents, surfaced as
        // editable fields here so refinements (e.g. "move it to Friday") can
        // target them via the existing ReplaceDate/ReplaceTime machinery.
        foreach (['date' => 'Date', 'time' => 'Time'] as $key => $label) {
            if (isset($entities[$key])) {
                $editableFields[] = new EditableField(
                    key: $key,
                    label: $label,
                    value: $entities[$key],
                    source: 'detected',
                    required: false,
                );
            }
        }

        // due_at — derived from date + time for executor consumption.
        // The executor reads due_at directly from payload; date/time are kept
        // alongside so the refinement syncChangePayloads path stays coherent.
        $dueAt = null;
        if (isset($entities['date'])) {
            $dueAt = $entities['date'];
            if (isset($entities['time'])) {
                $dueAt .= ' '.$entities['time'];
            }
        }
        if ($dueAt !== null) {
            $editableFields[] = new EditableField(
                key: 'due_at',
                label: 'Due',
                value: $dueAt,
                source: 'derived',
                required: false,
            );
        }

        // Priority
        if (isset($entities['priority'])) {
            $editableFields[] = new EditableField(
                key: 'priority',
                label: 'Priority',
                value: $entities['priority'],
                source: 'detected',
                required: false,
            );
        }

        $payload = ['title' => 'Follow-up task'];
        if ($hasLead) {
            $payload['lead'] = $entities['lead'];
        }
        if (isset($entities['date'])) {
            $payload['date'] = $entities['date'];
        }
        if (isset($entities['time'])) {
            $payload['time'] = $entities['time'];
        }
        if ($dueAt !== null) {
            $payload['due_at'] = $dueAt;
        }
        if (isset($entities['priority'])) {
            $payload['priority'] = $entities['priority'];
        }

        $changes = [
            new ProposedChange(
                type: 'create',
                label: 'Create task',
                module: 'tasks',
                payload: $payload,
            ),
        ];

        return [$status, [], $editableFields, $changes, $prebuiltAmbiguities];
    }

    private function buildScheduleCall(array $entities, TemporalParseResult $temporal, array $prebuiltAmbiguities = []): array
    {
        $missing = [];
        $editableFields = [];
        $ambiguities = $prebuiltAmbiguities;

        if (isset($entities['lead'])) {
            $editableFields[] = new EditableField(
                key: 'lead',
                label: 'Lead',
                value: $entities['lead'],
                source: 'detected',
                required: true,
            );
        }

        // Date / Time: honour temporal entities already extracted by the parser.
        // Only mark them missing when they were not extracted — this keeps the
        // proposal consistent with any inferred-time warning surfaced alongside.
        foreach (['date' => 'Date', 'time' => 'Time'] as $key => $label) {
            if (isset($entities[$key])) {
                $editableFields[] = new EditableField(
                    key: $key,
                    label: $label,
                    value: $entities[$key],
                    source: 'detected',
                    required: true,
                    explanation: $this->temporalExplanationFor($key, $entities[$key], $temporal),
                );
            } else {
                $missing[] = new MissingField(
                    key: $key,
                    label: $label,
                    reason: "The command does not specify a {$label}.",
                    required: true,
                );
                $editableFields[] = new EditableField(
                    key: $key,
                    label: $label,
                    value: null,
                    source: 'missing',
                    required: true,
                );
            }
        }

        $changes = [
            new ProposedChange(
                type: 'schedule',
                label: 'Schedule call',
                module: 'calendar',
                payload: [],
            ),
        ];

        // Readiness mirrors buildFromDefinition: ready only when nothing is
        // missing and no blocking ambiguity remains (e.g. unresolved lead).
        $hasBlockingAmbiguity = collect($ambiguities)->contains(fn ($a) => $a['blocking'] ?? false);
        $status = (empty($missing) && ! $hasBlockingAmbiguity) ? 'ready' : 'draft';

        return [$status, $missing, $editableFields, $changes, $ambiguities];
    }

    private function buildFromDefinition(IntentDefinition $definition, array $entities, TemporalParseResult $temporal, array $prebuiltAmbiguities = []): array
    {
        $missing = [];
        $editableFields = [];
        $ambiguities = $prebuiltAmbiguities;

        // Keys already handled by pre-built ambiguities must not appear in missing.
        $ambiguousKeys = array_column($prebuiltAmbiguities, 'key');

        foreach ($definition->requirements as $req) {
            if (isset($entities[$req->key])) {
                $editableFields[] = new EditableField(
                    key: $req->key,
                    label: $req->label,
                    value: $entities[$req->key],
                    source: 'detected',
                    required: $req->required,
                    explanation: $this->temporalExplanationFor($req->key, $entities[$req->key], $temporal),
                );
            } elseif ($req->required && in_array($req->key, $ambiguousKeys, true)) {
                // Handled by a pre-built blocking ambiguity — skip missing and editable field.
            } elseif ($req->required) {
                $missing[] = new MissingField(
                    key: $req->key,
                    label: $req->label,
                    reason: "The command does not specify a {$req->label}.",
                    required: true,
                );
                $editableFields[] = new EditableField(
                    key: $req->key,
                    label: $req->label,
                    value: null,
                    source: 'missing',
                    required: true,
                );
            }
            // optional and not present — skip
        }

        $hasBlockingAmbiguity = collect($ambiguities)->contains(fn ($a) => $a['blocking'] ?? false);
        $status = (empty($missing) && ! $hasBlockingAmbiguity) ? 'ready' : 'draft';

        $changes = [
            new ProposedChange(
                type: $definition->operation,
                label: $definition->label,
                module: $definition->module,
                payload: $entities,
            ),
        ];

        return [$status, $missing, $editableFields, $changes, $ambiguities];
    }

    // ── Field explainability ───────────────────────────────────────────────────

    /**
     * Build optional parser-local explainability for a temporal editable field.
     *
     * Returns an explanation only for the 'date'/'time' keys, only when the
     * parser produced meaningful provenance, AND only when the parsed value
     * matches the field value. The value guard keeps the note accurate when the
     * entity originated elsewhere (e.g. a non-deterministic provider or a prior
     * refinement) rather than from this source text. Any other key — or no match
     * — yields null, so no empty explanation object is ever attached.
     *
     * This is parser-local explainability only: it never affects entities,
     * status, readiness, confidence, ambiguity handling, or execution.
     */
    private function temporalExplanationFor(string $key, mixed $value, TemporalParseResult $temporal): ?EditableFieldExplanation
    {
        $data = match ($key) {
            'date' => $temporal->date !== null && (string) $temporal->date === (string) $value
                ? $temporal->dateExplanation()
                : null,
            'time' => $temporal->time !== null && (string) $temporal->time === (string) $value
                ? $temporal->timeExplanation()
                : null,
            default => null,
        };

        if ($data === null) {
            return null;
        }

        return new EditableFieldExplanation(
            source: $data['source'],
            expression: $data['expression'],
            confidence: $data['confidence'],
            message: $data['message'],
        );
    }
}
