<?php

namespace Fluxio\Actions\DTO;

/**
 * Describes a single allowed mutation operation for an intent.
 *
 * - operation: one of the semantic operations produced by RuleBasedRefinementInterpreter
 *              ('replace', 'clear', 'append', 'remove')
 * - fields:    list of field keys for which this operation is permitted
 * - collection: when true, this capability covers collection-level semantics
 *              (e.g. append/remove items, or collection replace via operation='replace' + target)
 */
readonly class MutationCapability
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        public string $operation,
        public array  $fields,
        public bool   $collection = false,
    ) {}

    public function toArray(): array
    {
        return [
            'operation'  => $this->operation,
            'fields'     => $this->fields,
            'collection' => $this->collection,
        ];
    }
}
