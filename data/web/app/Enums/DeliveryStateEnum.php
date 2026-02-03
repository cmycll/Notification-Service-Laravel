<?php

namespace App\Enums;

enum DeliveryStateEnum: string
{
    case QUEUED = 'queued';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case UNKNOWN = 'unknown';
}
