<?php

namespace Tests\Unit\Actions\Diagnostics\Evaluation;

use Fluxio\Actions\Diagnostics\Evaluation\Exceptions\InvalidInterpretationCorpusException;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Tests\TestCase;

/**
 * Unit tests for the Phase 5B corpus loader.
 *
 * Corpus fixtures are written to a temp file per test — no committed fixtures,
 * no real Ollama, no pipeline involvement.
 */
class InterpretationCorpusLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    private InterpretationCorpusLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new InterpretationCorpusLoader;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function writeCorpus(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'corpus_').'.json';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_loads_a_valid_corpus(): void
    {
        $path = $this->writeCorpus(json_encode([
            [
                'id' => 'create-task',
                'text' => 'Create a task for Rossi',
                'locale' => 'en',
                'expected' => ['intent' => 'create_task', 'entities' => ['lead' => 'Rossi']],
                'notes' => ['happy path'],
            ],
            [
                'id' => 'unknown-case',
                'text' => 'Show me the dashboard',
                'expected' => ['intent' => 'unknown'],
            ],
        ]));

        $cases = $this->loader->load($path);

        $this->assertCount(2, $cases);
        $this->assertSame('create-task', $cases[0]->id);
        $this->assertSame('Create a task for Rossi', $cases[0]->text);
        $this->assertSame('en', $cases[0]->locale);
        $this->assertSame('create_task', $cases[0]->expectedIntent);
        $this->assertSame(['lead' => 'Rossi'], $cases[0]->expectedEntities);
        $this->assertSame(['happy path'], $cases[0]->notes);

        // locale defaults to en; missing entities/notes default to empty.
        $this->assertSame('en', $cases[1]->locale);
        $this->assertSame([], $cases[1]->expectedEntities);
        $this->assertSame([], $cases[1]->notes);
    }

    public function test_rejects_missing_file(): void
    {
        $this->expectException(InvalidInterpretationCorpusException::class);

        $this->loader->load(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.json');
    }

    public function test_rejects_invalid_json(): void
    {
        $path = $this->writeCorpus('{ this is not json ]');

        $this->expectException(InvalidInterpretationCorpusException::class);

        $this->loader->load($path);
    }

    public function test_rejects_case_without_id(): void
    {
        $path = $this->writeCorpus(json_encode([
            ['text' => 'Create a task', 'expected' => ['intent' => 'create_task']],
        ]));

        $this->expectException(InvalidInterpretationCorpusException::class);

        $this->loader->load($path);
    }

    public function test_rejects_case_without_text(): void
    {
        $path = $this->writeCorpus(json_encode([
            ['id' => 'no-text', 'expected' => ['intent' => 'create_task']],
        ]));

        $this->expectException(InvalidInterpretationCorpusException::class);

        $this->loader->load($path);
    }

    public function test_rejects_case_without_expected_intent(): void
    {
        $path = $this->writeCorpus(json_encode([
            ['id' => 'no-intent', 'text' => 'Create a task', 'expected' => ['entities' => ['lead' => 'Rossi']]],
        ]));

        $this->expectException(InvalidInterpretationCorpusException::class);

        $this->loader->load($path);
    }
}
