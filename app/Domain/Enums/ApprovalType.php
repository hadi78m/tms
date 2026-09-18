<?php

namespace App\Domain\Enums;

enum ApprovalType: string
{
    case Technical = 'technical';
    case Final = 'final';
}
