<?php

namespace Fluxio\Actions\DTO;

/**
 * Optional, parser-local explainability metadata attached to an EditableField.
 *
 * It records HOW a field value entered the proposal (e.g. a temporal value
 * resolved from "tomorrow" or inferred from "afternoon"), so the frontend can
 * render a small, non-invasive note beneath the field under review.
 *
 * IMPORTANT — scope of this metadata:
 *  - This is parser-local explainability ONLY. `confidence` is temporal parse
 *    confidence; it is NOT proposal confidence and does NOT imply execution
 *    safety. It does NOT bypass or affect validation, lifecycle readiness,
 *    confirmation, or ambiguity handling. Nothing in the proposal pipeline
 *    consumes it today.
 *
 * `source` carries parser-local provenance (e.g. 'explicit', 'relative',
 * 'weekday', 'day_part'). It is intentionally distinct from
 * EditableField::$source (detected / inferred / missing / …).
 */
class EditableFieldExplanation
{
    public function __construct(
        public readonly string $source,
        public readonly ?string $expression,
        public readonly ?float $confidence,
        public readonly string $message,
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'expression' => $this->expression,
            'confidence' => $this->confidence,
            'message' => $this->message,
        ];
    }
}
