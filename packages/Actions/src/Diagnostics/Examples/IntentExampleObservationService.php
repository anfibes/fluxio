<?php

namespace Fluxio\Actions\Diagnostics\Examples;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Examples\DTO\IntentExampleObservationMetrics;
use Fluxio\Actions\Diagnostics\Examples\DTO\IntentExampleObservationReport;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;
use Fluxio\Actions\Diagnostics\Observation\LlmObservationService;
use Fluxio\Actions\Examples\IntentExample;
use Fluxio\Actions\Examples\IntentExampleRegistry;
use Fluxio\Actions\Llm\Prompting\PromptExemplar;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;

/**
 * Diagnostics-only observation of literal Intent Examples through the existing LLM sandbox.
 *
 * Slice A1 measured the no-few-shot baseline. Slice A2 adds an OPT-IN, locale-aware few-shot
 * affordance: the locale's TEMPLATE examples are rendered into contract-shaped exemplars and
 * prepended to the prompt, while the LITERAL examples remain the evaluated inputs — so the
 * model is never scored on a string it was shown verbatim (leakage guard). Few-shot is off by
 * default; with no exemplars the prompt and behavior are byte-identical to A1.
 *
 * This is the first consumer of IntentExampleRegistry and lives entirely in the diagnostics
 * namespace — never in the resolver, interpretation runtime, lifecycle, or executors. It builds
 * no proposals, resolves no entities, executes nothing, persists nothing, and adds no
 * relaxed/parallel validator (exemplar contract-safety is checked with the SAME
 * LlmStructuredOutputValidator the sandbox uses). IntentExamples stay non-authoritative; the
 * deterministic provider remains the runtime authority.
 */
class IntentExampleObservationService
{
    public const DEFAULT_FEW_SHOT_LIMIT = 5;

    /**
     * Safe, label-only sample fillers used to render a template exemplar. Values are chosen to
     * be contract-legal AND prompt-consistent (date as YYYY-MM-DD, time as 24h HH:MM) and
     * deliberately DISJOINT from the evaluated literals' values, so an exemplar can never be a
     * verbatim copy of an evaluated input. They are user-facing labels, never IDs.
     *
     * @var array<string, string>
     */
    private const SLOT_FILLERS = [
        'lead' => 'Bianchi',
        'assignee' => 'Luca',
        'quote' => 'Q-2050',
        'date' => '2026-06-20',
        'time' => '09:00',
    ];

    public function __construct(
        private readonly IntentExampleRegistry $registry,
        private readonly LlmObservationService $observation,
        private readonly LlmStructuredOutputValidator $structuredValidator,
    ) {}

    /**
     * Select the literal examples to observe for a locale, in registry order, with optional
     * id and count filters. Template-only examples are excluded.
     *
     * @return list<IntentExample>
     */
    public function select(string $locale, ?string $caseId = null, ?int $limit = null): array
    {
        $examples = array_values(array_filter(
            $this->registry->byLocale($locale),
            static fn (IntentExample $e): bool => $e->hasLiteral(),
        ));

        if ($caseId !== null) {
            $examples = array_values(array_filter(
                $examples,
                static fn (IntentExample $e): bool => $e->id === $caseId,
            ));
        }

        if ($limit !== null) {
            $examples = array_slice($examples, 0, max(0, $limit));
        }

        return $examples;
    }

    /**
     * Build locale-aware few-shot exemplars from the locale's TEMPLATE examples — at most one
     * per intent, capped at $limit (default 5). Each exemplar is rendered with safe sample
     * fillers and its structured output is validated with the sandbox's own contract validator;
     * any exemplar that is not contract-safe, or whose rendered input collides with an evaluated
     * literal in $excludeTexts (leakage), is skipped.
     *
     * @param  list<string>  $excludeTexts  Evaluated literal inputs that must not appear as exemplars.
     * @return list<PromptExemplar>
     */
    public function fewShotExemplars(string $locale, ?int $limit = null, array $excludeTexts = []): array
    {
        $limit ??= self::DEFAULT_FEW_SHOT_LIMIT;

        $templates = array_values(array_filter(
            $this->registry->byLocale($locale),
            static fn (IntentExample $e): bool => $e->hasTemplate(),
        ));

        $exemplars = [];
        $seenIntents = [];

        foreach ($templates as $template) {
            if (count($exemplars) >= $limit) {
                break;
            }

            // One exemplar per intent.
            if (isset($seenIntents[$template->intent])) {
                continue;
            }

            $exemplar = $this->renderExemplar($template);

            if ($exemplar === null) {
                continue;
            }

            // Leakage guard: never show, as an exemplar, a string we will score verbatim.
            if (in_array($exemplar->inputText, $excludeTexts, true)) {
                continue;
            }

            $seenIntents[$template->intent] = true;
            $exemplars[] = $exemplar;
        }

        return $exemplars;
    }

