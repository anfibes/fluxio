<?php

namespace Fluxio\Actions\Contracts;

use Fluxio\Actions\DTO\ParsedIntent;

interface IntentResolverInterface
{
    public function resolve(string $text): ParsedIntent;
}
