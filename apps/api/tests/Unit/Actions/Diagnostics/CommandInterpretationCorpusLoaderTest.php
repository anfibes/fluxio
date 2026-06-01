<?php

namespace Tests\Unit\Actions\Diagnostics;

use Fluxio\Actions\Diagnostics\CommandInterpretation\CommandInterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\CommandInterpretation\Exceptions\InvalidCommandInterpretationCorpusException;
use PHPUnit\Framework\TestCase;

/**
 * Phase 9D.2 — the command-interpretation corpus loader turns valid JSON into
 * typed cases and rejects malformed corpora explicitly. Diagnostics-only.
 */
class CommandInterpretationCorpusLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function writeCorpus(string $json): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cmdcorpus_').'.json';
        file_put_contents($path, $json);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_loads_the_shipped_corpus(): void
    {
        $loader = new CommandInterpretationCorpusLoader;
        $cases = $loader->load(CommandInterpretationCorpusLoader::defaultCorpusPath());

        $this->assertNotEmpty($cases);
        $ids = array_map(static fn ($c) => $c->id, $cases);
        $this->assertContains('create-task-person-span-auto-resolves', $ids);
    }

    public function test_parses_expected_block_into_typed_case(): void
    {
        $loader = new CommandInterpretationCorpusLoader;
        $path = $this->writeCorpus(json_encode([[
            'id' => 'c1',
            'text' => 'Create a task for Mario Rossi tomorrow at 10',
            'expected' => [
                'intent' => 'create_task',
                'status' => 'ready',
                'lead' => 'Mario Rossi',
                'no_lead_ambiguity' => true,
                'entities' => ['time' => '10:00'],
                'entities_present' => ['date'],
            ],
        ]]));

        $case = $loader->load($path)[0];

        $this->assertSame('create_task', $case->expectedIntent);
        $this->assertSame('ready', $case->expectedStatus);
        $this->assertSame('Mario Rossi', $case->expectedLead);
        $this->assertTrue($case->expectNoLeadAmbiguity);
        $this->assertSame(['time' => '10:00'], $case->expectedEntities);
        $this->assertSame(['date'], $case->expectedEntitiesPresent);
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(InvalidCommandInterpretationCorpusException::class);
        (new CommandInterpretationCorpusLoader)->load('/nope/missing.json');
    }

    public function test_invalid_json_throws(): void
    {
        $path = $this->writeCorpus('{ not json');
        $this->expectException(InvalidCommandInterpretationCorpusException::class);
        (new CommandInterpretationCorpusLoader)->load($path);
    }

    public function test_case_missing_text_throws(): void
    {
        $path = $this->writeCorpus(json_encode([['id' => 'x', 'expected' => ['intent' => 'create_task']]]));
        $this->expectException(InvalidCommandInterpretationCorpusException::class);
        (new CommandInterpretationCorpusLoader)->load($path);
    }

    public function test_case_missing_expected_throws(): void
    {
        $path = $this->writeCorpus(json_encode([['id' => 'x', 'text' => 'Create a task']]));
        $this->expectException(InvalidCommandInterpretationCorpusException::class);
        (new CommandInterpretationCorpusLoader)->load($path);
    }

    public function test_malformed_ambiguity_throws(): void
    {
        $path = $this->writeCorpus(json_encode([[
            'id' => 'x',
            'text' => 'Call Rossi',
            'expected' => ['ambiguity' => ['blocking' => true]], // missing key
        ]]));
        $this->expectException(InvalidCommandInterpretationCorpusException::class);
        (new CommandInterpretationCorpusLoader)->load($path);
    }
}
