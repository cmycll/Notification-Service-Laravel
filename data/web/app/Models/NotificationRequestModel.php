<?php

namespace App\Models;

use App\Enums\StatusTypeEnum;
use App\Enums\ChannelTypeEnum;
use App\Enums\PriorityTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationRequestModel extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'notif_requests';

    protected $fillable = [
        'id',
        'client_id',
        'idempotency_key',
        'correlation_id',
        'template_subject',
        'template_body_path',
        'template_body_inline',
        'requested_count',
        'accepted_count',
        'pending_count',
        'sent_count',
        'failed_count',
        'cancelled_count',
        'channel',
        'priority',
        'status',
        'scheduled_at',
    ];

    protected $casts = [
        'status' => StatusTypeEnum::class,
        'channel' => ChannelTypeEnum::class,
        'priority' => PriorityTypeEnum::class,
        'scheduled_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NotificationMessageModel::class, 'request_id', 'id');
    }
}
