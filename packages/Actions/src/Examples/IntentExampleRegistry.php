<?php

namespace Fluxio\Actions\Examples;

use Fluxio\Actions\Examples\Exceptions\InvalidIntentExampleException;

/**
 * Read-only, in-memory registry of declarative intent examples.
 *
 * Loads every per-locale file under packages/Actions/resources/intent-examples/ via
 * the fail-closed IntentExampleLoader, deriving the expected locale from each file
 * name (so a record's locale must match its file). It is a curated, non-authoritative
 * resource library — the INPUT counterpart to IntentNarration.
 *
 * Intent Examples — slice 1 boundary: this registry is NOT wired into any
 * interpretation, resolver, prompt, corpus, lifecycle, or execution path. Nothing in
 * the runtime depends on it; it exists only to hold the seed library and feed future,
 * separately-reviewed consumers (corpus generation, few-shot prompting, embedding
 * retrieval, confirmed-interpretation memory, documentation).
 */
class IntentExampleRegistry
{
    /** @var list<IntentExample> */
    private array $examples = [];

    /** @var list<string> */
    private array $locales = [];

    public function __construct(IntentExampleLoader $loader, ?string $directory = null)
    {
        $directory ??= self::defaultDirectory();

        if (! is_dir($directory)) {
            throw new InvalidIntentExampleException("Intent-examples directory not found at [{$directory}].");
        }

        $files = glob(rtrim($directory, '/').'/*.json') ?: [];
        sort($files);

        $seenIds = [];

        foreach ($files as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME);
            $this->locales[] = $locale;

            foreach ($loader->load($file, $locale) as $example) {
                if (isset($seenIds[$example->id])) {
                    throw new InvalidIntentExampleException("Duplicate intent-example id [{$example->id}] across files.");
                }
                $seenIds[$example->id] = true;

                $this->examples[] = $example;
            }
        }
    }

    /**
     * Default shipped directory: packages/Actions/resources/intent-examples.
     * (src/Examples → src → Actions → resources/intent-examples)
     */
    public static function defaultDirectory(): string
    {
        return __DIR__.'/../../resources/intent-examples';
    }

    /** @return list<IntentExample> */
    public function all(): array
    {
        return $this->examples;
    }

    /** @return list<string> */
    public function locales(): array
    {
        return array_values(array_unique($this->locales));
    }

    /** @return list<IntentExample> */
    public function byLocale(string $locale): array
    {
        return array_values(array_filter($this->examples, static fn (IntentExample $e): bool => $e->locale === $locale));
    }

    /** @return list<IntentExample> */
    public function byIntent(string $intent): array
    {
        return array_values(array_filter($this->examples, static fn (IntentExample $e): bool => $e->intent === $intent));
    }

    /** @return list<IntentExample> */
    public function forIntentAndLocale(string $intent, string $locale): array
    {
        return array_values(array_filter(
            $this->examples,
            static fn (IntentExample $e): bool => $e->intent === $intent && $e->locale === $locale,
        ));
    }

    public function find(string $id): ?IntentExample
    {
        foreach ($this->examples as $example) {
            if ($example->id === $id) {
                return $example;
            }
        }

        return null;
    }
}
