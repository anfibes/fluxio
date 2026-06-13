<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Examples\Exceptions\InvalidIntentExampleException;
use Fluxio\Actions\Examples\IntentExample;
use Fluxio\Actions\Examples\IntentExampleLoader;
use Fluxio\Actions\Examples\IntentExampleRegistry;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Fluxio\Actions\Registry\IntentRegistry;
use Tests\TestCase;

/**
 * Intent Examples — slice 1 guardrails.
 *
 * Proves the declarative, read-only intent-examples library loads fail-closed, stays
 * compatible with the authoritative IntentRegistry / IntentDefinition vocabulary, and
 * is not wired into any runtime path. These are pure, in-memory checks (no DB).
 *
 * Supported locales are en + it — the project's supported set (backend
 * packages/Actions/lang/{en,it} and apps/web nuxt i18n both declare exactly en + it).
 */
class IntentExampleTest extends TestCase
{
    private const SUPPORTED_LOCALES = ['en', 'it'];

    private const MVP_INTENTS = [
        'create_task',
        'schedule_call',
        'schedule_meeting',
        'assign_lead',
        'prepare_contract_from_quote',
    ];

    private function registry(): IntentExampleRegistry
    {
        return $this->app->make(IntentExampleRegistry::class);
    }

    private function loader(): IntentExampleLoader
    {
        return $this->app->make(IntentExampleLoader::class);
    }

    private function intentRegistry(): IntentRegistry
    {
        return $this->app->make(IntentRegistry::class);
    }

    // ── 1. Files load successfully ────────────────────────────────────────────

    public function test_intent_example_files_load_successfully(): void
    {
        $examples = $this->registry()->all();

        $this->assertNotEmpty($examples);
        $this->assertContainsOnlyInstancesOf(IntentExample::class, $examples);
    }

    // ── 2. Every supported locale has an example file ─────────────────────────

