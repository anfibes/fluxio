<?php

namespace Fluxio\Actions\EntityResolution\Resolvers;

use Fluxio\Actions\EntityResolution\Contracts\EntityResolverInterface;
use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\DTO\ResolutionResult;
use Fluxio\Tasks\Models\Task;

/**
 * Resolves a task reference span (e.g. "Follow-up", "Rossi follow-up") to a real Task
 * record by title. Mirrors UserEntityResolver/LeadEntityResolver: deterministic
 * title scoring, the same auto-resolve threshold, and the same ambiguity lifecycle —
 * a single strong match auto-resolves, multiple matches surface a blocking ambiguity,
 * no match returns no-match.
 *
 * Matching is the runtime identity authority; the interpreter only supplies the span.
 * Candidate ids exist here (as in the other resolvers) so the ambiguity panel can be
 * resolved, but they are never emitted by the interpreter/provider.
 */
class TaskEntityResolver implements EntityResolverInterface
{
    /**
     * Minimum confidence for a single candidate to be auto-resolved without user
     * confirmation. Mirrors the lead/user resolvers — a single weak match surfaces an
     * explicit ambiguity panel rather than silently targeting the wrong task.
     */
    private const AUTO_RESOLVE_THRESHOLD = 0.8;

    public function supports(string $entityType): bool
    {
        return $entityType === 'task_query';
    }

    public function resolve(string $query, ResolutionContext $context): ResolutionResult
    {
        if (trim($query) === '') {
            return ResolutionResult::noMatch();
        }

        $candidates = [];

        foreach ($this->tasks() as $task) {
            $score = $this->score($task['label'], $query);

            if ($score > 0.0) {
                $candidates[] = new ResolutionCandidate(
                    id: $task['id'],
                    type: 'task',
                    label: $task['label'],
                    description: $task['description'],
                    confidence: $score,
                );
            }
        }

        if (empty($candidates)) {
            return ResolutionResult::noMatch();
        }

        usort($candidates, fn ($a, $b) => $b->confidence <=> $a->confidence);

        if (count($candidates) === 1 && $candidates[0]->confidence >= self::AUTO_RESOLVE_THRESHOLD) {
            return ResolutionResult::autoResolved($candidates[0]);
        }

        return ResolutionResult::ambiguous($candidates);
    }

    /**
     * Scoring tiers (case-insensitive), mirroring the lead/user resolvers:
     *   1.0  — exact title match
     *   0.8  — title starts with the query followed by a word boundary
     *   0.65 — query appears at a word boundary inside the title
     *   0.0  — no match
     */
    private function score(string $label, string $query): float
    {
        $normalLabel = mb_strtolower($label);
        $normalQuery = mb_strtolower($query);

        if ($normalLabel === $normalQuery) {
            return 1.0;
        }

        if (str_starts_with($normalLabel, $normalQuery.' ')) {
            return 0.8;
        }

        if ((bool) preg_match('/\b'.preg_quote($normalQuery, '/').'\b/', $normalLabel)) {
            return 0.65;
        }

        return 0.0;
    }

    /**
     * Real Task rows projected to the fields the resolver needs, ordered by id so
     * the base order is deterministic: with equal confidence, PHP's stable usort
     * preserves this ascending-id order, keeping ordinal selectors ("the first
     * one", "the second one") stable across requests. Mirrors LeadEntityResolver.
     *
     * @return array<int, array{id: int, label: string, description: string|null}>
     */
    private function tasks(): array
    {
        return Task::query()
            ->select(['id', 'title', 'status'])
            ->orderBy('id')
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'label' => $t->title,
                'description' => $t->status,
            ])
            ->all();
    }
}
