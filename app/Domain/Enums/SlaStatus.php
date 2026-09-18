<?php

namespace App\Domain\Enums;

enum SlaStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Stopped = 'stopped';
}
