<?php

namespace App\Enums;

enum ChannelTypeEnum: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
    case PUSH = 'push';
}
