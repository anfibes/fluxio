<?php

namespace Tests\Unit\Actions\Diagnostics;

use Tests\TestCase;

/**
 * A7.2 isolation guard: the capability-class profile abstraction is DIAGNOSTICS-ONLY.
 *
 * It may be referenced only from the Diagnostics namespace (where it lives and is consumed) and
 * Console (the diagnostic command that wires it). It must never appear in the runtime interpretation
 * path — resolvers, interpretation providers (incl. OllamaInterpretationProvider), executors,
 * lifecycle services, controllers, resources, or the Llm transport layer.
 */
class DiagnosticsProfilesIsolationTest extends TestCase
{
    public function test_profiles_are_referenced_only_from_diagnostics_and_console(): void
    {
        $srcDir = (string) realpath(__DIR__.'/../../../../../../packages/Actions/src');
        $this->assertNotSame('', $srcDir, 'Could not locate packages/Actions/src.');

        $allowedDirs = [
            $srcDir.DIRECTORY_SEPARATOR.'Diagnostics'.DIRECTORY_SEPARATOR,
            $srcDir.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR,
        ];

        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();

            if (! str_contains((string) file_get_contents($realPath), 'Diagnostics\\Profiles')) {
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

        $this->assertSame(
            [],
            $offenders,
            "Observation profiles must stay diagnostics-only (Diagnostics/Console). Offending files: \n".implode("\n", $offenders),
        );
    }

    public function test_runtime_interpretation_provider_does_not_reference_profiles(): void
    {
        $provider = (string) file_get_contents(
            __DIR__.'/../../../../../../packages/Actions/src/Interpretation/Providers/OllamaInterpretationProvider.php',
        );

        $this->assertStringNotContainsString('Diagnostics\\Profiles', $provider);
        $this->assertStringNotContainsString('ObservationProfile', $provider);
    }
}
