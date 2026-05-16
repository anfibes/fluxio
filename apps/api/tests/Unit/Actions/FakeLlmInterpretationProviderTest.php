<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Interpretation\Providers\FakeLlmInterpretationProvider;
use Tests\TestCase;

class FakeLlmInterpretationProviderTest extends TestCase
{
    private FakeLlmInterpretationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakeLlmInterpretationProvider();
    }

    private function context(): InterpretationContext
    {
        return new InterpretationContext();
    }

    // ── Controlled response ──────────────────────────────────────────────────

    public function test_returns_registered_command_for_known_phrase(): void
    {
        $expected = new NormalizedCommand(
            intent:     'schedule_meeting',
            confidence: 0.95,
            sourceText: 'Book a meeting with Rossini',
            locale:     'en',
            entities:   ['lead_query' => 'Rossini'],
        );

        $this->provider->addResponse('Book a meeting with Rossini', $expected);

        $result = $this->provider->interpret('Book a meeting with Rossini', $this->context());

        $this->assertSame($expected, $result);
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $expected = new NormalizedCommand(
            intent:     'create_task',
            confidence: 0.9,
            sourceText: 'Create a task',
            locale:     'en',
        );

        $this->provider->addResponse('Create a task', $expected);

        $result = $this->provider->interpret('CREATE A TASK', $this->context());

        $this->assertSame($expected, $result);
    }

    public function test_returns_unknown_intent_for_unregistered_phrase(): void
    {
        $result = $this->provider->interpret('Something completely unknown', $this->context());

        $this->assertEquals('unknown', $result->intent);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function test_unknown_fallback_preserves_source_text(): void
    {
        $text   = 'Something completely unknown';
        $result = $this->provider->interpret($text, $this->context());

        $this->assertEquals($text, $result->sourceText);
    }

    public function test_unknown_fallback_uses_context_locale(): void
    {
        $result = $this->provider->interpret('Anything', new InterpretationContext(locale: 'it'));

        $this->assertEquals('it', $result->locale);
    }

    public function test_multiple_responses_are_stored_independently(): void
    {
        $task = new NormalizedCommand(intent: 'create_task',      confidence: 0.9, sourceText: 'Make a task',  locale: 'en');
        $call = new NormalizedCommand(intent: 'schedule_call',    confidence: 0.7, sourceText: 'Call Rossini', locale: 'en');

        $this->provider->addResponse('Make a task',  $task);
        $this->provider->addResponse('Call Rossini', $call);

        $this->assertSame($task, $this->provider->interpret('Make a task',  $this->context()));
        $this->assertSame($call, $this->provider->interpret('Call Rossini', $this->context()));
    }

    // ── Binding guard ────────────────────────────────────────────────────────

    public function test_is_not_the_default_bound_provider(): void
    {
        $bound = $this->app->make(InterpretationProviderInterface::class);

        $this->assertNotInstanceOf(FakeLlmInterpretationProvider::class, $bound);
        $this->assertInstanceOf(DeterministicInterpretationProvider::class, $bound);
    }

    public function test_implements_interpretation_provider_interface(): void
    {
        $this->assertInstanceOf(InterpretationProviderInterface::class, $this->provider);
    }
}
