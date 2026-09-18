<?php

namespace App\Domain\Enums;

enum DurationUnit: string
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
}
