<?php

namespace Fluxio\Actions\Examples;

/**
 * One declarative intent expression example (Intent Examples — slice 1).
 *
 * Describes HOW a user may naturally express an intent in a given locale, as a
 * literal phrase and/or a slotted template. It is curated, read-only seed data —
 * NOT runtime state. An IntentExample never interprets input, builds proposals,
 * resolves entities, emits IDs, or decides readiness/ambiguity/execution. It is the
 * INPUT counterpart to IntentNarration (OUTPUT) over the same intent +
 * requirement-key slot vocabulary.
 *
 * Built exclusively by IntentExampleLoader from the versioned per-locale JSON in
 * packages/Actions/resources/intent-examples/. A record carries `text` (literal),
 * `template` (slotted), or both — at least one is always present and non-empty.
 */
final class IntentExample
{
    /**
     * @param  array<string, array{placeholder: string, entity_key: string}>  $slots
     * @param  list<string>  $tags
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $locale,
        public readonly string $intent,
        public readonly ?string $text,
        public readonly ?string $template,
        public readonly array $slots = [],
        public readonly string $source = 'seed',
        public readonly array $tags = [],
        public readonly array $notes = [],
    ) {}

    public function hasLiteral(): bool
    {
        return $this->text !== null && $this->text !== '';
    }

    public function hasTemplate(): bool
    {
        return $this->template !== null && $this->template !== '';
    }
}
