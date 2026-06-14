<?php

namespace Fluxio\Actions\EntityResolution\Resolvers;

use App\Models\User;
use Fluxio\Actions\EntityResolution\Contracts\EntityResolverInterface;
use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\DTO\ResolutionResult;

class UserEntityResolver implements EntityResolverInterface
{
    /**
     * Minimum confidence for a single candidate to be auto-resolved without user
     * confirmation. Mirrors LeadEntityResolver — a single weak match surfaces an
     * explicit ambiguity panel rather than silently assigning the wrong person.
     */
    private const AUTO_RESOLVE_THRESHOLD = 0.8;

    public function supports(string $entityType): bool
    {
        return $entityType === 'user_query';
    }

    public function resolve(string $query, ResolutionContext $context): ResolutionResult
    {
        if (trim($query) === '') {
            return ResolutionResult::noMatch();
        }

        $candidates = [];

        foreach ($this->users() as $user) {
            $score = $this->score($user['label'], $query);

            if ($score > 0.0) {
                $candidates[] = new ResolutionCandidate(
                    id:          $user['id'],
                    type:        'user',
                    label:       $user['label'],
                    description: $user['description'],
                    confidence:  $score,
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
     * Scoring tiers (case-insensitive), mirroring LeadEntityResolver:
     *   1.0 — exact name match
     *   0.8 — name starts with query followed by a word boundary
     *   0.65 — query appears at a word boundary inside the name
     *   0.0  — no match
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

    /** @return array<int, array{id: int, label: string, description: string|null}> */
    private function users(): array
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->get()
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'label'       => $u->name,
                'description' => $u->email,
            ])
            ->all();
    }
}
