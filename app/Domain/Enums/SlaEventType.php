<?php

namespace App\Domain\Enums;

enum SlaEventType: string
{
    case Start = 'start';
    case Pause = 'pause';
    case Resume = 'resume';
    case Stop = 'stop';
    case Breach = 'breach';
}
