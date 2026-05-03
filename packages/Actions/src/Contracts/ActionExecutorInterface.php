<?php

namespace Fluxio\Actions\Contracts;

use Fluxio\Actions\Models\ActionProposal;

interface ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): array;
}
