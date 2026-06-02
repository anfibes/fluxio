<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Illuminate\Console\Command;

/**
 * Development-only diagnostic command. Prints the deterministic, provider-facing
 * grammar artifact produced by InterpretationGrammar::export() as JSON, for developer
 * inspection of what an interpretation provider is allowed to emit today.
 *
 * It is a pure read: it resolves the runtime-owned InterpretationGrammar (a projection
 * of IntentRegistry + ProviderSandboxContract), serializes export(), and prints it. It
 * builds no proposals, calls no provider/LLM, makes no network request, touches no
 * ActionInterpreterService, persists nothing, and writes no files. It is blocked in
 * production.
 *
 * CI boundary: this is OPT-IN diagnostics, not a CI gate, and is intentionally NOT part
 * of `composer test:actions-corpora`. The artifact prepares future constrained-emission
 * experiments but introduces no schema generation, GBNF, or constrained decoding.
 */
class ExportInterpretationGrammarCommand extends Command
{
    protected $signature = 'actions:export-interpretation-grammar
                            {--compact : Emit compact single-line JSON instead of pretty JSON}';

    protected $description = 'Diagnostics: print the exported provider-facing InterpretationGrammar artifact as JSON. Read-only, no provider/LLM/network. NOT a CI gate. Non-production.';

    public function handle(InterpretationGrammar $grammar): int
    {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:export-interpretation-grammar is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (! $this->option('compact')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($grammar->export(), $flags));

        return self::SUCCESS;
    }
}
