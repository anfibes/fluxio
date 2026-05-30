<?php

namespace Fluxio\Actions\Support;

/**
 * Lightweight, parser-local explainability for deterministic temporal parsing.
 *
 * Produced by DateTimeExpressionParser::explain(). It mirrors the values that
 * parse() returns (date / time strings) and adds provenance: which kind of
 * expression produced each value, the matched expression text, human-readable
 * explanations, and a parser-local confidence.
 *
 * IMPORTANT — scope of `confidence`:
 *  - It is *temporal parse confidence only*. It is NOT proposal confidence,
 *    does NOT imply execution safety, does NOT bypass validation, and does NOT
 *    affect lifecycle readiness. Nothing in the proposal pipeline consumes it
 *    today; it exists for future explainability.
 *
 * Source values:
 *  - 'explicit'  — an explicit clock time (e.g. "at 10:30")
 *  - 'relative'  — a relative day expression (today / tomorrow / day after
 *                  tomorrow / in N days)
 *  - 'weekday'   — a weekday expression (Monday … Sunday, optionally next/this)
 *  - 'day_part'  — a time inferred from a fixed day-part mapping (morning,
 *                  afternoon, …)
 *  - 'none'      — nothing recognised for that component
 */
final class TemporalParseResult
{
    /**
     * @param  list<string>  $explanations
     */
    public function __construct(
        public readonly ?string $date,
        public readonly ?string $time,
        public readonly string $dateSource,
        public readonly string $timeSource,
        public readonly ?string $dateExpression,
        public readonly ?string $timeExpression,
        public readonly array $explanations,
        public readonly float $confidence,
        // Per-component parser-local confidence (same scope caveat as $confidence).
        // Defaulted so existing call sites stay valid; explain() always sets them.
        public readonly float $dateConfidence = 0.0,
        public readonly float $timeConfidence = 0.0,
    ) {}

    public function hasDate(): bool
    {
        return $this->date !== null;
    }

    public function hasTime(): bool
    {
        return $this->time !== null;
    }

    /** True when the time came from a fixed day-part mapping rather than an explicit clock value. */
    public function isTimeInferred(): bool
    {
        return $this->timeSource === 'day_part';
    }

    /**
     * Field-centric provenance for the date value, shaped for an editable field
     * explanation. Returns null when no date was recognised — callers must never
     * attach an empty explanation.
     *
     * @return array{source: string, expression: ?string, confidence: float, message: string}|null
     */
    public function dateExplanation(): ?array
    {
        if ($this->date === null || $this->dateSource === 'none') {
            return null;
        }

        $message = $this->dateSource === 'weekday'
            ? "Date resolved from weekday '{$this->dateExpression}'."
            : "Date resolved from '{$this->dateExpression}'.";

        return [
            'source' => $this->dateSource,
            'expression' => $this->dateExpression,
            'confidence' => $this->dateConfidence,
            'message' => $message,
        ];
    }

    /**
     * Field-centric provenance for the time value, shaped for an editable field
     * explanation. The day-part message matches the Phase 6C inferred-time
     * warning verbatim so the field note and the warning stay consistent.
     * Returns null when no time was recognised.
     *
     * @return array{source: string, expression: ?string, confidence: float, message: string}|null
     */
    public function timeExplanation(): ?array
    {
        if ($this->time === null || $this->timeSource === 'none') {
            return null;
        }

        $message = $this->timeSource === 'day_part'
            ? "Time inferred from '{$this->timeExpression}' as {$this->time}."
            : "Time set from '{$this->timeExpression}' as {$this->time}.";

        return [
            'source' => $this->timeSource,
            'expression' => $this->timeExpression,
            'confidence' => $this->timeConfidence,
            'message' => $message,
        ];
    }

    /**
     * @return array{date: ?string, time: ?string, date_source: string, time_source: string, date_expression: ?string, time_expression: ?string, explanations: list<string>, confidence: float}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'time' => $this->time,
            'date_source' => $this->dateSource,
            'time_source' => $this->timeSource,
            'date_expression' => $this->dateExpression,
            'time_expression' => $this->timeExpression,
            'explanations' => $this->explanations,
            'confidence' => $this->confidence,
        ];
    }
}
