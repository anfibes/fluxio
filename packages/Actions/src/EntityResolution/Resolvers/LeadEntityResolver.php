<?php

namespace Fluxio\Actions\EntityResolution\Resolvers;

use Fluxio\Actions\EntityResolution\Contracts\EntityResolverInterface;
use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\DTO\ResolutionResult;
use Fluxio\Leads\Models\Lead;

/**
 * Resolves a lead reference span (e.g. "Mario Rossi", "Rossi") to a real Lead record
 * by name or company. DB-backed (mirrors TaskEntityResolver / UserEntityResolver):
 * the candidates it scores ARE the rows the executors will later act on, so a proposal
 * that becomes ready through this resolver can always be executed against the same
 * domain entity.
 *
 * Matching is the runtime identity authority; the interpreter only supplies the span
 * (lead_query) and never an id. Candidate ids are real Lead primary keys so the
 * ambiguity panel resolves to an actual row, but they are never emitted by the
 * interpreter/provider.
 *
 * Scoring is unchanged from the previous in-memory implementation (exact 1.0 /
 * starts-with 0.8 / word-boundary 0.65), now evaluated against BOTH `name` and
 * `company` and taking the stronger of the two — consistent with the executors, which
 * look the lead up by name OR company.
 */
class LeadEntityResolver implements EntityResolverInterface
{
    /**
     * Minimum confidence for a single candidate to be auto-resolved without user
     * confirmation. Candidates below this threshold surface an explicit ambiguity
     * panel even when they are the sole match, preventing silent wrong assignments.
     */
    private const AUTO_RESOLVE_THRESHOLD = 0.8;

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

        foreach ($this->leads() as $lead) {
            $score = max(
                $this->score($lead['name'], $query),
                $this->score((string) ($lead['company'] ?? ''), $query),
            );

            if ($score > 0.0) {
                $candidates[] = new ResolutionCandidate(
                    id: $lead['id'],
                    type: $lead['type'],
                    label: $lead['name'],
                    description: $lead['company'],
                    confidence: $score,
                );
            }
        }

        if (empty($candidates)) {
            return ResolutionResult::noMatch();
        }

        // Sort by confidence descending; equal-confidence candidates retain their
        // DB order (ascending id — see leads()), so PHP 8+ stable usort gives a
        // deterministic ordering.
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
        if ($label === '') {
            return 0.0;
        }

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
     * Real Lead rows projected to the fields the resolver needs, ordered by id so
     * equal-confidence ambiguity ordering is deterministic. A lead carrying a company
     * value is treated as a company-type contact, otherwise a person — this is the only
     * person/company signal the Lead schema exposes and it drives the ambiguity
     * type-narrowing dimension ("the company one" / "the person one").
     *
     * @return array<int, array{id: int, name: string, company: string|null, type: string}>
     */
    private function leads(): array
    {
        return Lead::query()
            ->select(['id', 'name', 'company'])
            ->orderBy('id')
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'company' => $lead->company,
                'type' => ($lead->company !== null && trim((string) $lead->company) !== '') ? 'company' : 'person',
            ])
            ->all();
    }
}
