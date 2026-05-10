<?php

namespace Fluxio\Actions\Contracts;

use Fluxio\Actions\DTO\NormalizedCommand;

interface CommandInterpreterInterface
{
    public function interpret(string $text): NormalizedCommand;
}
