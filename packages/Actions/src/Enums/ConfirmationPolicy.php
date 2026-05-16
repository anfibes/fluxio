<?php

namespace Fluxio\Actions\Enums;

enum ConfirmationPolicy: string
{
    case Required = 'required';
    case Strong   = 'strong';
    case Optional = 'optional';
}
