<?php

namespace App\Enums;

enum StrikeReason: string
{
    case LateCancellation = 'late_cancellation';
    case MissedCheckIn = 'missed_check_in';
}
