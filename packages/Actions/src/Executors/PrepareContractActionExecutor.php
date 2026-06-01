<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;

class PrepareContractActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        return ExecutionResult::make(
            summary: 'Contract draft prepared successfully.',
            details: [
                'type' => 'contract_prepared',
                'status' => 'drafted',
                'lead' => $fields->get('lead')['value'] ?? null,
                'quote' => $fields->get('quote')['value'] ?? null,
            ],
        );
    }
}
