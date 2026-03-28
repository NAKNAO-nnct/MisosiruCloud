<?php

declare(strict_types=1);

namespace App\Enums;

enum VmStatus: string
{
    case Pending = 'pending';
    case Cloning = 'cloning';
    case Configuring = 'configuring';
    case Starting = 'starting';
    case Ready = 'ready';
    case Error = 'error';
}
