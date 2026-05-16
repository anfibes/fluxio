<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\Models\ActionProposal;

class PrepareContractActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): array
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        return [
            'type'    => 'contract_prepared',
            'status'  => 'drafted',
            'lead'    => $fields->get('lead')['value'] ?? null,
            'quote'   => $fields->get('quote')['value'] ?? null,
            'message' => 'Contract draft prepared successfully.',
        ];
    }
}
