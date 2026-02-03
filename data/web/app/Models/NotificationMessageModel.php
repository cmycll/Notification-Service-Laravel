<?php

namespace App\Models;

use App\Enums\StatusTypeEnum;
use App\Enums\DeliveryStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationMessageModel extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'notif_request_notifications';

    protected $fillable = [
        'id',
        'request_id',
        'to',
        'vars',
        'channel',
        'priority',
        'status',
        'delivery_state',
        'attempts',
        'provider_message_id',
        'last_error',
    ];

    protected $casts = [
        'status' => StatusTypeEnum::class,
        'delivery_state' => DeliveryStateEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'vars' => 'array',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(NotificationRequestModel::class, 'request_id', 'id');
    }
}
