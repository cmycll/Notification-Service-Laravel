<?php

namespace App\Enums;

enum UserStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DELETED = 'deleted';
    case SUSPENDED = 'suspended';
}
