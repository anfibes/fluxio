<?php

namespace Fluxio\Actions\Diagnostics\Examples;

/**
 * Diagnostics-only few-shot exemplar selection strategy (Slice A3.1).
 *
 * `Blind` keeps the A2/A4 behavior: one template exemplar per intent in registry order, the SAME
 * set shown for every evaluated case. `Selected` chooses, per evaluation case, the most relevant
 * exemplars using a small deterministic scorer (same locale, same intent, slot overlap, template
 * preference, diversity guard) — driven only by already-known case metadata, never by inferring
 * intent from the case text.
 *
 * This is an experiment knob, not runtime behavior: it only changes which exemplars condition the
 * diagnostic prompt. IntentExamples stay non-authoritative; the deterministic provider stays the
 * runtime authority.
 */
enum ExemplarStrategy: string
{
    /** One template exemplar per intent, registry order, identical for every case (A2/A4). */
    case Blind = 'blind';

    /** Per-case, relevance-ranked exemplars chosen by the deterministic selector (A3.1). */
    case Selected = 'selected';

    public static function fromString(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }
}
