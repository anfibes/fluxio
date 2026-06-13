<?php

namespace Fluxio\Actions\Diagnostics\Observation;

/**
 * Diagnostics-only helper: pulls the first complete top-level JSON object out of free model text.
 *
 * Used by the format-off experiment (E2): when the transport is NOT forced into `format=json`, a
 * reasoning model may emit `<think>…</think>` (or prose) followed by a JSON object. This finds that
 * object so the SAME structured-output validator can judge it — it never relaxes or repairs the
 * payload, it only locates and decodes a candidate. A string-aware brace scanner is used so braces
 * inside string literals don't confuse nesting.
 *
 * It is pure and side-effect-free, lives only in the diagnostics namespace, and never touches the
 * runtime interpretation path.
 */
final class JsonObjectExtractor
{
    /**
     * Return the decoded first balanced `{ … }` object in $text, or null if none decodes.
     *
     * @return array<string, mixed>|null
     */
    public static function extract(string $text): ?array
    {
        $length = strlen($text);
        $start = strpos($text, '{');

        while ($start !== false) {
            $end = self::matchingBrace($text, $start, $length);

            if ($end !== null) {
                $candidate = substr($text, $start, $end - $start + 1);
                $decoded = json_decode($candidate, true);

                if (is_array($decoded) && ! array_is_list($decoded)) {
                    return $decoded;
                }
            }

            $next = strpos($text, '{', $start + 1);
            $start = $next;
        }

        return null;
    }

    /**
     * Index of the brace matching the `{` at $open, accounting for string literals and escapes;
     * null if unbalanced.
     */
    private static function matchingBrace(string $text, int $open, int $length): ?int
    {
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $open; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}
