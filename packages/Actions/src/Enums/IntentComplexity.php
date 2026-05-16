<?php

namespace Fluxio\Actions\Enums;

enum IntentComplexity: string
{
    case Simple   = 'simple';
    case Domain   = 'domain';
    case Workflow = 'workflow';
}