    /**
     * Render an ordered list of IntentExamples into contract-shaped exemplars, preserving order,
     * dropping any that cannot be rendered contract-safe (missing filler / validator rejection) or
     * whose rendered input collides with an evaluated input in $excludeTexts (leakage guard).
     *
     * Used by the SELECTED few-shot strategy (Slice A3.1): the deterministic selector returns
     * ranked IntentExamples per case, and this turns them into the same PromptExemplar shape the
     * blind path produces — so both strategies feed the prompt identically and differ only in
     * which/ordered exemplars are shown.
     *
     * @param  list<IntentExample>  $examples
     * @param  list<string>  $excludeTexts
     * @return list<PromptExemplar>
     */
    public function renderExemplars(array $examples, array $excludeTexts = []): array
    {
        $exemplars = [];

        foreach ($examples as $example) {
            $exemplar = $this->renderExemplar($example);

            if ($exemplar === null) {
                continue;
            }

            if (in_array($exemplar->inputText, $excludeTexts, true)) {
                continue; // leakage guard: never show a string we will score verbatim.
            }

            $exemplars[] = $exemplar;
        }

        return $exemplars;
    }

    /**
     * Render one TEMPLATE example into a contract-shaped PromptExemplar, or null if it cannot be
     * rendered contract-safe (missing filler, or output rejected by the structured validator).
     */
    private function renderExemplar(IntentExample $template): ?PromptExemplar
    {
        // Only TEMPLATE examples render into exemplars: a literal carries no slots/fillers, so it
        // has no contract-shaped entities to demonstrate. The selector may rank a literal highly,
        // but it is dropped here rather than shown with an empty input.
        if (! $template->hasTemplate()) {
            return null;
        }

        $input = (string) $template->template;
        $entities = [];

        foreach ($template->slots as $slot) {
            $entityKey = $slot['entity_key'];
            $placeholder = $slot['placeholder'];

            if (! array_key_exists($entityKey, self::SLOT_FILLERS)) {
                return null;
            }

            $filler = self::SLOT_FILLERS[$entityKey];
            $input = str_replace($placeholder, $filler, $input);
            $entities[$entityKey] = $filler;
        }

        $output = [
            'intent' => $template->intent,
            'confidence' => 0.9,
            'entities' => $entities,
            'notes' => [],
        ];

        // Contract-safety guarantee: an exemplar's output must pass the SAME validator the
        // sandbox applies to real model output. If it would not, drop it rather than teach the
        // model something the contract forbids.
        if (! $this->structuredValidator->validate($output)->valid) {
            return null;
        }

        return new PromptExemplar(inputText: $input, output: $output, sourceId: $template->id);
    }

    /**
     * Replay a single literal example through the unchanged LLM observation pipeline, optionally
     * with few-shot exemplars.
     *
     * The example is adapted to the shared InterpretationCorpusCase shape: its literal `text` is
     * the input, its `intent` the expectation. Literal Intent Examples carry no expected
     * entities, so the entity expectation is empty (entity agreement is reported as n/a).
     *
     * @param  list<PromptExemplar>  $exemplars
     */
    public function observeExample(IntentExample $example, array $exemplars = []): LlmObservationResult
    {
        $case = new InterpretationCorpusCase(
            id: $example->id,
            text: (string) $example->text,
            locale: $example->locale,
            expectedIntent: $example->intent,
            expectedEntities: [],
            notes: $example->notes,
        );

        return $this->observation->observe($case, $exemplars);
    }

    /**
     * @param  list<LlmObservationResult>  $results
     * @param  list<string>  $fewShotExampleIds
     */
    public function report(string $locale, array $results, bool $fewShotEnabled = false, array $fewShotExampleIds = []): IntentExampleObservationReport
    {
        return new IntentExampleObservationReport(
            locale: $locale,
            results: $results,
            metrics: IntentExampleObservationMetrics::fromResults($results),
            fewShotEnabled: $fewShotEnabled,
            fewShotExampleIds: $fewShotExampleIds,
        );
    }

    /**
     * Convenience: select + (optional few-shot) + observe + aggregate in one call (used by tests).
     */
    public function observe(string $locale = 'it', ?string $caseId = null, ?int $limit = null, bool $fewShot = false, ?int $fewShotLimit = null): IntentExampleObservationReport
    {
        $examples = $this->select($locale, $caseId, $limit);

        $exemplars = $fewShot
            ? $this->fewShotExemplars($locale, $fewShotLimit, array_map(static fn (IntentExample $e): string => (string) $e->text, $examples))
            : [];

        $results = array_map(
            fn (IntentExample $e): LlmObservationResult => $this->observeExample($e, $exemplars),
            $examples,
        );

        return $this->report(
            $locale,
            $results,
            $fewShot,
            array_values(array_filter(array_map(static fn (PromptExemplar $x): ?string => $x->sourceId, $exemplars))),
        );
    }
}
