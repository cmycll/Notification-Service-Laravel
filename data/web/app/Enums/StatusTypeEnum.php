<?php

namespace App\Enums;

enum StatusTypeEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case UNKNOWN = 'unknown';
}
