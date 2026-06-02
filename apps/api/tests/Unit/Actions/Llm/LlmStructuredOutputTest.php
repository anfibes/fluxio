<?php

namespace Tests\Unit\Actions\Llm;

use Fluxio\Actions\Llm\DTO\LlmStructuredOutput;
use PHPUnit\Framework\TestCase;

/**
 * Phase 9F.1C — the provider-internal structured-output DTO.
 *
 * Projects an already-validated payload into the typed four-field contract. It does
 * not validate (that is LlmStructuredOutputValidator's job); these tests pin only the
 * shape-preserving projection used to map to NormalizedCommand.
 */
class LlmStructuredOutputTest extends TestCase
{
    public function test_projects_the_four_contract_fields(): void
    {
        $output = LlmStructuredOutput::fromValidatedPayload([
            'intent'     => 'create_task',
            'confidence' => 0.82,
            'entities'   => ['lead' => 'Rossi', 'due_at' => 'tomorrow'],
            'notes'      => ['assumed task module'],
        ]);

        $this->assertSame('create_task', $output->intent);
        $this->assertSame(0.82, $output->confidence);
        $this->assertSame(['lead' => 'Rossi', 'due_at' => 'tomorrow'], $output->entities);
        $this->assertSame(['assumed task module'], $output->notes);
    }

    public function test_notes_default_to_empty_list_when_absent(): void
    {
        $output = LlmStructuredOutput::fromValidatedPayload([
            'intent'     => 'unknown',
            'confidence' => 0.1,
            'entities'   => [],
        ]);

        $this->assertSame([], $output->notes);
    }

    public function test_integer_confidence_is_normalized_to_float(): void
    {
        $output = LlmStructuredOutput::fromValidatedPayload([
            'intent'     => 'create_task',
            'confidence' => 1,
            'entities'   => [],
            'notes'      => [],
        ]);

        $this->assertSame(1.0, $output->confidence);
    }

    public function test_notes_are_reindexed_to_a_list(): void
    {
        $output = LlmStructuredOutput::fromValidatedPayload([
            'intent'     => 'create_task',
            'confidence' => 0.5,
            'entities'   => [],
            'notes'      => [2 => 'b', 5 => 'c'],
        ]);

        $this->assertSame(['b', 'c'], $output->notes);
    }
}