    public function test_every_supported_locale_has_an_example_file(): void
    {
        $locales = $this->registry()->locales();

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $this->assertContains($locale, $locales, "Missing intent-examples file for locale [{$locale}].");
        }
    }

    // ── 3. Every MVP intent has examples for each supported locale ────────────

    public function test_every_mvp_intent_has_examples_for_each_supported_locale(): void
    {
        $registry = $this->registry();

        foreach (self::MVP_INTENTS as $intent) {
            foreach (self::SUPPORTED_LOCALES as $locale) {
                $this->assertNotEmpty(
                    $registry->forIntentAndLocale($intent, $locale),
                    "Expected at least one example for intent [{$intent}] in locale [{$locale}].",
                );
            }
        }
    }

    public function test_each_intent_locale_has_a_literal_and_a_template_example(): void
    {
        $registry = $this->registry();

        foreach (self::MVP_INTENTS as $intent) {
            foreach (self::SUPPORTED_LOCALES as $locale) {
                $examples = $registry->forIntentAndLocale($intent, $locale);

                $this->assertTrue(
                    collect($examples)->contains(fn (IntentExample $e) => $e->hasLiteral()),
                    "Expected a literal example for [{$intent}]/[{$locale}].",
                );
                $this->assertTrue(
                    collect($examples)->contains(fn (IntentExample $e) => $e->hasTemplate()),
                    "Expected a template example for [{$intent}]/[{$locale}].",
                );
            }
        }
    }

    // ── 4. Every example intent exists in IntentRegistry ──────────────────────

    public function test_every_example_intent_exists_in_intent_registry(): void
    {
        $intentRegistry = $this->intentRegistry();

        foreach ($this->registry()->all() as $example) {
            $this->assertNotNull(
                $intentRegistry->find($example->intent),
                "Example [{$example->id}] references unknown intent [{$example->intent}].",
            );
        }
    }

    // ── 5. Every slot/placeholder is compatible with requirement/parser keys ──

    public function test_every_slot_entity_key_is_a_requirement_or_parser_key(): void
    {
        $intentRegistry = $this->intentRegistry();

        foreach ($this->registry()->all() as $example) {
            $definition = $intentRegistry->find($example->intent);
            $this->assertNotNull($definition);

            $allowed = array_merge($definition->requirementKeys(), InterpretationGrammar::UNIVERSAL_PARSER_KEYS);

            foreach ($example->slots as $slotName => $slot) {
                $this->assertContains(
                    $slot['entity_key'],
                    $allowed,
                    "Slot [{$slotName}] in example [{$example->id}] maps to [{$slot['entity_key']}], not a requirement/parser key for [{$example->intent}].",
                );
            }
        }
    }

    public function test_template_placeholders_are_all_covered_by_slots(): void
    {
        foreach ($this->registry()->all() as $example) {
            if (! $example->hasTemplate()) {
                continue;
            }

            preg_match_all('/\{(\w+)\}/', (string) $example->template, $matches);
            $declared = array_map(static fn (array $s): string => $s['placeholder'], array_values($example->slots));

            foreach ($matches[0] as $placeholder) {
                $this->assertContains(
                    $placeholder,
                    $declared,
                    "Template placeholder [{$placeholder}] in example [{$example->id}] has no matching slot.",
                );
            }
        }
    }

    // ── 6. Forbidden runtime/authority fields rejected or absent ──────────────

    public function test_seed_examples_contain_no_forbidden_entity_keys(): void
    {
        $forbidden = ['id', 'lead_id', 'selected_candidate_id', 'candidate_id', 'proposal_id', 'status', 'ready', 'confirmed', 'executed', 'execution_result', 'execution_failure'];

        foreach ($this->registry()->all() as $example) {
            foreach ($example->slots as $slot) {
                $this->assertNotContains($slot['entity_key'], $forbidden, "Example [{$example->id}] uses a forbidden entity_key.");
            }
        }
    }

    public function test_loader_rejects_forbidden_top_level_field(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $this->loadJson('en', [[
            'id' => 'en-bad', 'locale' => 'en', 'intent' => 'create_task',
            'text' => 'Create a task for Rossini',
            'status' => 'ready', // forbidden runtime field
        ]]);
    }

    public function test_loader_rejects_forbidden_slot_entity_key(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $this->loadJson('en', [[
            'id' => 'en-bad', 'locale' => 'en', 'intent' => 'create_task',
            'template' => 'Create a task for {lead}',
            'slots' => ['lead' => ['placeholder' => '{lead}', 'entity_key' => 'lead_id']],
        ]]);
    }

    public function test_loader_rejects_slot_key_not_in_requirements(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        // assign_lead has no 'quote' requirement.
        $this->loadJson('en', [[
            'id' => 'en-bad', 'locale' => 'en', 'intent' => 'assign_lead',
            'template' => 'Assign {lead} from {quote}',
            'slots' => [
                'lead' => ['placeholder' => '{lead}', 'entity_key' => 'lead'],
                'quote' => ['placeholder' => '{quote}', 'entity_key' => 'quote'],
            ],
        ]]);
    }

    // ── 7. Duplicate ids rejected ─────────────────────────────────────────────

    public function test_loader_rejects_duplicate_ids(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $this->loadJson('en', [
            ['id' => 'dup', 'locale' => 'en', 'intent' => 'create_task', 'text' => 'Create a task for Rossini'],
            ['id' => 'dup', 'locale' => 'en', 'intent' => 'create_task', 'text' => 'Create a task for Bianchi'],
        ]);
    }

    // ── 8. Locale mismatch rejected ───────────────────────────────────────────

    public function test_loader_rejects_locale_mismatch(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        // File expects 'en' but the record declares 'it'.
        $this->loadJson('en', [[
            'id' => 'en-mismatch', 'locale' => 'it', 'intent' => 'create_task', 'text' => 'Crea un\'attività per Rossini',
        ]]);
    }

    // ── 9. Invalid / missing required fields rejected ─────────────────────────

    public function test_loader_rejects_unknown_intent(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $this->loadJson('en', [[
            'id' => 'en-unknown', 'locale' => 'en', 'intent' => 'archive_record', 'text' => 'Archive Rossini',
        ]]);
    }

    public function test_loader_rejects_missing_id(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $this->loadJson('en', [[
            'locale' => 'en', 'intent' => 'create_task', 'text' => 'Create a task for Rossini',
        ]]);
    }

    public function test_loader_rejects_record_without_text_or_template(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $this->loadJson('en', [[
            'id' => 'en-empty', 'locale' => 'en', 'intent' => 'create_task',
        ]]);
    }

    public function test_loader_rejects_invalid_json(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $path = $this->tempFile('{ not valid json');
        $this->loader()->load($path, 'en');
    }

    public function test_loader_rejects_template_with_uncovered_placeholder(): void
    {
        $this->expectException(InvalidIntentExampleException::class);

        $this->loadJson('en', [[
            'id' => 'en-uncovered', 'locale' => 'en', 'intent' => 'create_task',
            'template' => 'Create a task for {lead} about {topic}',
            'slots' => ['lead' => ['placeholder' => '{lead}', 'entity_key' => 'lead']],
        ]]);
    }

    // ── 10. Examples are consumed ONLY from the diagnostics namespace ──────────

    /**
     * Scans every PHP file under packages/Actions/src that references the Examples
     * subsystem and returns the offenders that live OUTSIDE the allowed consumer
     * namespaces. Allowed consumers: the Examples subsystem itself, Diagnostics
     * (Slice A1 — IntentExampleObservationService), and Llm/Prompting (Slice A2 — the
     * few-shot exemplar boundary). Everything else (resolvers, interpretation
     * runtime/providers, lifecycle/services, executors, controllers, resources) is a
     * runtime path and must NOT reference Examples.
     *
     * @return list<string>
     */
    private function exampleReferencesOutsideAllowedConsumers(string &$srcDir): array
    {
        $srcDir = (string) realpath(__DIR__.'/../../../../../packages/Actions/src');
        $this->assertNotSame('', $srcDir, 'Could not locate packages/Actions/src.');

        $allowedDirs = [
            $srcDir.DIRECTORY_SEPARATOR.'Examples'.DIRECTORY_SEPARATOR,
            $srcDir.DIRECTORY_SEPARATOR.'Diagnostics'.DIRECTORY_SEPARATOR,
            $srcDir.DIRECTORY_SEPARATOR.'Llm'.DIRECTORY_SEPARATOR.'Prompting'.DIRECTORY_SEPARATOR,
        ];

        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();

            $contents = (string) file_get_contents($realPath);

            $referencesExamples = str_contains($contents, 'Fluxio\\Actions\\Examples')
                || str_contains($contents, 'IntentExampleRegistry')
                || str_contains($contents, 'IntentExampleLoader');

            if (! $referencesExamples) {
                continue;
            }

            $allowed = false;
            foreach ($allowedDirs as $allowedDir) {
                if (str_starts_with($realPath, $allowedDir)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                $offenders[] = $realPath;
            }
        }

        return $offenders;
    }

    public function test_no_runtime_code_references_the_examples_subsystem(): void
    {
        $srcDir = '';
        $offenders = $this->exampleReferencesOutsideAllowedConsumers($srcDir);

        $this->assertSame(
            [],
            $offenders,
            "Intent Examples may only be consumed from the Examples or Diagnostics namespaces. Offending runtime files: \n".implode("\n", $offenders),
        );
    }

    public function test_examples_are_consumed_from_the_diagnostics_namespace(): void
    {
        // Slice A1 wired the first consumer (IntentExampleObservationService) under
        // src/Diagnostics. Prove the consumer actually lives there, so the guard above is
        // meaningfully exercised (and would catch a move out of the allowed namespace).
        $srcDir = '';
        $this->exampleReferencesOutsideAllowedConsumers($srcDir);

        $diagnosticsDir = $srcDir.DIRECTORY_SEPARATOR.'Diagnostics';
        $found = false;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($diagnosticsDir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getRealPath()), 'IntentExampleRegistry')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected a diagnostics consumer of IntentExampleRegistry under src/Diagnostics.');
    }

    public function test_registry_is_resolvable_and_queryable(): void
    {
        $registry = $this->registry();

        $byLocale = $registry->byLocale('it');
        $this->assertNotEmpty($byLocale);
        $this->assertTrue(collect($byLocale)->every(fn (IntentExample $e) => $e->locale === 'it'));

        $byIntent = $registry->byIntent('create_task');
        $this->assertNotEmpty($byIntent);

        $found = $registry->find('en-create-task-basic');
        $this->assertNotNull($found);
        $this->assertSame('create_task', $found->intent);

        $this->assertNull($registry->find('does-not-exist'));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function loadJson(string $expectedLocale, array $records): array
    {
        $path = $this->tempFile((string) json_encode($records));

        return $this->loader()->load($path, $expectedLocale);
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'intent-examples-').'.json';
        file_put_contents($path, $contents);

        return $path;
    }
}
