<?php

namespace Fluxio\Actions\Diagnostics;

use Fluxio\Actions\Diagnostics\DTO\InterpretationComparisonResult;
use Fluxio\Actions\Diagnostics\DTO\InterpretationProviderResult;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Throwable;

/**
 * Development-only diagnostics: runs the deterministic provider and the Ollama
 * sandbox provider against the same input and reports how they diverge.
 *
 * Both providers are injected as explicit concrete instances (see the binding
 * in ActionsServiceProvider) so this service never resolves
 * InterpretationProviderInterface — which would only return the single
 * configured runtime provider. This is NOT runtime hybrid mode: it builds no
 * proposals, calls no ActionInterpreterService, resolves no entities, and the
 * configured runtime provider is left untouched.
 *
 * Provider failures are captured as result data, not rethrown, so a single
 * provider error never aborts the comparison. Runtime interpretation keeps its
 * fail-closed behaviour; this catch-and-represent applies to diagnostics only.
 */
class InterpretationComparisonService
{
    public function __construct(
        private readonly InterpretationProviderInterface $deterministicProvider,
        private readonly InterpretationProviderInterface $ollamaProvider,
    ) {}

    public function compare(string $text, ?InterpretationContext $context = null): InterpretationComparisonResult
    {
        $context ??= new InterpretationContext;

        $deterministic = $this->run('deterministic', $this->deterministicProvider, $text, $context);
        $ollama = $this->run('ollama', $this->ollamaProvider, $text, $context);

        $bothSucceeded = $deterministic->success && $ollama->success;
        $anyFailed = ! $deterministic->success || ! $ollama->success;

        $notes = [];
        $sameIntent = null;
        $confidenceDelta = null;
        $entityDiff = $this->emptyEntityDiff();
        $warningDiff = $this->emptyWarningDiff();

        if (! $deterministic->success) {
            $notes[] = "deterministic provider failed: {$deterministic->errorClass}";
        }
        if (! $ollama->success) {
            $notes[] = "ollama provider failed: {$ollama->errorClass}";
        }

        if ($bothSucceeded) {
            $sameIntent = $deterministic->intent === $ollama->intent;
            $confidenceDelta = round((float) $ollama->confidence - (float) $deterministic->confidence, 6);
            $entityDiff = $this->diffEntities($deterministic->entities, $ollama->entities);
            $warningDiff = $this->diffWarnings($deterministic->warnings, $ollama->warnings);
            $notes[] = $sameIntent ? 'intent match' : 'intent mismatch';
        }

        return new InterpretationComparisonResult(
            text: $text,
            deterministic: $deterministic,
            ollama: $ollama,
            bothSucceeded: $bothSucceeded,
            anyFailed: $anyFailed,
            sameIntent: $sameIntent,
            confidenceDelta: $confidenceDelta,
            entityDiff: $entityDiff,
            warningDiff: $warningDiff,
            notes: $notes,
        );
    }

    private function run(
        string $providerKey,
        InterpretationProviderInterface $provider,
        string $text,
        InterpretationContext $context,
    ): InterpretationProviderResult {
        try {
            return InterpretationProviderResult::fromCommand($providerKey, $provider->interpret($text, $context));
        } catch (Throwable $e) {
            return InterpretationProviderResult::fromFailure($providerKey, $e);
        }
    }

    /**
     * Exact key/value diff. No fuzzy or semantic comparison.
     *
     * @param  array<string, mixed>  $deterministic
     * @param  array<string, mixed>  $ollama
     * @return array{only_in_deterministic: array<string, mixed>, only_in_ollama: array<string, mixed>, different_values: array<string, array{deterministic: mixed, ollama: mixed}>, same_values: array<string, mixed>}
     */
    private function diffEntities(array $deterministic, array $ollama): array
    {
        $onlyInDeterministic = [];
        $different = [];
        $same = [];

        foreach ($deterministic as $key => $value) {
            if (! array_key_exists($key, $ollama)) {
                $onlyInDeterministic[$key] = $value;
            } elseif ($value === $ollama[$key]) {
                $same[$key] = $value;
            } else {
                $different[$key] = ['deterministic' => $value, 'ollama' => $ollama[$key]];
            }
        }

        $onlyInOllama = [];
        foreach ($ollama as $key => $value) {
            if (! array_key_exists($key, $deterministic)) {
                $onlyInOllama[$key] = $value;
            }
        }

        return [
            'only_in_deterministic' => $onlyInDeterministic,
            'only_in_ollama' => $onlyInOllama,
            'different_values' => $different,
            'same_values' => $same,
        ];
    }

    /**
     * @param  list<string>  $deterministic
     * @param  list<string>  $ollama
     * @return array{only_in_deterministic: list<string>, only_in_ollama: list<string>, common: list<string>}
     */
    private function diffWarnings(array $deterministic, array $ollama): array
    {
        return [
            'only_in_deterministic' => array_values(array_diff($deterministic, $ollama)),
            'only_in_ollama' => array_values(array_diff($ollama, $deterministic)),
            'common' => array_values(array_intersect($deterministic, $ollama)),
        ];
    }

    /**
     * @return array{only_in_deterministic: array<string, mixed>, only_in_ollama: array<string, mixed>, different_values: array<string, array{deterministic: mixed, ollama: mixed}>, same_values: array<string, mixed>}
     */
    private function emptyEntityDiff(): array
    {
        return [
            'only_in_deterministic' => [],
            'only_in_ollama' => [],
            'different_values' => [],
            'same_values' => [],
        ];
    }

    /**
     * @return array{only_in_deterministic: list<string>, only_in_ollama: list<string>, common: list<string>}
     */
    private function emptyWarningDiff(): array
    {
        return [
            'only_in_deterministic' => [],
            'only_in_ollama' => [],
            'common' => [],
        ];
    }
}
