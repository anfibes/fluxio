<?php

namespace Tests\Unit\Actions\Diagnostics;

use Fluxio\Actions\Diagnostics\Refinement\Exceptions\InvalidRefinementCorpusException;
use Fluxio\Actions\Diagnostics\Refinement\RefinementCorpusLoader;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8A: the refinement corpus loader turns valid JSON into typed cases and
 * fails clearly on malformed corpora. Pure unit — no DB, no pipeline.
 */
class RefinementCorpusLoaderTest extends TestCase
{
    private RefinementCorpusLoader $loader;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new RefinementCorpusLoader;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function writeCorpus(string $json): string
    {
        $path = tempnam(sys_get_temp_dir(), 'refcorpus_').'.json';
        file_put_contents($path, $json);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_loads_the_shipped_corpus(): void
    {
        $cases = $this->loader->load(RefinementCorpusLoader::defaultCorpusPath());

        $this->assertNotEmpty($cases);

        $byId = [];
        foreach ($cases as $case) {
            $byId[$case->id] = $case;
        }

        $this->assertArrayHasKey('temporal-replace-time', $byId);
        $this->assertSame(['replace_time'], $byId['temporal-replace-time']->expectedSemanticTypes);
        $this->assertSame(['time'], $byId['temporal-replace-time']->expectedChangedFields);

        // Setup refinements and ambiguity expectations parse into structure.
        $this->assertSame(['Add Marco too'], $byId['participant-replace']->setupRefinements);
        $this->assertSame('lead', $byId['ambiguity-narrow-company']->expectedAmbiguity['key']);
        $this->assertFalse($byId['ambiguity-narrow-company']->expectedAmbiguity['resolved']);
        $this->assertTrue($byId['unsupported-vague-text']->expectNoChanges);
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(InvalidRefinementCorpusException::class);
        $this->loader->load('/no/such/refinement-corpus.json');
    }

    public function test_invalid_json_throws(): void
    {
        $this->expectException(InvalidRefinementCorpusException::class);
        $this->loader->load($this->writeCorpus('{ not json'));
    }

    public function test_non_list_root_throws(): void
    {
        $this->expectException(InvalidRefinementCorpusException::class);
        $this->loader->load($this->writeCorpus('{"id": "x"}'));
    }

    public function test_missing_initial_command_throws(): void
    {
        $json = json_encode([[
            'id' => 'x',
            'refinement_text' => 'At 11:00',
            'expected' => ['changed_fields' => ['time']],
        ]]);

        $this->expectException(InvalidRefinementCorpusException::class);
        $this->loader->load($this->writeCorpus($json));
    }

    public function test_missing_refinement_text_throws(): void
    {
        $json = json_encode([[
            'id' => 'x',
            'initial_command' => 'Schedule a meeting with Rossini tomorrow at 10',
            'expected' => ['changed_fields' => ['time']],
        ]]);

        $this->expectException(InvalidRefinementCorpusException::class);
        $this->loader->load($this->writeCorpus($json));
    }

    public function test_missing_expected_object_throws(): void
    {
        $json = json_encode([[
            'id' => 'x',
            'initial_command' => 'Schedule a meeting with Rossini tomorrow at 10',
            'refinement_text' => 'At 11:00',
        ]]);

        $this->expectException(InvalidRefinementCorpusException::class);
        $this->loader->load($this->writeCorpus($json));
    }

    public function test_ambiguity_without_resolved_throws(): void
    {
        $json = json_encode([[
            'id' => 'x',
            'initial_command' => 'Schedule a meeting with Rossi tomorrow at 10',
            'refinement_text' => 'The company',
            'expected' => ['ambiguity' => ['key' => 'lead']],
        ]]);

        $this->expectException(InvalidRefinementCorpusException::class);
        $this->loader->load($this->writeCorpus($json));
    }
}
