<?php

namespace Fluxio\Actions\EntityResolution\Resolvers;

use Fluxio\Actions\EntityResolution\Contracts\EntityResolverInterface;
use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\DTO\ResolutionResult;
use Fluxio\Actions\EntityResolution\Repositories\InMemoryLeadRepository;

class LeadEntityResolver implements EntityResolverInterface
{
    /**
     * Minimum confidence for a single candidate to be auto-resolved without user
     * confirmation. Candidates below this threshold surface an explicit ambiguity
     * panel even when they are the sole match, preventing silent wrong assignments.
     */
    private const AUTO_RESOLVE_THRESHOLD = 0.8;

    public function __construct(private readonly InMemoryLeadRepository $repository) {}

    public function supports(string $entityType): bool
    {
        return $entityType === 'lead_query';
    }

    public function resolve(string $query, ResolutionContext $context): ResolutionResult
    {
        if (trim($query) === '') {
            return ResolutionResult::noMatch();
        }

        $candidates = [];

        foreach ($this->repository->all() as $lead) {
            $score = $this->score($lead['label'], $query);

            if ($score > 0.0) {
                $candidates[] = new ResolutionCandidate(
                    id:          $lead['id'],
                    type:        $lead['type'],
                    label:       $lead['label'],
                    description: $lead['description'],
                    confidence:  $score,
                );
            }
        }

        if (empty($candidates)) {
            return ResolutionResult::noMatch();
        }

        // Sort by confidence descending; equal-confidence candidates retain their
        // original repository order (PHP 8+ usort is stable).
        usort($candidates, fn ($a, $b) => $b->confidence <=> $a->confidence);

        // Auto-resolve only when there is exactly one candidate whose confidence
        // meets or exceeds the threshold. A single weak match still requires the
        // user to explicitly confirm to avoid silent mis-assignments.
        if (count($candidates) === 1 && $candidates[0]->confidence >= self::AUTO_RESOLVE_THRESHOLD) {
            return ResolutionResult::autoResolved($candidates[0]);
        }

        return ResolutionResult::ambiguous($candidates);
    }

    /**
     * Score a lead label against a query string.
     *
     * Scoring tiers (case-insensitive):
     *   1.0 — exact match
     *   0.8 — label starts with query followed by a word boundary (e.g. "Rossi SRL" for "Rossi")
     *   0.65 — query appears at a word boundary inside the label (e.g. "Mario Rossi" for "Rossi")
     *   0.0  — no match
     *
     * Word-boundary semantics prevent "Rossini" from matching the query "Rossi".
     */
    private function score(string $label, string $query): float
    {
        $normalLabel = mb_strtolower($label);
        $normalQuery = mb_strtolower($query);

        if ($normalLabel === $normalQuery) {
            return 1.0;
        }

        if (str_starts_with($normalLabel, $normalQuery . ' ')) {
            return 0.8;
        }

        if ((bool) preg_match('/\b' . preg_quote($normalQuery, '/') . '\b/', $normalLabel)) {
            return 0.65;
        }

        return 0.0;
    }
}
