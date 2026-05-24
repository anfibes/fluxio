<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Interpretation\Providers\OllamaInterpretationProvider;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Covers the config-driven selection of the interpretation provider.
 *
 * The deterministic provider is the default; the Ollama sandbox is opt-in via
 * ACTIONS_INTERPRETATION_PROVIDER=ollama. No real Ollama is contacted — only
 * container resolution is exercised. An unknown value must fail explicitly.
 */
class InterpretationProviderConfigBindingTest extends TestCase
{
    private function resolveWith(string $provider): mixed
    {
        config(['actions.interpreter.provider' => $provider]);

        // Drop any previously resolved binding so the closure re-evaluates config.
        $this->app->forgetInstance(InterpretationProviderInterface::class);

        return $this->app->make(InterpretationProviderInterface::class);
    }

    public function test_config_default_remains_deterministic(): void
    {
        // Default value declared in packages/Actions/config/actions.php.
        $this->assertSame('deterministic', config('actions.interpreter.provider'));

        $instance = $this->app->make(InterpretationProviderInterface::class);
        $this->assertInstanceOf(DeterministicInterpretationProvider::class, $instance);
    }

    public function test_config_opt_in_binds_ollama_provider(): void
    {
        $instance = $this->resolveWith('ollama');

        $this->assertInstanceOf(OllamaInterpretationProvider::class, $instance);
    }

    public function test_deterministic_value_binds_deterministic_provider(): void
    {
        $instance = $this->resolveWith('deterministic');

        $this->assertInstanceOf(DeterministicInterpretationProvider::class, $instance);
    }

    public function test_unknown_provider_value_fails_explicitly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveWith('gpt-5-turbo-ultra');
    }
}
