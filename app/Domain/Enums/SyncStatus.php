<?php

namespace App\Domain\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}
