<?php

namespace Tests\Unit\Actions\Diagnostics;

use Fluxio\Actions\Diagnostics\Observation\JsonObjectExtractor;
use Tests\TestCase;

/**
 * Unit tests for the A7.1/E2 free-text JSON extractor. Pure, no I/O, no model. It only LOCATES
 * and decodes the first complete JSON object — it never repairs or relaxes anything (the existing
 * validator still judges whatever is extracted).
 */
class JsonObjectExtractorTest extends TestCase
{
    public function test_extracts_object_after_a_reasoning_block(): void
    {
        $text = "<think>The user wants a task for Bianchi.</think>\n".
            '{"intent":"create_task","confidence":0.9,"entities":{"lead":"Bianchi"},"notes":[]}';

        $this->assertSame(
            ['intent' => 'create_task', 'confidence' => 0.9, 'entities' => ['lead' => 'Bianchi'], 'notes' => []],
            JsonObjectExtractor::extract($text),
        );
    }

    public function test_handles_nested_objects(): void
    {
        $text = 'prefix {"intent":"x","entities":{"a":{"b":"c"}}} suffix';

        $this->assertSame(
            ['intent' => 'x', 'entities' => ['a' => ['b' => 'c']]],
            JsonObjectExtractor::extract($text),
        );
    }

    public function test_ignores_braces_inside_string_literals(): void
    {
        $text = 'note {"intent":"create_task","entities":{"lead":"a } b {"}}';

        $this->assertSame(
            ['intent' => 'create_task', 'entities' => ['lead' => 'a } b {']],
            JsonObjectExtractor::extract($text),
        );
    }

    public function test_skips_a_malformed_object_and_finds_a_later_valid_one(): void
    {
        $text = '{not json} then {"intent":"assign_lead"}';

        $this->assertSame(['intent' => 'assign_lead'], JsonObjectExtractor::extract($text));
    }

    public function test_returns_null_when_there_is_no_object(): void
    {
        $this->assertNull(JsonObjectExtractor::extract('I cannot help with that.'));
        $this->assertNull(JsonObjectExtractor::extract(''));
    }

    public function test_empty_object_is_not_treated_as_an_extractable_candidate(): void
    {
        // `{}` carries no fields — reported as not extractable, consistent with the contract that
        // an empty object yields no usable command (the same outcome forced-JSON mode produces).
        $this->assertNull(JsonObjectExtractor::extract('{}'));
        $this->assertNull(JsonObjectExtractor::extract("{\n\n}"));
    }
}
